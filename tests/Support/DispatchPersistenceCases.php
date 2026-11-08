<?php
namespace Tests\Support;
use App\CoreFacturalo\{Facturalo};
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\Models\Tenant\{Company,Dispatch,Establishment,PersonAddress};
use App\Services\Fiscal\{HkaDispatchEmission,HkaDispatchPdf,DeliveryOrderPersistence};
use Illuminate\Support\Str;
use Modules\Dispatch\Models\{Driver,Transport,Dispatcher};

trait DispatchPersistenceCases
{
    public function test_other_commercial_mail_endpoints_preserve_transport_result(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        $common=['user_id'=>$invoice['user_id'],'external_id'=>(string)Str::uuid(),'establishment_id'=>$invoice['establishment_id'],
            'establishment'=>json_encode($invoice['establishment']),'fiscal_environment'=>'demo','state_type_id'=>'05',
            'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','customer_id'=>$invoice['customer_id'],
            'customer'=>json_encode($invoice['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'0'];
        $order=$db->table('order_notes')->insertGetId($common+['prefix'=>'PED']);
        $contract=$db->table('contracts')->insertGetId($common+['prefix'=>'CON']);
        $opportunity=$db->table('sale_opportunities')->insertGetId($common+['prefix'=>'OPV']);
        $purchaseCommon=['user_id'=>$invoice['user_id'],'external_id'=>(string)Str::uuid(),'establishment_id'=>$invoice['establishment_id'],
            'fiscal_environment'=>'demo','state_type_id'=>'05','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'supplier_id'=>$invoice['customer_id'],'supplier'=>json_encode($invoice['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'0'];
        $purchaseOrder=$db->table('purchase_orders')->insertGetId($purchaseCommon+['prefix'=>'OC','payment_method_type_id'=>'01']);
        $purchase=$db->table('purchases')->insertGetId($purchaseCommon+['group_id'=>'01','document_type_id'=>'01','series'=>'FF01','number'=>1]);
        $orderForm=$db->table('order_forms')->insertGetId(array_diff_key($common,array_flip(['currency_type_id','exchange_rate_sale','total']))+[
            'prefix'=>'OF','observations'=>'Prueba','transport_mode_type_id'=>'02','transfer_reason_type_id'=>'24','date_of_shipping'=>'2026-10-26',
            'transshipment_indicator'=>0,'unit_type_id'=>'KG','total_weight'=>'1','packages_number'=>1,'origin'=>'{}','delivery'=>'{}',
            'dispatcher_id'=>$raw['dispatcher_id'],'driver_id'=>$raw['driver_id']]);
        if (!class_exists('Log')) class_alias(\Illuminate\Support\Facades\Log::class,'Log');
        $logger=new class { public function channel($name) {return $this;} public function error(...$args) {} public function info(...$args) {} };
        $app->instance('log',$logger); \Illuminate\Support\Facades\Log::clearResolvedInstance('log');
        $db->table('configurations')->update(['smtp_host'=>'smtp.invalid','smtp_port'=>25,'smtp_user'=>'qa@example.test','smtp_password'=>'test','smtp_encryption'=>'tls']);
        $mailer=new class {
            public $fail=true; public $calls=0; public $recipient;
            public function to($recipients) {$this->recipient=$recipients;return $this;}
            public function send($mailable) {$this->calls++; if ($this->fail || in_array('failure@example.test',$this->recipient,true)) throw new \RuntimeException('Fallo simulado');}
        };
        $app->instance('mail.manager',$mailer); \Illuminate\Support\Facades\Mail::clearResolvedInstance('mail.manager');
        \Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
        $renderer=$this->createMock(\Modules\CashReport\Services\CashReportRenderer::class);
        $renderer->expects(self::exactly(2))->method('pdfContent')->willReturn('%PDF-caja-sintetico');
        $app->instance(\Modules\CashReport\Services\CashReportRenderer::class,$renderer);
        $cashId=$db->table('cash')->where('user_id',$invoice['user_id'])->value('id');
        $request=fn($id)=>\Illuminate\Http\Request::create('/', 'POST',['id'=>$id,'customer_id'=>$invoice['customer_id'],'customer_email'=>'qa@example.test','email'=>'qa@example.test']);
        $endpoints=[
            fn()=>(new \Modules\Order\Http\Controllers\OrderNoteController())->email($request($order)),
            fn()=>(new \Modules\Order\Http\Controllers\Api\OrderNoteController())->email($request($order)),
            fn()=>(new \Modules\Order\Http\Controllers\OrderFormController())->email($request($orderForm)),
            fn()=>(new \Modules\Purchase\Http\Controllers\Api\PurchaseController())->email($request($purchase)),
            fn()=>(new \Modules\Purchase\Http\Controllers\PurchaseOrderController())->email($request($purchaseOrder)),
            fn()=>(new \Modules\Sale\Http\Controllers\ContractController())->email($request($contract)),
            fn()=>(new \Modules\Sale\Http\Controllers\SaleOpportunityController())->email($request($opportunity)),
            fn()=>(new \App\Http\Controllers\Tenant\Api\MobileController())->document_email($request($document->id)),
            fn()=>(new \Modules\Pos\Http\Controllers\CashController())->email(\Illuminate\Http\Request::create('/', 'POST',
                ['cash_id'=>$cashId,'email'=>'qa@example.test'])),
        ];
        foreach ($endpoints as $send) self::assertFalse($send()['success']);
        $mailer->fail=false;
        foreach ($endpoints as $send) self::assertTrue($send()['success']);
        $quote=new \Modules\Purchase\Models\PurchaseQuotation();
        $quote->id=1;
        $quote->suppliers=[['supplier_id'=>$invoice['customer_id'],'email'=>'failure@example.test'],['supplier_id'=>$invoice['customer_id'],'email'=>'qa@example.test']];
        $purchaseQuote=new \Modules\Purchase\Http\Controllers\PurchaseQuotationController();
        self::assertFalse($purchaseQuote->email($quote)['success'],'Un fallo inicial no puede ocultarse con un éxito posterior.');
        $quote->suppliers=[['supplier_id'=>$invoice['customer_id'],'email'=>'qa@example.test']];
        self::assertTrue($purchaseQuote->email($quote)['success']);
        $quote->suppliers=[];
        self::assertFalse($purchaseQuote->email($quote)['success']);
        self::assertSame(21,$mailer->calls);
        \Illuminate\Support\Facades\Mail::clearResolvedInstance('mail.manager');
        \Illuminate\Support\Facades\Log::clearResolvedInstance('log');
    }

    public function test_free_form_document_email_endpoints_report_transport_result(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        $noteController=new \App\Http\Controllers\Tenant\SaleNoteController();
        $noteInput=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($noteController->mergeData($noteInput));
        if (!class_exists('Log')) class_alias(\Illuminate\Support\Facades\Log::class,'Log');
        $logger=new class { public function channel($name) {return $this;} public function error(...$args) {} public function info(...$args) {} };
        $app->instance('log',$logger); \Illuminate\Support\Facades\Log::clearResolvedInstance('log');
        $db->table('configurations')->update(['smtp_host'=>'smtp.invalid','smtp_port'=>25,'smtp_user'=>'qa@example.test','smtp_password'=>'test','smtp_encryption'=>'tls']);
        $mailer=new class {
            public $fail=true; public $calls=0;
            public function to($recipients) {return $this;}
            public function send($mailable) {$this->calls++; if ($this->fail) throw new \RuntimeException('Fallo simulado');}
        };
        $app->instance('mail.manager',$mailer); \Illuminate\Support\Facades\Mail::clearResolvedInstance('mail.manager');
        $endpoints=[
            fn ()=>(new \App\Http\Controllers\Tenant\DocumentController())->email(\App\Http\Requests\Tenant\DocumentEmailRequest::create('/', 'POST',
                ['id'=>$document->id,'recipients'=>['qa@example.test']])),
            fn ()=>$noteController->email(\Illuminate\Http\Request::create('/', 'POST',['id'=>$note->id,'customer_email'=>'qa@example.test'])),
            fn ()=>(new \App\Http\Controllers\Tenant\Api\SaleNoteController())->email(\Illuminate\Http\Request::create('/', 'POST',
                ['id'=>$note->id,'customer_email'=>'qa@example.test'])),
            fn ()=>(new \App\Http\Controllers\Tenant\DispatchController())->email(\Illuminate\Http\Request::create('/', 'POST',
                ['id'=>$order->id,'customer_email'=>'qa@example.test'])),
        ];
        foreach ($endpoints as $send) {
            $result=$send(); self::assertFalse($result['success']);
            self::assertStringContainsString('No se pudo enviar',$result['message']);
            self::assertSame(0,(int)$db->table('email_send_log')->latest('id')->value('sendit'));
        }
        $mailer->fail=false;
        foreach ($endpoints as $send) {
            self::assertTrue($send()['success']);
            self::assertSame(1,(int)$db->table('email_send_log')->latest('id')->value('sendit'));
        }
        self::assertSame(8,$mailer->calls);
        \Illuminate\Support\Facades\Mail::clearResolvedInstance('mail.manager');
        \Illuminate\Support\Facades\Log::clearResolvedInstance('log');
    }

    public function test_legacy_void_request_does_not_claim_to_cancel_an_invoice(): void
    {
        [$db,$app,,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.5',
        ]]);
        $credit=(new Facturalo)->forWebDocuments()->save($partial)->getDocument()->fresh();
        $item=\App\Models\Tenant\Item::findOrFail($invoice['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $global=$item->exactValue('stock'); $physical=$warehouse->exactValue('stock');
        $request=\App\CoreFacturalo\Requests\Inputs\VoidedInput::set(['date_of_reference'=>date('Y-m-d'),
            'documents'=>[['document_id'=>$document->id,'description'=>'Prueba']]]);
        try { (new Facturalo)->save($request); self::fail('La ruta antigua no debe informar una anulación no realizada.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('documents',$error->errors()); }
        self::assertSame(0,$db->table('voided')->count());
        self::assertNotSame('11',(string)$document->fresh()->state_type_id);
        self::assertNotSame('11',(string)$credit->fresh()->state_type_id);
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo($global));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo($physical));
        $legacy=\App\Models\Tenant\Voided::create(collect($request)->except(['type','documents'])->all());
        $legacy->documents()->create($request['documents'][0]);
        $db->table('documents')->where('id',$document->id)->update(['state_type_id'=>'11']);
        try { (new \App\Http\Controllers\Tenant\VoidedController())->destroy($legacy->id); self::fail('No debe reactivarse la factura por una comunicación histórica.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('documents',$error->errors()); }
        self::assertSame('11',(string)$document->fresh()->state_type_id);
        self::assertTrue($db->table('voided')->where('id',$legacy->id)->exists());
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo($global));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo($physical));
    }

    public function test_free_form_control_cannot_duplicate_a_delivery_order_control(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        // Synthetic existing external control, kept in this temporary database only.
        $db->table('dispatches')->where('id',$order->id)->update(['control_number'=>'00-7']);
        $doc=(new Facturalo)->save($invoice)->getDocument()->fresh();
        try {
            \App\Services\Fiscal\FreeFormControl::register($doc,['control_number'=>'00-00000007','evidence_reference'=>'Factura física']);
            self::fail('El control pertenece a la orden de entrega.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('control_number',$error->errors());
        }
        self::assertNull($doc->fresh()->control_number);
    }

    public function test_delivery_order_reports_keep_fractional_quantities_and_frozen_units(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('route')->willReturn('http://dispatch.example.test/document');
        $url->method('to')->willReturn('http://dispatch.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $app->instance('url',$url);
        $raw['items'][0]['quantity']='1.2345';
        $first=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        $firstLine=$first->items()->firstOrFail();
        self::assertSame('1.2345',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($firstLine,'quantity')));
        $before=$firstLine->getAttributes();
        $data=$firstLine->getCollectionData();
        self::assertSame('1.2345',$data['quantity']);
        self::assertSame($before,$firstLine->getAttributes(),'Leer el reporte no debe cambiar la partida.');
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $item->unit_type_id='KG'; $item->save();
        self::assertSame('UND',$firstLine->getCollectionData()['item']['unit_type_id']);
        $raw['creation_key']=(string)Str::uuid(); $raw['items'][0]['quantity']='2.3456';
        $second=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        $secondLine=$second->items()->firstOrFail();
        $lines=new \Illuminate\Database\Eloquent\Collection([$firstLine,$secondLine]);
        $unitTotals=\Modules\Report\Services\DispatchReportAmounts::totalsByUnit($lines);
        self::assertSame('1.2345',\App\Support\Exact\Decimal::represent($unitTotals['UND']));
        self::assertSame('2.3456',\App\Support\Exact\Decimal::represent($unitTotals['KG']));
        $unknown=clone $firstLine; $unknown->item=(object)['description'=>'Sin unidad'];
        self::assertNull(\Modules\Report\Services\DispatchReportAmounts::unit($unknown));
        self::assertSame([],\Modules\Report\Services\DispatchReportAmounts::totalsByUnit([$unknown]));
        $params=['document_type_id'=>null,'period'=>'date','date_start'=>date('Y-m-d'),'date_end'=>date('Y-m-d'),
            'month_start'=>null,'month_end'=>null,'item_id'=>null,'user_id'=>null,'customer_id'=>null,'establishment_id'=>null];
        $request=\Illuminate\Http\Request::create('/','GET',$params);
        $controller=new \Modules\Report\Http\Controllers\ReportGuideController();
        $totals=$controller->totalsByItem($request);
        self::assertCount(2,$totals);
        self::assertEqualsCanonicalizing(['1.2345','2.3456'],$totals->pluck('quantity')->all());
        config(['tenant.items_per_page'=>10]);
        $page=$controller->records($request);
        $old=(array)$db->table('dispatch_items')->where('id',$firstLine->id)->first();
        $changed=json_decode($old['calculation_snapshot'],true) ?: \App\Support\Exact\Decimal::snapshot([]);
        $changed['values']['quantity']=\App\Support\Exact\Decimal::fraction('9');
        $db->table('dispatch_items')->where('id',$firstLine->id)->update(['quantity'=>'9',
            'calculation_snapshot'=>json_encode($changed,JSON_THROW_ON_ERROR)]);
        self::assertSame('1.2345',$page->toArray($request)->firstWhere('id',$firstLine->id)['quantity']);
        self::assertSame('9',$controller->records($request)->toArray($request)->firstWhere('id',$firstLine->id)['quantity']);
        $db->table('dispatch_items')->where('id',$firstLine->id)->update(['quantity'=>$old['quantity'],
            'calculation_snapshot'=>$old['calculation_snapshot']]);
        $this->capsule->addConnection($db->getConfig(),'dispatch_report_writer');
        $writer=$this->capsule->getConnection('dispatch_report_writer'); $interleaved=false;
        $db->listen(function ($query) use (&$interleaved,$writer,$firstLine,$changed) {
            if ($interleaved || $query->connectionName!=='tenant' || !str_contains($query->sql,'from `dispatch_items`')) return;
            $interleaved=true;
            $writer->table('dispatch_items')->where('id',$firstLine->id)->update(['quantity'=>'9',
                'calculation_snapshot'=>json_encode($changed,JSON_THROW_ON_ERROR)]);
        });
        $during=$controller->records($request)->toArray($request);
        self::assertTrue($interleaved);
        self::assertSame('1.2345',$during->firstWhere('id',$firstLine->id)['quantity']);
        self::assertSame('9',$controller->records($request)->toArray($request)->firstWhere('id',$firstLine->id)['quantity']);
        $writer->table('dispatch_items')->where('id',$firstLine->id)->update(['quantity'=>$old['quantity'],
            'calculation_snapshot'=>$old['calculation_snapshot']]);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/dispatch-report-views',
            'view.paths'=>[base_path('resources/views')], 'excel'=>require base_path('config/excel.php')]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        $company=Company::firstOrFail();
        $establishment=Establishment::findOrFail($raw['establishment_id']);
        $records=$lines;
        $html=view('report::guides.report_pdf',compact('records','company','establishment','params'))->render();
        self::assertStringContainsString('Total UND',$html);
        self::assertStringContainsString('Total KG',$html);
        self::assertStringContainsString('1.2345',$html);
        self::assertStringContainsString('2.3456',$html);
        $pdf=new \Dompdf\Dompdf(); $pdf->loadHtml($html); $pdf->render();
        $bytes=$pdf->output(); self::assertStringStartsWith('%PDF-',$bytes);
        if ($dir=getenv('PRO9_DISPATCH_REPORT_EVIDENCE')) file_put_contents($dir.'/ordenes.pdf',$bytes);
        $temp=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temp);
        foreach ([(new \Modules\Report\Exports\GuidesConsolidatedExport())->records($records),
            (new \Modules\Report\Exports\GuidesConsolidatedTotalExport())->records($totals)] as $export) {
            $export->company($company)->establishment($establishment)->params($params);
            $file=(new \Maatwebsite\Excel\Writer($temp))->export($export,\Maatwebsite\Excel\Excel::XLSX);
            try {
                $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
                self::assertNotNull($book->getSheetByName('Fracciones exactas'));
                $values=collect($book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false))->flatten()->all();
                self::assertContains('1.2345',$values); self::assertContains('2.3456',$values);
                $book->disconnectWorksheets();
            } finally { $file->delete(); }
        }
        $app->bind('dompdf.wrapper',fn($app)=>new \Barryvdh\DomPDF\PDF(new \Dompdf\Dompdf(),$app['config'],$app['files'],$app['view']));
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        $job=new \Modules\Report\Jobs\ProcessReportSalesConsolidated(1,1,$params,$raw['establishment_id'],'detail','pdf','guides');
        $buildPdf=new \ReflectionMethod($job,'buildPdf'); $buildPdf->setAccessible(true);
        self::assertStringStartsWith('%PDF-',$buildPdf->invoke($job,$company,$establishment,$params)->output());
        $buildExcel=new \ReflectionMethod($job,'buildExcelExport'); $buildExcel->setAccessible(true);
        $queuedExport=$buildExcel->invoke($job,$company,$establishment,$params);
        $file=(new \Maatwebsite\Excel\Writer($temp))->export($queuedExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
            self::assertNotNull($book->getSheetByName('Fracciones exactas'));
            $book->disconnectWorksheets();
        } finally { $file->delete(); }
    }

    public function test_dispatch_invoice_catalog_only_offers_active_receipt_methods(): void
    {
        [$db, $app, $raw] = $this->dispatchFixture();
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new class {
            public function json($data = [], $status = 200, array $headers = [], $options = 0)
            {
                return new \Illuminate\Http\JsonResponse($data, $status, $headers, $options);
            }
        });
        Company::first()->forceFill(['fiscal_emission_mode' => 'free_form'])->save();
        $db->table('payment_method_types')->where('id', '01')->update(['collection_kind' => 'account', 'is_active' => 1]);
        $db->table('payment_method_types')->where('id', '04')->update(['collection_kind' => 'cash', 'is_active' => 1]);
        $dispatch = (new Facturalo)->save(DispatchInput::set($raw))->getDocument();

        $response = app(\App\Http\Controllers\Tenant\DispatchController::class)->generateDocumentTables($dispatch->id)->getData(true);
        $methods = collect($response['payment_method_types'])->keyBy('id');
        self::assertSame('account', $methods['01']['collection_kind']);
        self::assertSame('cash', $methods['04']['collection_kind']);
        self::assertFalse($methods->has('05'));
        self::assertFalse($methods->has('09'));
        self::assertNotEmpty($response['payment_conditions']);
        foreach ($methods as $method) {
            self::assertContains($method['collection_kind'], ['cash', 'account']);
            self::assertTrue((bool) $method['is_active']);
        }
    }

