<?php
namespace Tests\Support;
use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\{Company,Person,DocumentReceivedRetention,ReceivedRetentionUpload};
use App\Services\Fiscal\{FiscalProfileService,IslrRetentionService,IvaRetentionService,HkaIslrRetention};
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
trait IslrRetentionPersistenceCases
{
    public function test_invoice_ticket_rounds_retention_forecasts_from_their_original_fractions(): void
    {
        [$db, $app, $input] = $this->bootIslrFixture();
        $db->table('companies')->update(['fiscal_emission_mode' => 'free_form']);
        $input['payment_condition_id'] = '02';
        $input['payments'] = [];
        $input['fee'] = [['date' => '2026-11-10', 'currency_type_id' => 'VES', 'amount' => '0.05']];
        $input['islr_retention_prevision']['percentage'] = '12';
        $input['islr_retention_prevision']['subtrahend'] = '0';
        $input['items'][0] = array_replace($input['items'][0], [
            'quantity' => '0.333333333333',
            'quantity_input' => ['unit_price' => '3', 'amount' => '1'],
            'unit_price' => '0.144999999988',
            'price_input' => ['amount' => '0.12499999999', 'currency_type_id' => 'VES', 'includes_iva' => false],
        ]);

        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        foreach (['iva_retention_prevision', 'islr_retention_prevision'] as $field) {
            $forecast = $document->fiscal_data->{$field};
            self::assertSame('0.005', $forecast['amount']);
            self::assertTrue(\App\Support\Exact\Decimal::readRequired($forecast, 'amount')->isEqualTo('0.0049999999996'));
        }

        $directory = sys_get_temp_dir().'/'.$this->database;
        config(['view.compiled' => $directory.'/retention-ticket-views', 'view.paths' => [base_path('resources/views')]]);
        mkdir(config('view.compiled'), 0777, true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $pdf = (new \App\CoreFacturalo\Facturalo())->createPdf($document, 'invoice', 'ticket', 'string');
        self::assertStringStartsWith('%PDF-', $pdf);
        $path = $directory.'/retention-forecast.pdf';
        file_put_contents($path, $pdf);
        try {
            $text = new \Symfony\Component\Process\Process(['pdftotext', '-layout', $path, '-']);
            $text->mustRun();
            self::assertMatchesRegularExpression('/Retención IVA prevista(?:\h*\(informativa\))?\s+0\.00\h*$/m', $text->getOutput());
            self::assertMatchesRegularExpression('/Retención ISLR prevista(?:\h*\(informativa\))?\s+0\.00\h*$/m', $text->getOutput());
        } finally {
            unlink($path);
        }
    }

    public function test_free_form_cash_collection_respects_both_retention_forecasts(): void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='01';
        $short=$input; $short['payments'][0]['payment']='201';
        try { (new Facturalo)->save($short); self::fail('Las previsiones no deben cubrir una falta adicional de cobro.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $document=(new Facturalo)->save($input)->getDocument()->fresh();
        self::assertSame('30',\App\Support\Exact\Decimal::represent($document->exactBalance()));
        self::assertSame('24',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($document->fiscal_data->iva_retention_prevision,'amount')));
        self::assertSame('6',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($document->fiscal_data->islr_retention_prevision,'amount')));
        self::assertCount(0,$document->received_retentions);
        $edit=array_replace($input,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number,
            'payments'=>[],'received_retentions'=>[],'guarantee_fund'=>null]);
        (new Facturalo)->update($edit,$document->id);
        self::assertSame('30',\App\Support\Exact\Decimal::represent($document->fresh()->exactBalance()));
        $edit['items'][0]['quantity']='3';
        try { (new Facturalo)->update($edit,$document->id); self::fail('La previsión recalculada no cubre el incremento neto.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame('232',\App\Support\Exact\Decimal::represent($document->fresh()->exactValue('total')));
        \App\Services\Fiscal\FreeFormControl::register($document,['control_number'=>'00-1','evidence_reference'=>'Factura física sintética']);
        $iva=IvaRetentionService::store($document->fresh(),$this->ivaVoucher($document->fresh()));
        self::assertSame('applied',$iva->application_status);
        self::assertSame('6',\App\Support\Exact\Decimal::represent($document->fresh()->exactBalance()));
        \App\Services\Fiscal\FiscalDocumentPersistence::assertFreeFormCashCollection($document->fresh(),$document->fresh()->exactBalance());
        try { \App\Services\Fiscal\FiscalDocumentPersistence::assertFreeFormCashCollection($document->fresh(),'7'); self::fail('No se debe descontar dos veces la previsión ya aplicada.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame([],$state->calls);
    }

    private function bootIslrFixture(): array
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        config(['iva_retention.demo_net_collection_verified'=>true,'islr_retention.demo_net_collection_verified'=>true]);
        FiscalProfileService::store(Company::firstOrFail(),SpeProfiles::profile());
        // ISLR authority does not require SPE or authority to withhold IVA.
        FiscalProfileService::store(Person::findOrFail($input['customer_id']),SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true]));
        $input['payments'][0]['payment']=202;
        $input['payments'][0]['financial_intermediation']='none';
        $input['iva_retention_prevision']=['enabled'=>true];
        $input['islr_retention_prevision']=['enabled'=>true,'concept_id'=>'055','percentage'=>4,'subtrahend'=>2,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000',
            'fiscal_context'=>['concept_basis'=>'DEMO comprobante acreditado','evidence_reference'=>'DEMO soporte privado']];
        $state=(object)['remote'=>[],'calls'=>[],'mode'=>'normal'];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function($request)use($db,$state){
            self::assertSame(0,$db->transactionLevel());
            $state->calls[]=[$request->method(),$request->url(),$request->data()];
            if(str_ends_with($request->url(),'/Autenticacion'))return Http::response(['token'=>'private-fixture-token','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/DescargaArchivo'))return Http::response(['codigo'=>'203']);
            $islr=str_ends_with($request->url(),'/AplicarRetencionISLR');
            if($islr || str_ends_with($request->url(),'/AplicarRetencion')) {
                $data=$request->data();$key=($islr?'ISLR':'IVA').$data['numeroControl'];$field=$islr?'infoRetencionISLR':'infoRetencion';
                if($request->method()==='GET') {
                    if($state->mode==='unknown')return Http::response(['codigo'=>'203','mensaje'=>'unverified absence']);
                    return Http::response(['codigo'=>'200',$field=>$state->remote[$key] ?? null]);
                }
                if($state->mode==='rejected')return Http::response(['codigo'=>'203','validaciones'=>['bad voucher']]);
                if($request->method()==='DELETE')$state->remote[$key]=null;
                else $state->remote[$key]=array_intersect_key($data,array_flip(['serie','tipoDocumento','numeroDocumento','numeroControl']))+($islr?array_intersect_key($data['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido'])):['totalesRetencion'=>$data['totalRetencion']]);
                if($state->mode==='timeout')throw new \Illuminate\Http\Client\ConnectionException('private-fixture-token');
                return Http::response(['codigo'=>'200']);
            }
            $id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
            return Http::response(['codigo'=>'200','resultado'=>$id+['numeroControl'=>'00-'.str_pad($id['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        return [$db,$app,$input,$state];
    }
    private function islrVoucher($doc,array $changes=[]):array
    {
        return array_replace(SpeProfiles::voucher($doc),['tax_kind'=>'ISLR','voucher_number'=>'ISLR recibido / 001','voucher_date'=>'2026-09-10','agent_id'=>$doc->customer_id,
            'concept_id'=>'055','base'=>200,'percentage'=>4,'subtrahend'=>2,'amount'=>6,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000'],$changes);
    }
    public function test_free_form_received_iva_and_islr_wait_for_verified_control_and_restore_balance(): void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        self::assertSame('free_form',$doc->fiscal_emission_mode);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/unpaid-retention-views',
            'view.paths'=>[base_path('resources/views')],'excel'=>require base_path('config/excel.php')]);
        mkdir(config('view.compiled'),0777,true);$app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        $app->register(\Illuminate\Translation\TranslationServiceProvider::class);
        \Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
        config(['tenant.items_per_page'=>20]);
        $temp=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temp);
        $checkExport=function (?string $balance, ?string $retained) use ($doc,$temp) {
            $dataset=\App\Support\Exact\Receivables::dataset(['stablishmentUnpaidAll'=>1]);
            $rows=collect(iterator_to_array($dataset))->keyBy('report_key');$key='document:'.$doc->id;
            if ($balance===null) self::assertFalse($rows->has($key));
            else {
                self::assertSame($balance,$rows[$key]['total_to_pay']);
                self::assertSame($retained,$rows[$key]['retention_amount']);
            }
            $export=(new \Modules\Report\Exports\NoPaidExport())->records($dataset)->company(Company::firstOrFail());
            $file=(new \Maatwebsite\Excel\Writer($temp))->export($export,\Maatwebsite\Excel\Excel::XLSX);
            try {
                $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
                $cells=collect($book->getSheet(0)->toArray(null,true,false,false))->flatten()->map(fn($v)=>(string)$v)->all();
                $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
                if ($balance===null) self::assertContains('No se encontraron registros.',$cells);
                else {
                    self::assertContains($balance,$cells);
                    self::assertContains([$key,'retention_amount',$retained,$retained,'1'],$fractions);
                }
                $book->disconnectWorksheets();
            } finally {$file->delete();}
        };
        $checkState=function (string $balance) use ($doc,$temp) {
            $fresh=$doc->fresh();$request=new \Illuminate\Http\Request();
            $screen=(new \Modules\Report\Http\Resources\StateAccountCollection(collect([$fresh])))->toArray($request)->first();
            self::assertSame($balance,$screen['payment_state']);
            $query=\Illuminate\Http\Request::create('/reports/state-account/records','GET',[
                'period'=>'date','date_start'=>'2026-09-10','date_end'=>'2026-09-10','document_type_id'=>'01']);
            $page=(new \Modules\Report\Http\Controllers\ReportStateAccountController())->records($query)->getData(true);
            $listed=collect($page['data'])->firstWhere('id',$doc->id);
            self::assertNotNull($listed);self::assertSame($balance,$listed['payment_state']);
            $export=(new \Modules\Report\Exports\StatusClientExport())->records(collect([$doc->fresh()]))
                ->company(Company::firstOrFail())->establishment(\App\Models\Tenant\Establishment::findOrFail($doc->establishment_id))
                ->filters(['user_type'=>'admin'])->categories([])->categories_services([]);
            $file=(new \Maatwebsite\Excel\Writer($temp))->export($export,\Maatwebsite\Excel\Excel::XLSX);
            try {
                $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
                $rows=$book->getSheet(0)->toArray(null,true,false,false);$header=null;$entry=null;
                foreach ($rows as $row) {
                    if (in_array('Total por pagar',$row,true)) $header=$row;
                    if (in_array($doc->number_full,$row,true)) $entry=$row;
                }
                self::assertNotNull($header);self::assertNotNull($entry);
                self::assertSame($balance,$entry[array_search('Total por pagar',$header,true)]);
                $fractions=$book->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
                $integer=explode('.',$balance)[0];
                self::assertContains(['document:'.$doc->id,'payment_state',$integer,$integer,'1'],$fractions);
                $book->disconnectWorksheets();
            } finally {$file->delete();}
        };
        $control='00-00000001';
        $iva=IvaRetentionService::store($doc,$this->ivaVoucher($doc,['invoice_control'=>$control]));
        $islr=IslrRetentionService::store($doc,$this->islrVoucher($doc,['invoice_control'=>$control]));
        self::assertSame('pending_validation',$iva->application_status);
        self::assertSame('pending_validation',$islr->application_status);
        self::assertSame('30',\App\Support\Exact\Decimal::represent($doc->fresh()->exactBalance()));
        $checkExport('30','0');
        $checkState('30.00');
        // The administrator transcribes the control from a synthetic physical-form reference.
        $request=\Illuminate\Http\Request::create('/','POST',['control_number'=>$control,'evidence_reference'=>'Factura física de prueba']);
        $response=(new \App\Http\Controllers\Tenant\DocumentFiscalController())->registerFreeFormControl($request,$doc->id);
        self::assertTrue($response['success']); self::assertSame($control,$response['control_number']);
        self::assertSame('not_requested',$doc->fresh()->emission->status);
        self::assertNull($doc->fresh()->emission->control_number);
        self::assertSame(auth()->id(),$doc->fresh()->emission->response['free_form_control']['actor_id']);
        self::assertSame('applied',$iva->fresh()->application_status);
        self::assertSame('applied',$islr->fresh()->application_status);
        self::assertSame('0',\App\Support\Exact\Decimal::represent($doc->fresh()->exactBalance()));
        $checkExport(null,null);
        $checkState('0.00');
        $account=(new \Modules\Report\Http\Resources\StateAccountCollection(collect([$doc->fresh()])))->toArray(new \Illuminate\Http\Request())->first();
        self::assertSame('0.00',$account['payment_state']);
        IslrRetentionService::reverse($islr->fresh(),['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección de prueba']);
        self::assertSame('6',\App\Support\Exact\Decimal::represent($doc->fresh()->exactBalance()));
        $checkExport('6','24');
        $checkState('6.00');
        IvaRetentionService::reverse($iva->fresh(),['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección de prueba']);
        self::assertSame('30',\App\Support\Exact\Decimal::represent($doc->fresh()->exactBalance()));
        $checkExport('30','0');
        $checkState('30.00');
        self::assertCount(0,$state->calls);
    }

    public function test_free_form_control_requires_admin_unique_real_reference_and_rolls_back(): void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $first=(new Facturalo)->save($input)->getDocument()->fresh();
        $secondInput=$input; $secondInput['external_id']=(string)Str::uuid();
        $second=(new Facturalo)->save($secondInput)->getDocument()->fresh();
        $service=\App\Services\Fiscal\FreeFormControl::class;
        $viewer=auth()->user(); $originalType=$viewer->type;
        self::assertTrue($service::canRegister($first,$viewer)); $viewer->type='seller';
        self::assertFalse($service::canRegister($first,$viewer));
        try { $service::register($first,['control_number'=>'00-1','evidence_reference'=>'Factura física']); self::fail('Sólo el administrador registra el control.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { self::assertSame(403,$error->getStatusCode()); }
        $viewer->type=$originalType;
        foreach ([['control_number'=>'00-0','evidence_reference'=>'Factura'],['control_number'=>'00-1','evidence_reference'=>'   '],['control_number'=>'no-control','evidence_reference'=>'Factura']] as $invalid) {
            try { $service::register($first,$invalid); self::fail('Datos inválidos'); }
            catch (ValidationException $error) { self::assertNotEmpty($error->errors()); }
        }
        $db->beginTransaction();
        $service::register($first,['control_number'=>'00-1','evidence_reference'=>'Factura física']);
        self::assertSame('00-00000001',$first->fresh()->control_number);
        $db->rollBack();
        self::assertNull($first->fresh()->control_number);
        self::assertArrayNotHasKey('free_form_control',$first->fresh()->emission->response ?? []);
        $service::register($first->fresh(),['control_number'=>'00-1','evidence_reference'=>'Factura física']);
        $audit=$first->fresh()->emission->response['free_form_control'];
        self::assertSame('00-00000001',$audit['control_number']); self::assertSame('Factura física',$audit['evidence_reference']);
        $service::register($first->fresh(),['control_number'=>'00-00000001','evidence_reference'=>'Reintento']);
        self::assertSame($audit,$first->fresh()->emission->response['free_form_control']);
        try { $service::register($first->fresh(),['control_number'=>'00-2','evidence_reference'=>'Factura']); self::fail('Control no reemplazable'); }
        catch (ValidationException $error) { self::assertArrayHasKey('control_number',$error->errors()); }
        try { $service::register($second,['control_number'=>'00-1','evidence_reference'=>'Factura']); self::fail('Control duplicado'); }
        catch (ValidationException $error) { self::assertArrayHasKey('control_number',$error->errors()); }
        $db->table('documents')->where('id',$first->id)->update(['control_number'=>'00-2']);
        try { $service::register($second,['control_number'=>'00-00000002','evidence_reference'=>'Factura']); self::fail('Control previo con ceros omitidos'); }
        catch (ValidationException $error) { self::assertArrayHasKey('control_number',$error->errors()); }
        $db->table('documents')->where('id',$second->id)->update(['fiscal_emission_mode'=>'digital']);
        try { $service::register($second->fresh(),['control_number'=>'00-3','evidence_reference'=>'Factura']); self::fail('La modalidad digital es independiente'); }
        catch (ValidationException $error) { self::assertArrayHasKey('document',$error->errors()); }
        $db->table('documents')->where('id',$second->id)->update(['fiscal_emission_mode'=>'free_form','state_type_id'=>'09']);
        try { $service::register($second->fresh(),['control_number'=>'00-3','evidence_reference'=>'Factura']); self::fail('Factura anulada'); }
        catch (ValidationException $error) { self::assertArrayHasKey('document',$error->errors()); }
        self::assertCount(0,$state->calls);
    }

    public function test_free_form_wrong_control_invalidates_voucher_without_settling(): void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $voucher=IvaRetentionService::store($doc,$this->ivaVoucher($doc,['invoice_control'=>'00-00000001']));
        \App\Services\Fiscal\FreeFormControl::register($doc,['control_number'=>'00-2','evidence_reference'=>'Factura física']);
        self::assertSame('invalid',$voucher->fresh()->application_status);
        self::assertSame('30',\App\Support\Exact\Decimal::represent($doc->fresh()->exactBalance()));
        self::assertSame('0',\App\Support\Exact\Decimal::represent($voucher->fresh()->exactValue('applied_amount')));
        self::assertCount(0,$state->calls);
    }

    public function test_free_form_concurrent_control_registration_cannot_duplicate(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $first=(new Facturalo)->save($input)->getDocument()->fresh();
        $input['external_id']=(string)Str::uuid();
        $second=(new Facturalo)->save($input)->getDocument()->fresh();
        $results=$this->runFiscalWorkers($first->id,'free-form-control',[
            ['document_id'=>$first->id,'control_number'=>'00-1','evidence_reference'=>'Factura física A'],
            ['document_id'=>$second->id,'control_number'=>'00-00000001','evidence_reference'=>'Factura física B'],
        ]);
        self::assertCount(1,array_filter($results,fn($result)=>isset($result['id'])));
        self::assertCount(1,array_filter($results,fn($result)=>in_array('control_number',$result['rejected'] ?? [],true)));
        self::assertSame(1,$db->table('documents')->where('control_number','00-00000001')->count());
    }

    public function test_islr_coexists_with_iva_idempotence_replacement_cash_and_hka():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        foreach(['WEB','POS','VENDEYA','HOTEL','ORDER','ECOMMERCE','SUSCRIPTION','TECHNICAL_SERVICE'] as $source){
            $doc=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid(),'source_module'=>$source]))->getDocument()->fresh();
            self::assertEquals(30,$doc->balance);self::assertEquals(24,$doc->fiscal_data->iva_retention_prevision['amount']);self::assertEquals(6,$doc->fiscal_data->islr_retention_prevision['amount']);
            $iva=IvaRetentionService::store($doc,$this->ivaVoucher($doc));self::assertEquals(6,$doc->fresh()->balance);
            $voucher=$this->islrVoucher($doc);$row=IslrRetentionService::store($doc,$voucher);
            self::assertSame($row->id,IslrRetentionService::store($doc,$voucher)->id);self::assertEquals(0,$doc->fresh()->balance);
            self::assertSame('confirmed',$row->islr_operations()->first()->status);
            self::assertArrayNotHasKey('codigoOperacion',$row->islr_operations()->first()->payload['detallesRetencionISLR']);
            self::assertEquals(202,$doc->fresh()->payments->sum('payment'));self::assertCount(2,$doc->fresh()->received_retentions);
            try{IslrRetentionService::store($doc,array_replace($voucher,['voucher_number'=>'changed']));self::fail('Reused key');}catch(ValidationException $e){self::assertSame(409,$e->status);}
            try{IslrRetentionService::store($doc,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Second active ISLR');}catch(ValidationException $e){self::assertArrayHasKey('voucher_number',$e->errors());}
            $reverse=['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir comprobante ISLR'];IslrRetentionService::reverse($row,$reverse);IslrRetentionService::reverse($row,$reverse);
            self::assertEquals(6,$doc->fresh()->balance);self::assertSame('confirmed',$iva->operations()->first()->status);self::assertNotNull($state->remote['IVA'.$doc->control_number]);
            self::assertEquals(202,$doc->fresh()->payments->sum('payment'));self::assertEquals(6,$doc->fresh()->fiscal_data->islr_retention_prevision['amount']);
            $replacement=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('applied',$replacement->application_status);self::assertCount(3,$doc->fresh()->received_retentions);
            self::assertStringNotContainsString('private-fixture-token',json_encode(IslrRetentionService::view($doc->fresh())));
        }
    }
    public function test_islr_pending_support_reference_rollback_and_recovery():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        foreach([['concept_id'=>'001'],['voucher_number'=>str_repeat('X',51)],['amount'=>7],['base'=>201],['attachment'=>'received_retentions/other.pdf'],['fiscal_context'=>[]]] as $change){
            try{IslrRetentionService::store($doc,$this->islrVoucher($doc,$change));self::fail('Invalid voucher');}catch(ValidationException $e){self::assertSame(422,$e->status);}
        }
        $doc->emission()->update(['status'=>'pending']);$row=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc,['invoice_control'=>'wrong']));
        self::assertEquals(30,$doc->fresh()->balance);self::assertSame('pending_validation',$row->application_status);
        $doc->emission()->update(['status'=>'confirmed']);IslrRetentionService::resume($doc->fresh());self::assertSame('invalid',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
        $calls=count($state->calls);$db->beginTransaction();$rolled=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc));self::assertEquals(24,$doc->fresh()->balance);self::assertSame($calls,count($state->calls));$db->rollBack();self::assertNull(DocumentReceivedRetention::find($rolled->id));self::assertEquals(30,$doc->fresh()->balance);
        $state->mode='timeout';$row=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc));self::assertSame('uncertain',$row->islr_operations()->first()->status);
        $state->mode='normal';app(HkaIslrRetention::class)->process($row,true);self::assertSame('confirmed',$row->islr_operations()->first()->status);
        $state->mode='unknown';IslrRetentionService::reverse($row,['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir']);self::assertSame('applied',$row->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
        $state->mode='normal';$row->islr_operations()->latest('id')->first()->update(['next_attempt_at'=>null]);app(HkaIslrRetention::class)->process($row);self::assertSame('reversed',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
    }
    public function test_islr_different_rates_declared_authority_and_net_capability():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $bank=$this->enableUsdRetentionBank($db);
        $input['payments'][0]['financial_intermediation']='financial_institution';$input['payments'][0]['payment_destination_id']=$bank;
        unset($input['iva_retention_prevision']);$input['currency_type_id']='USD';$input['payments'][0]['payment_method_type_id']='13';$input['exchange_rate_sale']='10.12345678';
        $input['payments'][0]['payment']=220;$input['islr_retention_prevision']['percentage']=4;
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $row=IslrRetentionService::store($doc,$this->islrVoucher($doc,['base'=>2000,'percentage'=>4,'subtrahend'=>20,'amount'=>60,'exchange_rate'=>'20.00000000']));
        self::assertSame('20.00000000',$row->exchange_rate);self::assertEquals(3,$row->applied_amount);self::assertSame('60.00',$row->islr_operations()->first()->payload['detallesRetencionISLR']['impuestoRetenido']);self::assertEquals(9,$doc->fresh()->balance);
        config(['islr_retention.demo_net_collection_verified'=>false]);$calls=count($state->calls);
        $blocked=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid()]))->getDocument()->fresh();self::assertSame('not_requested',$blocked->emission->status);self::assertSame($calls,count($state->calls));self::assertEquals(12,$blocked->balance);
        $profile=SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true,'spe'=>false,'iva_agent_basis'=>'none']);FiscalProfileService::store(Person::findOrFail($input['customer_id']),$profile);
        $p=IslrRetentionService::proposal($input['customer_id'],'2026-09-10',200,'VES',$input['islr_retention_prevision']);self::assertTrue($p['eligible']);
    }
    public function test_islr_conversion_keeps_frozen_fiscal_currency_through_submission_and_reconciliation(): void
    {
        [$db, $app, $input, $state] = $this->bootIslrFixture();
        $document = (new Facturalo)->save($input)->getDocument()->fresh();
        self::assertSame('VES', $document->currency_type_id);
        self::assertSame('VES', $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']['moneda']);

        $row = IslrRetentionService::store($document, $this->islrVoucher($document, [
            'base'=>'2', 'subtrahend'=>'0.03', 'amount'=>'0.05', 'currency_type_id'=>'USD', 'exchange_rate'=>'100.00000000',
        ]))->fresh();
        $operation = $row->islr_operations()->firstOrFail();
        self::assertTrue($row->exactValue('amount')->isEqualTo('0.05'));
        self::assertSame('100.00000000', $row->exchange_rate);
        self::assertSame('25', (string) $document->fresh()->exactBalance());
        self::assertSame('confirmed', $operation->status);
        self::assertSame('5.00', $operation->payload['detallesRetencionISLR']['impuestoRetenido']);
        self::assertSame('5.00', $state->remote['ISLR'.$document->control_number]['impuestoRetenido']);
    }

    public function test_islr_voucher_creation_upload_scope_and_activation_savepoint():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $token=(string)Str::uuid();$path='received_retentions/drafts/'.$token.'.pdf';Storage::disk('tenant')->put($path,'%PDF-private');
        ReceivedRetentionUpload::create(['token'=>$token,'user_id'=>auth()->id(),'path'=>$path,'expires_at'=>now()->addDay()]);
        $voucher=['tax_kind'=>'ISLR','operation_key'=>(string)Str::uuid(),'voucher_number'=>'Cliente-ISLR-01','voucher_date'=>'2026-09-10','agent_rif'=>'V-12345678-9','invoice_number'=>'FF01-00000100','invoice_control'=>'00-00000100','concept_id'=>'055','base'=>200,'percentage'=>4,'subtrahend'=>2,'amount'=>6,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000','upload_id'=>$token,'fiscal_context'=>['concept_basis'=>'DEMO acreditación','evidence_reference'=>'DEMO soporte']];
        $input['received_retentions']=[$voucher];$db->beginTransaction();$calls=count($state->calls);
        $pending=(new Facturalo)->save($input)->getDocument();self::assertSame('pending_validation',$pending->received_retentions()->first()->application_status);self::assertEquals(30,$pending->fresh()->balance);self::assertSame($calls,count($state->calls));$db->rollBack();
        self::assertNull(ReceivedRetentionUpload::where('token',$token)->first()->document_id);self::assertTrue(Storage::disk('tenant')->exists($path));
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();$row=$doc->received_retentions->first();self::assertSame('applied',$row->application_status);self::assertEquals(24,$doc->balance);self::assertSame('confirmed',$row->islr_operations()->first()->status);
        $other=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid(),'received_retentions'=>[]]))->getDocument()->fresh();
        try{IslrRetentionService::store($other,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Consumed token');}catch(ValidationException $e){self::assertArrayHasKey('upload_id',$e->errors());}
        auth()->user()->forceFill(['type'=>'seller','create_payment'=>false]);
        try{IslrRetentionService::store($other,$this->islrVoucher($other));self::fail('Unauthorized');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}
        auth()->user()->forceFill(['create_payment'=>true,'establishment_id'=>999]);
        try{IslrRetentionService::store($other,$this->islrVoucher($other));self::fail('Other establishment');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}
        auth()->user()->forceFill(['type'=>'admin','establishment_id'=>$doc->establishment_id]);
        $other->emission()->update(['status'=>'pending']);$bad=IslrRetentionService::store($other->fresh(),$this->islrVoucher($other));
        $payload=$other->fresh()->emission->payload;unset($payload['documentoElectronico']['encabezado']['identificacionDocumento']['moneda']);$other->emission()->update(['status'=>'confirmed','payload'=>$payload]);
        IslrRetentionService::resume($other->fresh());self::assertSame('invalid',$bad->fresh()->application_status);self::assertEquals(0,$bad->fresh()->applied_amount);self::assertEquals(30,$other->fresh()->balance);self::assertEquals(6,$other->fresh()->fiscal_data->islr_retention_prevision['amount']);self::assertSame(0,$bad->islr_operations()->count());
    }
    public function test_islr_hka_rejection_foreign_conflict_and_adopted_reversal():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();$state->mode='rejected';
        $row=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('applied',$row->application_status);self::assertEquals(24,$doc->fresh()->balance);self::assertSame('rejected',$row->islr_operations()->first()->status);
        self::assertSame('203',$row->islr_operations()->first()->result['mutation_code']);
        $duplicate=$row->getAttributes();foreach(['id','active_iva_document_id','active_islr_document_id','active_voucher_number'] as $key)unset($duplicate[$key]);
        $duplicate['operation_key']=(string)Str::uuid();$duplicate['voucher_number']='Different active ISLR';
        try{$db->table('document_received_retentions')->insert($duplicate);self::fail('Missing generated ISLR unique constraint');}catch(\Illuminate\Database\QueryException $e){self::assertStringContainsString('received_islr_active_unique',$e->getMessage());}

        $state->mode='normal';IslrRetentionService::reverse($row,['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir rechazo']);self::assertSame('reversed',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
        $voucher=$this->islrVoucher($doc);$foreign=new DocumentReceivedRetention;$foreign->forceFill($voucher);$payload=\App\Services\Fiscal\HkaIslrRetentionPayload::build($doc,$foreign);
        $key='ISLR'.$doc->control_number;$state->remote[$key]=\App\Services\Fiscal\HkaIslrRetentionPayload::identity($payload)+array_intersect_key($payload['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido']));
        $adopted=IslrRetentionService::store($doc,$voucher);self::assertSame('confirmed',$adopted->islr_operations()->first()->status);self::assertSame(0,$adopted->islr_operations()->first()->attempts);
        IslrRetentionService::reverse($adopted,['operation_key'=>(string)Str::uuid(),'reason'=>'Retirar comprobante adoptado']);self::assertSame('reversed',$adopted->fresh()->application_status);self::assertNull($state->remote[$key]);
        $state->remote[$key]=['numeroCompRetencion'=>'external-other'];$calls=count($state->calls);
        $conflict=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('conflict',$conflict->islr_operations()->first()->status);self::assertSame(0,$conflict->islr_operations()->first()->attempts);self::assertSame('external-other',$state->remote[$key]['numeroCompRetencion']);
        IslrRetentionService::reverse($conflict,['operation_key'=>(string)Str::uuid(),'reason'=>'Resolver discrepancia externa']);self::assertSame('applied',$conflict->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
        self::assertSame('conflict',$conflict->islr_operations()->latest('id')->first()->status);self::assertEquals(202,$doc->fresh()->payments->sum('payment'));
    }
    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_islr_retention_rejects_paid_balance_after_prior_transaction_read():void
    {
        [$db,$app,$input]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        self::assertEquals(30,$doc->balance);
        $voucher=$this->islrVoucher($doc); $worker=null;
        $db->beginTransaction();
        try {
            $prior=\App\Models\Tenant\Document::findOrFail($doc->id);
            self::assertEquals(30,$prior->balance);
            $worker=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $payment=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>'30',
                'currency_type_id'=>'VES','payment_destination_id'=>'cash','operation_key'=>(string)Str::uuid()];
            $worker->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$doc->id,
                'operation'=>'document-receipt','data'=>$payment],JSON_THROW_ON_ERROR));
            $worker->setTimeout(30); $worker->start();
            self::assertSame(0,$worker->wait(),$worker->getErrorOutput().$worker->getOutput());
            $result=json_decode(substr($worker->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('id',$result,json_encode($result));
            try {
                IslrRetentionService::store($prior,$voucher);
                self::fail('La retención no debe consumir un saldo ya cobrado.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('amount',$e->errors());
            }
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($worker && $worker->isRunning()) $worker->stop();
        }
        self::assertEquals(0,$doc->fresh()->balance);
        self::assertSame(0,$doc->received_retentions()->count());
        self::assertSame(2,$doc->payments()->count());
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    public function test_islr_concurrent_registration_collection_and_reversal():void
    {
        [$db,$app,$input]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $doc->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $voucher=$this->islrVoucher($doc);
        $result=$this->runFiscalWorkers($doc->id,'retention',[$voucher,$voucher]);
        self::assertCount(2,array_filter($result,fn($r)=>isset($r['id'])));self::assertSame($result[0]['id'],$result[1]['id']);self::assertEquals(24,$doc->fresh()->balance);
        $row=DocumentReceivedRetention::findOrFail($result[0]['id']);
        $reverse=['action'=>'reverse','retention_id'=>$row->id,'input'=>['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección concurrente']];
        $result=$this->runFiscalWorkers($doc->id,'iva-mutations',[$reverse,$reverse]);self::assertCount(2,array_filter($result,fn($r)=>isset($r['id'])));self::assertEquals(30,$doc->fresh()->balance);
        $payment=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','original_amount'=>30,'currency_type_id'=>'VES','exchange_rate'=>1,'operation_key'=>(string)Str::uuid(),'financial_intermediation'=>'none'];
        $voucher=$this->islrVoucher($doc);
        $result=$this->runFiscalWorkers($doc->id,'iva-mutations',[['action'=>'register','input'=>$voucher],['action'=>'payment','input'=>$payment]]);
        self::assertCount(1,array_filter($result,fn($r)=>isset($r['id'])));self::assertGreaterThanOrEqual(0,$doc->fresh()->balance);
        self::assertEquals(232,$doc->fresh()->payments->sum('payment')+$doc->fresh()->received_retentions->sum('applied_amount')+$doc->fresh()->balance);
    }
}