    public function test_dispatch_invoice_credit_keeps_cross_currency_receipt_and_rejects_wrong_account(): void
    {
        [$db, $app, $raw, $invoice] = $this->dispatchFixture();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $bankId=$db->table('banks')->value('id');
        $usd=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'OE USD','number'=>'OE-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $ves=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'OE VES','number'=>'OE-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $db->table('payment_method_types')->where('id','01')->update(['bank_account_id'=>$usd->id]);
        $dispatch=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatch_id']=$dispatch->id;
        $invoice['payment_condition_id']='02';
        $invoice['exchange_rate_sale']='10';
        $invoice['fee']=[['date'=>date('Y-m-d',strtotime('+1 day')),'currency_type_id'=>'VES','amount'=>'182']];
        $invoice['payments']=[['date_of_payment'=>date('Y-m-d'),'payment_method_type_id'=>'01','payment_destination_id'=>$ves->id,
            'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50','igtf_status'=>'not_applicable']];
        try {
            (new Facturalo)->forWebDocuments()->save($invoice);
            self::fail('Una cuenta VES no puede recibir el método USD.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        self::assertNull($dispatch->fresh()->reference_document_id);
        $invoice['payments'][0]['payment_destination_id']=$usd->id;
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('02',$document->payment_condition_id);
        self::assertSame('USD',$document->payments->first()->currency_type_id);
        self::assertSame('50',\App\Support\Exact\Decimal::represent($document->payments->first()->exactValue('payment')));
        self::assertSame('182',\App\Support\Exact\Decimal::represent($document->fee->first()->exactValue('amount')));
        self::assertSame('182',\App\Support\Exact\Decimal::represent($document->exactBalance()));
        self::assertSame($document->id,$dispatch->fresh()->reference_document_id);
        self::assertSame('account',\App\Services\Payments\Movement::row($document->payments->first()->global_payment)['method']['collection_kind']);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    private function dispatchFixture(): array
    {
        [$db,$app,$invoice]=$this->bootHkaFixture();$calls=0;
        $driver=Driver::create(['name'=>'PRUEBA-OE-Conductor','identity_document_type_id'=>'1','number'=>'12340001','license'=>'Tercera','telephone'=>'04120000000','is_active'=>true]);
        $vehicle=Transport::create(['brand'=>'Prueba','model'=>'Camión','plate_number'=>'PRUEBA01','is_active'=>true,'tuc'=>'PRUEBA01']);
        $carrier=Dispatcher::create(['name'=>'PRUEBA-OE-Transportista','identity_document_type_id'=>'6','number'=>'000000004','address'=>'Caracas de prueba','is_active'=>true]);
        $customer=\App\Models\Tenant\Person::findOrFail($invoice['customer_id']);
        $customer->forceFill(['address'=>'Caracas de prueba','district_id'=>'000619','province_id'=>'0229','department_id'=>'14','enabled'=>true])->save();
        $address=PersonAddress::create(['person_id'=>$customer->id,'country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Llegada de prueba','has_consigned'=>false]);
        $raw=['creation_key'=>(string)Str::uuid(),'id'=>null,'document_type_id'=>'09','establishment_id'=>$invoice['establishment_id'],'series'=>'TT01','number'=>'#',
            'date_of_issue'=>date('Y-m-d'),'time_of_issue'=>'12:00:00','date_of_shipping'=>date('Y-m-d'),'customer_id'=>$customer->id,
            'transfer_reason_type_id'=>'24','transfer_reason_description'=>'Entrega sintética','transport_mode_type_id'=>'02','driver_id'=>$driver->id,'transport_id'=>$vehicle->id,'dispatcher_id'=>$carrier->id,
            'origin_address_id'=>0,'delivery_address_id'=>$address->id,'unit_type_id'=>'KG','total_weight'=>2,'packages_number'=>1,'observations'=>'Prueba','hka_data'=>[],
            'items'=>[['item_id'=>$invoice['items'][0]['item_id'],'quantity'=>2,'unit_price'=>116]],'reference_documents'=>[]];
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use($db,&$calls) {
            self::assertSame(0,$db->transactionLevel(),'HKA must run after the outer commit');
            if (str_ends_with($request->url(),'/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'private-test','expiracion'=>date('c',time()+3600)]);
            if (str_ends_with($request->url(),'/DescargaArchivo')) {$pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,10,'Delivery HKA');return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);}
            $id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];$calls++;
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[],'resultado'=>$id+['numeroControl'=>'00-'.str_pad((string)$calls,8,'0',STR_PAD_LEFT),'urlConsulta'=>'https://example.test/guide']]);
        });
        return [$db,$app,$raw,$invoice];
    }

    public function test_dispatch_automatic_hka_idempotency_snapshots_pdf_and_outer_commit(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$d=null;
        $db->transaction(function() use($raw,&$d,$db) {
            $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
            self::assertSame('not_requested',$d->emission->status);
            self::assertNull($d->control_number);
        });
        $d=$d->fresh();self::assertSame('confirmed',$d->emission->status);self::assertSame('09',$d->document_type_id);
        $payload=$d->emission->payload['documentoElectronico'];self::assertSame('04',$payload['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('1',$payload['guiaDespacho']['esGuiaDespacho']);self::assertSame('0.0000',$payload['detallesItems'][0]['precioUnitario']);self::assertArrayNotHasKey('totales',$payload['encabezado']);
        \App\Models\Tenant\Configuration::first()->forceFill(['enabled_price_items_dispatch'=>true])->save();
        \App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id'])->forceFill(['currency_type_id'=>'VES'])->save();
        $priced=$d->emission->snapshot;$priced['items']=\App\Services\Fiscal\DeliveryOrderInput::resolve($raw)['items'];
        $pricedPayload=(new \App\Services\Fiscal\HkaDispatchPayloadBuilder)->build($priced,$d->emission->operation_key);
        self::assertSame('200.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['precioItem']);
        self::assertSame('32.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['valorIVA']);
        self::assertSame('232.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['valorTotalItem']);
        self::assertSame('G',$pricedPayload['documentoElectronico']['detallesItems'][0]['codigoImpuesto']);
        \App\Models\Tenant\Configuration::first()->forceFill(['enabled_price_items_dispatch'=>false])->save();
        self::assertSame('C62',$payload['detallesItems'][0]['unidadMedida']);
        self::assertStringStartsWith('%PDF-',app(HkaDispatchPdf::class)->download($d));
        $key=$d->emission->operation_key;$again=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        self::assertSame($d->id,$again->id);self::assertSame($key,$again->emission->operation_key);self::assertSame(1,$db->table('dispatches')->count());
        self::assertSame(0,$db->table('inventory_kardex')->where('inventory_kardexable_type',Dispatch::class)->count());
        $bad=$raw;$bad['total_weight']=3;
        try {(new Facturalo)->save(DispatchInput::set($bad));self::fail('UUID collision must fail');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(409,$e->getStatusCode());}
        try {DeliveryOrderPersistence::assertEditable($d);self::fail('Confirmed orders are immutable');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('document',$e->errors());}
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->statement('DROP TABLE dispatch_emissions');$db->statement('ALTER TABLE dispatches DROP COLUMN creation_key, DROP COLUMN creation_hash, DROP COLUMN hka_data');
        $legacy=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertSame(2,$db->table('dispatches')->count());self::assertNull(HkaDispatchEmission::record($legacy));
    }

    public function test_dispatch_global_kardex_exports_exact_amounts_and_identifies_order(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $opening=\App\Models\Tenant\Kardex::create(['date_of_issue'=>date('Y-m-d'),'item_id'=>$item->id,'quantity'=>'10']);
        $raw['items'][0]['quantity']='0.333333333333';
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $movementId=$order->items()->firstOrFail()->calculation_snapshot['inputs']['dispatch_global_inventory']['kardex_id'];
        $records=\App\Models\Tenant\Kardex::whereIn('id',[$opening->id,$movementId])->orderBy('id')->get();
        $row=\App\Support\Exact\LegacyKardexRow::from($records->last());
        self::assertSame('Orden de entrega',$row['description']); self::assertSame($order->number_full,$row['number']);
        self::assertTrue($row['exit']->isEqualTo('0.333333333333'));
        $root=sys_get_temp_dir().'/'.$this->database.'/dispatch-kardex'; mkdir($root.'/views',0777,true);
        config(['view.compiled'=>$root.'/views','view.paths'=>[base_path('resources/views')],
            'excel'=>require base_path('config/excel.php')]);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app->register(\Illuminate\Filesystem\FilesystemServiceProvider::class);
        $company=Company::firstOrFail(); $establishment=\App\Models\Tenant\Establishment::findOrFail($raw['establishment_id']);
        $html=view('tenant.reports.kardex.report_pdf',['reports'=>$records,'balance'=>0,'company'=>$company,'establishment'=>$establishment])->render();
        self::assertStringContainsString('Orden de entrega',$html); self::assertStringContainsString('0.333333333333',$html);
        self::assertStringContainsString('9.666666666667',$html);
        $pdf=new \Dompdf\Dompdf(); $pdf->loadHtml($html); $pdf->render(); file_put_contents($root.'/kardex.pdf',$pdf->output());
        if ($evidence=getenv('PRO9_DISPATCH_KARDEX_EVIDENCE_DIR')) copy($root.'/kardex.pdf',$evidence.'/kardex.pdf');
        $process=proc_open(['pdftotext','-layout',$root.'/kardex.pdf',$root.'/kardex.txt'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        self::assertIsResource($process); foreach ($pipes as $pipe) fclose($pipe); self::assertSame(0,proc_close($process));
        $pdfText=file_get_contents($root.'/kardex.txt');
        self::assertStringContainsString('0.333333333333',$pdfText); self::assertStringContainsString('9.666666666667',$pdfText);
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $excel=new \Maatwebsite\Excel\Excel(new \Maatwebsite\Excel\Writer($temporaryFiles),
            $this->createMock(\Maatwebsite\Excel\QueuedWriter::class),$this->createMock(\Maatwebsite\Excel\Reader::class),
            new \Maatwebsite\Excel\Files\Filesystem($app['filesystem']));
        $export=(new \App\Exports\KardexExport())->balance(0)->records($records)->company($company)->establishment($establishment);
        file_put_contents($root.'/kardex.xlsx',$excel->raw($export,\Maatwebsite\Excel\Excel::XLSX));
        $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($root.'/kardex.xlsx');
        $rows=$book->getSheet(0)->toArray(null,true,false,false); $cells=collect($rows)->flatten()->map(fn($v)=>(string)$v)->all();
        self::assertContains('0.333333333333',$cells); self::assertContains('9.666666666667',$cells);
        self::assertContains('Orden de entrega',$cells);
        $fractions=$book->getSheetByName('Fracciones exactas'); self::assertNotNull($fractions);
        $fractionRows=$fractions->toArray(null,true,false,false);
        self::assertContains([(string)$movementId,'quantity','0.333333333333','333333333333','1000000000000'],$fractionRows);
        $book->disconnectWorksheets();
        $db->transaction(function () use ($order) { $order->state_type_id='11'; $order->save(); });
        $reversal=\App\Models\Tenant\Kardex::where('item_id',$item->id)->orderByDesc('id')->firstOrFail();
        $reversalRow=\App\Support\Exact\LegacyKardexRow::from($reversal);
        self::assertSame('Anulación de orden de entrega',$reversalRow['description']);
        self::assertSame($order->number_full,$reversalRow['number']);
        self::assertTrue($reversalRow['exit']->isEqualTo('-0.333333333333'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('10'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('10'));
    }

    public function test_dispatch_mixed_orders_only_move_uncovered_global_quantity(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $raw['items'][0]['quantity']='0.1';
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $first=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $raw['creation_key']=(string)Str::uuid(); $raw['items'][0]['quantity']='0.2';
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>false]);
        $second=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.9'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.9'));
        $invoice['dispatches_relateds']=[$first->number_full,$second->number_full];
        $invoice['items'][0]['quantity']='0.3'; $invoice['items'][0]['unit_price']='3';
        $invoice['items'][0]['affectation_igv_type_id']='20'; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.7'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.7'));
        $db->transaction(function () use ($document) { $document->state_type_id='11'; $document->save(); });
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.9'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.9'));
    }

    public function test_direct_sale_note_invoice_does_not_repeat_physical_inventory(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame('8',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('8',(string)$warehouse->fresh()->exactValue('stock'));
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $unrelated=$invoice; $unrelated['order_note_id']=999999;
        try { (new Facturalo)->forWebDocuments()->save($unrelated); self::fail('No debe asociarse un pedido ajeno a la nota.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('order_note_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertNull($note->fresh()->document_id);
        $movement=\App\Models\Tenant\Kardex::where('sale_note_id',$note->id)->firstOrFail()->getAttributes();
        $db->table('kardex')->where('id',$movement['id'])->delete();
        try { (new Facturalo)->forWebDocuments()->save($invoice); self::fail('No debe inferirse un movimiento global inexistente.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('inventory',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertNull($note->fresh()->document_id);
        self::assertSame('8',(string)$item->fresh()->exactValue('stock'));
        $db->table('kardex')->insert($movement);
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('8',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('8',(string)$warehouse->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.5',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('17/2',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('17/2',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_direct_sale_note_invoice_preserves_one_third_of_presentation(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        unset($invoice['items'][0]['quantity_input']);
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('1/3',(string)$document->items->first()->exactValue('quantity'));
        self::assertTrue($document->exactValue('total')->isEqualTo($note->fresh()->exactValue('total')));
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('93/10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('93/10',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_direct_invoice_presentation_and_credits_restore_exact_global_stock(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.1',
        ]]);
        $source=$document->items->first(); $snapshot=$source->calculation_snapshot;
        $forged=$snapshot; $forged['inputs']['invoice_global_inventory']['quantity']=['n'=>'999','d'=>'1'];
        $db->table('document_items')->where('id',$source->id)->update(['calculation_snapshot'=>json_encode($forged,JSON_THROW_ON_ERROR)]);
        try { (new Facturalo)->forWebDocuments()->save($partial); self::fail('No se debe aceptar un movimiento global alterado.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('inventory',$error->errors()); }
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        $db->table('document_items')->where('id',$source->id)->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('93/10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('93/10',(string)$item->fresh()->exactValue('stock'));
        (new Facturalo)->forWebDocuments()->save($this->noteInput($invoice,$document,'07','01',[]));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        $invoice['external_id']=(string)Str::uuid();
        $second=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        $db->transaction(function () use ($second) { $second->state_type_id='11'; $second->save(); $second->save(); });
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
    }

    public function test_direct_pack_invoice_credits_and_void_use_exact_original_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-DIRECT-PACK-INVOICE'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $snapshot=json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']));
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333','calculation_snapshot'=>$snapshot]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('4',(string)$pack->fresh()->exactValue('stock'));
        (new Facturalo)->forWebDocuments()->save($this->noteInput($invoice,$document,'07','01',[]));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'0.333333333333','calculation_snapshot'=>$snapshot]);
        $invoice['external_id']=(string)Str::uuid();
        $second=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $db->transaction(function () use ($second) { $second->state_type_id='11'; $second->save(); $second->save(); });
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_order_pack_invoice_credit_uses_original_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-ORDER-PACK-INVOICE'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $orderInput=array_replace($invoice,['prefix'=>'PED','prepayments'=>[]]);
        $controller=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $created=$controller->store(\Modules\Order\Http\Requests\OrderNoteRequest::create('/','POST',$orderInput));
        $order=\Modules\Order\Models\OrderNote::findOrFail($created['data']['id']);
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $invoice['order_note_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>false]);
        $unrelated=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $mixed=$invoice; $mixed['dispatch_id']=$unrelated->id;
        try { (new Facturalo)->forWebDocuments()->save($mixed); self::fail('No debe asociarse una orden de entrega ajena al pedido.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('dispatches_relateds',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        $invoice['items'][0]['_order_components']=[['item_id'=>$component->id,'quantity'=>'500']];
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        self::assertSame(0,$document->inventory_kardex()->count(),'La factura no debe registrar otra salida del pedido.');
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('4',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_order_sale_note_presentation_and_credit_keep_global_stock_exact(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        $orders=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $created=$orders->store(\Modules\Order\Http\Requests\OrderNoteRequest::create('/','POST',array_replace($invoice,['prefix'=>'PED','prepayments'=>[]])));
        $order=\Modules\Order\Models\OrderNote::findOrFail($created['data']['id']);
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        $input=array_replace($invoice,['id'=>null,'order_note_id'=>$order->id,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],'payment_condition_id'=>'02']);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class); $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $notes=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $saved=$notes->storeWithData($input); self::assertTrue($saved['success'],$saved['message'] ?? '');
        $note=\App\Models\Tenant\SaleNote::findOrFail($saved['data']['id']);
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('93/10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('93/10',(string)$item->fresh()->exactValue('stock'));
    }

    public function test_order_pack_sale_note_invoice_credit_uses_original_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-ORDER-PACK-NOTE'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $orders=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $created=$orders->store(\Modules\Order\Http\Requests\OrderNoteRequest::create('/','POST',array_replace($invoice,['prefix'=>'PED','prepayments'=>[]])));
        $order=\Modules\Order\Models\OrderNote::findOrFail($created['data']['id']);
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $input=array_replace($invoice,['id'=>null,'order_note_id'=>$order->id,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],'payment_condition_id'=>'02']);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class); $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $notes=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $saved=$notes->storeWithData($input); self::assertTrue($saved['success'],$saved['message'] ?? '');
        $note=\App\Models\Tenant\SaleNote::findOrFail($saved['data']['id']);
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('4',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_sale_note_pack_edit_delete_and_void_use_original_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-NV-PACK-EDIT'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $snapshot=json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']));
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333','calculation_snapshot'=>$snapshot]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class); $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $controller->anulate($note->id); $controller->anulate($note->id);
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'0.333333333333','calculation_snapshot'=>$snapshot]);
        $input['external_id']=(string)Str::uuid();
        $second=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $edit=$input; $edit['id']=$second->id; $edit['number']=$second->number; $edit['external_id']=$second->external_id;
        $edit['items'][0]['quantity']='3';
        $result=$controller->storeWithData($edit); self::assertTrue($result['success'],$result['message'] ?? '');
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('2',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->delete();
        $controller->destroy_sale_note_item($second->items()->firstOrFail()->id);
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->insert(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333','calculation_snapshot'=>$snapshot]);
        $db->table('inventory_configurations')->update(['stock_control'=>1]);
        $input['external_id']=(string)Str::uuid(); $input['items'][0]['quantity']='999';
        $count=$db->table('sale_notes')->count();
        try {
            $db->transaction(function () use ($controller,$input) {
                $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
                foreach ($input['items'] as $line) $note->items()->create($line);
            });
            self::fail('El paquete debe rechazar componentes insuficientes.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertSame($count,$db->table('sale_notes')->count());
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('5',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_sale_note_pack_invoice_and_credit_keep_original_exact_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-NV-PACK'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $invoice['items'][0]['_sale_note_components']=[['item_id'=>$component->id,'quantity'=>'500']];
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('4',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_related_sale_note_packs_group_identical_original_components(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-NV-PACK-GROUP'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['quantity'=>'1/3']))]);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController(); $notes=[];
        foreach ([1,2] as $unused) {
            $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],
                'external_id'=>(string)Str::uuid()]);
            $input['items'][0]['quantity']='1';
            $notes[]=$db->transaction(function () use ($controller,$input) {
                $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
                foreach ($input['items'] as $line) $note->items()->create($line);
                return $note;
            });
        }
        $db->table('item_sets')->where('id',$set)->update(['quantity'=>'4','calculation_snapshot'=>null]);
        $invoice['sale_notes_relateds']=array_map(fn($note)=>['id'=>$note->id],$notes);
        $preview=\App\Support\Exact\SaleNoteConversionLines::preview(array_column($invoice['sale_notes_relateds'],'id'));
        self::assertCount(1,$preview['grouped_items']);
        $invoice['items'][0]['quantity']='2'; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)$pack->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('4',(string)$pack->fresh()->exactValue('stock'));
    }

    public function test_related_sale_note_packs_with_different_compositions_cannot_be_merged(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $component=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $pack=$component->replicate(); $pack->internal_id='QA-NV-PACK-DIFFERENT'; $pack->is_set=true; $pack->stock='5'; $pack->save();
        $set=$db->table('item_sets')->insertGetId(['item_id'=>$pack->id,'individual_item_id'=>$component->id,'quantity'=>'1']);
        $invoice['items'][0]['item_id']=$pack->id; $invoice['items'][0]['item']=$pack->fresh()->toArray();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController(); $notes=[];
        foreach (['1','2'] as $quantity) {
            $db->table('item_sets')->where('id',$set)->update(['quantity'=>$quantity,'calculation_snapshot'=>null]);
            $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],
                'external_id'=>(string)Str::uuid()]);
            $input['items'][0]['quantity']='1';
            $notes[]=$db->transaction(function () use ($controller,$input) {
                $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
                foreach ($input['items'] as $line) $note->items()->create($line);
                return $note;
            });
        }
        $invoice['sale_notes_relateds']=array_map(fn($note)=>['id'=>$note->id],$notes);
        $preview=\App\Support\Exact\SaleNoteConversionLines::preview(array_column($invoice['sale_notes_relateds'],'id'));
        self::assertCount(2,$preview['grouped_items']);
        $invoice['items'][0]['quantity']='2'; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        try { (new Facturalo)->forWebDocuments()->save($invoice); self::fail('No se deben fusionar composiciones distintas.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame('7',(string)$warehouse->fresh()->exactValue('stock'));
        $invoice['items'][0]['quantity']='1'; $invoice['items'][]=$invoice['items'][0];
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertCount(2,$document->items);
        self::assertSame('1',(string)\App\Support\Exact\Decimal::of($document->items[0]->item->inventory_components[0]->quantity));
        self::assertSame('2',(string)\App\Support\Exact\Decimal::of($document->items[1]->item->inventory_components[0]->quantity));
        self::assertSame('7',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_direct_sale_note_invoice_keeps_lot_and_partial_return(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->lots_enabled=true; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $lot=$db->table('item_lots_group')->insertGetId(['item_id'=>$item->id,'code'=>'DIRECT-NV','quantity'=>'5','date_of_due'=>'2027-11-30']);
        $selection=[['id'=>$lot,'code'=>'DIRECT-NV','compromise_quantity'=>'2']];
        $invoice['items'][0]['item']['IdLoteSelected']=$selection;
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],'payment_condition_id'=>'02']);
        $input['items'][0]['IdLoteSelected']=$selection;
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class); $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $saved=$controller->storeWithData($input); self::assertTrue($saved['success'],$saved['message'] ?? '');
        $note=\App\Models\Tenant\SaleNote::findOrFail($saved['data']['id']);
        self::assertSame('3',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        $invoice['sale_note_id']=$note->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('8',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('8',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('3',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.5',
            'lot_returns'=>[['id'=>$lot,'compromise_quantity'=>'0.5']],
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('7/2',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        self::assertSame('17/2',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('17/2',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_related_sale_notes_preserve_grouped_periodic_quantities(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController(); $notes=[];
        foreach ([1,2] as $unused) {
            $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],
                'external_id'=>(string)Str::uuid()]);
            $input['items'][0]['quantity']='0.333333333333';
            $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
            $notes[]=$db->transaction(function () use ($controller,$input) {
                $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
                foreach ($input['items'] as $line) $note->items()->create($line);
                return $note;
            });
        }
        $invoice['sale_notes_relateds']=array_map(fn($note)=>['id'=>$note->id],$notes);
        $invoice['items'][0]['quantity']='0.666666666667'; unset($invoice['items'][0]['quantity_input']);
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('2/3',(string)$document->items->first()->exactValue('quantity'));
        self::assertSame('28/3',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('28/3',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertTrue($document->exactValue('total')->isEqualTo($notes[0]->fresh()->exactValue('total')->plus($notes[1]->fresh()->exactValue('total'))));
    }

    public function test_related_sale_notes_invoice_only_moves_additional_fraction_and_reverses_it(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController(); $notes=[];
        foreach (['0.1','0.2'] as $quantity) {
            $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],
                'external_id'=>(string)Str::uuid()]);
            $input['items'][0]['quantity']=$quantity;
            $notes[]=$db->transaction(function () use ($controller,$input) {
                $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
                foreach ($input['items'] as $line) $note->items()->create($line);
                return $note;
            });
        }
        self::assertSame('97/10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('97/10',(string)$warehouse->fresh()->exactValue('stock'));
        $invoice['sale_notes_relateds']=array_map(fn($note)=>['id'=>$note->id],$notes);
        $invoice['items'][0]['quantity']='0.5'; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('19/2',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('19/2',(string)$warehouse->fresh()->exactValue('stock'));
        $db->transaction(function () use ($document) { $document->state_type_id='11'; $document->save(); });
        self::assertSame('97/10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('97/10',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_direct_sale_note_invoice_excludes_quantity_already_billed_through_order(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $raw['reference_sale_note_id']=$note->id; $raw['items'][0]['quantity']='1';
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $first=$invoice; $first['dispatch_id']=$order->id; $first['items'][0]['quantity']='1';
        $first['payments']=[]; $first['payment_condition_id']='02';
        (new Facturalo)->forWebDocuments()->save($first);
        $invoice['sale_note_id']=$note->id; $invoice['items'][0]['quantity']='2';
        $invoice['external_id']=(string)Str::uuid(); $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        (new Facturalo)->forWebDocuments()->save($invoice);
        self::assertSame('7',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('7',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_sale_note_delivery_order_invoice_preserves_single_inventory_movement(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        $raw['reference_sale_note_id']=$note->id;
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        $invoice['dispatch_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $movement=\App\Models\Tenant\Kardex::where('sale_note_id',$note->id)->firstOrFail()->getAttributes();
        $db->table('kardex')->where('id',$movement['id'])->delete();
        try {
            (new Facturalo)->forWebDocuments()->save($invoice);
            self::fail('No se debe inferir un movimiento global sin Kardex.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('inventory',$error->errors());
        }
        self::assertSame(0,$db->table('documents')->count());
        self::assertNull($order->fresh()->reference_document_id);
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'));
        $db->table('kardex')->insert($movement);
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'),'La factura no debe descontar nuevamente lo vendido.');
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.5',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8.5'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8.5'));
    }

    public function test_sale_note_presentation_moves_and_restores_exact_physical_inventory(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($invoice['items'][0]['item_id']);
        $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        self::assertSame('1',(string)\App\Models\Tenant\Kardex::where('sale_note_id',$note->id)->firstOrFail()->exactValue('quantity'));
        $controller->anulate($note->id); $controller->anulate($note->id);
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        $input['external_id']=(string)Str::uuid();
        $second=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $edit=$input; $edit['id']=$second->id; $edit['number']=$second->number; $edit['external_id']=$second->external_id;
        $edit['items'][0]['quantity']='0.666666666667';
        $edit['items'][0]['quantity_input']=['amount'=>'2','unit_price'=>'3'];
        $result=$controller->storeWithData($edit);
        self::assertTrue($result['success'],$result['message'] ?? '');
        self::assertSame('8',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('8',(string)$warehouse->fresh()->exactValue('stock'));
        $controller->destroy_sale_note_item($second->items()->firstOrFail()->id);
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        $input['external_id']=(string)Str::uuid();
        $third=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $raw['reference_sale_note_id']=$third->id; $raw['items'][0]['quantity']='1';
        $stale=DispatchInput::set($raw);
        $controller->anulate($third->id);
        try { (new Facturalo)->save($stale); self::fail('La orden no debe usar una nota anulada después de resolver el borrador.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('reference_sale_note_id',$error->errors()); }
        self::assertSame(0,$db->table('dispatches')->count());
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
        $input['external_id']=(string)Str::uuid();
        $fourth=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $stockRow=(array)$db->table('item_warehouse')->where('id',$warehouse->id)->first();
        $db->table('item_warehouse')->where('id',$warehouse->id)->delete();
        try { $controller->anulate($fourth->id); self::fail('No se debe anular sin saldo de almacén.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('inventory',$error->errors()); }
        self::assertNotSame('11',(string)$fourth->fresh()->state_type_id);
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        $db->table('item_warehouse')->insert($stockRow);
        $controller->anulate($fourth->id);
        self::assertSame('10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('10',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_sale_note_presentation_through_order_invoice_and_partial_credit(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $invoice['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        $raw['reference_sale_note_id']=$note->id; $raw['items'][0]['quantity']='1';
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        try { $controller->anulate($note->id); self::fail('No se anula la nota con orden vigente.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_note',$error->errors()); }
        try { $controller->destroy_sale_note_item($note->items()->firstOrFail()->id); self::fail('No se borra una partida usada por una orden.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_note',$error->errors()); }
        $invoice['dispatch_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('9',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('9',(string)$warehouse->fresh()->exactValue('stock'));
        $partial=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.1',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partial);
        self::assertSame('93/10',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('93/10',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_sale_note_service_through_delivery_order_does_not_require_a_stock_movement(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $item->unit_type_id='SERV'; $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['item']['unit_type_id']='SERV';
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame(0,\App\Models\Tenant\Kardex::where('sale_note_id',$note->id)->count());
        $raw['reference_sale_note_id']=$note->id;
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatch_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        (new Facturalo)->forWebDocuments()->save($invoice);
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('10'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('10'));
    }

    public function test_sale_note_global_kardex_preserves_nonterminating_quantity(): void
    {
        [$db,$app,,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $item=\App\Models\Tenant\Item::findOrFail($invoice['items'][0]['item_id']);
        $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.333333333333';
        $invoice['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertSame('1/3',(string)$note->items()->firstOrFail()->exactValue('quantity'));
        self::assertSame('1/3',(string)\App\Models\Tenant\Kardex::where('sale_note_id',$note->id)->firstOrFail()->exactValue('quantity'));
        self::assertSame('29/3',(string)$item->fresh()->exactValue('stock'));
        self::assertSame('29/3',(string)$warehouse->fresh()->exactValue('stock'));
    }

    public function test_sale_note_fraction_is_allocated_only_once_across_invoiced_orders(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $invoice['items'][0]['quantity']='0.3';
        $input=array_replace($invoice,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $note=$db->transaction(function () use ($controller,$input) {
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($input));
            foreach ($input['items'] as $line) $note->items()->create($line);
            return $note;
        });
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.7'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.7'));
        $raw['reference_sale_note_id']=$note->id; $raw['items'][0]['quantity']='0.1';
        $first=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $raw['creation_key']=(string)Str::uuid(); $raw['items'][0]['quantity']='0.2';
        $second=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        foreach ([[$first,'0.1'],[$second,'0.2']] as [$order,$quantity]) {
            $invoice['dispatch_id']=$order->id; $invoice['items'][0]['quantity']=$quantity;
            $invoice['external_id']=(string)Str::uuid();
            (new Facturalo)->forWebDocuments()->save($invoice);
            self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.7'));
            self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.7'));
        }
        $raw['creation_key']=(string)Str::uuid();
        $third=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatch_id']=$third->id; $invoice['items'][0]['quantity']='0.2';
        $invoice['external_id']=(string)Str::uuid();
        (new Facturalo)->forWebDocuments()->save($invoice);
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('9.5'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('9.5'));
    }

    public function test_dispatch_invoice_credit_restores_global_and_warehouse_stock(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatch_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument()->fresh();
        $partialInput=$this->noteInput($invoice,$document,'07','07',[[
            'affected_document_item_id'=>$document->items->first()->id,'quantity'=>'0.5',
        ]]);
        (new Facturalo)->forWebDocuments()->save($partialInput);
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8.5'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8.5'));
        $creditInput=$this->noteInput($invoice,$document,'07','01',[]);
        $credit=(new Facturalo)->forWebDocuments()->save($creditInput)->getDocument()->fresh();
        self::assertTrue($credit->exactValue('total')->isLessThan($document->exactValue('total')));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('10'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('10'),'La NC debe devolver también el saldo global.');
    }

    public function test_dispatch_invoice_does_not_repeat_global_stock_movement(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']); $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        $invoice['dispatch_id']=$order->id; $invoice['payments']=[]; $invoice['payment_condition_id']='02';
        $document=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'),'Facturar la orden no debe repetir la salida global.');
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        $db->transaction(function () use ($document) { $document->state_type_id='11'; $document->save(); });
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
    }

    public function test_dispatch_global_stock_follows_creation_edit_and_void_in_free_form(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();
        (new \App\Providers\KardexServiceProvider($app))->boot();
        (new \App\Providers\AnulationServiceProvider($app))->boot();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);
        $item->stock='10'; $item->save();
        $warehouse=\Modules\Inventory\Models\ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',1)->firstOrFail();
        $warehouse->stock='10'; $warehouse->save();
        $order=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('8'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('8'),'El saldo global debe reflejar la salida física.');
        $edit=$raw; $edit['id']=$order->id; $edit['items'][0]['quantity']='3';
        $order=(new Facturalo)->save(DispatchInput::set($edit))->getDocument();
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('7'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('7'));
        $db->transaction(function () use ($order) { $order->state_type_id='11'; $order->save(); });
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo('10'));
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('10'));
        $db->transaction(fn()=>$order->save());
        self::assertTrue($item->fresh()->exactValue('stock')->isEqualTo('10'));
    }

    public function test_dispatch_stock_uses_its_branch_and_edits_reverse_once(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $branch=Establishment::create(['description'=>'Other','country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Other test','code'=>'0001','telephone'=>'04120000000']);
        $warehouse=\Modules\Inventory\Models\Warehouse::create(['establishment_id'=>$branch->id,'description'=>'Other']);
        $db->table('item_warehouse')->insert(['warehouse_id'=>$warehouse->id,'item_id'=>$raw['items'][0]['item_id'],'stock'=>20]);
        $series=\App\Models\Tenant\Series::create(['establishment_id'=>$branch->id,'document_type_id'=>'09','number'=>'TEST-OE']);
        $raw['establishment_id']=$branch->id;$raw['series']='TEST-OE';
        $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();self::assertEquals(18,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        self::assertEquals(10,$db->table('item_warehouse')->where('warehouse_id',1)->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $edit=$raw;$edit['id']=$d->id;$edit['items'][0]['quantity']=3;
        $d=(new Facturalo)->save(DispatchInput::set($edit))->getDocument()->fresh();self::assertEquals(17,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $d->getConnection()->transaction(function() use($d) {$d->state_type_id='11';$d->save();});self::assertEquals(20,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $d->getConnection()->transaction(function() use($d) {$d->save();});self::assertEquals(20,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);$item->forceFill(['lots_enabled'=>true,'series_enabled'=>true])->save();
        $lot=\Modules\Item\Models\ItemLotsGroup::create(['item_id'=>$item->id,'code'=>'PRUEBA-OE-Lote','quantity'=>20,'date_of_due'=>date('Y-m-d',time()+86400)]);
        $serials=[];
        for($i=1;$i<=3;$i++) $serials[]=\Modules\Item\Models\ItemLot::create(['item_id'=>$item->id,'warehouse_id'=>$warehouse->id,'series'=>'PRUEBA-OE-'.$i,'date'=>date('Y-m-d'),'item_loteable_type'=>\Modules\Inventory\Models\Inventory::class,'item_loteable_id'=>1,'has_sale'=>false])->toArray();
        $raw['creation_key']=(string)Str::uuid();$raw['items'][0]['IdLoteSelected']=$lot->id;$raw['items'][0]['lots']=array_slice($serials,0,2);
        $tracked=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertEquals(18,$lot->fresh()->quantity);self::assertSame(2,$db->table('item_lots')->where('has_sale',1)->count());
        $repeated=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();self::assertSame($tracked->id,$repeated->id);self::assertEquals(18,$lot->fresh()->quantity);
        $edit=$raw;$edit['id']=$tracked->id;$edit['items'][0]['quantity']=3;$edit['items'][0]['lots']=$serials;
        $tracked=(new Facturalo)->save(DispatchInput::set($edit))->getDocument();
        self::assertEquals(17,$lot->fresh()->quantity);self::assertSame(3,$db->table('item_lots')->where('has_sale',1)->count());
        $db->transaction(function() use($tracked){$tracked->state_type_id='11';$tracked->save();});
        self::assertEquals(20,$lot->fresh()->quantity);self::assertSame(0,$db->table('item_lots')->where('has_sale',1)->count());
        $bad=$raw;$bad['items'][0]['lots']=[$serials[0],$serials[0]];
        try {DispatchInput::set($bad);self::fail('Duplicate serial rejected');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
        $bad=$raw;$bad['items'][0]['quantity']=1.5;$bad['items'][0]['lots']=[$serials[0]];
        try {DispatchInput::set($bad);self::fail('Fractional serial quantity rejected');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
    }

    public function test_dispatch_timeout_and_absence_reconcile_same_operation(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$http=new \Illuminate\Http\Client\Factory;$app->instance(\Illuminate\Http\Client\Factory::class,$http);\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $http->fake(function($request) {if(str_ends_with($request->url(),'/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'private-test','expiracion'=>date('c',time()+3600)]);throw new \Illuminate\Http\Client\ConnectionException('Timeout');});
        $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();self::assertSame('uncertain',$d->emission->status);$key=$d->emission->operation_key;
        $e=$d->emission;$response=$e->response;$response['started_at']=time()-60;$e->update(['response'=>$response]);
        $http=new \Illuminate\Http\Client\Factory;$app->instance(\Illuminate\Http\Client\Factory::class,$http);\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $http->fake(fn()=>\Illuminate\Support\Facades\Http::response(['codigo'=>'203','mensaje'=>'Consulta no procesada','validaciones'=>['Documento no encontrado en nuestra base de datos'],'estado'=>null]));
        $view=app(HkaDispatchEmission::class)->query($d);self::assertTrue($view['can_send'],json_encode($view));self::assertSame($key,$d->fresh()->emission->operation_key);self::assertSame(1,$db->table('dispatches')->count());
    }

    public function test_dispatch_customer_destinations_match_server_resolution_and_new_address_keeps_location(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();
        $customer=\App\Models\Tenant\Person::findOrFail($raw['customer_id']);
        $customer->forceFill(['address'=>'Dirección principal','department_id'=>'14','province_id'=>'0229','district_id'=>'000619'])->save();
        $controller=new \App\Http\Controllers\Tenant\DispatchController;
        $options=$controller->getDeliveryAddresses($customer->id);
        self::assertSame(0,$options[0]['id']);
        self::assertSame('Dirección principal',$options[0]['address']);
        self::assertContains($raw['delivery_address_id'],array_column($options,'id'));
        foreach ($options as $option) {
            $input=$raw;$input['delivery_address_id']=$option['id'];
            $resolved=\App\Services\Fiscal\DeliveryOrderInput::resolve($input);
            self::assertSame($option['address'],$resolved['delivery']['address']);
        }
        $address=\App\Models\Tenant\PersonAddress::findOrFail($raw['delivery_address_id']);
        $address->forceFill(['has_consigned'=>true])->save();
        self::assertNotContains($address->id,array_column($controller->getDeliveryAddresses($customer->id),'id'));
        $request=\Illuminate\Http\Request::create('/dispatch_addresses/save','POST',[
            'person_id'=>$customer->id,'address'=>'Nueva llegada','location_id'=>['14','0229','000619'],'has_consigned'=>false,
        ]);
        $result=(new \Modules\Dispatch\Http\Controllers\DispatchAddressController)->saveAddressPerson($request);
        $saved=\App\Models\Tenant\PersonAddress::findOrFail($result['id']);
        self::assertSame('14',$saved->department_id);self::assertSame('0229',$saved->province_id);self::assertSame('000619',$saved->district_id);
        $input=$raw;$input['delivery_address_id']=$saved->id;
        self::assertSame('Nueva llegada',\App\Services\Fiscal\DeliveryOrderInput::resolve($input)['delivery']['address']);
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $main=$raw;$main['delivery_address_id']=0;
        $order=(new Facturalo)->save(DispatchInput::set($main))->getDocument();
        self::assertSame('Dirección principal',$order->delivery->address);
        self::assertSame([],$controller->getAddressesOtherEstablishments($raw['establishment_id']));
        $branch=Establishment::create(['description'=>'Destino propio','country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Otro local','code'=>'0001','telephone'=>'04120000000']);
        $ownOptions=$controller->getAddressesOtherEstablishments($raw['establishment_id']);
        self::assertCount(1,$ownOptions);
        $own=$raw;$own['creation_key']=(string)Str::uuid();$own['transfer_reason_type_id']='04';$own['customer_id']=null;$own['delivery_address_id']=$ownOptions[0]['id'];
        $ownOrder=(new Facturalo)->save(DispatchInput::set($own))->getDocument();
        self::assertSame('Otro local',$ownOrder->delivery->address);
        self::assertSame(2,$db->table('dispatches')->count());
        $app->make(\Illuminate\Http\Client\Factory::class)->assertNothingSent();
    }

    public function test_dispatch_server_rejects_foreign_addresses_and_invalid_weight_before_network(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$bad=$raw;$bad['total_weight']=0;
        try {DispatchInput::set($bad);self::fail('Positive weight required');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('total_weight',$e->errors());}
        $bad=$raw;$bad['customer_id']=\App\Models\Tenant\Person::where('id','!=',$raw['customer_id'])->where('type','customers')->firstOrFail()->id;
        try {DispatchInput::set($bad);self::fail('Foreign address rejected');} catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e) {}
        $user=auth()->user();
        auth()->setUser(new \Illuminate\Auth\GenericUser(['id'=>999]));
        try {DispatchInput::set($raw);self::fail('Non-tenant principal rejected');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(403,$e->getStatusCode());}
        $seller=new class extends \App\Models\Tenant\User { public function searchModule($module){return true;} public function searchLevel($level){return true;} };
        $seller->forceFill(['id'=>$user->id,'type'=>'seller','establishment_id'=>$raw['establishment_id']+1]);auth()->setUser($seller);
        try {DispatchInput::set($raw);self::fail('Other branch rejected');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(403,$e->getStatusCode());}
        auth()->setUser($user);
        self::assertSame(0,$db->table('dispatches')->count());self::assertSame(1,$db->table('drivers')->count());
        $app->make(\Illuminate\Http\Client\Factory::class)->assertNothingSent();
    }
    public function test_dispatch_concurrent_creation_keeps_one_order_and_stock_effect_per_key(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $same=$this->runFiscalWorkers(1,'dispatch-create',[$raw,$raw]);
        self::assertSame($same[0]['id'],$same[1]['id']);self::assertSame(1,$db->table('dispatches')->count());
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $a=$raw;$a['creation_key']=(string)Str::uuid();$b=$raw;$b['creation_key']=(string)Str::uuid();
        $different=$this->runFiscalWorkers(1,'dispatch-create',[$a,$b]);self::assertNotSame($different[0]['id'],$different[1]['id']);
        self::assertNotSame($different[0]['number'],$different[1]['number']);self::assertSame(3,$db->table('dispatches')->count());
        self::assertEquals(4,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
    }

    public function test_dispatch_multi_order_invoice_moves_only_the_pending_quantities(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $first=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $raw['creation_key']=(string)Str::uuid();$db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>0]);
        $second=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $invoice['dispatches_relateds']=[$first->number_full,$second->number_full];$invoice['items'][0]['quantity']=4;$invoice['payments']=[];$invoice['payment_condition_id']='02';
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals(6,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        self::assertSame($saved->id,(int)$first->fresh()->reference_document_id);self::assertSame($saved->id,(int)$second->fresh()->reference_document_id);
        self::assertSame('2',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        $db->transaction(function() use($saved){$saved->state_type_id='11';$saved->save();});
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);$item->forceFill(['lots_enabled'=>true])->save();
        $lot=\Modules\Item\Models\ItemLotsGroup::create(['item_id'=>$item->id,'code'=>'PRUEBA-OE-Facturar','quantity'=>10,'date_of_due'=>date('Y-m-d',time()+86400)]);
        $raw['creation_key']=(string)Str::uuid();$raw['items'][0]['IdLoteSelected']=$lot->id;
        $third=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();$raw['creation_key']=(string)Str::uuid();
        $fourth=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatches_relateds']=[$third->number_full,$fourth->number_full];
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals(6,$lot->fresh()->quantity);self::assertSame('4',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        self::assertEquals($lot->id,$saved->items->first()->item->IdLoteSelected[0]->id);
        $db->transaction(function() use($saved){$saved->state_type_id='11';$saved->save();});
        self::assertEquals(10,$lot->fresh()->quantity);
        $item->forceFill(['lots_enabled'=>false])->save();unset($raw['items'][0]['IdLoteSelected']);
        $source=$db->transaction(function() use($invoice) {
            $order=\Modules\Order\Models\OrderNote::create(['user_id'=>auth()->id(),'external_id'=>(string)Str::uuid(),'establishment_id'=>$invoice['establishment_id'],'establishment'=>$invoice['establishment'],'state_type_id'=>'01','prefix'=>'PED','date_of_issue'=>date('Y-m-d'),'time_of_issue'=>'12:00:00','customer_id'=>$invoice['customer_id'],'customer'=>$invoice['customer'],'currency_type_id'=>'VES','exchange_rate_sale'=>1,'total'=>232]);
            $line=$invoice['items'][0];$line['quantity']=2;$order->items()->create($line);return $order;
        });
        $before=$db->table('item_warehouse')->where('item_id',$item->id)->value('stock');
        $raw['creation_key']=(string)Str::uuid();$raw['reference_order_note_id']=$source->id;
        $derived=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatches_relateds']=[$derived->number_full];$invoice['items'][0]['quantity']=2;
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals($before,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));self::assertSame('0',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        $bad=$raw;$bad['items'][0]['quantity']=3;
        try {DispatchInput::set($bad);self::fail('Quantity exceeds source order');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
    }

}
