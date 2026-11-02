<?php

namespace Tests\Support;

use App\Models\Tenant\{Cash, BankAccount};
use App\Services\Payments\{CashLedger, Movement};
use Modules\Finance\Models\IncomePayment;
use Modules\Expense\Models\ExpensePayment;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
trait PaymentClassificationPersistenceCases
{
    public function test_fractional_local_unit_invoice_and_mobile_weight_catalog(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $weightCatalog=(new \Modules\MobileApp\Http\Controllers\Api\DispatchController())->tables();
        self::assertSame(['KG','TON'],$weightCatalog['data']['unit_types']->pluck('id')->sort()->values()->all());
        $db->table('cat_unit_types')->where('id','TON')->update(['active'=>0]);
        $weightCatalog=(new \Modules\MobileApp\Http\Controllers\Api\DispatchController())->tables();
        self::assertSame(['KG'],$weightCatalog['data']['unit_types']->pluck('id')->all());

        $itemId=$input['items'][0]['item_id'];
        $db->table('items')->where('id',$itemId)->update(['unit_type_id'=>'KG','currency_type_id'=>'USD','sale_unit_price'=>'10']);
        $line=$input['items'][0];
        $line['item']=array_replace($line['item'],['unit_type_id'=>'KG','currency_type_id'=>'USD','sale_unit_price'=>'10']);
        $line['quantity']='0.3';$line['unit_price']='125';
        $line['price_input']=['amount'=>'10','currency_type_id'=>'USD','includes_iva'=>true];
        $input['items']=[$line];$input['exchange_rate_sale']='12.5';
        $input['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01',
            'payment_destination_id'=>'cash','currency_type_id'=>'VES','original_amount'=>'37.5',
            'exchange_rate'=>'12.5','payment'=>'37.5']];
        $invoice=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        $saved=$invoice->items()->firstOrFail();
        self::assertSame('KG',$saved->item->unit_type_id);
        self::assertSame('KGM',$saved->item->hka_unit_code);
        self::assertSame('0.3',\App\Support\Exact\Decimal::represent($saved->exactValue('quantity')));
        self::assertSame('125',\App\Support\Exact\Decimal::represent($saved->exactValue('unit_price')));
        self::assertSame('37.5',\App\Support\Exact\Decimal::represent($saved->exactValue('total')));
        self::assertSame('37.5',\App\Support\Exact\Decimal::represent($invoice->exactValue('total')));
        self::assertSame('37.5',\App\Support\Exact\Decimal::represent($invoice->payments()->firstOrFail()->exactValue('payment')));
        self::assertTrue($invoice->exactBalance()->isZero());
    }

    public function test_classic_subscription_generation_failure_rolls_back_the_plan(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $subscriptionPlan=$db->table('suscription_plans')->insertGetId(['name'=>'Plan aislado','description'=>'Prueba','total'=>'232']);
        $payload=['user_id'=>$input['user_id'],'suscription_plan_id'=>$subscriptionPlan,'cat_period_id'=>999,
            'quantity_period'=>1,'start_date'=>'2026-10-01','items'=>[],
            'customer_id'=>$input['customer_id'],'parent_customer_id'=>$input['customer_id'],
            'customer'=>$input['customer'],'parent_customer'=>$input['customer'],'children_customer'=>[],
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232'];
        $controller=new \Modules\Suscription\Http\Controllers\PaymentsSuscriptionController();
        $request=new \Modules\Suscription\Http\Requests\PaymentsSuscriptionRequest();
        $request->merge($payload);
        try { $controller->store($request); self::fail('Se aceptó un período inexistente.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('cat_period_id',$error->errors()); }
        self::assertSame(0,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        $db->table('exchange_rates')->where('date',now()->toDateString())->delete();
        $withoutRate=new class extends \Modules\Suscription\Services\SubscriptionSaleNoteController {
            public $calls=0;
            public function store(\App\Http\Requests\Tenant\SaleNoteRequest $request) { $this->calls++; return ['success'=>true,'data'=>['id'=>99]]; }
        };
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class,$withoutRate);
        $request->merge(['cat_period_id'=>1,'exchange_rate_sale'=>0]);
        try { $controller->store($request); self::fail('Se aceptó una tasa documental cero.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('exchange_rate_sale',$error->errors()); }
        self::assertSame(0,$withoutRate->calls);
        self::assertSame(0,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        $request->merge(['exchange_rate_sale'=>'10']);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/subscription-note-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $noteController=new class extends \Modules\Suscription\Services\SubscriptionSaleNoteController {
            public $calls=0, $generatedPath=null, $generatedPdf=null;
            public function store(\App\Http\Requests\Tenant\SaleNoteRequest $request) {
                if (++$this->calls===2) return ['success'=>false,'message'=>'Fallo simulado de segunda nota'];
                $result=parent::store($request);
                if (!empty($result['data']['id'])) {
                    $filename=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id'])->filename;
                    $this->generatedPath='sale_note/'.$filename.'.pdf';
                    $this->generatedPdf=\Illuminate\Support\Facades\Storage::disk('tenant')->get($this->generatedPath);
                }
                return $result;
            }
        };
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class,$noteController);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $request->merge(['cat_period_id'=>1,'quantity_period'=>2,'items'=>$input['items'],
            'total_prepayment'=>0,'total_charge'=>0,'total_discount'=>0,'total_free'=>0,'total_exportation'=>0,
            'total_taxed'=>200,'total_unaffected'=>0,'total_exonerated'=>0,'total_igv'=>32,
            'total_base_other_taxes'=>0,'total_other_taxes'=>0,'total_taxes'=>32,'total_value'=>200]);
        try { $controller->store($request); self::fail('Se aceptó el fallo de la segunda nota.'); }
        catch (\Illuminate\Validation\ValidationException $error) {
            self::assertSame('Fallo simulado de segunda nota',$error->errors()['sale_notes'][0]);
        }
        self::assertSame(2,$noteController->calls);
        self::assertStringStartsWith('%PDF-',$noteController->generatedPdf);
        self::assertFalse(\Illuminate\Support\Facades\Storage::disk('tenant')->exists($noteController->generatedPath));
        self::assertSame(0,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        $disk=\Illuminate\Support\Facades\Storage::disk('tenant');
        $disk->put($noteController->generatedPath,'archivo anterior');
        $noteController->calls=0;
        try { $controller->store($request); self::fail('Se aceptó el fallo de la segunda nota.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertSame('Fallo simulado de segunda nota',$error->errors()['sale_notes'][0]); }
        self::assertSame('archivo anterior',$disk->get($noteController->generatedPath));
        self::assertSame(0,$db->table('sale_notes')->count());
        $disk->delete($noteController->generatedPath);
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class,new \Modules\Suscription\Services\SubscriptionSaleNoteController());
        $failingDisk=new class($disk) {
            private $disk;
            public $failedPath;
            public function __construct($disk) { $this->disk=$disk; }
            public function put($path,$content,$options=[]) {
                if (str_starts_with($path,'sale_note/')) {
                    $this->failedPath=$path;
                    $this->disk->put($path,'archivo parcial');
                    return false;
                }
                return $this->disk->put($path,$content,$options);
            }
            public function __call($method,$arguments) { return $this->disk->$method(...$arguments); }
        };
        $filesystem=$app['filesystem'];
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('tenant')->andReturn($failingDisk);
        try {
            try { $controller->store($request); self::fail('Se aceptó un fallo de almacenamiento del PDF.'); }
            catch (\Illuminate\Validation\ValidationException $error) {
                self::assertSame('No se pudo guardar el PDF de la nota de suscripción.',$error->errors()['sale_notes'][0]);
            }
        } finally {
            \Illuminate\Support\Facades\Storage::swap($filesystem);
        }
        self::assertNotNull($failingDisk->failedPath);
        self::assertFalse($disk->exists($failingDisk->failedPath));
        self::assertSame(0,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class,new \Modules\Suscription\Services\SubscriptionSaleNoteController());
        $db->table('payment_method_types')->update(['is_active'=>0]);
        $request->merge(['quantity_period'=>1]);
        $controller->store($request);
        self::assertSame(1,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(1,$db->table('sale_notes')->count());
        self::assertSame('10.00000000',$db->table('sale_notes')->value('exchange_rate_sale'));
        self::assertSame(0,$db->table('sale_note_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $filename=$db->table('sale_notes')->value('filename');
        self::assertTrue(\Illuminate\Support\Facades\Storage::disk('tenant')->exists('sale_note/'.$filename.'.pdf'));
        self::assertStringStartsWith('%PDF-',\Illuminate\Support\Facades\Storage::disk('tenant')->get('sale_note/'.$filename.'.pdf'));
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1,'collection_kind'=>'account',
            'currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0]);
        $bankId=$db->table('banks')->value('id');
        $bankUsd=BankAccount::create(['bank_id'=>$bankId,'description'=>'Suscripción USD','number'=>'CLASSIC-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $bankVes=BankAccount::create(['bank_id'=>$bankId,'description'=>'Suscripción VES','number'=>'CLASSIC-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $paymentController=new \App\Http\Controllers\Tenant\SaleNotePaymentController();
        $paymentRequest=new \App\Http\Requests\Tenant\SaleNotePaymentRequest();
        $paymentRequest->merge(['sale_note_id'=>$db->table('sale_notes')->value('id'),'date_of_payment'=>'2026-10-01',
            'payment_method_type_id'=>'01','payment_destination_id'=>$bankVes->id,'currency_type_id'=>'USD',
            'original_amount'=>'23.20','exchange_rate'=>'10','payment'=>'232']);
        try { $paymentController->store($paymentRequest); self::fail('Se aceptó una cuenta VES para un cobro USD.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        self::assertSame(0,$db->table('sale_note_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $paymentRequest->merge(['payment_destination_id'=>$bankUsd->id]);
        self::assertTrue($paymentController->store($paymentRequest)['success']);
        $payment=\App\Models\Tenant\SaleNotePayment::firstOrFail();
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('23.2',$payment->original_amount);
        self::assertSame('232',$payment->payment);
        self::assertSame('0',$paymentController->document($payment->sale_note_id)['total_difference']);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
        self::assertSame('account',\App\Services\Payments\Classification::recordedKind($payment->fresh()));
        self::assertSame(1,$db->table('global_payments')->count());
        $cashReport=CashLedger::report(Cash::where('user_id',$input['user_id'])->where('state',true)->firstOrFail());
        self::assertSame('0',$cashReport['balances_by_currency']['VES']['final_balance']);
        self::assertSame('0',$cashReport['balances_by_currency']['USD']['final_balance']);
        $planId=$db->table('user_rel_suscription_plans')->value('id');
        $noteIds=$db->table('user_rel_suscription_plans')->value('sale_notes');
        $request->merge(['id'=>$planId,'sale_notes'=>'','quantity_period'=>2]);
        $controller->store($request);
        $controller->store($request);
        self::assertSame($noteIds,$db->table('user_rel_suscription_plans')->where('id',$planId)->value('sale_notes'));
        self::assertSame(1,$db->table('sale_notes')->count());
        self::assertSame(1,$db->table('sale_note_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame('account',\App\Services\Payments\Classification::recordedKind($payment->fresh()));
        $raceData=$request->except(['id','sale_notes']);
        $raceData['quantity_period']=0;
        $racePlan=\Modules\Suscription\Models\Tenant\UserRelSuscriptionPlan::create($raceData);
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('user_rel_suscription_plans')->where('id',$racePlan->id)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'subscription-edit',
                    'data'=>['id'=>$racePlan->id,'quantity_period'=>1]],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la edición de suscripción: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'user_rel_suscription_plans') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La edición no alcanzó el bloqueo: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertTrue($response['success'] ?? false, json_encode($response));
            }
        } finally {
            if ($db->transactionLevel()>0) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('sale_notes')->where('user_rel_suscription_plan_id',$racePlan->id)->count());
        self::assertSame(1,$db->table('sale_note_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $seriesIds=$db->table('series')->where('document_type_id','80')->pluck('id');
        $db->table('series_configurations')->whereIn('series_id',$seriesIds)->delete();
        $db->table('series')->whereIn('id',$seriesIds)->delete();
        $request->merge(['id'=>null,'cat_period_id'=>1]);
        try { $controller->store($request); self::fail('Se aceptó una suscripción sin serie.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('series_id',$error->errors()); }
        self::assertSame(2,$db->table('user_rel_suscription_plans')->count());
        self::assertSame(2,$db->table('sale_notes')->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }
    public function test_api_invoice_from_restaurant_checks_method_currency_and_bank_atomically(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false,'auto_send_pdf_email'=>false]);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bankId=$db->table('banks')->value('id');
        $ves=BankAccount::create(['bank_id'=>$bankId,'description'=>'API VES','number'=>'MOZO-INV-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $usd=BankAccount::create(['bank_id'=>$bankId,'description'=>'API USD','number'=>'MOZO-INV-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $input['exchange_rate_sale']='10'; $input['operation_type_id']='0101'; $input['date_of_issue']=now()->format('Y-m-d');
        $input['date_of_due']=$input['date_of_issue']; $input['actions']=['send_email'=>false];
        $input['payments']=[['date_of_payment'=>$input['date_of_issue'],'payment_method_type_id'=>'01',
            'payment_destination_id'=>$ves->id,'currency_type_id'=>'USD','original_amount'=>'23.20',
            'exchange_rate'=>'10','payment'=>'232']];
        $payload=\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($input);
        \Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
        $app['router']->get('downloads/{model}/{type}/{external_id}/{format?}',fn()=>null)->name('tenant.download.external_id');
        $app['router']->getRoutes()->refreshNameLookups();
        $middleware=new \App\CoreFacturalo\InputRequest();
        $controller=new \App\Http\Controllers\Tenant\Api\DocumentController();
        $send=fn($data)=>$middleware->handle(\Illuminate\Http\Request::create('/api/documents','POST',$data),
            fn($request)=>$controller->store($request),'document','api');
        try { $send($payload); self::fail('La cuenta VES no debe aceptar el pago USD.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $payload['pagos'][0]['codigo_destino_pago']=$usd->id;
        $legacy=$payload;
        unset($legacy['pagos'][0]['currency_type_id'],$legacy['pagos'][0]['original_amount'],$legacy['pagos'][0]['exchange_rate']);
        try { $send($legacy); self::fail('No se debe interpretar un cobro USD como VES al omitir la moneda.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>0]);
        try { $send($payload); self::fail('No se debe aceptar un método inactivo.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1]);
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        try { $send($payload); self::fail('No se debe aceptar una moneda recibida inactiva.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $usd->update(['status'=>0]);
        try { $send($payload); self::fail('No se debe aceptar una cuenta inactiva.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        $usd->update(['status'=>1]);
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $result=$send($payload);
        self::assertTrue($result['success']);
        $document=\App\Models\Tenant\Document::findOrFail($result['data']['id']);
        $payment=$document->payments()->firstOrFail();
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('23.2',$payment->original_amount);
        self::assertSame('232',$payment->payment);
        self::assertSame('account',Movement::row($payment->global_payment)['method']['collection_kind']);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);
        self::assertSame('USD',Movement::row($payment->global_payment->fresh())['currency_type_id']);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES',
            'cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $cashInput=$input;
        $cashInput['external_id']=(string)\Illuminate\Support\Str::uuid();
        $cashInput['payments']=[['date_of_payment'=>$input['date_of_issue'],'payment_method_type_id'=>'04',
            'payment_destination_id'=>'cash','currency_type_id'=>'VES','original_amount'=>'232','exchange_rate'=>'1','payment'=>'232']];
        $cashResult=$send(\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($cashInput));
        self::assertTrue($cashResult['success']);
        $cashPayment=\App\Models\Tenant\Document::findOrFail($cashResult['data']['id'])->payments()->firstOrFail();
        self::assertSame('cash',Movement::row($cashPayment->global_payment)['method']['collection_kind']);
        self::assertSame('VES',Movement::row($cashPayment->global_payment)['currency_type_id']);
        $cash=Cash::where('user_id',$input['user_id'])->where('state',true)->firstOrFail();
        $cashBalances=CashLedger::report($cash)['balances_by_currency'];
        self::assertSame('232',$cashBalances['VES']['final_balance']);
        self::assertSame('0',$cashBalances['USD']['final_balance']);
        $cashInput['external_id']=(string)\Illuminate\Support\Str::uuid();
        $cashInput['payments'][0]['payment_destination_id']=$usd->id;
        try { $send(\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($cashInput)); self::fail('El efectivo no debe registrarse en una cuenta bancaria.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        self::assertSame(2,$db->table('documents')->count());
        self::assertSame(2,$db->table('global_payments')->count());
    }
    public function test_api_sale_note_rejects_retired_currency_and_rolls_back_imported_customer(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $controller=new class extends \App\Http\Controllers\Tenant\Api\SaleNoteController {
            public function createPdf($sale_note=null,$format_pdf=null,$filename=null,$output='pdf') {}
        };
        $series=$db->table('series')->where('document_type_id','80')->value('id');
        $payload=array_replace($input,['id'=>null,'number'=>null,'series_id'=>$series,'document_type_id'=>'80','prefix'=>'NV',
            'currency_type_id'=>'PEN','force_create_if_not_exist'=>true,'type_period'=>null,'quantity_period'=>0,
            'datos_del_cliente_o_receptor'=>['codigo_tipo_documento_identidad'=>'1','numero_documento'=>'87654321',
                'apellidos_y_nombres_o_razon_social'=>'Cliente temporal','codigo_pais'=>'VE','ubigeo'=>'000619','direccion'=>'Prueba']]);
        $payload['items'][0]['internal_id']=$payload['items'][0]['item']['internal_id'];
        $send=fn($values)=>$controller->store(\Illuminate\Http\Request::create('/api/sale-notes','POST',$values));
        try { $send($payload); self::fail('PEN debe rechazarse antes de crear datos.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        self::assertFalse($db->table('persons')->where('number','87654321')->exists());
        self::assertSame(0,$db->table('sale_notes')->count());
        $payload['currency_type_id']='VES';
        $db->table('cat_currency_types')->where('id','VES')->update(['active'=>0]);
        try { $send($payload); self::fail('La moneda documental inactiva debe rechazarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        self::assertFalse($db->table('persons')->where('number','87654321')->exists());
        $db->table('cat_currency_types')->where('id','VES')->update(['active'=>1]);
        $payload['payments'][0]['payment_method_type_id']='ID-INEXISTENTE';
        try { $send($payload); self::fail('El cobro inválido debe revertir la importación.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }
        self::assertFalse($db->table('persons')->where('number','87654321')->exists());
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bankId=$db->table('banks')->value('id');
        $ves=BankAccount::create(['bank_id'=>$bankId,'description'=>'Mozo VES','number'=>'MOZO-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $usd=BankAccount::create(['bank_id'=>$bankId,'description'=>'Mozo USD','number'=>'MOZO-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payload['exchange_rate_sale']='10';
        $payload['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment_destination_id'=>$ves->id,
            'currency_type_id'=>'USD','original_amount'=>'23.20','exchange_rate'=>'10','payment'=>'232']];
        try { $send($payload); self::fail('La cuenta VES no admite el cobro bancario USD.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        self::assertFalse($db->table('persons')->where('number','87654321')->exists());
        self::assertSame(0,$db->table('sale_notes')->count());
        $payload['payments'][0]['payment_destination_id']=$usd->id;
        $legacy=$payload;
        unset($legacy['payments'][0]['currency_type_id'],$legacy['payments'][0]['original_amount'],$legacy['payments'][0]['exchange_rate']);
        try { $send($legacy); self::fail('No se debe interpretar un cobro USD como VES al omitir la moneda.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>0]);
        try { $send($payload); self::fail('No se debe aceptar un método inactivo.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>1]);
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        try { $send($payload); self::fail('La nota no debe aceptar una moneda recibida inactiva.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $usd->update(['status'=>0]);
        try { $send($payload); self::fail('No se debe aceptar una cuenta inactiva.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        $usd->update(['status'=>1]);
        self::assertFalse($db->table('persons')->where('number','87654321')->exists());
        self::assertSame(0,$db->table('sale_notes')->count());
        $result=$send($payload);
        self::assertTrue($result['success']);
        $note=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id']);
        $receipt=$note->payments()->firstOrFail();
        self::assertSame('USD',$receipt->currency_type_id);
        self::assertSame('23.2',$receipt->original_amount);
        self::assertSame('232',$receipt->payment);
        self::assertSame('account',Movement::row($receipt->global_payment)['method']['collection_kind']);
        self::assertSame(1,$db->table('persons')->where('number','87654321')->count());
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        self::assertSame('account',Movement::row($receipt->global_payment->fresh())['method']['collection_kind']);
        self::assertSame('USD',Movement::row($receipt->global_payment->fresh())['currency_type_id']);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $cashPayload=$payload;
        $cashPayload['force_create_if_not_exist']=false;
        $cashPayload['payments']=[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'04','payment_destination_id'=>'cash',
            'currency_type_id'=>'VES','original_amount'=>'232','exchange_rate'=>'1','payment'=>'232']];
        $cashResult=$send($cashPayload);
        self::assertTrue($cashResult['success']);
        $cashReceipt=\App\Models\Tenant\SaleNote::findOrFail($cashResult['data']['id'])->payments()->firstOrFail();
        self::assertSame('cash',Movement::row($cashReceipt->global_payment)['method']['collection_kind']);
        self::assertSame('VES',Movement::row($cashReceipt->global_payment)['currency_type_id']);
        $cash=Cash::where('user_id',$input['user_id'])->where('state',true)->firstOrFail();
        self::assertSame('232',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
        $invoiceInput=['codigo_tipo_documento'=>'01','codigo_tipo_operacion'=>'0101','codigo_tipo_moneda'=>'PEN',
            'totales'=>['total_venta'=>116],'datos_del_cliente_o_receptor'=>['codigo_tipo_documento_identidad'=>'1',
                'numero_documento'=>'87654322','apellidos_y_nombres_o_razon_social'=>'Cliente temporal'], 'items'=>[]];
        $nextCalled=false;
        try {
            (new \App\CoreFacturalo\InputRequest())->handle(\Illuminate\Http\Request::create('/api/documents','POST',$invoiceInput),
                function () use (&$nextCalled) {$nextCalled=true;},'document','api');
            self::fail('La API de factura debe rechazar PEN antes de crear datos.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        self::assertFalse($nextCalled);
        self::assertFalse($db->table('persons')->where('number','87654322')->exists());
        self::assertSame(0,$db->table('documents')->count());
    }
    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function test_api_invoice_reuses_sale_note_receipt_and_links_source(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false,'auto_send_pdf_email'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'API USD origen',
            'number'=>'API-NOTE-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $notes=new \App\Http\Controllers\Tenant\SaleNoteController();
        $data=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],'exchange_rate_sale'=>'10']);
        $note=\App\Models\Tenant\SaleNote::create($notes->mergeData($data));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $notes->savePayments($note,[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50']]);
        $source=$note->payments()->firstOrFail();
        $input['exchange_rate_sale']='10';$input['operation_type_id']='0101';$input['date_of_issue']=now()->format('Y-m-d');
        $input['date_of_due']=$input['date_of_issue'];$input['actions']=['send_email'=>false];
        $input['payments']=[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50']];
        $payload=\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::transform($input);
        $payload['codigo_nota_venta']=$note->id;
        $payload['pagos'][0]['source_sale_note_payment_id']=$source->id;
        $payload['pagos'][0]['date_of_payment']='2026-10-26';
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>0,'currency_type_id'=>'VES']);
        $bank->update(['status'=>0]);
        \Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
        $app['router']->get('downloads/{model}/{type}/{external_id}/{format?}',fn()=>null)->name('tenant.download.external_id');
        $app['router']->getRoutes()->refreshNameLookups();
        $middleware=new \App\CoreFacturalo\InputRequest();
        $api=new \App\Http\Controllers\Tenant\Api\DocumentController();
        $send=fn($data)=>$middleware->handle(\Illuminate\Http\Request::create('/api/documents','POST',$data),
            fn($request)=>$api->store($request),'document','api');
        $missing=$payload;$missing['pagos']=[];
        try { $send($missing); self::fail('La API debe conservar el cobro de la nota.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertNull($note->fresh()->document_id);
        $result=$send($payload);
        self::assertTrue($result['success']);
        $document=\App\Models\Tenant\Document::findOrFail($result['data']['id']);
        $payment=$document->payments()->firstOrFail();
        self::assertSame($document->id,$note->fresh()->document_id);
        self::assertSame((string)$source->id,$payment->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('5',\App\Support\Exact\Decimal::represent($payment->original_amount));
        self::assertSame('50',\App\Support\Exact\Decimal::represent($payment->payment));
        self::assertSame(1,$db->table('global_payments')->count());
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    public function test_restaurant_cash_links_credit_receipt_once_using_recorded_classification(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/restaurant-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>now()->format('Y-m-d'),
            'time_opening'=>'09:00:00','beginning_balance'=>'0','apply_restaurant'=>1]);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Restaurante USD',
            'number'=>'REST-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $input['payments']=[]; $input['payment_condition_id']='02'; $input['exchange_rate_sale']='10';
        $engine=new \App\CoreFacturalo\Facturalo(); $engine->forWebDocuments(); $engine->save($input);
        $document=$engine->getDocument();
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,['date_of_payment'=>now()->format('Y-m-d'),
            'payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,'currency_type_id'=>'USD',
            'exchange_rate'=>'10','original_amount'=>'23.20','igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $controller=new \Modules\Restaurant\Http\Controllers\CashController();
        $request=\Illuminate\Http\Request::create('/restaurant/cash/cash_document','POST',['document_id'=>$document->id]);
        self::assertTrue($controller->cash_document($request)->getData(true)['success']);
        self::assertTrue($controller->cash_document($request)->getData(true)['success']);
        $apiController=new \App\Http\Controllers\Tenant\Api\CashController();
        self::assertTrue($apiController->cash_document($request)->getData(true)['success']);
        self::assertTrue($apiController->cash_document($request)->getData(true)['success']);
        self::assertSame(1,$db->table('cash_document_credits')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());
        self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());
        self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());
        $link=$db->table('cash_documents')->where('cash_id',$cash->id)->where('document_id',$document->id)->value('id');
        $db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->update(['cash_document_id'=>null]);
        self::assertTrue($apiController->cash_document($request)->getData(true)['success']);
        self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());
        self::assertSame((int)$link,(int)$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->value('cash_document_id'));
        $db->table('cash_document_payments')->where('cash_id',$cash->id)->delete();
        $db->table('cash_document_credits')->where('cash_id',$cash->id)->delete();
        $db->table('cash_documents')->where('cash_id',$cash->id)->delete();
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('cash')->where('id',$cash->id)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'cash-document-link','data'=>['document_id'=>$document->id]],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició el enlace de caja: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'cash') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El enlace no alcanzó el bloqueo de caja: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertTrue($response['success'] ?? false);
            }
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('cash_document_credits')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());
        self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('document_id',$document->id)->count());
        self::assertSame(1,$db->table('cash_document_payments')->where('cash_id',$cash->id)->where('document_payment_id',$payment->id)->count());
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        $controller->close($cash->id);
        self::assertFalse((bool)$cash->fresh()->state);
    }
    public function test_full_subscription_edit_preserves_existing_orders_and_status(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $planId = $db->table('suscription_plans')->insertGetId(['name' => 'Plan aislado', 'description' => 'Prueba', 'total' => '232']);
        $payload = [
            'suscription_plan_id' => $planId, 'parent_customer_id' => $input['customer_id'],
            'parent_customer' => $input['customer'], 'customer_id' => $input['customer_id'],
            'customer' => $input['customer'], 'start_date' => '2026-11-02', 'total' => '232',
            'items' => [], 'quantity_period' => 2,
        ];
        $controller = new \Modules\FullSuscription\Http\Controllers\PaymentsFullSuscriptionController();
        $request = new \Modules\FullSuscription\Http\Requests\PaymentsFullSuscriptionRequest();
        $request->replace($payload);
        $created = $controller->store($request);
        $id = $created['data']->resource->id;
        self::assertSame(1, $db->table('suscription_orders')->where('suscription_id', $id)->count());
        $db->table('user_rel_suscription_plans')->where('id', $id)->update(['subscription_status' => 'paused', 'orders_created' => 2]);
        $payload['id'] = $id;
        $payload['orders_created'] = 0;
        $payload['subscription_status'] = 'authorized';
        $request->replace($payload);
        $controller->store($request);
        $controller->store($request);
        self::assertSame(1, $db->table('suscription_orders')->where('suscription_id', $id)->count());
        $subscription = $db->table('user_rel_suscription_plans')->where('id', $id)->first();
        self::assertSame('paused', $subscription->subscription_status);
        self::assertSame(2, (int) $subscription->orders_created);
        $model = \Modules\FullSuscription\Models\Tenant\UserRelSuscriptionPlan::findOrFail($id);
        self::assertSame('pausada', $model->calculateStatus());
        foreach (['cancelled', 'canceled', 'finished'] as $status) {
            $model->subscription_status = $status;
            self::assertSame('cancelada', $model->calculateStatus());
        }

        $db->unprepared("CREATE TRIGGER fail_subscription_order BEFORE INSERT ON suscription_orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba'");
        unset($payload['id']);
        $request->replace($payload);
        try {
            $controller->store($request);
            self::fail('La creación debe fallar si no se puede insertar la orden.');
        } catch (\Illuminate\Database\QueryException $error) {
            self::assertStringContainsString('Fallo de prueba', $error->getMessage());
        } finally {
            $db->unprepared('DROP TRIGGER fail_subscription_order');
        }
        self::assertSame(1, $db->table('user_rel_suscription_plans')->count());
        self::assertSame(1, $db->table('suscription_orders')->count());

        \Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$id,
            'type'=>'suscription_order','status'=>'paid','amount'=>'25','date_of_issue'=>now()]);
        \Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$id,
            'type'=>'new_suscription','status'=>'paid','amount'=>'100','date_of_issue'=>now()]);
        $responseFactory=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responseFactory->method('json')->willReturnCallback(fn ($data)=>new \Illuminate\Http\JsonResponse($data));
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$responseFactory);
        $stats=(new \Modules\FullSuscription\Http\Controllers\PaymentReceiptFullSuscriptionController())->statistics()->getData(true)['statistics'];
        self::assertSame(2,(int)$stats['total_orders']);
        self::assertSame(1,(int)$stats['total_approved']);
        self::assertSame(1,(int)$stats['total_pending']);
        self::assertNull($stats['total_collected']);
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_automatic_subscription_invoice_has_no_unverified_cash_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $httpCalls=0;
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function () use (&$httpCalls) {
            $httpCalls++;
            return \Illuminate\Support\Facades\Http::response([],500);
        });
        $app->instance(\App\Services\ExchangeRates\TenantExchangeRateService::class, new class extends \App\Services\ExchangeRates\TenantExchangeRateService {
            public function __construct() {}
            public function exchange($date): array { return ['sale'=>'10.00000000']; }
        });
        $db->table('payment_method_types')->where('collection_kind','cash')->update(['is_active'=>0]);
        $item=\App\Models\Tenant\Item::findOrFail($input['items'][0]['item_id']);
        $row=(object) array_replace($input['items'][0], ['item'=>$item]);
        $producer=new class { use \Modules\FullSuscription\Services\SuscriptionDocumentService; };
        $data=$producer->transform($input, collect([$row]));
        self::assertSame('01',$data['document_type_id']);
        self::assertSame('02',$data['payment_condition_id']);
        self::assertSame([],$data['payments']);
        $plan=$db->table('suscription_plans')->insertGetId(['name'=>'Plan de prueba','description'=>'Plan aislado','total'=>'232']);
        $line=$input['items'][0];
        $db->table('item_rel_suscription_plans')->insert(array_intersect_key($line,array_flip([
            'item_id','quantity','unit_value','unit_price','price_type_id','affectation_igv_type_id','total_base_igv',
            'percentage_igv','total_igv','total_taxes','total_value','total','warehouse_id'])) + ['suscription_plan_id'=>$plan]);
        $subscription=$db->table('user_rel_suscription_plans')->insertGetId([
            'suscription_plan_id'=>$plan,'user_id'=>$input['user_id'],'items'=>'[]',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),'parent_customer'=>'{}',
            'total'=>'232','total_taxed'=>'200','total_igv'=>'32','total_taxes'=>'32','total_value'=>'200']);
        $order=\Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$subscription,
            'type'=>'suscription_order','status'=>'pending','amount'=>'232','date_of_issue'=>now()]);
        $db->statement('CREATE TABLE plans (id INT PRIMARY KEY, name VARCHAR(100), limit_documents INT, include_sale_notes_limit_documents TINYINT)');
        $db->statement('CREATE TABLE clients (id INT PRIMARY KEY, hostname_id INT, name VARCHAR(100), plan_id INT, whatsapp_messages_limit_override INT NULL)');
        $db->table('plans')->insert(['id'=>1,'name'=>'Prueba','limit_documents'=>0,'include_sale_notes_limit_documents'=>0]);
        $db->table('clients')->insert(['id'=>1,'hostname_id'=>1,'name'=>'Prueba','plan_id'=>1]);
        $hostname=new \Hyn\Tenancy\Models\Hostname(); $hostname->id=1;
        $tenancy=$this->getMockBuilder(\Hyn\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['hostname'])->getMock();
        $tenancy->method('hostname')->willReturn($hostname);
        $app->instance(\Hyn\Tenancy\Environment::class,$tenancy);
        $app->instance(\App\Http\Controllers\Tenant\DocumentController::class,new \App\Http\Controllers\Tenant\DocumentController());
        $failingController=new class extends \App\Http\Controllers\Tenant\DocumentController {
            public function __construct() {}
            public function storeFromSubscription(\App\Http\Requests\Tenant\DocumentRequest $request) {
                $engine=new \App\CoreFacturalo\Facturalo();
                $engine->save($request->all());
                throw new \RuntimeException('Fallo aislado después de guardar.');
            }
        };
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/subscription-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $controller=new \Modules\FullSuscription\Http\Controllers\PaymentReceiptFullSuscriptionController();
        $response=$controller->generate($order->id)->getData(true);
        self::assertTrue($response['success'],$response['message']);
        $document=$order->fresh()->documentable;
        self::assertNotNull($document);
        self::assertSame('FULL_SUSCRIPTION',$document->source_module);
        self::assertFalse($document->emission->response['auto_send_requested']);
        self::assertArrayNotHasKey(\App\Services\Fiscal\HkaCreditInvoice::KEY,$document->emission->response);
        self::assertStringContainsString('requiere validación',\App\Services\Fiscal\HkaCreditInvoice::reason($document));
        self::assertSame(0,$httpCalls);
        $emission=app(\App\Services\Fiscal\HkaEmission::class)->send($document);
        self::assertNotSame('confirmed',$emission['status']);
        self::assertSame(0,$httpCalls);
        self::assertFalse(\App\Services\Fiscal\HkaPdf::view($document->fresh())['a4']['available']);
        self::assertTrue($controller->generate($order->id)->getData(true)['success']);
        self::assertSame(1,$db->table('documents')->count());
        $failedOrder=\Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$subscription,
            'type'=>'suscription_order','status'=>'pending','amount'=>'232','date_of_issue'=>now()]);
        $app->instance(\App\Http\Controllers\Tenant\DocumentController::class,$failingController);
        self::assertFalse($controller->generate($failedOrder->id)->getData(true)['success']);
        self::assertNull($failedOrder->fresh()->documentable_id);
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame('02',$document->payment_condition_id);
        self::assertSame(0,$db->table('document_payments')->where('document_id',$document->id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
        self::assertSame('232',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Suscripción USD',
            'number'=>'SUB-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $vesBank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Suscripción VES',
            'number'=>'SUB-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $receipt=['date_of_payment'=>now()->format('Y-m-d'),
            'payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,'currency_type_id'=>'USD',
            'exchange_rate'=>'10','original_amount'=>'23.20','igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid()];
        try {
            \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,array_replace($receipt,['payment_destination_id'=>$vesBank->id]));
            self::fail('La cuenta VES no debe recibir el cobro USD.');
        } catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        self::assertSame(0,$db->table('global_payments')->count());
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$receipt);
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('23.2',$payment->original_amount);
        self::assertSame('0',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document->fresh()])[$document->id]));
        $movement=$payment->global_payment;
        self::assertSame('account',Movement::row($movement)['method']['collection_kind']);
        self::assertSame('USD',Movement::row($movement)['currency_type_id']);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        self::assertSame('account',Movement::row($movement->fresh())['method']['collection_kind']);
        self::assertSame('USD',Movement::row($movement->fresh())['currency_type_id']);

        $db->table('user_rel_suscription_plans')->where('id',$subscription)->update(['currency_type_id'=>'USD','items'=>json_encode([$line])]);
        $db->table('suscription_plans')->where('id',$plan)->update(['currency_type_id'=>'USD']);
        $db->table('item_rel_suscription_plans')->where('suscription_plan_id',$plan)->update(['unit_price'=>'174']);
        $usdOrder=\Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$subscription,
            'type'=>'suscription_order','status'=>'pending','amount'=>'232','date_of_issue'=>now()]);
        $app->instance(\App\Http\Controllers\Tenant\DocumentController::class,new \App\Http\Controllers\Tenant\DocumentController());
        $result=$controller->generate($usdOrder->id)->getData(true);
        self::assertTrue($result['success'],$result['message']);
        $usdDocument=$usdOrder->fresh()->documentable;
        self::assertSame('USD',$usdDocument->currency_type_id);
        self::assertSame('232',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$usdDocument])[$usdDocument->id]));
        $inactiveOrder=\Modules\FullSuscription\Models\Tenant\SuscriptionOrder::create(['suscription_id'=>$subscription,
            'type'=>'suscription_order','status'=>'pending','amount'=>'232','date_of_issue'=>now()]);
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        self::assertFalse($controller->generate($inactiveOrder->id)->getData(true)['success']);
        self::assertNull($inactiveOrder->fresh()->documentable_id);
        self::assertSame(2,$db->table('documents')->count());
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $vesReceipt=\App\Services\Fiscal\FiscalDocumentPersistence::payment($usdDocument,[
            'date_of_payment'=>now()->format('Y-m-d'),'payment_method_type_id'=>'04','payment_destination_id'=>$vesBank->id,
            'currency_type_id'=>'VES','exchange_rate'=>'10','original_amount'=>'100',
            'igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid(),
        ]);
        self::assertSame('VES',$vesReceipt->currency_type_id);
        self::assertSame('100',$vesReceipt->original_amount);
        self::assertSame('222',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$usdDocument->fresh()])[$usdDocument->id]));
    }
    public function test_loan_payment_reclassifies_method_and_checks_destination_with_rollback(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $bankId = $db->table('banks')->value('id');
        $ves = BankAccount::create(['bank_id'=>$bankId, 'description'=>'VES', 'number'=>'LOAN-VES', 'currency_type_id'=>'VES', 'initial_balance'=>'0']);
        $usd = BankAccount::create(['bank_id'=>$bankId, 'description'=>'USD', 'number'=>'LOAN-USD', 'currency_type_id'=>'USD', 'initial_balance'=>'0']);
        $loan = $db->table('bank_loans')->insertGetId(['user_id'=>$input['user_id'], 'bank_loan_type_id'=>1,
            'establishment_id'=>$input['establishment_id'], 'bank_id'=>$bankId, 'bank_account_id'=>$ves->id,
            'currency_type_id'=>'VES', 'external_id'=>(string)\Illuminate\Support\Str::uuid(), 'number'=>1,
            'date_of_issue'=>'2026-10-26', 'time_of_issue'=>'09:00:00', 'bank'=>'{}', 'total'=>'100', 'fiscal_environment'=>'demo']);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $controller = new \Modules\Expense\Http\Controllers\BankLoanPaymentController();
        $send = function ($method, $destination) use ($controller, $loan) {
            $request = new \Modules\Expense\Http\Requests\BankLoanPaymentRequest();
            $request->replace(['bank_loan_id'=>$loan, 'date_of_payment'=>'2026-10-26', 'payment_method_type_id'=>$method,
                'payment_destination_id'=>$destination, 'payment'=>'10']);
            return $controller->store($request);
        };
        self::assertTrue($send('01',$ves->id)['success']);
        $movement = $db->table('global_payments')->first();
        self::assertSame('account',json_decode($movement->report_context,true)['method']['collection_kind']);
        foreach ([['01',$usd->id],['01','cash'],['04',$ves->id]] as [$method,$destination]) {
            if ($method === '04') $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'VES','is_active'=>1]);
            try { $send($method,$destination); self::fail('Debe rechazar el destino o método incompatible.'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        $db->table('payment_method_types')->where('id','01')->update(['is_active'=>0]);
        try { $send('01',$ves->id); self::fail('Debe rechazarse un método inactivo.'); }
        catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
        $snapshot = json_decode($db->table('global_payments')->where('id',$movement->id)->value('report_context'),true);
        self::assertSame('account',$snapshot['method']['collection_kind']);
        self::assertSame('VES',$snapshot['currency_type_id']);
        self::assertSame(1,$db->table('bank_loan_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }
    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_document_payment_workbook_keeps_received_and_applied_currencies_and_balance(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'REPORT-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $id=$db->table('documents')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'issuer'=>json_encode(['name'=>'Test fiscal','number'=>'J123456789']),'fiscal_environment'=>'demo',
            'fiscal_emission_mode'=>'digital','group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01',
            'series'=>'FF01','number'=>1,'date_of_issue'=>'2026-09-10','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total_value'=>'100','total'=>'100']);
        $document=\App\Models\Tenant\Document::findOrFail($id);
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,['date_of_payment'=>'2026-09-10',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'currency_type_id'=>'USD',
            'exchange_rate'=>'10','original_amount'=>'4','igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $credit=(array)$db->table('documents')->where('id',$id)->first(); unset($credit['id']);
        $credit=array_replace($credit,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'document_type_id'=>'07',
            'series'=>'FC01','number'=>2,'total'=>'20','total_value'=>'20','total_payable'=>null,'calculation_snapshot'=>null]);
        $creditId=$db->table('documents')->insertGetId($credit);
        $db->table('notes')->insert(['document_id'=>$creditId,'affected_document_id'=>$id,'note_type'=>'credit','note_description'=>'Ajuste de prueba']);
        $db->table('payment_method_types')->where('id','13')->update(['description'=>'Nombre cambiado']);
        $controller=new \App\Http\Controllers\Tenant\DocumentController();
        $transform=new \ReflectionMethod($controller,'transformReportPayment');$transform->setAccessible(true);
        $record=$transform->invoke($controller,collect([$document->fresh()]))->first();
        self::assertSame('40.00',$record->balance);
        self::assertSame('VES',$record->currency_type_id);
        self::assertSame('USD',$record->payments[0]['currency_type_id']);
        self::assertSame('4',$record->payments[0]['payment']);
        self::assertSame('40',$record->payments[0]['applied_payment']);
        self::assertSame('10.00000000',$record->payments[0]['exchange_rate']);
        self::assertNotSame('Nombre cambiado',$record->payments[0]['payment_method_type_description']);
        $app->instance('files',new \Illuminate\Filesystem\Filesystem());
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/payment-workbook','view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $export=(new \App\Exports\PaymentExport())->records(collect([$record]))->payment_count(1);
        $sheets=$export->sheets();
        self::assertCount(2,$sheets);
        self::assertSame('Fracciones exactas',$sheets[1]->title());
        $html=$export->view()->render();
        self::assertStringContainsString('Moneda del documento',$html);
        self::assertStringContainsString('Moneda recibida 1',$html);
        $spreadsheet=(new \PhpOffice\PhpSpreadsheet\Reader\Html())->loadFromString($html);
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir()));
        (new \Maatwebsite\Excel\Sheet($spreadsheet->createSheet()))->export($sheets[1]);
        $path=tempnam(sys_get_temp_dir(),'pro9-payment-workbook-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
            $opened=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path);
            $cells=collect($opened->getSheet(0)->toArray(null,true,false,false))->flatten()->map(fn($value)=>(string)$value)->all();
            self::assertContains('Moneda recibida 1',$cells);
            self::assertContains('VES',$cells); self::assertContains('USD',$cells);
            self::assertContains('40.00',$cells); self::assertContains('4.00',$cells);
            self::assertContains('10.00000000',$cells);
            $fractions=$opened->getSheetByName('Fracciones exactas')->toArray(null,true,false,false);
            self::assertContains([(string)$id,$record->invoice,(string)$payment->id,'rate','VES/USD','10','1'],$fractions);
            self::assertContains([(string)$id,$record->invoice,(string)$payment->id,'applied','VES','40','1'],$fractions);
            $opened->disconnectWorksheets();
        } finally { $spreadsheet->disconnectWorksheets(); \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder()); if (is_file($path)) unlink($path); }
        $ids=[$id]; $original=(array)$db->table('documents')->where('id',$id)->first(); unset($original['id']);
        for ($number=10;$number<20;$number++) {
            $ids[]=$db->table('documents')->insertGetId(array_replace($original,['number'=>$number,'external_id'=>(string)\Illuminate\Support\Str::uuid()]));
        }
        $batch=\App\Models\Tenant\Document::with(['payments.global_payment','person.person_type'])->whereIn('id',$ids)->get();
        $db->flushQueryLog(); $db->enableQueryLog();
        $batchRows=$transform->invoke($controller,$batch);
        $queryCount=count($db->getQueryLog()); $db->disableQueryLog();
        self::assertCount(11,$batchRows);
        self::assertLessThanOrEqual(8,$queryCount,'El reporte debe leer ajustes y saldos por lote.');
        $balances=\App\Support\Exact\Receivables::balancesForDocuments($batch);
        foreach ($batch as $item) self::assertTrue($balances[$item->id]->isEqualTo($item->exactBalance()));
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($payment,'Prueba de reversión');
        $record=$transform->invoke($controller,collect([$document->fresh()]))->first();
        self::assertSame('80.00',$record->balance);
        self::assertCount(0,$record->payments);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_document_receivable_kpi_uses_current_balance_after_foreign_receipt_reversal(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'KPI-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $id=$db->table('documents')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'issuer'=>json_encode(['name'=>'Test fiscal','number'=>'J123456789']),'fiscal_environment'=>'demo',
            'fiscal_emission_mode'=>'digital','group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01',
            'series'=>'FF01','number'=>1,'date_of_issue'=>'2026-09-10','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total_value'=>'100','total'=>'100']);
        $document=\App\Models\Tenant\Document::findOrFail($id);
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,['date_of_payment'=>'2026-09-10',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'currency_type_id'=>'USD',
            'exchange_rate'=>'10','original_amount'=>'4','igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $controller=new \App\Http\Controllers\Tenant\DocumentController();
        $request=\Illuminate\Http\Request::create('/');
        self::assertSame('Bs. 60.00',$controller->kpis($request)['receivable']['total']);
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($payment,'Prueba de reversión');
        self::assertSame('100',$document->fresh()->balance);
        self::assertSame('Bs. 100.00',$controller->kpis($request)['receivable']['total']);
        $credit=(array)$db->table('documents')->where('id',$id)->first();
        unset($credit['id']);
        $credit=array_replace($credit,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'document_type_id'=>'07',
            'series'=>'FC01','number'=>2,'total'=>'20','total_value'=>'20','total_payable'=>null,'calculation_snapshot'=>null]);
        $creditId=$db->table('documents')->insertGetId($credit);
        $db->table('notes')->insert(['document_id'=>$creditId,'affected_document_id'=>$id,
            'note_type'=>'credit','note_description'=>'Ajuste de prueba']);
        self::assertSame('80',$document->fresh()->balance);
        self::assertSame('Bs. 80.00',$controller->kpis($request)['receivable']['total']);
        $sellerId=$db->table('users')->insertGetId(['name'=>'KPI vendedor','email'=>'kpi-seller@example.test',
            'password'=>'not-a-login-hash','type'=>'seller','establishment_id'=>$input['establishment_id']]);
        $other=(array)$db->table('documents')->where('id',$id)->first();
        unset($other['id']);
        $other=array_replace($other,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'series'=>'FF01','number'=>3,
            'seller_id'=>$sellerId,'currency_type_id'=>'USD','exchange_rate_sale'=>'10','total'=>'1.23','total_value'=>'1.23',
            'total_payable'=>null,'calculation_snapshot'=>null]);
        $usdId=$db->table('documents')->insertGetId($other);
        $other=array_replace($other,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'number'=>4,
            'user_id'=>$sellerId,'seller_id'=>null,'currency_type_id'=>'VES','total'=>'5','total_value'=>'5']);
        $sellerDocumentId=$db->table('documents')->insertGetId($other);
        foreach ([$id=>\Carbon\Carbon::now()->subDay()->toDateString(),$usdId=>\Carbon\Carbon::now()->addDays(5)->toDateString(),
                  $sellerDocumentId=>\Carbon\Carbon::now()->addDays(31)->toDateString()] as $docId=>$due) {
            $db->table('invoices')->insert(['document_id'=>$docId,'operation_type_id'=>'0101','date_of_due'=>$due]);
        }
        $summary=$controller->kpis($request)['receivable'];
        self::assertSame('Bs. 97.30',$summary['total']);
        self::assertSame('Bs. 80.00',$summary['overdue']);
        self::assertSame('Bs. 12.30',$summary['current']);
        auth()->setUser(\App\Models\Tenant\User::findOrFail($sellerId));
        $sellerSummary=$controller->kpis($request)['receivable'];
        self::assertSame('Bs. 17.30',$sellerSummary['total']);
        self::assertSame('Bs. 0.00',$sellerSummary['overdue']);
        self::assertSame('Bs. 12.30',$sellerSummary['current']);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_simultaneous_document_receipts_after_prior_read_respect_balance(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'INVOICE-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $otherBank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD 2',
            'number'=>'INVOICE-RACE-2','currency_type_id'=>'USD','initial_balance'=>'0']);
        foreach (['different', 'same', 'different_destination'] as $case) {
            $sameKey=$case !== 'different';
            $id=$db->table('documents')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
                'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
                'issuer'=>json_encode(['name'=>'Test fiscal','number'=>'J123456789']),'fiscal_environment'=>'demo',
                'fiscal_emission_mode'=>'digital','group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01',
                'series'=>'FF01','number'=>['different'=>1,'same'=>2,'different_destination'=>3][$case],'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
                'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total_value'=>'100','total'=>'100']);
            $payload=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,
                'currency_type_id'=>'USD','exchange_rate'=>'10','original_amount'=>'10','igtf_status'=>'not_applicable'];
            $workers=[];
            $key=(string)\Illuminate\Support\Str::uuid();
            $db->beginTransaction();
            try {
                $db->table('companies')->where('id',1)->lockForUpdate()->first();
                for ($i=0;$i<2;$i++) {
                    $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                    $data=$payload+['operation_key'=>$sameKey ? $key : (string)\Illuminate\Support\Str::uuid()];
                    if ($case === 'different_destination' && $i === 1) $data['payment_destination_id']=$otherBank->id;
                    $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$id,'operation'=>'document-receipt','data'=>$data],JSON_THROW_ON_ERROR));
                    $process->setTimeout(30); $process->start(); $workers[]=$process;
                }
                $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
                foreach ($workers as $process) {
                    while (!str_contains($process->getOutput(),"READY\n")) {
                        if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició el cobro de factura: '.$process->getErrorOutput());
                        usleep(10000);
                    }
                    self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                    do {
                        $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                        $sql=strtolower($query->INFO ?? '');
                        if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,'companies') && str_contains($sql,'for update')) break;
                        if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cobro no alcanzó el bloqueo de empresa: '.$process->getErrorOutput().$process->getOutput());
                        usleep(10000);
                    } while (true);
                }
                $db->commit();
                $accepted=0; $rejected=0; $ids=[];
                foreach ($workers as $process) {
                    self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                    $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                    if (isset($response['id'])) { $accepted++; $ids[]=$response['id']; }
                    elseif (in_array($case === 'different_destination' ? 'operation_key' : 'payment',$response['rejected'] ?? [],true)) $rejected++;
                }
                self::assertSame($case === 'same' ? 2 : 1,$accepted);
                self::assertSame($case === 'same' ? 0 : 1,$rejected);
                if ($case === 'same') self::assertSame($ids[0],$ids[1]);
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($workers as $process) if ($process->isRunning()) $process->stop();
            }
            self::assertSame(1,$db->table('document_payments')->where('document_id',$id)->count());
            self::assertSame(['different'=>1,'same'=>2,'different_destination'=>3][$case],$db->table('global_payments')->count());
            self::assertTrue((bool)$db->table('documents')->where('id',$id)->value('total_canceled'));
        }
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_simultaneous_sale_note_receipts_keep_current_settlement_state(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'SALE-NOTE-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $noteController=new \App\Http\Controllers\Tenant\SaleNoteController();
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV',
            'exchange_rate_sale'=>'10','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($noteController->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $payload=['sale_note_id'=>$note->id,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'11.60',
            'exchange_rate'=>'10','payment'=>'116'];
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('sale_notes')->where('id',$note->id)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>'sale-note-receipt','data'=>$payload,'pre_read'=>true],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició el cobro concurrente: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,'sale_notes') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cobro no alcanzó el bloqueo de la nota: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertTrue($response['success'] ?? false);
            }
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(2,$db->table('sale_note_payments')->where('sale_note_id',$note->id)->count());
        self::assertSame(2,$db->table('global_payments')->count());
        self::assertSame('0',(new \App\Http\Controllers\Tenant\SaleNotePaymentController())->document($note->id)['total_difference']);
        self::assertTrue((bool)$note->fresh()->total_canceled);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_simultaneous_receipts_do_not_exceed_quotation_or_technical_service_balance(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $common=['user_id'=>$input['user_id'],'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo','currency_type_id'=>'VES',
            'exchange_rate_sale'=>'400','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','total'=>'4000'];
        $quotationId=$db->table('quotations')->insertGetId($common+['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment'=>json_encode($input['establishment']),'state_type_id'=>'05','prefix'=>'COT']);
        $serviceId=$db->table('technical_services')->insertGetId($common+['cellphone'=>'','description'=>'Servicio',
            'state'=>'','reason'=>'','serial_number'=>'RACE','cost'=>'0']);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'BALANCE-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        foreach ([['quotation-receipt','quotations','quotation_id',$quotationId,'quotation_payments'],
                  ['technical-receipt','technical_services','technical_service_id',$serviceId,'technical_service_payments']] as [$operation,$table,$field,$id,$paymentTable]) {
            $payload=[$field=>$id,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
                'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'10',
                'exchange_rate'=>'400','payment'=>'4000'];
            $workers=[];
            $db->beginTransaction();
            try {
                $db->table($table)->where('id',$id)->lockForUpdate()->first();
                for ($i=0;$i<2;$i++) {
                    $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                    $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>$operation,'data'=>$payload,'pre_read'=>true],JSON_THROW_ON_ERROR));
                    $process->setTimeout(30); $process->start(); $workers[]=$process;
                }
                $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
                foreach ($workers as $process) {
                    while (!str_contains($process->getOutput(),"READY\n")) {
                        if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició el cobro concurrente: '.$process->getErrorOutput());
                        usleep(10000);
                    }
                    self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                    do {
                        $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                        $sql=strtolower($query->INFO ?? '');
                        if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,$table) && str_contains($sql,'for update')) break;
                        if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cobro no alcanzó el bloqueo del documento: '.$process->getErrorOutput().$process->getOutput());
                        usleep(10000);
                    } while (true);
                }
                $db->commit();
                $accepted=0; $rejected=0;
                foreach ($workers as $process) {
                    self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                    $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                    if (!empty($response['success'])) $accepted++;
                    elseif (in_array('payment',$response['rejected'] ?? [],true)) $rejected++;
                }
                self::assertSame(1,$accepted,"{$table}: debe guardarse un solo cobro.");
                self::assertSame(1,$rejected,"{$table}: el otro cobro debe exceder el saldo restante.");
            } finally {
                if ($db->transactionLevel()) $db->rollBack();
                foreach ($workers as $process) if ($process->isRunning()) $process->stop();
            }
            self::assertSame(1,$db->table($paymentTable)->where($field,$id)->count());
        }
        self::assertSame(2,$db->table('global_payments')->count());
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_bank_currency_edit_and_receipt_serialize_without_mismatched_currency(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'], 'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'], 'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo', 'state_type_id'=>'05', 'prefix'=>'COT',
            'date_of_issue'=>'2026-10-26', 'time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES', 'exchange_rate_sale'=>'400', 'total'=>'8000',
        ]);
        $createBank=fn($number)=>BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),
            'description'=>'USD','number'=>$number,'currency_type_id'=>'USD','initial_balance'=>'0']);
        $payload=fn($bank)=>['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'payment'=>'4000',
            'currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'400'];
        $start=function ($operation,$data) use ($db) {
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>$operation,'data'=>$data],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El proceso no llegó al bloqueo: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,'bank_accounts') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) {
                    $state=$control->select('SELECT ID, COMMAND, STATE, INFO FROM information_schema.processlist WHERE DB = ?',[$db->getDatabaseName()]);
                    $diagnostic=$process->getErrorOutput().$process->getOutput().json_encode($state);
                    if ($process->isRunning()) $process->stop(1);
                    self::fail('El proceso no quedó esperando el bloqueo de la cuenta: '.$diagnostic);
                }
                usleep(10000);
            } while (true);
            return $process;
        };
        $result=function ($process) {
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            return json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
        };
        $bank=$createBank('RACE-RECEIPT-FIRST'); $process=null;
        $db->beginTransaction();
        try {
            BankAccount::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            $process=$start('bank-currency-edit',['id'=>$bank->id,'currency_type_id'=>'VES']);
            (new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store(
                \Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $payload($bank)));
            $db->commit();
            self::assertContains('currency_type_id',$result($process)['rejected']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame('USD',$bank->fresh()->currency_type_id);
        self::assertSame(1,$db->table('quotation_payments')->count());

        $bank=$createBank('RACE-EDIT-FIRST'); $process=null;
        $db->beginTransaction();
        try {
            BankAccount::whereKey($bank->id)->lockForUpdate()->firstOrFail();
            $process=$start('quotation-receipt',$payload($bank));
            (new \App\Http\Controllers\Tenant\BankAccountController())->store(
                \App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', ['id'=>$bank->id,'currency_type_id'=>'VES']));
            $db->commit();
            self::assertContains('payment_destination_id',$result($process)['rejected']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame('VES',$bank->fresh()->currency_type_id);
        self::assertSame(1,$db->table('quotation_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_receipt_rechecks_currency_after_concurrent_deactivation(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'CURRENCY-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payload=['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'10','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10'];
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>'quotation-receipt',
                'data'=>$payload,'pre_read'=>true],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició el proceso de cobro: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'cat_currency_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cobro no esperó el bloqueo de moneda: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('currency_type_id',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(0,$db->table('quotation_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $process=null;
        $db->beginTransaction();
        try {
            $receipt=(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store(
                \Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $payload));
            self::assertTrue($receipt['success']);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>'currency-deactivate',
                'data'=>['currency'=>'USD']],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la desactivación: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'update') && str_contains($sql,'cat_currency_types')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La desactivación no esperó al cobro: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertTrue($result['success']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $saved=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('USD',Movement::row($saved->global_payment)['currency_type_id']);
        self::assertSame('1',Movement::row($saved->global_payment)['amount']);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_method_activation_rechecks_classification_after_concurrent_edit(): void
    {
        [$db]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>0]);
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'unclassified']);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'method-activate',
                'data'=>['id'=>'13','is_active'=>true]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la activación: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La activación no esperó el bloqueo: '.$process->getErrorOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('collection_kind',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $method=$db->table('payment_method_types')->where('id','13')->first();
        self::assertSame('unclassified',$method->collection_kind);
        self::assertSame(0,(int)$method->is_active);
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','is_active'=>0]);
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'method-reclassify',
                'data'=>['id'=>'13','description'=>'Zelle','collection_kind'=>'unclassified','currency_type_id'=>'USD']],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la edición: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La edición no esperó el bloqueo: '.$process->getErrorOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('collection_kind',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $method=$db->table('payment_method_types')->where('id','13')->first();
        self::assertSame('account',$method->collection_kind);
        self::assertSame(1,(int)$method->is_active);
    }

    public function test_receipt_attachment_reuses_concurrently_committed_movement_after_prior_read(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'ATTACH-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $paymentId=$db->table('quotation_payments')->insertGetId(['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10','payment'=>'10']);
        $payment=\Modules\Sale\Models\QuotationPayment::findOrFail($paymentId);
        $process=null;
        $db->beginTransaction();
        try {
            self::assertSame(0,$db->table('global_payments')->count());
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'receipt-attach',
                'data'=>['payment_id'=>$paymentId,'destination_id'=>$bank->id]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30);
            $process->run();
            self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());
            self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success']);
            \App\Services\Payments\ReceiptDestination::attach($payment,['payment_destination_id'=>$bank->id]);
            $db->commit();
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('global_payments')->count());
        try {
            $db->transaction(fn()=>\App\Services\Payments\ReceiptDestination::attach($payment,['payment_destination_id'=>'cash']));
            self::fail('Un reintento no puede cambiar el destino del movimiento.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        $secondId=$db->table('quotation_payments')->insertGetId(['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10','payment'=>'10']);
        $second=\Modules\Sale\Models\QuotationPayment::findOrFail($secondId);
        $process=null; $db->beginTransaction();
        try {
            $db->table('quotation_payments')->where('id',$secondId)->lockForUpdate()->first();
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'receipt-attach',
                'data'=>['payment_id'=>$secondId,'destination_id'=>$bank->id]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            do {
                if (preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match)) {
                    $query=$this->capsule->getConnection('control')->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'quotation_payments') && str_contains($sql,'for update')) break;
                }
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La asociación no esperó el bloqueo del cobro: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            \App\Services\Payments\ReceiptDestination::attach($second,['payment_destination_id'=>$bank->id]);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('global_payments')->where('payment_type',\Modules\Sale\Models\QuotationPayment::class)->where('payment_id',$secondId)->count());
        $thirdId=$db->table('quotation_payments')->insertGetId(['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10','payment'=>'10']);
        $third=\Modules\Sale\Models\QuotationPayment::findOrFail($thirdId);
        $process=null; $db->beginTransaction();
        try {
            $db->table('global_payments')->count(); // Establish a consistent read before the other association commits.
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'receipt-attach',
                'data'=>['payment_id'=>$thirdId,'destination_id'=>$bank->id]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->run();
            self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());
            $third->payment='11';
            try { $third->save(); self::fail('El importe no puede cambiar después de asociar el movimiento.'); }
            catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
            $db->commit();
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame('10',\App\Support\Exact\Decimal::represent($db->table('quotation_payments')->where('id',$thirdId)->value('payment')));
    }

    public function test_sale_note_batch_uses_current_locked_method_and_blocks_reclassification(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1,'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD lote','number'=>'BATCH-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10';
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]])));
        $row=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10','payment'=>'10'];
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'USD']);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'sale-note-batch',
                'data'=>['note_id'=>$note->id,'payments'=>[$row,$row]]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició el lote: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            do {
                $query=$this->capsule->getConnection('control')->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El lote no esperó el método: '.$process->getErrorOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('payment_destination_id',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(0,$db->table('sale_note_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','cash_currency_type_id'=>null]);
        $process=null; $db->beginTransaction();
        try {
            $controller->savePayments($note,[$row,$row]);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'method-reclassify',
                'data'=>['id'=>'13','description'=>'Zelle','collection_kind'=>'cash','currency_type_id'=>'USD','is_active'=>1]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la reclasificación: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            do {
                $query=$this->capsule->getConnection('control')->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La reclasificación no esperó el lote: '.$process->getErrorOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput());
            self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success'] ?? false);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(2,$db->table('global_payments')->count());
        foreach (\Modules\Finance\Models\GlobalPayment::all() as $movement) {
            self::assertSame('account',data_get($movement->report_context,'method.collection_kind'));
            self::assertSame('USD',data_get($movement->report_context,'currency_type_id'));
        }
        self::assertSame('cash',$db->table('payment_method_types')->where('id','13')->value('collection_kind'));
    }

    public function test_receipt_serializes_with_concurrent_method_reclassification(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'METHOD-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payment=['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'10','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10'];
        $method=['id'=>'13','description'=>'Zelle','collection_kind'=>'cash','currency_type_id'=>'VES','is_active'=>1, 'hka_code'=>'99', 'iva_rate_type_id'=>null];
        $start=function ($operation,$data,$preRead=false) use ($db) {
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>$operation,
                'data'=>$data,'pre_read'=>$preRead],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició el proceso: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                $expected=$operation==='quotation-receipt' ? str_contains($sql,'for update') : str_contains($sql,'update');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && $expected) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No esperó el bloqueo del método: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            return $process;
        };
        $finish=function ($process) {
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            return json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
        };
        $process=null; $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
            $process=$start('quotation-receipt',$payment,true);
            $db->commit();
            self::assertContains('currency_type_id',$finish($process)['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(0,$db->table('quotation_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        // Same currency, changed nature: the transaction has a snapshot from before the catalog edit.
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null]);
        $process=null; $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash','currency_type_id'=>'USD','cash_currency_type_id'=>'USD']);
            $process=$start('quotation-receipt',$payment,true);
            $db->commit();
            self::assertContains('payment_destination_id',$finish($process)['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(0,$db->table('quotation_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $cashPayment=$payment;
        $cashPayment['payment_destination_id']='cash';
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null]);
        $process=null; $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash','currency_type_id'=>'USD','cash_currency_type_id'=>'USD','iva_rate_type_id'=>'IGTF']);
            $process=$start('quotation-receipt',$cashPayment,true);
            $db->commit();
            self::assertTrue($finish($process)['success'] ?? false);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $cashGlobal=\Modules\Finance\Models\GlobalPayment::query()->firstOrFail();
        self::assertSame('cash',data_get($cashGlobal->report_context,'method.collection_kind'));
        self::assertSame('USD',data_get($cashGlobal->report_context,'currency_type_id'));
        self::assertSame('IGTF',data_get($cashGlobal->report_context,'method.iva_rate_type_id'));
        self::assertSame('3.000000000000',data_get($cashGlobal->report_context,'method.iva_rate_percentage'));
        self::assertSame(Cash::class,$cashGlobal->destination_type);
        self::assertSame((int)$cash->id,(int)$cashGlobal->destination_id);
        // Remove only synthetic rows in this temporary database before the remaining concurrency cases.
        $db->table('global_payments')->where('id',$cashGlobal->id)->delete();
        $db->table('quotation_payments')->where('quotation_id',$quotationId)->delete();
        $db->table('cash')->where('id',$cash->id)->update(['state'=>false]);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'cash','currency_type_id'=>'USD','cash_currency_type_id'=>'USD']);
        try {
            (new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store(
                \Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $payment));
            self::fail('El destino bancario anterior no sirve cuando el método pasa a efectivo.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        self::assertSame(0,$db->table('quotation_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','13')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null]);
        $process=null; $db->beginTransaction();
        try {
            $result=(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store(
                \Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $payment));
            self::assertTrue($result['success']);
            $process=$start('method-reclassify',$method);
            $db->commit();
            self::assertTrue($finish($process)['success']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $saved=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('USD',Movement::row($saved->global_payment)['currency_type_id']);
        self::assertSame('account',data_get($saved->global_payment->report_context,'method.collection_kind'));
        self::assertSame('IGTF',$saved->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000',$saved->payment_method_snapshot['iva_rate_percentage']);
        self::assertNull($db->table('payment_method_types')->where('id','13')->value('iva_rate_type_id'));
        self::assertSame('cash',$db->table('payment_method_types')->where('id','13')->value('collection_kind'));
        self::assertSame(1,$db->table('global_payments')->count());
    }

    // ######## INICIO TRAZABILIDAD CONFIGURACION DEL MEDIO ########
    public function test_receipt_reads_tax_percentage_after_concurrent_catalog_update(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1,'iva_rate_type_id'=>'IGTF']);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'TAX-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payment=['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'10','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10'];
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('cat_iva_rate_types')->where('id','IGTF')->update(['percentage'=>'4.25']);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>'quotation-receipt',
                'data'=>$payment,'pre_read'=>true],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició el proceso: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'cat_iva_rate_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No esperó el bloqueo del impuesto: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            self::assertTrue(json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['success']);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $receipt=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('4.250000000000',$receipt->payment_method_snapshot['iva_rate_percentage']);
        self::assertSame('4.250000000000',$receipt->global_payment->report_context['method']['iva_rate_percentage']);
    }
    // ######## FIN TRAZABILIDAD CONFIGURACION DEL MEDIO ########

    public function test_quotation_and_technical_service_limit_the_exact_applied_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $common=['user_id'=>$input['user_id'],'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo','currency_type_id'=>'VES',
            'exchange_rate_sale'=>'400','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00'];
        $quotationId=$db->table('quotations')->insertGetId($common+['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment'=>json_encode($input['establishment']),'state_type_id'=>'05','prefix'=>'COT','total'=>'4000.01']);
        $serviceId=$db->table('technical_services')->insertGetId($common+['cellphone'=>'','description'=>'Servicio',
            'state'=>'','reason'=>'','serial_number'=>'TEST','total'=>'4000.01','cost'=>'0.01']);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'LIMIT-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $cases=[
            [new \Modules\Sale\Http\Controllers\QuotationPaymentController(), \Modules\Sale\Http\Requests\QuotationPaymentRequest::class,
                'quotation_id',$quotationId,'4000.01','4.01','0.01'],
            [new \Modules\Sale\Http\Controllers\TechnicalServicePaymentController(), \Modules\Sale\Http\Requests\TechnicalServicePaymentRequest::class,
                'technical_service_id',$serviceId,'4000.02','4.02','0.02'],
        ];
        foreach ($cases as [$controller,$requestClass,$field,$id,$total,$remaining,$final]) {
            $data=[$field=>$id,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
                'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'9.99',
                'exchange_rate'=>'400','payment'=>'1'];
            self::assertTrue($controller->store($requestClass::create('/', 'POST', $data))['success']);
            $document=$controller->document($id);
            self::assertSame($total,$document['total']);
            self::assertSame('3996',$document['total_paid']);
            self::assertSame($remaining,$document['total_difference']);
            self::assertSame($remaining,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::readRequired($document,'total_difference')));
            $data['original_amount']='0.02';
            try {
                $controller->store($requestClass::create('/', 'POST', $data));
                self::fail('El importe aplicado calculado por el servidor supera el saldo.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('payment',$error->errors());
            }
            self::assertSame($remaining,$controller->document($id)['total_difference']);
            $data['original_amount']='0.01';
            self::assertTrue($controller->store($requestClass::create('/', 'POST', $data))['success']);
            self::assertSame($final,$controller->document($id)['total_difference']);
        }
        self::assertSame(4,$db->table('global_payments')->count());
        $quote=\App\Models\Tenant\Quotation::findOrFail($quotationId);
        $oldRows=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments($quote->payments)->all();
        $newReceipt=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'400','payment'=>'400'];
        $line=array_replace($input['items'][0],['quantity'=>'1','unit_price'=>'8000']);
        $edit=\App\Http\Requests\Tenant\QuotationRequest::create('/', 'POST', ['id'=>$quotationId,
            'customer_id'=>$input['customer_id'],'currency_type_id'=>'VES','total'=>'9999','items'=>[$line],
            'payments'=>array_merge($oldRows,[$newReceipt])]);
        self::assertTrue((new \App\Http\Controllers\Tenant\QuotationController())->update($edit)['success']);
        $after=(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->document($quotationId);
        self::assertSame('8000',$after['total']);
        self::assertSame('4400',$after['total_paid']);
        self::assertSame('3600',$after['total_difference']);
        $allRows=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(\App\Models\Tenant\Quotation::findOrFail($quotationId)->payments)->all();
        $edit->merge(['total'=>'3999','items'=>[array_replace($line,['unit_price'=>'3999'])],'payments'=>$allRows]);
        try {
            (new \App\Http\Controllers\Tenant\QuotationController())->update($edit);
            self::fail('La edición no puede reducir el total por debajo de los cobros conservados.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('total',$error->errors());
        }
        self::assertSame('8000',(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->document($quotationId)['total']);
        self::assertSame(3,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());
        $initialQuoteId=$db->table('quotations')->insertGetId($common+['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment'=>json_encode($input['establishment']),'state_type_id'=>'05','prefix'=>'COT','total'=>'4000']);
        $first=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'400','payment'=>'1'];
        try {
            $db->transaction(fn()=>(new \App\Http\Controllers\Tenant\QuotationController())->savePayments(
                \App\Models\Tenant\Quotation::findOrFail($initialQuoteId),[$first,array_replace($first,['original_amount'=>'6'])]));
            self::fail('Los cobros iniciales no pueden superar el total convertido.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment',$error->errors());
        }
        self::assertSame(0,$db->table('quotation_payments')->where('quotation_id',$initialQuoteId)->count());
        self::assertSame(5,$db->table('global_payments')->count());
        $createController=$this->getMockBuilder(\App\Http\Controllers\Tenant\QuotationController::class)
            ->disableOriginalConstructor()->onlyMethods(['createPdf'])->getMock();
        $createData=['customer_id'=>$input['customer_id'],'establishment_id'=>$input['establishment_id'],
            'prefix'=>'COT','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','currency_type_id'=>'VES',
            'exchange_rate_sale'=>'400','total'=>'9999','items'=>[$line],
            'payments'=>[array_replace($first,['original_amount'=>'21'])]];
        $before=$db->table('quotations')->count();
        try {
            $createController->store(\App\Http\Requests\Tenant\QuotationRequest::create('/', 'POST', $createData));
            self::fail('La creación debe validar el cobro contra el total calculado de las partidas.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment',$error->errors());
        }
        self::assertSame($before,$db->table('quotations')->count());
        self::assertSame(5,$db->table('global_payments')->count());
        $createData['payments'][0]['original_amount']='20';
        $created=$createController->store(\App\Http\Requests\Tenant\QuotationRequest::create('/', 'POST', $createData));
        self::assertTrue($created['success']);
        $createdBalance=(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->document($created['data']['id']);
        self::assertSame('8000',$createdBalance['total']);
        self::assertSame('8000',$createdBalance['total_paid']);
        self::assertSame('0',$createdBalance['total_difference']);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    public function test_mobile_update_does_not_remove_a_concurrently_recorded_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,new class {
            public function json($data=[], $status=200, array $headers=[], $options=0) { return new \Illuminate\Http\JsonResponse($data,$status,$headers,$options); }
        });
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $input['payments']=[];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'MOBILE-ATTACH-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payment=\App\Models\Tenant\DocumentPayment::create(['document_id'=>$document->id,'date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10',
            'payment'=>'10','tax_amount'=>'0','payment_method_snapshot'=>\App\Services\Payments\Classification::snapshot(\App\Models\Tenant\PaymentMethodType::findOrFail('13')),
            'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $received=\Modules\MobileApp\Models\ReceivedPayment::create(['provider'=>'Zelle','amount'=>'1','currency_type_id'=>'USD',
            'raw_text'=>'Prueba','package_name'=>'test.mobile','posted_at'=>'2026-10-26 12:00:00','device_id'=>'test',
            'dedup_hash'=>'mobile-existing-race','status'=>'matched','matched_document_id'=>$document->id,
            'matched_document_payment_id'=>$payment->id]);
        $controller=new \Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController();
        $request=\Modules\MobileApp\Http\Requests\Api\ReceivedPaymentClaimRequest::create('/','POST',[]);
        $process=null; $db->beginTransaction();
        try {
            self::assertSame(0,$db->table('global_payments')->count());
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'document-attach',
                'data'=>['payment_id'=>$payment->id,'destination_id'=>$bank->id]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->run();
            self::assertSame(0,$process->getExitCode(),$process->getErrorOutput().$process->getOutput());
            try { $controller->update($received->id,$request); self::fail('No se debe borrar un cobro contabilizado.'); }
            catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { self::assertSame(409,$error->getResponse()->getStatusCode()); }
            $db->commit();
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertNotNull($payment->fresh());
        self::assertSame((int)$payment->id,(int)$received->fresh()->matched_document_payment_id);
        self::assertSame(1,$db->table('global_payments')->count());
        $movement=\Modules\Finance\Models\GlobalPayment::query()->firstOrFail();
        self::assertSame('account',data_get($movement->report_context,'method.collection_kind'));
        self::assertSame('USD',data_get($movement->report_context,'currency_type_id'));
        self::assertSame('1',Movement::row($movement)['amount']);
    }

    public function test_mobile_capture_keeps_currency_and_rejects_a_conflicting_duplicate(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $responses=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->method('json')->willReturnCallback(fn($data,$status=200)=>new \Illuminate\Http\JsonResponse($data,$status));
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$responses);
        $payload=['provider'=>'Zelle','amount'=>'99999999999999.99','currency_type_id'=>'USD','sender_name'=>'Cliente',
            'raw_text'=>'Cobro recibido','package_name'=>'test.mobile','posted_at'=>'2026-10-28 12:00:00',
            'device_id'=>'device-1','dedup_hash'=>'capture-test-1'];
        $request=\Modules\MobileApp\Http\Requests\Api\ReceivedPaymentStoreRequest::create('/', 'POST', $payload);
        $request->setContainer($app);
        $request->validateResolved();
        $controller=new \Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController();
        $result=$controller->store($request);
        self::assertSame('USD',$result['data']['currency_type_id']);
        self::assertSame('99999999999999.99',$result['data']['amount_decimal']);
        self::assertFalse($result['duplicated']);
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        self::assertTrue($controller->store($request)['duplicated']);
        $newPayload=array_replace($payload,['dedup_hash'=>'capture-test-2']);
        $newRequest=\Modules\MobileApp\Http\Requests\Api\ReceivedPaymentStoreRequest::create('/', 'POST', $newPayload);
        $newRequest->setContainer($app);
        $newRequest->validateResolved();
        try { $controller->store($newRequest); self::fail('No se aceptan capturas nuevas en moneda inactiva.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('currency_type_id',$error->errors()); }
        $payload['currency_type_id']='VES';
        $conflict=\Modules\MobileApp\Http\Requests\Api\ReceivedPaymentStoreRequest::create('/', 'POST', $payload);
        $conflict->setContainer($app);
        $conflict->validateResolved();
        try { $controller->store($conflict); self::fail('Una captura no puede cambiar de moneda al reintentarse.'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $error) { self::assertSame(409,$error->getResponse()->getStatusCode()); }
        self::assertSame(1,$db->table('received_payments')->count());
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $payload['currency_type_id']='USD'; $payload['dedup_hash']='capture-concurrent';
        $processes=[];
        $db->beginTransaction();
        try {
            $db->table('cat_currency_types')->where('id','USD')->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>0,'operation'=>'mobile-capture','data'=>$payload],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+10;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cliente concurrente no llegó al bloqueo: '.$process->getErrorOutput());
                    usleep(10000);
                }
            }
            $db->commit();
            $duplicates=[];
            foreach ($processes as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertTrue($response['success']);
                $duplicates[]=$response['duplicated'];
            }
            sort($duplicates);
            self::assertSame([false,true],$duplicates);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(2,$db->table('received_payments')->count());
    }

    public function test_contract_keeps_the_original_cross_currency_receipt_on_edit(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $id=$db->table('contracts')->insertGetId(['user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'CON','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'400','total'=>'4000']);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CONTRACT-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $contract=\Modules\Sale\Models\Contract::withoutEagerLoads()->findOrFail($id);
        $save=new \ReflectionMethod(\Modules\Sale\Http\Controllers\ContractController::class,'savePayments');
        $save->setAccessible(true);
        $db->transaction(fn()=>$save->invoke(new \Modules\Sale\Http\Controllers\ContractController(),$contract,[[
            'payment'=>'4000','original_amount'=>'10','currency_type_id'=>'USD','exchange_rate'=>'400',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'date_of_payment'=>'2026-10-26',
        ]]));
        $payment=$contract->payments()->firstOrFail();
        $snapshot=$payment->payment_method_snapshot;
        $rows=\Modules\Sale\Http\Resources\ContractResource::getTransformPayments(collect([$payment]))->all();
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES','description'=>'Nombre nuevo','is_active'=>0]);
        $historical=\Modules\Sale\Http\Resources\ContractResource::getTransformPayments(collect([$payment->fresh()]))->first();
        self::assertSame($snapshot['description'],$historical['payment_method_snapshot']['description']);
        self::assertSame('USD · USD',$historical['payment_destination_description']);
        $serialized=(new \Modules\Sale\Http\Resources\ContractResource($contract->fresh()))->toArray(\Illuminate\Http\Request::create('/', 'GET'));
        $serializedPayment=$serialized['contract']->payments[0];
        self::assertSame($snapshot['description'],$serializedPayment['payment_method_snapshot']['description']);
        self::assertSame('USD · USD',$serializedPayment['payment_destination_description']);
        $edit=\Modules\Sale\Http\Requests\ContractRequest::create('/', 'POST', [
            'id'=>$id, 'customer_id'=>$input['customer_id'], 'establishment_id'=>$input['establishment_id'],
            'currency_type_id'=>'VES', 'items'=>[], 'payments'=>$rows,
        ]);
        $controller=$this->getMockBuilder(\Modules\Sale\Http\Controllers\ContractController::class)->onlyMethods(['createPdf'])->getMock();
        self::assertTrue($controller->store($edit)['success']);
        self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);
        self::assertSame('10',Movement::row($payment->fresh()->global_payment)['amount']);
        self::assertSame(1,$db->table('global_payments')->count());
        $changed=$rows;
        $changed[0]['payment']='5000';
        $edit->merge(['payments'=>$changed]);
        try {
            $controller->store($edit);
            self::fail('Un cobro registrado no puede modificarse al editar el contrato.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame('4000',$payment->fresh()->payment);
        $edit->merge(['payments'=>[]]);
        self::assertTrue($controller->store($edit)['success']);
        self::assertSame(0,$db->table('contract_payments')->where('contract_id',$id)->count());
        $context=json_decode($db->table('global_payments')->value('report_context'),true,512,JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('reversal',$context);
    }

    public function test_default_cash_orders_custom_ids_like_the_frontend(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        foreach (['^','!'] as $id) $db->table('payment_method_types')->insert([
            'id'=>$id,'description'=>'Efectivo personalizado','hka_code'=>'99','collection_kind'=>'cash',
            'currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_credit'=>0,'is_active'=>1,
        ]);
        self::assertSame('!', \App\Services\Payments\Classification::defaultCashMethod('VES')->id);
        self::assertSame('!', $db->transaction(fn()=>\App\Services\Payments\Classification::defaultCashMethod('VES')->id));
    }

    public function test_cash_opening_keeps_its_own_currency_when_catalog_methods_change(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->whereIn('id',['01','10'])->update([
            'collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>0,
        ]);
        $db->table('payment_method_types')->where('id','04')->update([
            'collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1,
        ]);
        $db->table('payment_method_types')->where('id','11')->update([
            'collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1,
        ]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        try {
            $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'5']]));
            self::fail('Una apertura USD positiva requiere efectivo activo en USD.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_method_type_id',$error->errors());
            self::assertStringContainsString('USD',$error->errors()['payment_method_type_id'][0]);
        }
        self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());
        self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','13')->update([
            'collection_kind'=>'cash','currency_type_id'=>'USD','cash_currency_type_id'=>'USD','is_cash'=>1,'is_active'=>1,
        ]);
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        try {
            $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'5']]));
            self::fail('No se admite una apertura en moneda inactiva.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'5']]));
        self::assertSame(['04','13'],$db->table('cash_transactions')->where('cash_id',$cash->id)->orderBy('id')->pluck('payment_method_type_id')->all());
        $db->table('payment_method_types')->whereIn('id',['04','13'])->update([
            'collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>0,
        ]);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'5']]));
        $report=CashLedger::report($cash);
        self::assertSame('100',$report['balances_by_currency']['VES']['beginning_balance']);
        self::assertSame('5',$report['balances_by_currency']['USD']['beginning_balance']);
        self::assertCount(2,$report['rows']);
        self::assertSame(['cash','cash'],array_column(array_column($report['rows'],'method'),'collection_kind'));
        self::assertSame(['04','13'],array_column(array_column($report['rows'],'method'),'id'));
        self::assertSame(2,$db->table('global_payments')->count());
        self::assertTrue(CashLedger::close($cash)['success']);
        $db->table('payment_method_types')->where('collection_kind','cash')->update(['is_active'=>0]);
        $zero=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-27','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($zero,[]));
        self::assertSame(2,$db->table('cash_currency_balances')->where('cash_id',$zero->id)->count());
        self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$zero->id)->count());
    }

    public function test_cash_opening_rechecks_concurrent_method_deactivation(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $process=null;
        $db->beginTransaction();
        try {
            $db->table('payment_method_types')->where('id','10')->update(['is_active'=>0]);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'cash-opening',
                'data'=>['cash_id'=>$cash->id,'opening_balances'=>['VES'=>'100','USD'=>'5']]],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la apertura concurrente: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'for update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La apertura no esperó el bloqueo del método: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('payment_method_type_id',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        self::assertSame(0,$db->table('cash_currency_balances')->where('cash_id',$cash->id)->count());
        self::assertSame(0,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>1]);
        $process=null;
        $db->beginTransaction();
        try {
            CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'5']]);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'method-deactivate','data'=>['id'=>'10']],JSON_THROW_ON_ERROR));
            $process->setTimeout(30); $process->start();
            $deadline=microtime(true)+10;
            while (!str_contains($process->getOutput(),"READY\n")) {
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No inició la desactivación concurrente: '.$process->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'payment_method_types') && str_contains($sql,'update')) break;
                if (!$process->isRunning() || microtime(true)>$deadline) self::fail('La desactivación no esperó la apertura: '.$process->getErrorOutput().$process->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
            $result=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertTrue($result['success'] ?? false);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($process && $process->isRunning()) $process->stop();
        }
        $report=CashLedger::report($cash);
        self::assertSame('5',$report['balances_by_currency']['USD']['beginning_balance']);
        self::assertSame('cash',$report['rows'][1]['method']['collection_kind']);
        self::assertSame('USD',$report['rows'][1]['currency_type_id']);
        self::assertSame(2,$db->table('global_payments')->count());
    }

    public function test_hotel_retries_do_not_duplicate_a_received_payment(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $responses=$this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->method('json')->willReturnCallback(fn($data,$status=200)=>new \Illuminate\Http\JsonResponse($data,$status));
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,$responses);
        self::assertSame(0,(int)$db->select("SHOW INDEX FROM hotel_rent_orders WHERE Key_name = 'hotel_rent_orders_operation_key_unique'")[0]->Non_unique);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $rentId=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'hotel_room_id'=>1,'payment_status'=>'PAID','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'HOTEL-RETRY','currency_type_id'=>'USD','initial_balance'=>'0']);
        $payload=['operation_key'=>(string)\Illuminate\Support\Str::uuid(), 'products'=>[[
            'id'=>null,'item_id'=>$input['items'][0]['item_id'],'total'=>'4000','payment_status'=>'PAID',
            'rent_payment'=>['payment_method_type_id'=>'13','payment_destination_id'=>$bank->id, 'payment'=>'4000',
                'currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'400'],
        ]]];
        $controller=new \Modules\Hotel\Http\Controllers\HotelRentController();
        $request=\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', $payload);
        self::assertSame(200,$controller->addProductsToRoom($request,$rentId)->getStatusCode());
        self::assertSame(200,$controller->addProductsToRoom(\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', $payload),$rentId)->getStatusCode());
        self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $payload['products'][0]['rent_payment']['original_amount']='11';
        try {
            $controller->addProductsToRoom(\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', $payload),$rentId);
            self::fail('No debe aceptarse la misma clave con datos distintos.');
        } catch (\Illuminate\Validation\ValidationException $expected) {
            self::assertArrayHasKey('operation_key',$expected->errors());
        }
        self::assertSame(1,$db->table('global_payments')->count());
        $payload['products'][0]['rent_payment']['original_amount']='10';
        $payload['operation_key']=(string)\Illuminate\Support\Str::uuid();
        $processes=[];
        $db->beginTransaction();
        try {
            $db->table('hotel_rents')->where('id',$rentId)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$rentId,'operation'=>'hotel-retry','data'=>$payload],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+10;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cliente concurrente no llegó al bloqueo: '.$process->getErrorOutput());
                    usleep(10000);
                }
            }
            $db->commit();
            foreach ($processes as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                self::assertTrue($response['success']);
            }
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(2,$db->table('hotel_rent_item_payments')->count());
        self::assertSame(2,$db->table('global_payments')->count());
    }

    public function test_hotel_rolls_back_the_new_note_when_a_receipt_cannot_be_linked(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $rentId=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'hotel_room_id'=>1, 'payment_status'=>'PAID', 'output_date'=>'2026-10-27', 'output_time'=>'12:00', 'establishment_id'=>$input['establishment_id']]);
        $seriesId=$db->table('series')->where('document_type_id','80')->value('id');
        $noteInput=array_replace($input,['series_id'=>$seriesId,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $noteController=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)
            ->disableOriginalConstructor()->onlyMethods(['storeWithData'])->getMock();
        $noteController->method('storeWithData')->willReturnCallback(function ($data) {
            $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
            $data['id']=null;
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($data));
            return ['success'=>true,'data'=>['id'=>$note->id]];
        });
        $app->instance(\App\Http\Controllers\Tenant\SaleNoteController::class,$noteController);
        $request=\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', [
            'sale_note'=>$noteInput,
            'products'=>[['id'=>null,'item_id'=>$input['items'][0]['item_id'],'total'=>'232','payment_status'=>'PAID',
                'rent_payment'=>['payment_method_type_id'=>'01','payment_destination_id'=>'cash','payment'=>'232']]],
        ]);
        try {
            (new \Modules\Hotel\Http\Controllers\HotelRentController())->addProductsToRoom($request,$rentId);
            self::fail('Debe rechazarse un cobro que no existe en la nota.');
        } catch (\Illuminate\Validation\ValidationException $expected) {
            self::assertArrayHasKey('payment',$expected->errors());
        }
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('hotel_rent_orders')->count());
        self::assertSame(0,$db->table('hotel_rent_items')->count());
    }

    public function test_hotel_reuses_its_sale_note_receipt_without_creating_another_movement(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'HOTEL-SOURCE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10.00000000';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $row=['payment'=>'50','original_amount'=>'5','currency_type_id'=>'USD','exchange_rate'=>'10.00000000',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'date_of_payment'=>'2026-10-26','reference'=>'H-1'];
        $controller->savePayments($note,[$row]);
        $source=$note->payments()->firstOrFail();
        $rentId=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'hotel_room_id'=>1,'payment_status'=>'PAID','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
        $orderId=$db->table('hotel_rent_orders')->insertGetId(['hotel_rent_id'=>$rentId,'order_number'=>1,'order_status'=>'PAID',
            'sale_note_id'=>$note->id,'establishment_id'=>$input['establishment_id']]);
        $itemId=$db->table('hotel_rent_items')->insertGetId(['type'=>'PRO','hotel_rent_id'=>$rentId,'hotel_rent_order_id'=>$orderId,
            'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'50']),'payment_status'=>'PAID']);
        $item=\Modules\Hotel\Models\HotelRentItem::findOrFail($itemId);
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES']);
        $db->transaction(fn()=>(new \Modules\Hotel\Http\Controllers\HotelRentController())->saveHotelRentItemPayment($row,$item));
        $hotelPayment=$item->payments()->firstOrFail();
        self::assertSame((int)$source->id,(int)$hotelPayment->source_sale_note_payment_id);
        self::assertSame('USD',$hotelPayment->currency_type_id);
        self::assertSame('5',$hotelPayment->original_amount);
        self::assertSame(1,$db->table('global_payments')->count());
        $otherId=$db->table('hotel_rent_items')->insertGetId(['type'=>'PRO','hotel_rent_id'=>$rentId,'hotel_rent_order_id'=>$orderId,
            'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'50']),'payment_status'=>'PAID']);
        try {
            $db->transaction(fn()=>(new \Modules\Hotel\Http\Controllers\HotelRentController())->saveHotelRentItemPayment($row,\Modules\Hotel\Models\HotelRentItem::findOrFail($otherId)));
            self::fail('No se puede reutilizar el mismo cobro dos veces.');
        } catch (\Illuminate\Validation\ValidationException $expected) {
            self::assertArrayHasKey('payment',$expected->errors());
        }
        self::assertSame(1,$db->table('hotel_rent_item_payments')->count());
        try { $source->delete(); self::fail('No se puede borrar un cobro vinculado al alojamiento.'); }
        catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('payment',$expected->errors()); }
    }

    public function test_hotel_receipt_preserves_received_currency_and_checks_paid_total(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $rentId=$db->table('hotel_rents')->insertGetId([
            'customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']), 'hotel_room_id'=>1,
            'payment_status'=>'PAID', 'output_date'=>'2026-10-27', 'output_time'=>'12:00', 'establishment_id'=>$input['establishment_id'],
        ]);
        $itemId=$db->table('hotel_rent_items')->insertGetId([
            'type'=>'PRO', 'hotel_rent_id'=>$rentId, 'item_id'=>$input['items'][0]['item_id'],
            'item'=>json_encode(['total'=>'4000']), 'payment_status'=>'PAID',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'HOTEL-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $item=\Modules\Hotel\Models\HotelRentItem::findOrFail($itemId);
        $row=['payment'=>'4000','original_amount'=>'10','currency_type_id'=>'USD','exchange_rate'=>'400.00000000',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'reference'=>null];
        $db->transaction(fn()=>(new \Modules\Hotel\Http\Controllers\HotelRentController())->saveHotelRentItemPayment($row,$item));
        $payment=$item->payments()->firstOrFail();
        self::assertSame('4000',$payment->payment);
        self::assertSame('10',$payment->original_amount);
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('10',Movement::row($payment->global_payment)['amount']);
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $accountController=new \App\Http\Controllers\Tenant\BankAccountController();
        $accountEdit=\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', ['id'=>$bank->id,'currency_type_id'=>'VES']);
        try {
            $accountController->store($accountEdit);
            self::fail('Una cuenta con cobros no puede cambiar de moneda.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame('USD',$bank->fresh()->currency_type_id);
        self::assertSame('USD',Movement::row($payment->global_payment)['currency_type_id']);
        $inactive=BankAccount::create(['bank_id'=>$bank->bank_id,'description'=>'Sin movimientos','number'=>'HOTEL-UNUSED','currency_type_id'=>'USD','initial_balance'=>'0']);
        $unusedEdit=\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', ['id'=>$inactive->id,'currency_type_id'=>'VES']);
        self::assertTrue($accountController->store($unusedEdit)['success']);
        self::assertSame('VES',$inactive->fresh()->currency_type_id);
        $db->table('transfer_account_payments')->insert(['origin_id'=>$bank->id,'origin_type'=>BankAccount::class,
            'destiny_id'=>$inactive->id,'destiny_type'=>BankAccount::class,'currency_type_id'=>null,'amount'=>'1',
            'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]);
        try {
            $accountController->store(\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', ['id'=>$inactive->id,'currency_type_id'=>'USD']));
            self::fail('Un traslado anterior sin snapshot también protege la moneda.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertTrue($accountController->store(\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', [
            'id'=>$bank->id,'currency_type_id'=>'USD','description'=>'Cuenta actualizada']))['success']);
        self::assertSame('Cuenta actualizada',$bank->fresh()->description);
        $opening=BankAccount::create(['bank_id'=>$bank->bank_id,'description'=>'Saldo inicial','number'=>'QT-OPENING','currency_type_id'=>'USD','initial_balance'=>'0.01']);
        try {
            $accountController->store(\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', ['id'=>$opening->id,'currency_type_id'=>'VES']));
            self::fail('Una cuenta con saldo inicial no puede cambiar de moneda.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame('USD',$opening->fresh()->currency_type_id);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $balance=(new \Modules\Finance\Http\Controllers\BalanceController())->getBalanceBySingleBankAcount($bank->fresh());
        self::assertSame('10',$balance['balance']);
        self::assertSame('10',$balance['credits']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    }

    public function test_quotation_receipt_can_be_applied_to_an_invoice_without_a_second_movement(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'QT-REUSE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
            'quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
        ]);
        $controller=new \Modules\Sale\Http\Controllers\QuotationPaymentController();
        self::assertTrue($controller->store($request)['success']);
        $source=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        $frozen=$source->payment_method_snapshot;
        $row=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(collect([$source]))->first();
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES','description'=>'Cambio posterior','is_active'=>0]);
        $methodRules=(new \App\Http\Requests\Tenant\DocumentRequest())->rules()['payments.*.payment_method_type_id'];
        validator(['payments'=>[$row]],['payments.*.payment_method_type_id'=>$methodRules])->validate();
        $input['quotation_id']=$quotationId;
        $input['exchange_rate_sale']='10';
        $input['payments']=[$row];
        $input['payments'][0]['exchange_rate_source']='dato alterado';
        $input['payments'][0]['exchange_rate_date']='2026-01-01';
        $fresh=$input;
        unset($fresh['payments'][0]['id'], $fresh['payments'][0]['quotation_id']);
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($fresh); self::fail('No se puede omitir el cobro de origen.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        unset($fresh['quotation_id']);
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($fresh); self::fail('Un cobro nuevo no puede usar un método inactivo.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }
        $tampered=$input;
        $tampered['payments'][0]['original_amount']='6';
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($tampered); self::fail('No se puede alterar el importe del cobro original.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $applied=$document->payments()->firstOrFail();
        self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);
        self::assertSame((string)$source->id,$applied->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame($frozen['description'],$applied->payment_method_snapshot['description']);
        self::assertSame('USD',$applied->currency_type_id);
        self::assertSame('5',$applied->original_amount);
        self::assertSame('50',$applied->payment);
        self::assertSame($source->exchange_rate_source,$applied->exchange_rate_source);
        self::assertSame($source->exchange_rate_date->format('Y-m-d'),$applied->exchange_rate_date->format('Y-m-d'));
        self::assertSame(1,$db->table('global_payments')->count());
        $report=\App\Services\Payments\DocumentPaymentReport::row($applied);
        self::assertSame('0',$report['payment']);
        self::assertSame('50',$report['applied_payment']);
        self::assertSame($source->global_payment->report_context['destination_label'],$report['destination_description']);
        try { $controller->destroy($source->id); self::fail('El cobro aplicado no se puede eliminar.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }
        self::assertSame(1,$db->table('quotation_payments')->where('id',$source->id)->count());
        $input['external_id']=(string)\Illuminate\Support\Str::uuid();
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input); self::fail('No se puede aplicar dos veces el mismo cobro.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($applied,'Reversión de prueba');
        $replacement=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame((string)$source->id,$replacement->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_quotation_receipt_is_reused_through_sale_note_and_invoice(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>1,'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'iva_rate_type_id'=>'IGTF']);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'QT-CHAIN','currency_type_id'=>'USD','initial_balance'=>'0']);
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
            'quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'10',
            'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
        ]);
        $quotationController=new \Modules\Sale\Http\Controllers\QuotationPaymentController();
        $quotationController->store($request);
        $source=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('IGTF', $source->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000', $source->payment_method_snapshot['iva_rate_percentage']);
        $row=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(collect([$source]))->first();
        self::assertSame('10',$row['payment_method_type_id']);
        $db->table('quotations')->where('id',$quotationId)->update(['payment_method_type_id'=>'09']);
        $quotationResource=new \App\Http\Resources\Tenant\QuotationResource(\App\Models\Tenant\Quotation::findOrFail($quotationId));
        $quotationData=$quotationResource->toArray(\Illuminate\Http\Request::create('/quotations/record2/'.$quotationId));
        self::assertSame('02',$quotationData['quotation']['payment_condition_id']);
        self::assertSame('10',$quotationData['quotation']['payments'][0]['payment_method_type_id']);
        self::assertSame('USD',$quotationData['quotation']['payments'][0]['currency_type_id']);
        $storeData=(new \Modules\Store\Http\Controllers\StoreController())->getRecord('quotations',$quotationId);
        self::assertTrue($storeData['success']);
        self::assertSame('10',$storeData['data']['payments'][0]['payment_method_type_id']);
        self::assertSame('USD',$storeData['data']['payments'][0]['currency_type_id']);
        self::assertSame('02',$storeData['data']['payment_condition_id']);
        self::assertSame([],$storeData['data']['data_payments_fee']);
        $db->table('quotations')->where('id',$quotationId)->update([
            'payment_method_type_id'=>'10', 'prepayments'=>json_encode([['reference'=>'anticipo-padre']]),
            'total_prepayment'=>'5',
        ]);
        $originalSnapshot=$source->fresh()->payment_method_snapshot;
        $copied=(new \App\Http\Controllers\Tenant\QuotationController())->duplicate(\Illuminate\Http\Request::create('/', 'POST',['id'=>$quotationId]));
        $copy=\App\Models\Tenant\Quotation::findOrFail($copied['data']['id']);
        self::assertSame('10',$copy->payment_method_type_id);
        self::assertSame(0,$copy->payments()->count());
        self::assertEmpty((array)$copy->prepayments);
        self::assertTrue($copy->exactValue('total_prepayment')->isZero());
        self::assertSame($originalSnapshot,$source->fresh()->payment_method_snapshot);
        self::assertSame(1,\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('quotations')->where('id',$quotationId)->update(['payment_method_type_id'=>'09']);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $input['exchange_rate_sale']='10';
        $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'description'=>'Cambio posterior','iva_rate_type_id'=>null]);
        $saleInput=array_replace($input,['id'=>null,'quotation_id'=>$quotationId,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[$row]]);
        $missingReceipt=$saleInput;
        $missingReceipt['payments']=[];
        $rejected=$controller->storeWithData($missingReceipt);
        self::assertFalse($rejected['success'],'La conversión no debe omitir el cobro de la cotización.');
        self::assertStringContainsString('cobro',mb_strtolower($rejected['message']));
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('sale_note_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $apiRequest=\Illuminate\Http\Request::create('/api/sale-notes','POST',[
            'quotation_id'=>$quotationId,'customer_id'=>$input['customer_id'],'establishment_id'=>$input['establishment_id'],
            'currency_type_id'=>'VES','payments'=>[],
        ]);
        try { (new \App\Http\Controllers\Tenant\Api\SaleNoteController())->store($apiRequest); self::fail('La API no debe omitir el cobro de la cotización.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $invoiceWithoutReceipt=array_replace($input,['quotation_id'=>$quotationId,'payments'=>[]]);
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($invoiceWithoutReceipt);
            self::fail('La factura no debe omitir el cobro de la cotización.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        $result=$controller->storeWithData($saleInput);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota desde la cotización.');
        $note=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id']);
        $applied=$note->payments()->firstOrFail();
        self::assertSame(\Modules\Sale\Models\QuotationPayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);
        self::assertSame('IGTF',$applied->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000',$applied->payment_method_snapshot['iva_rate_percentage']);
        self::assertSame('10',$applied->payment_method_type_id);
        self::assertSame('USD',$applied->currency_type_id);
        self::assertSame('5',$applied->original_amount);
        self::assertSame('50',$applied->payment);
        self::assertNull($applied->global_payment);
        self::assertSame((string)$bank->id,$applied->payment_method_snapshot['receipt_origin']['destination_id']);
        $noteRows=$note->getTransformPayments()->all();
        self::assertSame((string)$bank->id,(string)$noteRows[0]['payment_destination_id']);
        self::assertSame([],$db->transaction(fn()=>\App\Services\Payments\ExistingReceipts::synchronize($note,$noteRows,'VES')));
        self::assertSame(1,$db->table('global_payments')->count());
        $repeated=$saleInput;
        $repeated['external_id']=(string)\Illuminate\Support\Str::uuid();
        $duplicate=$controller->storeWithData($repeated);
        self::assertFalse($duplicate['success']);
        self::assertStringContainsString('cobro',mb_strtolower($duplicate['message']));
        self::assertSame(1,$db->table('sale_notes')->where('quotation_id',$quotationId)->count());
        self::assertSame(1,$db->table('sale_note_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        try {
            $db->transaction(function () use ($applied,$bank,$input) {
                $applied->global_payment()->create(['destination_type'=>BankAccount::class,'destination_id'=>$bank->id,'user_id'=>$input['user_id'],'fiscal_environment'=>'demo']);
            });
            self::fail('Una aplicación no puede generar un segundo movimiento.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
        self::assertSame(1,$db->table('global_payments')->count());
        try { $quotationController->destroy($source->id); self::fail('La aplicación impide eliminar el cobro original.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_quotation_payment_id',$error->errors()); }
        try { $applied->payment='10'; $applied->save(); self::fail('La aplicación no puede cambiarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
        $input['quotation_id']=$quotationId;
        $input['sale_note_id']=$note->id;
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $bank->update(['status'=>0]);
        $currentDestinations=(new class { use \Modules\Finance\Traits\FinanceTrait; })->getPaymentDestinations();
        self::assertNotContains($bank->id,$currentDestinations->pluck('id')->all());
        $displayRows=$note->getTransformPayments()->all();
        self::assertSame('USD · USD',$displayRows[0]['payment_destination_description']);
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$applied->fresh()]))->first()];
        self::assertSame((string)$bank->id,(string)$input['payments'][0]['payment_destination_id']);
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $invoicePayment=$document->payments()->firstOrFail();
        self::assertSame(\App\Models\Tenant\SaleNotePayment::class,$invoicePayment->payment_method_snapshot['receipt_origin']['type']);
        self::assertSame($source->payment_method_snapshot['description'],$invoicePayment->payment_method_snapshot['description']);
        self::assertSame('IGTF',$invoicePayment->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000',$invoicePayment->payment_method_snapshot['iva_rate_percentage']);
        self::assertSame('10',$invoicePayment->payment_method_type_id);
        self::assertSame('5',$invoicePayment->original_amount);
        self::assertSame('50',$invoicePayment->payment);
        self::assertSame(1,$db->table('global_payments')->count());
        try { $db->transaction(fn()=>$applied->fresh()->delete()); self::fail('Una aplicación enlazada a factura no puede borrarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment',$error->errors()); }
        self::assertSame(1,$db->table('sale_note_payments')->count());

        // La aplicación histórica no cobra IGTF otra vez. Un cobro nuevo y sujeto,
        // posterior a la conversión, sí genera su propio cargo idempotente.
        self::assertSame(0, \App\Models\Tenant\DocumentTax::where('document_payment_id', $invoicePayment->id)->count());
        $company = \App\Models\Tenant\Company::firstOrFail();
        $company->forceFill(['igtf_enabled'=>true, 'igtf_rate'=>3])->save();
        \Tests\Support\SpeProfiles::versions($db, $company->id, $input['customer_id']);
        $db->table('payment_method_types')->where('id','10')->update([
            'collection_kind'=>'cash', 'currency_type_id'=>'USD', 'cash_currency_type_id'=>'USD', 'is_cash'=>1, 'is_active'=>1,
        ]);
        $operationKey = (string) \Illuminate\Support\Str::uuid();
        $newReceipt = [
            'date_of_payment'=>'2026-10-26', 'payment_method_type_id'=>'10', 'payment_destination_id'=>'cash',
            'currency_type_id'=>'USD', 'original_amount'=>'5', 'exchange_rate'=>'10',
            'igtf_status'=>'subject', 'financial_intermediation'=>'none', 'operation_key'=>$operationKey,
        ];
        $fiscal = \App\Services\Fiscal\FiscalDocumentPersistence::class;
        $subjectPayment = $fiscal::payment($document, $newReceipt);
        self::assertSame('50', $subjectPayment->payment);
        self::assertSame('0.15', $subjectPayment->tax_amount);
        $tax = \App\Models\Tenant\DocumentTax::where('document_payment_id', $subjectPayment->id)->firstOrFail();
        $taxNote = \App\Models\Tenant\Document::findOrFail($tax->document_id);
        self::assertSame('IGTF', $taxNote->note->note_debit_type_id);
        self::assertEquals(1.5, $taxNote->total);
        self::assertEquals(0, $taxNote->payments()->firstOrFail()->cash_received_amount);
        self::assertSame($subjectPayment->id, $fiscal::payment($document, $newReceipt)->id);
        self::assertSame(1, \App\Models\Tenant\DocumentTax::where('document_payment_id', $subjectPayment->id)->count());
        self::assertSame(2, $db->table('global_payments')->count());
        self::assertSame('5.15', Movement::row($subjectPayment->global_payment)['amount']);
        self::assertNull($taxNote->payments()->firstOrFail()->global_payment);
        $fiscal::reverse($subjectPayment, 'Reversión de prueba');
        self::assertSame('11', $taxNote->fresh()->state_type_id);
        self::assertNotNull($subjectPayment->fresh()->reversed_at);
        self::assertSame(2, $db->table('global_payments')->count());
        self::assertNull(Movement::row($subjectPayment->fresh()->global_payment));
        self::assertSame('5', Movement::row($source->fresh()->global_payment)['amount']);
        self::assertSame('USD', $source->fresh()->currency_type_id);
        self::assertSame('account', $source->fresh()->payment_method_snapshot['collection_kind']);
    }

    public function test_quotation_conversion_requires_all_mixed_receipts(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bankId=$db->table('banks')->value('id');
        $usd=BankAccount::create(['bank_id'=>$bankId,'description'=>'USD','number'=>'QT-MIX-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $ves=BankAccount::create(['bank_id'=>$bankId,'description'=>'VES','number'=>'QT-MIX-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $payments=new \Modules\Sale\Http\Controllers\QuotationPaymentController();
        foreach ([['13',$usd->id,'USD','5','50'],['04',$ves->id,'VES','20','20']] as [$method,$destination,$currency,$received,$applied]) {
            $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
                'quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>$method,
                'payment_destination_id'=>$destination,'currency_type_id'=>$currency,'original_amount'=>$received,
                'exchange_rate'=>'10','payment'=>$applied,
            ]);
            self::assertTrue($payments->store($request)['success']);
        }
        $rows=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(
            \Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->orderBy('id')->get())->all();
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $notes=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $data=array_replace($input,['id'=>null,'quotation_id'=>$quotationId,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV',
            'exchange_rate_sale'=>'10','payments'=>[$rows[0]]]);
        $rejected=$notes->storeWithData($data);
        self::assertFalse($rejected['success']);
        self::assertStringContainsString('cobro',mb_strtolower($rejected['message']));
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(2,$db->table('global_payments')->count());
        $invoice=array_replace($input,['quotation_id'=>$quotationId,'payments'=>[$rows[0]]]);
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($invoice); self::fail('Falta el segundo abono.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        $data['payments']=$rows;
        $saved=$notes->storeWithData($data);
        self::assertTrue($saved['success'],$saved['message'] ?? 'No se pudo aplicar los abonos.');
        self::assertSame(2,$db->table('sale_note_payments')->count());
        self::assertSame(2,$db->table('global_payments')->count());
    }

    public function test_quotation_conversion_rechecks_receipts_after_a_concurrent_payment(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo','state_type_id'=>'05','prefix'=>'COT','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'QT-STALE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
            'quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50',
        ]);
        $worker=null;
        $db->beginTransaction();
        try {
            $db->table('quotations')->where('id',$quotationId)->lockForUpdate()->first();
            (new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store($request);
            $worker=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
            $worker->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'quotation-conversion-preflight','data'=>['quotation_id'=>$quotationId]],JSON_THROW_ON_ERROR));
            $worker->setTimeout(30);$worker->start();
            $deadline=microtime(true)+10;
            while (!str_contains($worker->getOutput(),"READY\n")) {
                if (!$worker->isRunning() || microtime(true)>$deadline) self::fail('No inició la conversión concurrente: '.$worker->getErrorOutput());
                usleep(10000);
            }
            self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$worker->getErrorOutput(),$match));
            $control=$this->capsule->getConnection('control');
            do {
                $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                $sql=strtolower($query->INFO ?? '');
                if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'quotations') && str_contains($sql,'for update')) break;
                if (!$worker->isRunning() || microtime(true)>$deadline) self::fail('La conversión no alcanzó el bloqueo: '.$worker->getErrorOutput().$worker->getOutput());
                usleep(10000);
            } while (true);
            $db->commit();
            self::assertSame(0,$worker->wait(),$worker->getErrorOutput());
            $result=json_decode(substr($worker->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            self::assertContains('payments',$result['rejected'] ?? []);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            if ($worker && $worker->isRunning()) $worker->stop();
        }
        self::assertSame(1,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('documents')->count());
    }

    public function test_simultaneous_quotation_receipt_applications_allow_only_one_sale_note(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $common=['user_id'=>$input['user_id'],'establishment_id'=>$input['establishment_id'],
            'establishment'=>json_encode($input['establishment']),'fiscal_environment'=>'demo','state_type_id'=>'05',
            'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','customer_id'=>$input['customer_id'],
            'customer'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232'];
        $quotationId=$db->table('quotations')->insertGetId($common+['external_id'=>(string)\Illuminate\Support\Str::uuid(),'prefix'=>'COT']);
        $noteIds=[];
        foreach ([1,2] as $number) $noteIds[]=$db->table('sale_notes')->insertGetId($common+[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'prefix'=>'NV','document_type_id'=>'80',
            'number'=>$number,'quotation_id'=>$quotationId]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'QT-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
            'quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
        ]);
        (new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store($request);
        $source=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        $row=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(collect([$source]))->first();
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('quotations')->where('id',$quotationId)->lockForUpdate()->first();
            foreach ($noteIds as $noteId) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$noteId,'operation'=>'quotation-apply-note','data'=>$row],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició la aplicación concurrente: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,'quotations') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No alcanzó el bloqueo de la cotización: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            $accepted=$rejected=0;
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                if (!empty($response['success'])) $accepted++;
                elseif (in_array('source_quotation_payment_id',$response['rejected'] ?? [],true)) $rejected++;
            }
            self::assertSame(1,$accepted);
            self::assertSame(1,$rejected);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('sale_note_payments')->whereIn('sale_note_id',$noteIds)->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_sale_note_receipt_deletion_and_invoice_reuse_are_serialized(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'SN-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[['payment'=>'50','original_amount'=>'5','currency_type_id'=>'USD','exchange_rate'=>'10',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'date_of_payment'=>'2026-10-26']]);
        $source=$note->payments()->firstOrFail();
        $input['sale_note_id']=$note->id;
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$source]))->first()];
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $missing=$input;
        $missing['payments']=[];
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($missing); self::fail('La factura no debe omitir el cobro de la nota.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(1,$db->table('global_payments')->count());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('sale_notes')->where('id',$note->id)->lockForUpdate()->first();
            foreach ([['sale-note-delete',['payment_id'=>$source->id]], ['sale-note-reuse-invoice',$input]] as [$operation,$payload]) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$note->id,'operation'=>$operation,'data'=>$payload],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició la operación concurrente: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null) === 'Execute' && ((str_contains($sql,'sale_notes') && str_contains($sql,'for update'))
                        || (str_contains($sql,'insert into `documents`')))) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No alcanzó el bloqueo de la nota: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            $accepted=$rejected=0;
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                if (!empty($response['success'])) $accepted++;
                elseif (array_intersect(['payment','source_sale_note_payment_id'],$response['rejected'] ?? [])) $rejected++;
            }
            self::assertSame(1,$accepted);
            self::assertSame(1,$rejected);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('global_payments')->count());
        $documents=$db->table('documents')->count();
        self::assertTrue(($documents === 1 && $db->table('sale_note_payments')->where('id',$source->id)->exists())
            || ($documents === 0 && !$db->table('sale_note_payments')->where('id',$source->id)->exists()));
    }

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function test_multiple_sale_notes_preserve_receipts_and_reject_omissions(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'VES múltiple','number'=>'MULTI-NOTE-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10';
        $noteIds=[];
        foreach ([['10','cash','USD','5','50'],['04',$bank->id,'VES','20','20']] as [$method,$destination,$currency,$original,$amount]) {
            $data=array_replace($input,['id'=>null,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
            $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($data));
            foreach ($input['items'] as $line) $note->items()->create($line);
            $controller->savePayments($note,[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>$method,
                'payment_destination_id'=>$destination,'currency_type_id'=>$currency,'original_amount'=>$original,
                'exchange_rate'=>'10','payment'=>$amount]]);
            $noteIds[]=$note->id;
        }
        $preview=\App\Support\Exact\SaleNoteConversionLines::preview($noteIds);
        self::assertCount(2,$preview['payments']);
        self::assertSame(['USD','VES'],array_column($preview['payments'],'currency_type_id'));
        self::assertSame(['10','04'],array_column($preview['payments'],'payment_method_type_id'));
        $input['sale_notes_relateds']=array_map(fn($id)=>['id'=>$id],$noteIds);
        $input['payments']=[$preview['payments'][0]];
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input); self::fail('La factura debe conservar los cobros de ambas notas.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(2,$db->table('global_payments')->count());
        $input['payments']=$preview['payments'];
        $updatedNote=\App\Models\Tenant\SaleNote::findOrFail($noteIds[0]);
        $controller->savePayments($updatedNote,[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'10',
            'payment_destination_id'=>'cash','currency_type_id'=>'USD','original_amount'=>'1','exchange_rate'=>'10','payment'=>'10']]);
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input); self::fail('La selección desactualizada debe incluir el nuevo abono.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(3,$db->table('global_payments')->count());
        $input['payments']=\App\Support\Exact\SaleNoteConversionLines::preview($noteIds)['payments'];
        foreach ([['currency_type_id','USD','VES'],['exchange_rate_sale','11','10'],['state_type_id','11','01'],['state_type_id','13','01']] as [$field,$changed,$original]) {
            $db->table('sale_notes')->where('id',$noteIds[1])->update([$field=>$changed]);
            try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input); self::fail('La conversión debe revalidar la nota actual.'); }
            catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_notes_relateds',$error->errors()); }
            self::assertSame(0,$db->table('documents')->count());
            $db->table('sale_notes')->where('id',$noteIds[1])->update([$field=>$original]);
        }
        $tampered=$input;
        $tampered['payments'][0]['original_amount']='999';
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($tampered); self::fail('Un cobro alterado debe revertir los vínculos.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_sale_note_payment_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('sale_notes')->whereIn('id',$noteIds)->whereNotNull('document_id')->count());
        $electronicExcess=$input;
        $electronicExcess['payments'][]=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'04',
            'payment_destination_id'=>$bank->id,'currency_type_id'=>'VES','original_amount'=>'200','exchange_rate'=>'10','payment'=>'200'];
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($electronicExcess); self::fail('El exceso bancario no puede usar el efectivo de una nota anterior como vuelto.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(3,$db->table('global_payments')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $applied=$document->payments()->orderBy('id')->get();
        self::assertCount(3,$applied);
        self::assertSame(['USD','VES','USD'],$applied->pluck('currency_type_id')->all());
        self::assertSame(['5','20','1'],$applied->map(fn($row)=>\App\Support\Exact\Decimal::represent($row->original_amount))->all());
        self::assertSame(3,$db->table('global_payments')->count());
        self::assertSame([$document->id,$document->id],$db->table('sale_notes')->whereIn('id',$noteIds)->orderBy('id')->pluck('document_id')->all());
        try { $controller->deleteRelationInvoice(\Illuminate\Http\Request::create('/', 'POST', ['id'=>$noteIds[0]])); self::fail('No se debe desvincular una factura sin revertirla.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_note_id',$error->errors()); }
        self::assertSame($document->id,$db->table('sale_notes')->where('id',$noteIds[0])->value('document_id'));
        self::assertCount(2,json_decode($db->table('documents')->where('id',$document->id)->value('sale_notes_relateds'),true));
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input); self::fail('Las notas ya vinculadas no se deben facturar dos veces.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('sale_notes_relateds',$error->errors()); }
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame(3,$db->table('global_payments')->count());
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($applied[0],'Reversión de prueba');
        $resolved=$db->transaction(fn()=>\App\Services\Payments\ConvertedReceipt::resolve($document,$input['payments'][0]));
        self::assertSame((string)$input['payments'][0]['id'],$resolved['id']);
        self::assertNotNull($applied[0]->fresh()->reversed_at);
        self::assertSame($document->id,$db->table('sale_notes')->where('id',$noteIds[0])->value('document_id'));
        self::assertSame(3,$db->table('global_payments')->count());
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function test_concurrent_invoices_cannot_reuse_an_unpaid_sale_note(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $data=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($data));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $input['sale_note_id']=$note->id;
        $input['payments']=[];
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('sale_notes')->where('id',$note->id)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $payload=$input;
                $payload['external_id']=(string)\Illuminate\Support\Str::uuid();
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$note->id,'operation'=>'sale-note-reuse-invoice','data'=>$payload],JSON_THROW_ON_ERROR));
                $process->setTimeout(45);$process->start();$workers[]=$process;
            }
            $deadline=microtime(true)+15;$control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició la conversión concurrente. '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null)==='Execute' && str_contains($sql,'sale_notes') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No alcanzó el bloqueo de la nota. '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            $accepted=$rejected=0;
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                if (!empty($response['success'])) $accepted++;
                elseif (in_array('sale_notes_relateds',$response['rejected'] ?? [],true)) $rejected++;
            }
            self::assertSame(1,$accepted);
            self::assertSame(1,$rejected);
            self::assertSame(1,$db->table('documents')->count());
            self::assertSame($db->table('documents')->value('id'),$db->table('sale_notes')->where('id',$note->id)->value('document_id'));
            self::assertSame(0,$db->table('global_payments')->count());
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    public function test_technical_receipts_apply_to_invoice_and_through_sale_note_without_duplicate_income(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $common=['user_id'=>$input['user_id'],'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo','currency_type_id'=>'VES',
            'exchange_rate_sale'=>'10','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'cellphone'=>'','description'=>'Servicio','state'=>'','reason'=>'','total'=>'232','cost'=>'0'];
        $serviceIds=[];
        foreach (['DIRECT','CHAIN'] as $serial) $serviceIds[]=$db->table('technical_services')->insertGetId($common+['serial_number'=>$serial]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'TS-REUSE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $paymentController=new \Modules\Sale\Http\Controllers\TechnicalServicePaymentController();
        $sources=[]; $rows=[];
        foreach ($serviceIds as $serviceId) {
            $request=\Modules\Sale\Http\Requests\TechnicalServicePaymentRequest::create('/', 'POST', [
                'technical_service_id'=>$serviceId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
                'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
            ]);
            self::assertTrue($paymentController->store($request)['success']);
            $source=\Modules\Sale\Models\TechnicalServicePayment::where('technical_service_id',$serviceId)->firstOrFail();
            $service=\Modules\Sale\Models\TechnicalService::findOrFail($serviceId);
            $data=$service->getCollectionData();
            $row=$data['payments']->first();
            self::assertSame('2026-10-26',$row['date_of_payment']);
            self::assertSame('USD',$row['currency_type_id']);
            self::assertSame((string)$bank->id,(string)$row['payment_destination_id']);
            self::assertArrayNotHasKey('global_payment',$row);
            $sources[]=$source; $rows[]=$row;
        }
        $frozen=$sources[0]->payment_method_snapshot;
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES','description'=>'Cambio posterior','is_active'=>0]);
        $input['exchange_rate_sale']='10';
        $input['technical_service_id']=$serviceIds[0];
        $input['payments']=[$rows[0]];
        $tampered=$input; $tampered['payments'][0]['payment_destination_id']='cash';
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($tampered); self::fail('El destino debe ser el del cobro original.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $applied=$document->payments()->firstOrFail();
        self::assertSame(\Modules\Sale\Models\TechnicalServicePayment::class,$applied->payment_method_snapshot['receipt_origin']['type']);
        self::assertSame($frozen['description'],$applied->payment_method_snapshot['description']);
        self::assertSame('USD',$applied->currency_type_id);
        self::assertSame('5',$applied->original_amount);
        self::assertSame('50',$applied->payment);
        self::assertNull($applied->global_payment);
        self::assertSame(2,$db->table('global_payments')->count());
        try { $paymentController->destroy($sources[0]->id); self::fail('El origen aplicado no puede eliminarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }
        $duplicate=$input; $duplicate['external_id']=(string)\Illuminate\Support\Str::uuid();
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($duplicate); self::fail('Un cobro no se puede aplicar dos veces.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }
        self::assertSame(1,$db->table('documents')->count());
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $saleInput=array_replace($input,['id'=>null,'technical_service_id'=>$serviceIds[1],'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[$rows[1]]]);
        $result=$controller->storeWithData($saleInput);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota del servicio.');
        $note=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id']);
        $notePayment=$note->payments()->firstOrFail();
        self::assertSame(\Modules\Sale\Models\TechnicalServicePayment::class,$notePayment->payment_method_snapshot['receipt_origin']['type']);
        self::assertSame((string)$bank->id,(string)$notePayment->payment_method_snapshot['receipt_origin']['destination_id']);
        self::assertSame('5',$notePayment->original_amount);
        self::assertNull($notePayment->global_payment);
        try { $paymentController->destroy($sources[1]->id); self::fail('El origen aplicado a una nota no puede eliminarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_technical_service_payment_id',$error->errors()); }
        $invoiceInput=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(), 'technical_service_id'=>null, 'sale_note_id'=>$note->id]);
        $invoiceInput['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$notePayment]))->first()];
        $invoice=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($invoiceInput)->getDocument();
        self::assertSame((string)$notePayment->id,$invoice->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame('5',$invoice->payments()->firstOrFail()->original_amount);
        self::assertSame(2,$db->table('global_payments')->count());
    }

    public function test_simultaneous_technical_receipt_applications_allow_only_one_sale_note(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $common=['user_id'=>$input['user_id'],'establishment_id'=>$input['establishment_id'],
            'fiscal_environment'=>'demo','date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'],'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES','exchange_rate_sale'=>'10','total'=>'232'];
        $serviceId=$db->table('technical_services')->insertGetId($common+['cellphone'=>'','description'=>'Servicio',
            'state'=>'','reason'=>'','serial_number'=>'TS-RACE','cost'=>'0']);
        $noteIds=[];
        foreach ([1,2] as $number) $noteIds[]=$db->table('sale_notes')->insertGetId($common+[
            'establishment'=>json_encode($input['establishment']),'state_type_id'=>'01',
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),'prefix'=>'NV','document_type_id'=>'80',
            'number'=>$number,'technical_service_id'=>$serviceId]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'TS-RACE','currency_type_id'=>'USD','initial_balance'=>'0']);
        $request=\Modules\Sale\Http\Requests\TechnicalServicePaymentRequest::create('/', 'POST', [
            'technical_service_id'=>$serviceId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10',
        ]);
        (new \Modules\Sale\Http\Controllers\TechnicalServicePaymentController())->store($request);
        $row=\Modules\Sale\Models\TechnicalService::findOrFail($serviceId)->getCollectionData()['payments']->first();
        $workers=[];
        $db->beginTransaction();
        try {
            $db->table('technical_services')->where('id',$serviceId)->lockForUpdate()->first();
            foreach ($noteIds as $noteId) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$noteId,'operation'=>'technical-apply-note','data'=>$row],JSON_THROW_ON_ERROR));
                $process->setTimeout(30); $process->start(); $workers[]=$process;
            }
            $deadline=microtime(true)+10; $control=$this->capsule->getConnection('control');
            foreach ($workers as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No se inició la aplicación concurrente: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertSame(1,preg_match('/LOCK_TEST_CONNECTION=(\d+)/',$process->getErrorOutput(),$match));
                do {
                    $query=$control->selectOne('SELECT COMMAND, INFO FROM information_schema.processlist WHERE ID = ?',[(int)$match[1]]);
                    $sql=strtolower($query->INFO ?? '');
                    if (($query->COMMAND ?? null) === 'Execute' && str_contains($sql,'technical_services') && str_contains($sql,'for update')) break;
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('No alcanzó el bloqueo del servicio: '.$process->getErrorOutput().$process->getOutput());
                    usleep(10000);
                } while (true);
            }
            $db->commit();
            $accepted=$rejected=0;
            foreach ($workers as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $response=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
                if (!empty($response['success'])) $accepted++;
                elseif (in_array('source_technical_service_payment_id',$response['rejected'] ?? [],true)) $rejected++;
            }
            self::assertSame(1,$accepted);
            self::assertSame(1,$rejected);
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame(1,$db->table('sale_note_payments')->whereIn('sale_note_id',$noteIds)->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_order_draft_payment_creates_money_only_when_generating_the_invoice(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'ORDER-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $wrong=BankAccount::create(['bank_id'=>$bank->bank_id,'description'=>'VES','number'=>'ORDER-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $draft=['id'=>null,'document_id'=>null,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id,'payment'=>'50','currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10'];
        $orderInput=array_replace($input,['prefix'=>'PED','exchange_rate_sale'=>'10','prepayments'=>[$draft]]);
        $controller=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $request=\Modules\Order\Http\Requests\OrderNoteRequest::create('/', 'POST', $orderInput);
        $result=$controller->store($request);
        self::assertTrue($result['success']);
        $order=\Modules\Order\Models\OrderNote::findOrFail($result['data']['id']);
        self::assertSame('USD',data_get($order->prepayments,'0.currency_type_id'));
        self::assertSame(0,$db->table('global_payments')->count());
        $invoiceInput=$input;
        $invoiceInput['exchange_rate_sale']='10';
        $invoiceInput['order_note_id']=$order->id;
        $invoiceInput['payments']=[$draft];
        $bad=$invoiceInput; $bad['payments'][0]['payment_destination_id']=$wrong->id;
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($bad); self::fail('La cuenta debe coincidir con la moneda recibida.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($invoiceInput)->getDocument();
        $receipt=$document->payments()->firstOrFail();
        self::assertSame('USD',$receipt->currency_type_id);
        self::assertSame('5',$receipt->original_amount);
        self::assertSame('50',$receipt->payment);
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame('5',Movement::row($receipt->global_payment)['amount']);
    }

    public function test_order_with_price_entered_without_iva(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $input['prefix']='PED';
        $input['prepayments']=[];
        $input['items'][0]=array_replace($input['items'][0],['quantity'=>1,'unit_price'=>'116',
            'price_input'=>['amount'=>'100','currency_type_id'=>'VES','includes_iva'=>false],
            'unit_value'=>'100','total_value'=>'100',
            'total_base_igv'=>'100','total_igv'=>'16',
            'total_taxes'=>'16','total'=>'116']);
        $controller=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $result=$controller->store(\Modules\Order\Http\Requests\OrderNoteRequest::create('/', 'POST', $input));
        self::assertTrue($result['success']);
        $order=\Modules\Order\Models\OrderNote::findOrFail($result['data']['id']);
        self::assertSame('116',\App\Support\Exact\Decimal::represent($order->exactValue('total')));
        $line=$order->items()->firstOrFail();
        self::assertSame('100',data_get($line->calculation_snapshot,'inputs.price_input.amount'));
        self::assertFalse(data_get($line->calculation_snapshot,'inputs.price_input.includes_iva'));
        $input['id']=$order->id;
        $input['items'][0]['id']=$line->id;
        $controller->update(\Modules\Order\Http\Requests\OrderNoteRequest::create('/', 'POST', $input));
        $line=$line->fresh();
        self::assertSame('100',data_get($line->calculation_snapshot,'inputs.price_input.amount'));
        self::assertFalse(data_get($line->calculation_snapshot,'inputs.price_input.includes_iva'));
        self::assertSame('116',\App\Support\Exact\Decimal::represent($order->fresh()->exactValue('total')));
    }

    public function test_technical_document_failure_rolls_back_the_temporary_service_item(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $app->instance('log', new class { public function error($message): void {} });
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $input['document_type_id']='nv';
        $input['series']='NV01';
        $input['prefix']='NV';
        $input['items'][0]['item_id']=null;
        $input['items'][0]['internal_id']='TECH-ROLLBACK-UNIQUE';
        $input['items'][0]['item_type_id']='02';
        $input['items'][0]['description']='Servicio temporal';
        $input['items'][0]['unit_type_id']='SERV';
        $input['payments']=[['id'=>123,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
            'payment_destination_id'=>'cash','payment'=>'50']];
        $response=(new \Modules\Sale\Http\Controllers\GenerateDocumentController())->store(\Illuminate\Http\Request::create('/generate-document','POST',$input));
        self::assertFalse($response['success']);
        self::assertStringContainsString('No se puede copiar un cobro registrado',$response['message']);
        self::assertSame(0,$db->table('items')->where('internal_id','TECH-ROLLBACK-UNIQUE')->count());
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_payment_destination_catalog_loads_bank_names_in_one_query(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $bankId=$db->table('banks')->value('id');
        BankAccount::create(['bank_id'=>$bankId,'description'=>'Cuenta VES','number'=>'BATCH-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $catalog=new class { use \Modules\Finance\Traits\FinanceTrait; };
        $db->enableQueryLog();
        $db->flushQueryLog();
        $first=$catalog->getPaymentDestinations();
        $firstQueries=count(array_filter($db->getQueryLog(),fn($q)=>str_contains(strtolower($q['query']),'from `banks`')));
        BankAccount::create(['bank_id'=>$bankId,'description'=>'Cuenta USD','number'=>'BATCH-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $db->flushQueryLog();
        $both=$catalog->getPaymentDestinations();
        $bothQueries=count(array_filter($db->getQueryLog(),fn($q)=>str_contains(strtolower($q['query']),'from `banks`')));
        $db->disableQueryLog();
        self::assertCount(1,$first);
        self::assertCount(2,$both);
        self::assertSame(['VES','USD'],$both->pluck('currency_type_id')->all());
        self::assertSame(1,$firstQueries);
        self::assertSame($firstQueries,$bothQueries,'El catálogo no debe consultar un banco por cada cuenta.');
        self::assertStringContainsString('Cuenta USD',$both->last()['description']);
        BankAccount::create(['bank_id'=>$bankId,'description'=>'Inactiva','number'=>'BATCH-OFF','currency_type_id'=>'USD','initial_balance'=>'0','status'=>0]);
        self::assertCount(2,$catalog->getPaymentDestinations());
    }

    public function test_receipt_dialog_catalogs_exclude_credit_and_unclassified_methods(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','is_active'=>1,'is_credit'=>1]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','is_active'=>1,'is_credit'=>1]);
        $db->table('payment_method_types')->where('id','05')->update(['collection_kind'=>'credit','is_active'=>1,'is_credit'=>0]);
        $db->table('payment_method_types')->where('id','12')->update(['collection_kind'=>'unclassified','is_active'=>1]);
        $db->table('payment_method_types')->insert(['id'=>'06','description'=>'Personalizado','hka_code'=>'99',
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_active'=>1]);
        foreach ([new \Modules\Sale\Http\Controllers\TechnicalServicePaymentController(),
            new \Modules\Sale\Http\Controllers\QuotationPaymentController(),
            new \App\Http\Controllers\Tenant\SaleNotePaymentController(),
            new \App\Http\Controllers\Tenant\DocumentPaymentController(),
            new \Modules\Finance\Http\Controllers\IncomeController(),
            new \Modules\Sale\Http\Controllers\GenerateDocumentController(),
            new \Modules\Purchase\Http\Controllers\PurchasePaymentController()] as $controller) {
            $methods=$controller->tables()['payment_method_types'];
            self::assertContains('01',$methods->pluck('id')->all());
            self::assertContains('04',$methods->pluck('id')->all());
            self::assertContains('06',$methods->pluck('id')->all());
            self::assertNotContains('05',$methods->pluck('id')->all());
            self::assertNotContains('12',$methods->pluck('id')->all());
            self::assertEmpty($methods->filter(fn($method)=>!in_array($method->collection_kind,['cash','account'],true)));
        }
    }

    public function test_technical_service_without_cash_and_repeated_edits_do_not_duplicate_cash_link(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $controller=$this->getMockBuilder(\Modules\Sale\Http\Controllers\TechnicalServiceController::class)->onlyMethods(['createPdf'])->getMock();
        $data=['customer_id'=>$input['customer_id'],'establishment_id'=>$input['establishment_id'],
            'cellphone'=>'04121234567','description'=>'Servicio de prueba','state'=>'registrado','reason'=>'prueba',
            'serial_number'=>'TEST-TECH','cost'=>'100','prepayment'=>'0','date_of_issue'=>'2026-10-26',
            'time_of_issue'=>'09:00:00','currency_type_id'=>'VES','exchange_rate_sale'=>'10','items'=>[]];
        $request=\Modules\Sale\Http\Requests\TechnicalServiceRequest::create('/', 'POST', $data);
        self::assertTrue($controller->store($request)['success']);
        $id=$db->table('technical_services')->value('id');
        self::assertSame(0,$db->table('cash_documents')->where('technical_service_id',$id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26',
            'time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $data['id']=$id;
        foreach ([1,2] as $_) {
            $edit=\Modules\Sale\Http\Requests\TechnicalServiceRequest::create('/', 'POST', $data);
            self::assertTrue($controller->store($edit)['success']);
        }
        self::assertSame(1,$db->table('cash_documents')->where('cash_id',$cash->id)->where('technical_service_id',$id)->count());
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_sale_note_without_open_cash_explains_rejection_and_rolls_back(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $input=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        try {
            $db->transaction(fn()=>\App\Models\Tenant\SaleNote::create((new \App\Http\Controllers\Tenant\SaleNoteController())->mergeData($input)));
            self::fail('Una nota de venta debe exigir turno de caja.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertStringContainsString('Abra una caja',$error->errors()['cash'][0]);
        }
        self::assertSame(0,$db->table('sale_notes')->count());
        self::assertSame(0,$db->table('cash_documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_quotation_receipt_keeps_received_dollars_and_applied_bolivars(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'], 'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'], 'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo', 'state_type_id'=>'05', 'prefix'=>'COT',
            'date_of_issue'=>'2026-10-26', 'time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES', 'exchange_rate_sale'=>'400', 'total'=>'4000',
        ]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'QT-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $inactive=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Inactiva','number'=>'QT-INACTIVE','currency_type_id'=>'USD','initial_balance'=>'0','status'=>0]);
        $destinations=(new class { use \Modules\Finance\Traits\FinanceTrait; })->getPaymentDestinations();
        self::assertContains($bank->id,$destinations->pluck('id')->all());
        self::assertNotContains($inactive->id,$destinations->pluck('id')->all());
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', [
            'quotation_id'=>$quotationId, 'date_of_payment'=>'2026-10-26', 'payment_method_type_id'=>'13',
            'payment_destination_id'=>$bank->id, 'payment'=>'4000', 'currency_type_id'=>'USD',
            'original_amount'=>'10', 'exchange_rate'=>'400.00000000',
        ]);
        self::assertTrue((new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store($request)['success']);
        $payment=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('4000',$payment->payment);
        self::assertSame('10',$payment->original_amount);
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('10',Movement::row($payment->global_payment)['amount']);
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $movement=(new \Modules\Finance\Http\Resources\MovementCollection(collect([$payment->global_payment])))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]))->first();
        self::assertSame('USD',$movement['currency_type_id']);
        self::assertSame('10.00',$movement['input']);
        self::assertSame('10.00',$movement['balance']);
        $mobile=(new \Modules\MobileApp\Http\Resources\Api\FinanceMovementCollection(collect([$payment->global_payment])))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'))->first();
        self::assertSame('USD',$mobile['currency_type_id']);
        self::assertSame('10',$mobile['amount_decimal']);
        self::assertSame('4000',$mobile['amount_pen_decimal']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $snapshot=$payment->payment_method_snapshot;
        $rows=\App\Http\Resources\Tenant\QuotationResource::getTransformPayments(collect([$payment]))->all();
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES']);
        $edit=\App\Http\Requests\Tenant\QuotationRequest::create('/', 'POST', [
            'id'=>$quotationId, 'customer_id'=>$input['customer_id'], 'currency_type_id'=>'VES',
            'items'=>[], 'payments'=>$rows,
        ]);
        $controller=new \App\Http\Controllers\Tenant\QuotationController();
        self::assertTrue($controller->update($edit)['success']);
        self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame('USD',$payment->fresh()->currency_type_id);
        $changed=$rows;
        $changed[0]['original_amount']='20';
        $edit->merge(['payments'=>$changed]);
        try {
            $controller->update($edit);
            self::fail('Un cobro registrado no puede modificarse al editar la cotización.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('original_amount',$error->errors());
        }
        self::assertSame('10',$payment->fresh()->original_amount);
        self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);
        self::assertSame(1,$db->table('global_payments')->count());
        $finance=new class { use \Modules\Finance\Traits\FinanceTrait; };
        $balance=$finance->getBalanceBySingleBankAcount($bank->fresh());
        self::assertSame('10',$balance['quotation_payment']);
        self::assertSame('10',$balance['balance']);
        $edit->merge(['payments'=>[]]);
        self::assertTrue($controller->update($edit)['success']);
        self::assertSame(0,$db->table('quotation_payments')->where('quotation_id',$quotationId)->count());
        $context=json_decode($db->table('global_payments')->value('report_context'),true,512,JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('reversal',$context);
        self::assertSame('0',$finance->getBalanceBySingleBankAcount($bank->fresh())['balance']);
    }

    public function test_quotation_reclassified_bank_method_checks_currency_and_keeps_snapshot(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id', '01')->update([
            'collection_kind'=>'account', 'currency_type_id'=>'USD', 'cash_currency_type_id'=>null,
            'is_cash'=>0, 'is_credit'=>0, 'is_active'=>1,
        ]);
        $quotationId=$db->table('quotations')->insertGetId([
            'user_id'=>$input['user_id'], 'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'], 'establishment'=>json_encode($input['establishment']),
            'fiscal_environment'=>'demo', 'state_type_id'=>'05', 'prefix'=>'COT',
            'date_of_issue'=>'2026-10-26', 'time_of_issue'=>'09:00:00',
            'customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'currency_type_id'=>'VES', 'exchange_rate_sale'=>'400', 'total'=>'4000',
        ]);
        $usd=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CLASS-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $ves=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'VES','number'=>'CLASS-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $data=['quotation_id'=>$quotationId,'date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
            'currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'400','payment'=>'4000'];
        $controller=new \Modules\Sale\Http\Controllers\QuotationPaymentController();
        foreach ([$ves->id, 'cash'] as $invalid) {
            try {
                $controller->store(\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $data+['payment_destination_id'=>$invalid]));
                self::fail('El destino incompatible debe rechazarse.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertNotEmpty($error->errors());
            }
            self::assertSame(0,$db->table('quotation_payments')->count());
            self::assertSame(0,$db->table('global_payments')->count());
        }
        self::assertTrue($controller->store(\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $data+['payment_destination_id'=>$usd->id]))['success']);
        $payment=\Modules\Sale\Models\QuotationPayment::where('quotation_id',$quotationId)->firstOrFail();
        self::assertSame('10',$payment->original_amount);
        self::assertSame('4000',$payment->payment);
        self::assertSame('USD',$payment->currency_type_id);
        self::assertSame('account',Movement::row($payment->global_payment)['method']['collection_kind']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['income']);
        $snapshot=$payment->payment_method_snapshot;
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'USD','is_cash'=>1]);
        self::assertSame($snapshot,$payment->fresh()->payment_method_snapshot);
        self::assertSame('account',Movement::row($payment->global_payment->fresh())['method']['collection_kind']);
        self::assertSame('10',Movement::row($payment->global_payment->fresh())['amount']);
    }

    public function test_whatsapp_confirmed_usd_price_keeps_rate_and_receipt_in_ves(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(true);
        $db->table('configurations')->update(['auto_send_hka'=>false, 'auto_send_pdf_email'=>false]);
        $itemId=$input['items'][0]['item_id'];
        $db->table('items')->where('id',$itemId)->update(['currency_type_id'=>'USD','sale_unit_price'=>'3','has_igv'=>1]);
        $date=now('America/Caracas')->toDateString();
        $db->table('exchange_rates')->where('date',$date)->delete();
        $tool=new \Modules\WhatsAppBot\Services\Tools\CreateDocumentTool();
        $arguments=['document_type'=>'factura','customer_id'=>$input['customer_id'],'items'=>[['item_id'=>$itemId,'quantity'=>'0.1']]];
        $db->table('exchange_rates')->delete();
        self::assertSame('error',$tool->execute($arguments)['status']);
        self::assertSame(0,$db->table('documents')->count());
        $db->table('exchange_rates')->insert(['date'=>$date,'date_original'=>$date,'sale'=>'10','purchase'=>'10','sale_original'=>'10','purchase_original'=>'10']);
        $result=$tool->execute($arguments);
        self::assertSame('ready_for_confirmation',$result['status'],json_encode($result));
        self::assertStringContainsString('10.00000000', $result['summary_text']);
        self::assertStringContainsString('Total: Bs. 3.00', $result['summary_text']);
        $draft=$result['draft'];
        $db->table('items')->where('id',$itemId)->update(['sale_unit_price'=>'999']);
        $db->table('exchange_rates')->where('date',$date)->update(['sale'=>'20']);
        $user=\App\Models\Tenant\User::findOrFail($input['user_id']);
        $mapper=new \Modules\WhatsAppBot\Services\Facturalo\FacturaloDraftMapper();
        $payload=$mapper->map($draft,$user);
        self::assertSame('10.00000000',$payload['exchange_rate_sale']);
        self::assertSame('3.00',$payload['payments'][0]['payment']);
        self::assertSame('3', \App\Support\Exact\Decimal::represent($payload['items'][0]['item']['sale_unit_price']));
        self::assertSame('demo',$payload['fiscal_environment']);
        $altered=$draft;$altered['total']='4.00';
        try { $mapper->map($altered,$user); self::fail('Debe rechazar un total que no coincide con el borrador.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('total',$error->errors()); }
        $oldDraft=$draft;unset($oldDraft['pricing_version']);
        try { $mapper->map($oldDraft,$user); self::fail('Un borrador anterior debe confirmarse nuevamente.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->save($payload)->getDocument();
        self::assertSame('3',\App\Support\Exact\Decimal::represent($document->fresh()->exactValue('total')));
        $payment=$document->payments()->firstOrFail();
        self::assertSame('3',$payment->payment);
        self::assertSame('VES',$payment->currency_type_id);
        self::assertSame('cash',Movement::row($payment->global_payment)['method']['collection_kind']);
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('items')->where('id',$itemId)->update(['sale_unit_price'=>'3']);
        $db->table('exchange_rates')->where('date',$date)->update(['sale'=>'10']);
        $arguments['discount_amount']='1';
        $discounted=$tool->execute($arguments);
        self::assertSame('ready_for_confirmation',$discounted['status'],json_encode($discounted));
        self::assertStringContainsString('Total: Bs. 1.84',$discounted['summary_text']);
        $discountedPayload=$mapper->map($discounted['draft'],$user);
        $discountedDocument=(new \App\CoreFacturalo\Facturalo())->save($discountedPayload)->getDocument();
        self::assertSame('1.84',\App\Support\Exact\Decimal::represent($discountedDocument->fresh()->exactValue('total')));
        self::assertSame('1.84',$discountedDocument->payments()->firstOrFail()->payment);
        self::assertSame(2,$db->table('global_payments')->count());
    }

    public function test_whatsapp_orchestrator_persists_the_confirmed_draft_with_a_mock_sender(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(true);
        $logger=new class { public $error=null; public function __call($name,$args) { if ($name==='error') $this->error=$args[1]['exception'] ?? null; } };
        $app->instance('log',$logger); \Illuminate\Support\Facades\Facade::clearResolvedInstance('log');
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/bot-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('configurations')->update(['auto_send_hka'=>false, 'auto_send_pdf_email'=>false]);
        $itemId=$input['items'][0]['item_id'];
        $db->table('items')->where('id',$itemId)->update(['currency_type_id'=>'USD','sale_unit_price'=>'3','has_igv'=>1]);
        $date=now('America/Caracas')->toDateString();
        $db->table('exchange_rates')->where('date',$date)->delete();
        $db->table('exchange_rates')->insert(['date'=>$date,'date_original'=>$date,'sale'=>'10','purchase'=>'10','sale_original'=>'10','purchase_original'=>'10']);
        $draft=(new \Modules\WhatsAppBot\Services\Tools\CreateDocumentTool())->execute([
            'document_type'=>'factura','customer_id'=>$input['customer_id'],'items'=>[['item_id'=>$itemId,'quantity'=>'0.1']],
        ]);
        self::assertSame('ready_for_confirmation',$draft['status'],json_encode($draft));
        $sender=$this->getMockBuilder(\Modules\WhatsAppBot\Services\Evolution\EvolutionSender::class)->disableOriginalConstructor()->getMock();
        $sender->expects(self::never())->method('sendText');
        $attempt=0;
        $sender->expects(self::exactly(2))->method('sendDocumentPdf')->with('test-phone',self::anything(),self::anything(),self::stringContains('Bs. 3.00'),null)
            ->willReturnCallback(function () use (&$attempt) { if (++$attempt === 1) throw new \RuntimeException('Fallo simulado de entrega'); return ['ok'=>true]; });
        $sessions=$this->getMockBuilder(\Modules\WhatsAppBot\Services\Session\SessionMachine::class)->disableOriginalConstructor()->getMock();
        $intent=$this->getMockBuilder(\Modules\WhatsAppBot\Services\IntentClassifier::class)->disableOriginalConstructor()->getMock();
        $orchestrator=new \Modules\WhatsAppBot\Services\BotOrchestrator($sessions,$sender,$intent);
        $emit=new \ReflectionMethod($orchestrator,'emitDocument');$emit->setAccessible(true);
        $result=$emit->invoke($orchestrator,$draft['draft'],\App\Models\Tenant\User::findOrFail($input['user_id']),'test-phone',null);
        self::assertTrue($result['success'], $result['message'].' '.($logger->error ?? ''));
        self::assertStringContainsString('Total: Bs. 3.00',$result['message']);
        self::assertStringContainsString('no se pudo enviar el PDF',$result['message']);
        $originalPayment=\App\Models\Tenant\Document::firstOrFail()->payments()->firstOrFail();
        $originalContext=$originalPayment->global_payment->report_context;
        $db->table('payment_method_types')->where('collection_kind','cash')->where('currency_type_id','VES')
            ->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>0]);
        $retry=$emit->invoke($orchestrator,$draft['draft'],\App\Models\Tenant\User::findOrFail($input['user_id']),'test-phone',null);
        self::assertTrue($retry['success'],$retry['message']);
        self::assertSame($originalContext,$originalPayment->global_payment->fresh()->report_context);
        self::assertSame('cash',Movement::row($originalPayment->global_payment->fresh())['method']['collection_kind']);
        self::assertSame('VES',Movement::row($originalPayment->global_payment->fresh())['currency_type_id']);
        $newDraft=$draft['draft'];$newDraft['operation_key']=(string)\Illuminate\Support\Str::uuid();
        $noCash=$emit->invoke($orchestrator,$newDraft,\App\Models\Tenant\User::findOrFail($input['user_id']),'test-phone',null);
        self::assertFalse($noCash['success']);
        self::assertStringContainsString('efectivo activo en VES',$noCash['message']);
        $changed=$draft['draft'];$changed['observations']='Cambio posterior';
        $conflict=$emit->invoke($orchestrator,$changed,\App\Models\Tenant\User::findOrFail($input['user_id']),'test-phone',null);
        self::assertFalse($conflict['success']);
        self::assertStringContainsString('otros datos',$conflict['message']);
        self::assertSame(1,$db->table('documents')->count());
        $document=\App\Models\Tenant\Document::firstOrFail();
        self::assertSame('3',\App\Support\Exact\Decimal::represent($document->exactValue('total')));
        self::assertSame('3',$document->payments()->firstOrFail()->payment);
        self::assertSame(1,$db->table('global_payments')->count());
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($document->payments()->firstOrFail(),'Reversión de prueba');
        $reversed=$emit->invoke($orchestrator,$draft['draft'],\App\Models\Tenant\User::findOrFail($input['user_id']),'test-phone',null);
        self::assertFalse($reversed['success']);
        self::assertStringContainsString('revertidos',$reversed['message']);
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame(1,$db->table('document_payments')->count());
    }

    public function test_whatsapp_concurrent_confirmations_reuse_the_same_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(true);
        $db->table('configurations')->update(['auto_send_hka'=>false,'auto_send_pdf_email'=>false]);
        $itemId=$input['items'][0]['item_id'];
        $db->table('items')->where('id',$itemId)->update(['currency_type_id'=>'USD','sale_unit_price'=>'3','has_igv'=>1]);
        $date=now('America/Caracas')->toDateString();
        $db->table('exchange_rates')->where('date',$date)->delete();
        $db->table('exchange_rates')->insert(['date'=>$date,'date_original'=>$date,'sale'=>'10','purchase'=>'10','sale_original'=>'10','purchase_original'=>'10']);
        $prepared=(new \Modules\WhatsAppBot\Services\Tools\CreateDocumentTool())->execute([
            'document_type'=>'factura','customer_id'=>$input['customer_id'],'items'=>[['item_id'=>$itemId,'quantity'=>'0.1']],
        ]);
        self::assertSame('ready_for_confirmation',$prepared['status'],json_encode($prepared));
        $user=\App\Models\Tenant\User::findOrFail($input['user_id']);
        try {
            \Modules\WhatsAppBot\Services\Facturalo\DraftOperation::run($prepared['draft'],$user,function () use ($prepared,$user) {
                $payload=(new \Modules\WhatsAppBot\Services\Facturalo\FacturaloDraftMapper())->map($prepared['draft'],$user);
                (new \App\CoreFacturalo\Facturalo())->save($payload);
                throw new \RuntimeException('Fallo simulado antes de confirmar');
            });
            self::fail('Debe revertirse el guardado incompleto.');
        } catch (\RuntimeException $error) { self::assertSame('Fallo simulado antes de confirmar',$error->getMessage()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('document_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $processes=[];
        $db->beginTransaction();
        try {
            $db->table('companies')->where('id',1)->lockForUpdate()->first();
            for ($i=0;$i<2;$i++) {
                $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $process->setInput(json_encode(['connection'=>$db->getConfig(),'operation'=>'whatsapp-draft',
                    'data'=>['draft'=>$prepared['draft'],'user_id'=>$input['user_id']]],JSON_THROW_ON_ERROR));
                $process->setTimeout(60);$process->start();$processes[]=$process;
            }
            $deadline=microtime(true)+15;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(),"READY\n")) {
                    if (!$process->isRunning() || microtime(true)>$deadline) self::fail('El cliente concurrente no llegó al bloqueo: '.$process->getErrorOutput());
                    usleep(10000);
                }
                self::assertTrue($process->isRunning());
            }
            $db->commit();
            $ids=[];
            foreach ($processes as $process) {
                self::assertSame(0,$process->wait(),$process->getErrorOutput().$process->getOutput());
                $ids[]=json_decode(substr($process->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR)['id'];
            }
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
        self::assertSame($ids[0],$ids[1]);
        self::assertSame(1,$db->table('documents')->count());
        self::assertSame(1,$db->table('document_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_whatsapp_draft_requires_open_cash_and_uses_current_cash_classification(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $user=\App\Models\Tenant\User::findOrFail($input['user_id']);
        $draft=['document_type_id'=>'01','customer_id'=>$input['customer_id'],'pricing_version'=>1,'operation_key'=>(string)\Illuminate\Support\Str::uuid(),'exchange_rate_sale'=>'10',
            'total'=>'116.00','items'=>[['item_id'=>$input['items'][0]['item_id'],'quantity'=>'1','price'=>'116','price_currency'=>'VES','includes_iva'=>true]]];
        $mapper=new \Modules\WhatsAppBot\Services\Facturalo\FacturaloDraftMapper();
        try { $mapper->map($draft,$user); self::fail('La preparación debe exigir caja abierta.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_destination_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        $cash=Cash::create(['user_id'=>$user->id,'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $payload=$mapper->map($draft,$user);
        self::assertSame('04',$payload['payment_method_type_id']);
        self::assertSame('04',$payload['payments'][0]['payment_method_type_id']);
        self::assertSame('cash',$payload['payments'][0]['payment_destination_id']);
        self::assertSame('VES',$payload['currency_type_id']);
        $db->table('payment_method_types')->where('collection_kind','cash')->where('currency_type_id','VES')->update(['is_active'=>0]);
        try { $mapper->map($draft,$user); self::fail('La preparación debe exigir efectivo VES activo.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payment_method_type_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
    }

    public function test_store_import_preflights_all_payment_methods_before_creating_customers(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        if (!class_exists('Auth')) class_alias(\Illuminate\Support\Facades\Auth::class, 'Auth');
        $today=\Carbon\Carbon::now()->format('Y-m-d');
        $db->table('exchange_rates')->insert(['date'=>$today,'date_original'=>$today,'sale_original'=>'10','purchase_original'=>'10','sale'=>'10','purchase'=>'10']);
        $db->table('payment_method_types')->where('collection_kind','cash')->update(['is_active'=>0]);
        $db->table('payment_method_types')->where('id','11')->update(['is_active'=>1,'collection_kind'=>'account']);
        $method=$db->table('payment_method_types')->where('id','11')->value('description');
        $before=$db->table('persons')->count();
        $first=array_fill(0,23,null); $first[8]=$method; $first[9]='V11111111';
        $second=array_fill(0,23,null); $second[8]='Método inexistente'; $second[9]='V22222222';
        try {
            (new \Modules\Order\Imports\MiTiendaPeImport())->collection(collect([[], $first, $second]));
            self::fail('Debe rechazar el lote antes de crear clientes si falta efectivo compatible.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_method_type_id',$error->errors());
        }
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('order_notes')->count());
        $db->table('configuration_mi_tienda_pe')->update(['autogenerate'=>0]);
        $first[0]='Factura'; $first[1]='IMPORT-1'; $first[2]='Prueba'; $first[3]='Importacion';
        $first[4]=''; $first[5]=''; $first[6]=''; $first[7]=''; $first[9]='123456789';
        $first[10]=''; $first[11]='INEXISTENTE'; $first[12]=1; $first[21]='26/10/2026';
        $second=$first; $second[0]='Boleta'; $second[1]='IMPORT-2';
        try {
            (new \Modules\Order\Imports\MiTiendaPeImport())->collection(collect([[], $first, $second]));
            self::fail('Un error posterior debe revertir el cliente creado en la primera fila.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Boletas',$error->getMessage());
        }
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('order_notes')->count());
        $db->table('configuration_mi_tienda_pe')->insert(['establishment_id'=>$input['establishment_id'],'user_id'=>$input['user_id'],'series_order_note_id'=>null,'series_document_ft_id'=>null,'payment_destination_id'=>null,'currency_type_id'=>'VES','autogenerate'=>1]);
        $db->table('payment_method_types')->where('id','11')->update(['currency_type_id'=>'USD']);
        $second[8]=$method;
        $first[0]=$second[0]='Factura';
        try {
            (new \Modules\Order\Imports\MiTiendaPeImport())->collection(collect([[], $first, $second]));
            self::fail('La factura automática no puede inventar una conversión para un método USD.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('order_notes')->count());
        $db->table('payment_method_types')->where('id','11')->update(['currency_type_id'=>'VES']);
        $account=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Cuenta USD','number'=>'IMPORT-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $db->table('configuration_mi_tienda_pe')->update(['payment_destination_id'=>$account->id]);
        try {
            (new \Modules\Order\Imports\MiTiendaPeImport())->collection(collect([[], $first, $second]));
            self::fail('Una cuenta USD no puede recibir un cobro automático VES.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_destination_id',$error->errors());
        }
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('order_notes')->count());
    }

    public function test_storefront_quotation_builder_uses_venezuelan_tax_rate(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $id=$input['items'][0]['item_id'];
        $db->table('items')->where('id',$id)->update(['apply_store'=>1,'sale_unit_price'=>'116',
            'currency_type_id'=>'VES','sale_affectation_igv_type_id'=>'10']);
        $class=new \ReflectionClass(\Modules\Ecommerce\Http\Controllers\QuotationStorefrontController::class);
        $controller=$class->newInstanceWithoutConstructor();
        $builder=$class->getMethod('buildItemsAndTotals');
        $builder->setAccessible(true);
        $result=$builder->invoke($controller,[['item_id'=>$id,'quantity'=>'1']],1.0,true);
        self::assertCount(1,$result['items']);
        self::assertEquals(16,$result['items'][0]['percentage_igv']);
        self::assertEquals(100,$result['items'][0]['total_value']);
        self::assertEquals(16,$result['items'][0]['total_igv']);
        self::assertEquals(116,$result['totals']['total']);
        $hidden=$builder->invoke($controller,[['item_id'=>$id,'quantity'=>'1']],'1',false);
        self::assertEquals(0,$hidden['totals']['total']);
        self::assertEquals(116,$hidden['items'][0]['item']['suggested_unit_price']);
    }

    public function test_storefront_quotation_rejects_missing_cash_before_creating_guest(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configuration_ecommerce')->updateOrInsert(['id'=>1],['quotation_enabled'=>1,'information_contact_name'=>'Test',
            'information_contact_email'=>'test@example.test','information_contact_phone'=>'04121234567']);
        $db->table('payment_method_types')->where('collection_kind','cash')->where('currency_type_id','VES')->update(['is_active'=>0]);
        $app->instance('auth',new class {
            public function guard($name=null) { return $this; }
            public function user() { return null; }
        });
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class,new class {
            public function json($data=[], $status=200, $headers=[], $options=0) { return new \Illuminate\Http\JsonResponse($data,$status,$headers,$options); }
        });
        $class=new \ReflectionClass(\Modules\Ecommerce\Http\Controllers\QuotationStorefrontController::class);
        $controller=$class->newInstanceWithoutConstructor();
        $before=$db->table('persons')->count();
        $response=$controller->store(\Illuminate\Http\Request::create('/ecommerce/quotations','POST',[
            'contact_name'=>'Invitado de prueba','email'=>'guest@example.test','telephone'=>'04121234567',
            'items'=>[['item_id'=>$input['items'][0]['item_id'],'quantity'=>'1']],
        ]));
        self::assertSame(422,$response->getStatusCode());
        self::assertStringContainsString('efectivo',strtolower($response->getData(true)['message']));
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('quotations')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES',
            'cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $db->table('exchange_rates')->delete();
        $item=\App\Models\Tenant\Item::findOrFail($input['items'][0]['item_id']);
        $db->transaction(function () use ($item) {
            $item->apply_store=1;
            $item->currency_type_id='USD';
            $item->setExactValues(['sale_unit_price'=>\App\Support\Exact\Decimal::of(1)->dividedBy(3)]);
            $item->save();
        });
        $app->instance('log',new class extends \Psr\Log\NullLogger { public function channel($name=null) { return $this; } });
        if (!class_exists('Log')) class_alias(\Illuminate\Support\Facades\Log::class,'Log');
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $pdf=$this->getMockBuilder(\App\Http\Controllers\Tenant\QuotationController::class)->onlyMethods(['createPdf'])->getMock();
        $app->instance(\App\Http\Controllers\Tenant\QuotationController::class,$pdf);
        $missingRate=$controller->store(\Illuminate\Http\Request::create('/ecommerce/quotations','POST',[
            'contact_name'=>'Invitado de prueba','email'=>'guest@example.test','telephone'=>'04121234567',
            'items'=>[['item_id'=>$item->id,'quantity'=>'1']],
        ]));
        self::assertSame(422,$missingRate->getStatusCode(),$missingRate->getContent());
        self::assertStringContainsString('tasa',strtolower($missingRate->getData(true)['message']));
        self::assertSame($before,$db->table('persons')->count());
        self::assertSame(0,$db->table('quotations')->count());
        $today=\Carbon\Carbon::now()->format('Y-m-d');
        $db->table('exchange_rates')->insert(['date'=>$today,'date_original'=>$today,'sale'=>'10.12345678',
            'purchase'=>'10.12345678','sale_original'=>'10.12345678','purchase_original'=>'10.12345678']);
        $response=$controller->store(\Illuminate\Http\Request::create('/ecommerce/quotations','POST',[
            'contact_name'=>'Invitado de prueba','email'=>'guest@example.test','telephone'=>'04121234567',
            'items'=>[['item_id'=>$input['items'][0]['item_id'],'quantity'=>'1']],
        ]));
        self::assertSame(200,$response->getStatusCode(),$response->getContent());
        $quotation=\App\Models\Tenant\Quotation::findOrFail($response->getData(true)['quotation']['id']);
        self::assertSame('04',$quotation->payment_method_type_id);
        self::assertSame('VES',$quotation->currency_type_id);
        self::assertSame(1,$quotation->items()->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $line=$quotation->items()->firstOrFail();
        $expected=\App\Support\Exact\Decimal::of(1)->dividedBy(3)->multipliedBy('10.12345678');
        self::assertTrue($line->exactValue('unit_price')->isEqualTo($expected));
        self::assertTrue($quotation->exactValue('total')->isEqualTo($expected));
        self::assertTrue($quotation->exactValue('subtotal')->isEqualTo($expected->dividedBy('1.16')));
        self::assertEquals(['n'=>'1','d'=>'3'],data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.amount'));
        self::assertSame('10.12345678',data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.exchange_rate'));
        $update=$quotation->toArray();
        $update['items']=\App\Http\Resources\Tenant\QuotationResource::getTransformItems(collect([$line]),'VES')->toArray();
        $update['items'][0]['calculation_snapshot']['values']['unit_price']=['n'=>'999999','d'=>'1'];
        $update['payments']=[];
        $pdf->update(\App\Http\Requests\Tenant\QuotationRequest::create('/quotations','POST',$update));
        $line=$line->fresh();
        self::assertNotNull($line);
        self::assertTrue($line->exactValue('unit_price')->isEqualTo($expected));
        self::assertTrue($quotation->fresh()->exactValue('total')->isEqualTo($expected));
        self::assertEquals(['n'=>'1','d'=>'3'],data_get($line->calculation_snapshot,'inputs.storefront_catalog_price.amount'));
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('quotations')->where('id',$quotation->id)->update(['prepayments'=>json_encode([['reference'=>'padre']])]);
        $copied=$pdf->duplicate(\Illuminate\Http\Request::create('/quotations/duplicate','POST',['id'=>$quotation->id]));
        $copy=\App\Models\Tenant\Quotation::findOrFail($copied['data']['id']);
        self::assertTrue($copy->items()->firstOrFail()->exactValue('unit_price')->isEqualTo($expected));
        self::assertTrue($copy->exactValue('total')->isEqualTo($expected));
        self::assertEmpty((array)$copy->prepayments);
        self::assertSame(0,$copy->payments()->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $invalid=$update;
        $invalid['items'][0]['id']=$line->id+1000;
        try {
            $pdf->update(\App\Http\Requests\Tenant\QuotationRequest::create('/quotations','POST',$invalid));
            self::fail('No se puede asociar una partida ajena o inexistente.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('items',$error->errors()); }
        self::assertTrue($line->fresh()->exactValue('unit_price')->isEqualTo($expected));
        $changed=$update;
        $changed['items'][0]['unit_price']='4';
        $pdf->update(\App\Http\Requests\Tenant\QuotationRequest::create('/quotations','POST',$changed));
        self::assertSame('4',\App\Support\Exact\Decimal::represent($line->fresh()->exactValue('unit_price')));
        self::assertNull(data_get($line->fresh()->calculation_snapshot,'inputs.storefront_catalog_price'));
    }

    public function test_store_import_generates_credit_and_reclassified_bank_invoices(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        if (!class_exists('Auth')) class_alias(\Illuminate\Support\Facades\Auth::class, 'Auth');
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $today=\Carbon\Carbon::now()->format('Y-m-d');
        $db->table('exchange_rates')->insert(['date'=>$today,'date_original'=>$today,'sale_original'=>'10','purchase_original'=>'10','sale'=>'10','purchase'=>'10']);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0,'is_credit'=>0,'is_active'=>1]);
        $db->table('payment_method_types')->where('id','09')->update(['collection_kind'=>'credit','is_credit'=>1,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Cuenta VES','number'=>'IMPORT-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
        $series=$db->table('series')->where('document_type_id','01')->where('establishment_id',$input['establishment_id'])->value('id');
        $db->table('configuration_mi_tienda_pe')->insert(['establishment_id'=>$input['establishment_id'],'user_id'=>$input['user_id'],'series_order_note_id'=>null,'series_document_ft_id'=>$series,'payment_destination_id'=>$bank->id,'currency_type_id'=>'VES','autogenerate'=>1]);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $db->statement('CREATE TABLE plans (id INT PRIMARY KEY, name VARCHAR(100), limit_documents INT, include_sale_notes_limit_documents TINYINT)');
        $db->statement('CREATE TABLE clients (id INT PRIMARY KEY, hostname_id INT, name VARCHAR(100), plan_id INT, whatsapp_messages_limit_override INT NULL)');
        $db->table('plans')->insert(['id'=>1,'name'=>'Prueba','limit_documents'=>0,'include_sale_notes_limit_documents'=>0]);
        $db->table('clients')->insert(['id'=>1,'hostname_id'=>1,'name'=>'Prueba','plan_id'=>1]);
        $hostname=new \Hyn\Tenancy\Models\Hostname(); $hostname->id=1;
        $tenancy=$this->getMockBuilder(\Hyn\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['hostname'])->getMock();
        $tenancy->method('hostname')->willReturn($hostname);
        $app->instance(\Hyn\Tenancy\Environment::class,$tenancy);
        $order=$this->getMockBuilder(\Modules\Order\Http\Controllers\OrderNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $document=$this->getMockBuilder(\App\Http\Controllers\Tenant\DocumentController::class)->onlyMethods(['storeWithData'])->getMock();
        $document->method('storeWithData')->willReturnCallback(function ($data) {
            $saved=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($data)->getDocument();
            return ['success'=>true,'data'=>['id'=>$saved->id]];
        });
        $import=new class($order,$document) extends \Modules\Order\Imports\MiTiendaPeImport {
            private $orderController; private $documentController;
            public function __construct($order,$document) { $this->orderController=$order; $this->documentController=$document; }
            protected function makeOrderNoteController(): \Modules\Order\Http\Controllers\OrderNoteController { return $this->orderController; }
            protected function makeDocumentController(): \App\Http\Controllers\Tenant\DocumentController { return $this->documentController; }
        };
        $row=array_fill(0,23,null);
        $row[0]='Factura'; $row[1]='IMPORT-CREDIT'; $row[2]=''; $row[3]=''; $row[4]=''; $row[5]=''; $row[6]=''; $row[7]='';
        $row[8]=$db->table('payment_method_types')->where('id','09')->value('description');
        $row[9]=$input['customer']['number']; $row[10]=''; $row[11]='MOCK-ITEM-VES-001'; $row[12]=1;
        $row[14]=16; $row[15]=16; $row[16]=0; $row[17]=100; $row[18]=0; $row[19]=116; $row[21]='26/10/2026';
        $import->collection(collect([[], $row]));
        $credit=\App\Models\Tenant\Document::orderBy('id')->firstOrFail();
        self::assertSame('02',$credit->payment_condition_id);
        self::assertCount(0,$credit->payments);
        self::assertSame(0,$db->table('global_payments')->count());
        $row[1]='IMPORT-BANK'; $row[8]=$db->table('payment_method_types')->where('id','01')->value('description');
        $import->collection(collect([[], $row]));
        $bankDocument=\App\Models\Tenant\Document::orderByDesc('id')->firstOrFail();
        $payment=$bankDocument->payments()->firstOrFail();
        self::assertSame('01',$payment->payment_method_type_id);
        self::assertSame('VES',$payment->currency_type_id);
        self::assertSame('account',$payment->global_payment->report_context['method']['collection_kind']);
        self::assertSame((string)$bank->id,(string)$payment->global_payment->destination_id);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_purchase_import_uses_classified_cash_and_creates_one_movement(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(true);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        $db->table('persons')->where('id',$input['customer_id'])->update(['type'=>'suppliers']);
        $data=['supplier_ruc'=>$input['customer']['number'],'document_type_id'=>'01','establishment_id'=>$input['establishment_id'],
            'series'=>'CP01','number'=>1,'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','currency_type_id'=>'VES',
            'exchange_rate_sale'=>'400','total'=>'12','items'=>[],'payment_method_type_id'=>null];
        $request=\App\Http\Requests\Tenant\PurchaseImportRequest::create('/', 'POST', $data);
        $result=(new \App\Http\Controllers\Tenant\PurchaseController())->import($request);
        self::assertTrue($result['success'],$result['message'] ?? 'Importación fallida');
        $payment=\App\Models\Tenant\PurchasePayment::where('purchase_id',$result['data']['id'])->firstOrFail();
        self::assertSame('04',$payment->payment_method_type_id);
        self::assertSame('cash',Movement::row($payment->global_payment)['method']['collection_kind']);
        self::assertSame('egress',Movement::row($payment->global_payment)['direction']);
        self::assertSame('12',Movement::row($payment->global_payment)['amount']);
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('collection_kind','cash')->where('currency_type_id','VES')->update(['is_active'=>0]);
        $data['number']=2;$result=(new \App\Http\Controllers\Tenant\PurchaseController())->import(\App\Http\Requests\Tenant\PurchaseImportRequest::create('/', 'POST', $data));
        self::assertFalse($result['success']);self::assertSame(1,$db->table('purchases')->count());self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_purchase_update_preserves_recorded_payment_after_reclassification_mysql(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(true);
        $id=$db->table('purchases')->insertGetId([
            'user_id'=>$input['user_id'],'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'establishment_id'=>$input['establishment_id'],'fiscal_environment'=>'demo','state_type_id'=>'01',
            'group_id'=>'01','document_type_id'=>'01','series'=>'CP01','number'=>1,
            'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','supplier_id'=>$input['customer_id'],
            'supplier'=>json_encode($input['customer']),'currency_type_id'=>'VES','exchange_rate_sale'=>'400',
            'total'=>'12','filename'=>'purchase-test', 'payment_condition_id'=>'01',
        ]);
        $purchase=\App\Models\Tenant\Purchase::findOrFail($id);
        $payment=$db->transaction(function () use ($purchase) {
            $payment=$purchase->purchase_payments()->create(['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment'=>'12','reference'=>'R']);
            \App\Services\Payments\ReceiptDestination::attach($payment,['payment_destination_id'=>'cash']);
            return $payment;
        });
        $global=$payment->global_payment;
        $context=$global->report_context;
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>0]);
        $rows=\App\Http\Resources\Tenant\PurchaseResource::getTransformPayments(collect([$payment]))->all();
        $data=['id'=>$id,'supplier_id'=>$input['customer_id'],'document_type_id'=>'01','currency_type_id'=>'VES',
            'items'=>[],'fee'=>[],'payments'=>$rows];
        $controller=new class extends \App\Http\Controllers\Tenant\PurchaseController { public function createPdf($purchase=null,$format_pdf=null,$filename=null) {} };
        $request=\App\Http\Requests\Tenant\PurchaseRequest::create('/', 'POST', $data);
        self::assertTrue($controller->update($request)['success']);
        self::assertSame(1,$db->table('purchase_payments')->where('purchase_id',$id)->count());
        self::assertSame($context,$global->fresh()->report_context);
        self::assertSame('12',$payment->fresh()->payment);
        $data['payments'][0]['payment']='13';$request=\App\Http\Requests\Tenant\PurchaseRequest::create('/', 'POST', $data);
        try { $controller->update($request); self::fail('El pago registrado no debe modificarse.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('payments',$error->errors()); }
        self::assertSame('12',$payment->fresh()->payment);
        self::assertSame($context,$global->fresh()->report_context);
        $paymentController=new \Modules\Purchase\Http\Controllers\PurchasePaymentController();
        $db->beginTransaction();
        try {
            self::assertTrue($paymentController->destroy($payment->id)['success']);
            $db->rollBack();
        } finally { if ($db->transactionLevel()) $db->rollBack(); }
        self::assertNotNull($payment->fresh());
        self::assertArrayNotHasKey('reversal',$global->fresh()->report_context);
        $deleted=$paymentController->destroy($payment->id);
        self::assertTrue($deleted['success']);
        self::assertNull($payment->fresh());
        self::assertArrayHasKey('reversal',$global->fresh()->report_context);
        self::assertNull(Movement::row($global->fresh()));
    }

    public function test_quotation_resource_preserves_dollar_cash_method(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $router = new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($app), $app);
        $app->instance('url', new \Illuminate\Routing\UrlGenerator($router->getRoutes(), $app['request']));
        $id = $db->table('quotations')->insertGetId([
            'user_id' => $input['user_id'], 'external_id' => (string) \Illuminate\Support\Str::uuid(),
            'establishment_id' => $input['establishment_id'], 'establishment' => json_encode($input['establishment']),
            'fiscal_environment' => 'demo', 'state_type_id' => '05', 'prefix' => 'COT',
            'date_of_issue' => '2026-10-26', 'time_of_issue' => '09:00:00',
            'customer_id' => $input['customer_id'], 'customer' => json_encode($input['customer']),
            'currency_type_id' => 'VES', 'exchange_rate_sale' => '400', 'total' => '4000',
            'payment_method_type_id' => '10',
        ]);
        $resource = new \App\Http\Resources\Tenant\QuotationResource(\App\Models\Tenant\Quotation::findOrFail($id));
        $result = $resource->toArray(\Illuminate\Http\Request::create('/quotations/record/'.$id));
        self::assertSame('10', $result['quotation']['payment_method_type_id']);
        self::assertSame('01', $result['quotation']['payment_condition_id']);
        $db->table('payment_method_types')->where('id','10')->update(['collection_kind'=>'credit','is_credit'=>0]);
        $resource = new \App\Http\Resources\Tenant\QuotationResource(\App\Models\Tenant\Quotation::findOrFail($id));
        $result = $resource->toArray(\Illuminate\Http\Request::create('/quotations/record/'.$id));
        self::assertSame('02', $result['quotation']['payment_condition_id']);
    }

    public function test_global_payment_export_reports_failures_and_uses_distinct_files(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->getSchemaBuilder()->create('websites',function ($table) {
            $table->increments('id');
            $table->softDeletes();
        });
        $db->table('websites')->insert(['id'=>1]);
        $tray=\App\Models\Tenant\DownloadTray::create([
            'user_id'=>$input['user_id'],'module'=>'FINANCE','format'=>'xlsx','type'=>'Prueba',
        ]);
        $tenancy=$this->getMockBuilder(\Hyn\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['tenant'])->getMock();
        $tenancy->expects(self::exactly(6))->method('tenant')->willReturnArgument(0);
        $app->instance(\Hyn\Tenancy\Environment::class,$tenancy);
        \Illuminate\Support\Facades\Log::shouldReceive('info')->zeroOrMoreTimes();
        $params=['period'=>'month','date_start'=>null,'date_end'=>null,'month_start'=>'fecha-imposible',
            'month_end'=>'2026-10','payment_type'=>null,'destination_type'=>'','user_id'=>$input['user_id'],
            'establishment_id'=>$input['establishment_id']];
        $missingTenantJob=new \Modules\Finance\Jobs\ProcessGlobalPaymentReport($params,$tray->id,999999);
        try { $missingTenantJob->handle(); self::fail('Debe rechazar un tenant inexistente.'); }
        catch (\Throwable $e) { self::assertInstanceOf(\Illuminate\Database\Eloquent\ModelNotFoundException::class,$e); }
        $tray->refresh();
        self::assertSame('IN_PROCESS',$tray->status);
        self::assertNull($tray->date_end);
        $job=new \Modules\Finance\Jobs\ProcessGlobalPaymentReport($params,$tray->id,1);
        try { $job->handle(); self::fail('Debe propagar el error de generación.'); }
        catch (\Throwable $e) { self::assertInstanceOf(\Carbon\Exceptions\InvalidFormatException::class,$e); }
        $tray->refresh();
        self::assertSame('FAILED',$tray->status);
        self::assertNotNull($tray->date_end);
        self::assertNull($tray->file_name);
        $tray->update(['status'=>'IN_PROCESS','date_end'=>null]);
        $params['month_start']='2026-10';
        $job->params=$params;
        $exporter=$this->createMock(\Maatwebsite\Excel\Exporter::class);
        $exporter->expects(self::once())->method('store')->willReturn(false);
        $app->instance(\Maatwebsite\Excel\Exporter::class,$exporter);
        try { $job->handle(); self::fail('Debe propagar el fallo de almacenamiento.'); }
        catch (\Throwable $e) { self::assertSame('No se pudo guardar el reporte de pagos.',$e->getMessage()); }
        $tray->refresh();
        self::assertSame('FAILED',$tray->status);
        self::assertNull($tray->file_name);
        $tray->update(['status'=>'IN_PROCESS','date_end'=>null]);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/global-payment-job-views',
            'view.paths'=>[base_path('resources/views')], 'excel'=>require base_path('config/excel.php')]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $excel=new \Maatwebsite\Excel\Excel(new \Maatwebsite\Excel\Writer($temporaryFiles),
            $this->createMock(\Maatwebsite\Excel\QueuedWriter::class), $this->createMock(\Maatwebsite\Excel\Reader::class),
            new \Maatwebsite\Excel\Files\Filesystem($app['filesystem']));
        $app->instance(\Maatwebsite\Excel\Exporter::class,$excel);
        $job->handle();
        $tray->refresh();
        self::assertSame('FINISHED',$tray->status);
        $file=sys_get_temp_dir().'/'.$this->database.'/'.$tray->path.'/'.$tray->file_name.'.xlsx';
        self::assertFileExists($file);
        $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
        self::assertGreaterThan(0,$book->getSheetCount());
        $book->disconnectWorksheets();
        $other=\App\Models\Tenant\DownloadTray::create([
            'user_id'=>$input['user_id'],'module'=>'FINANCE','format'=>'xlsx','type'=>'Otra prueba',
        ]);
        (new \Modules\Finance\Jobs\ProcessGlobalPaymentReport($params,$other->id,1))->handle();
        $other->refresh();
        $otherFile=sys_get_temp_dir().'/'.$this->database.'/'.$other->path.'/'.$other->file_name.'.xlsx';
        self::assertSame('FINISHED',$other->status);
        self::assertNotSame($tray->file_name,$other->file_name);
        self::assertStringEndsWith('_'.$tray->id,$tray->file_name);
        self::assertStringEndsWith('_'.$other->id,$other->file_name);
        self::assertFileExists($otherFile);
        $pdfTray=\App\Models\Tenant\DownloadTray::create([
            'user_id'=>$input['user_id'],'module'=>'FINANCE','format'=>'pdf','type'=>'Prueba PDF',
        ]);
        $pdf=new class {
            public function setPaper($size,$orientation) { return $this; }
            public function output() { return '%PDF-archivo-de-prueba'; }
        };
        $pdfAdapter=new class($pdf) {
            private $pdf;
            public function __construct($pdf) { $this->pdf=$pdf; }
            public function loadView(...$args) { return $this->pdf; }
        };
        $app->instance('dompdf.wrapper',$pdfAdapter);
        \Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');
        $originalFilesystem=$app['filesystem'];
        $disk=$this->createMock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->expects(self::once())->method('put')->willReturn(false);
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->once()->with('tenant')->andReturn($disk);
        $pdfJob=new \Modules\Finance\Jobs\ProcessGlobalPaymentReport($params,$pdfTray->id,1);
        try {
            try { $pdfJob->handle(); self::fail('Debe propagar el fallo al guardar PDF.'); }
            catch (\Throwable $e) { self::assertSame('No se pudo guardar el reporte de pagos.',$e->getMessage()); }
        } finally {
            \Illuminate\Support\Facades\Storage::swap($originalFilesystem);
        }
        $pdfTray->refresh();
        self::assertSame('FAILED',$pdfTray->status);
        self::assertNull($pdfTray->file_name);
        $pdfTray->update(['status'=>'IN_PROCESS','date_end'=>null]);
        $pdfJob->handle();
        $pdfTray->refresh();
        self::assertSame('FINISHED',$pdfTray->status);
        $pdfFile=sys_get_temp_dir().'/'.$this->database.'/'.$pdfTray->path.'/'.$pdfTray->file_name.'.pdf';
        self::assertSame('%PDF-archivo-de-prueba',file_get_contents($pdfFile));
        self::assertStringEndsWith('_'.$pdfTray->id,$pdfTray->file_name);
        unlink($pdfFile);
        unlink($file);
        unlink($otherFile);
    }

    public function test_movement_export_records_failure_and_success(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->getSchemaBuilder()->create('websites',function ($table) {
            $table->increments('id');
            $table->softDeletes();
        });
        $db->table('websites')->insert(['id'=>1]);
        $tray=\App\Models\Tenant\DownloadTray::create([
            'user_id'=>$input['user_id'],'module'=>'FINANCE','format'=>'xlsx','type'=>'Prueba',
        ]);
        $tenancy=$this->getMockBuilder(\Hyn\Tenancy\Environment::class)->disableOriginalConstructor()->onlyMethods(['tenant'])->getMock();
        $tenancy->expects(self::exactly(4))->method('tenant')->willReturnArgument(0);
        $app->instance(\Hyn\Tenancy\Environment::class,$tenancy);
        \Illuminate\Support\Facades\Log::shouldReceive('debug')->zeroOrMoreTimes();
        $params=(object)['period'=>'month','date_start'=>null,'date_end'=>null,
            'month_start'=>'fecha-imposible','month_end'=>'2026-10','last_cash_opening'=>'false','user_id'=>$input['user_id']];
        $job=new \Modules\Finance\Jobs\ProcessMovementsReport($params,$tray->id,1);
        try {
            $job->handle();
            self::fail('El error de generación debe propagarse a la cola.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\Carbon\Exceptions\InvalidFormatException::class,$exception);
        }
        $tray->refresh();
        self::assertSame('FAILED',$tray->status);
        self::assertNotNull($tray->date_end);
        self::assertNull($tray->file_name);
        $tray->update(['status'=>'IN_PROCESS','date_end'=>null]);
        $params->month_start='2026-10';
        $params->establishment_id=$input['establishment_id'];
        $exporter=$this->createMock(\Maatwebsite\Excel\Exporter::class);
        $exporter->expects(self::once())->method('store')->willReturn(false);
        $app->instance(\Maatwebsite\Excel\Exporter::class,$exporter);
        try {
            $job->handle();
            self::fail('El error de almacenamiento debe propagarse a la cola.');
        } catch (\Throwable $exception) {
            self::assertInstanceOf(\RuntimeException::class,$exception);
            self::assertSame('No se pudo guardar el reporte de movimientos.',$exception->getMessage());
        }
        $tray->refresh();
        self::assertSame('FAILED',$tray->status);
        self::assertNull($tray->file_name);
        $tray->update(['status'=>'IN_PROCESS','date_end'=>null]);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/movement-job-views',
            'view.paths'=>[base_path('resources/views')], 'excel'=>require base_path('config/excel.php')]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $excel=new \Maatwebsite\Excel\Excel(new \Maatwebsite\Excel\Writer($temporaryFiles),
            $this->createMock(\Maatwebsite\Excel\QueuedWriter::class), $this->createMock(\Maatwebsite\Excel\Reader::class),
            new \Maatwebsite\Excel\Files\Filesystem($app['filesystem']));
        $app->instance(\Maatwebsite\Excel\Exporter::class,$excel);
        $job->handle();
        $tray->refresh();
        self::assertSame('FINISHED',$tray->status);
        self::assertNotNull($tray->date_end);
        $file=sys_get_temp_dir().'/'.$this->database.'/'.$tray->path.'/'.$tray->file_name.'.xlsx';
        self::assertFileExists($file);
        $workbook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
        self::assertSame('Mov. de ingresos y egresos',$workbook->getActiveSheet()->getTitle());
        $workbook->disconnectWorksheets();
        $otherTray=\App\Models\Tenant\DownloadTray::create([
            'user_id'=>$input['user_id'],'module'=>'FINANCE','format'=>'xlsx','type'=>'Prueba dos',
        ]);
        (new \Modules\Finance\Jobs\ProcessMovementsReport($params,$otherTray->id,1))->handle();
        $otherTray->refresh();
        $otherFile=sys_get_temp_dir().'/'.$this->database.'/'.$otherTray->path.'/'.$otherTray->file_name.'.xlsx';
        self::assertSame('FINISHED',$otherTray->status);
        self::assertNotSame($tray->file_name,$otherTray->file_name);
        self::assertStringEndsWith('_'.$tray->id,$tray->file_name);
        self::assertStringEndsWith('_'.$otherTray->id,$otherTray->file_name);
        self::assertFileExists($file);
        self::assertFileExists($otherFile);
        unlink($otherFile);
        unlink($file);
    }

    public function test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'100']);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'10']]));
        self::assertSame(2,$db->table('cash_transactions')->where('cash_id',$cash->id)->count());
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1,'is_active'=>1]);
        foreach ([['04','VES','50',false],['10','USD','5',false],['11','VES','200',false],['01','USD','20',false],['1','VES','30',true],['1','USD','2',true]] as [$method,$currency,$amount,$expense]) {
            $db->transaction(function() use($db,$input,$cash,$method,$currency,$amount,$expense) {
                if ($expense) {
                    $id=$db->table('expenses')->insertGetId(['user_id'=>$input['user_id'],'expense_type_id'=>$db->table('expense_types')->value('id'),
                        'establishment_id'=>$input['establishment_id'],'supplier_id'=>$input['customer_id'],'supplier'=>json_encode($input['customer']),
                        'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                        'exchange_rate_sale'=>'100','total'=>$amount,'state_type_id'=>'05','number'=>'E-'.$currency,'fiscal_environment'=>'demo']);
                    $payment=ExpensePayment::create(['expense_id'=>$id,'expense_method_type_id'=>1,'date_of_payment'=>'2026-10-26','payment'=>$amount]);
                    $type=Cash::class;$destination=$cash->id;
                } else {
                    $id=$db->table('income')->insertGetId(['user_id'=>$input['user_id'],'income_type_id'=>$db->table('income_types')->value('id'),
                        'income_reason_id'=>$db->table('income_reasons')->value('id'),'establishment_id'=>$input['establishment_id'],'customer'=>'Cliente',
                        'currency_type_id'=>$currency,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00',
                        'exchange_rate_sale'=>'100','total'=>$amount,'state_type_id'=>'05','number'=>1,'fiscal_environment'=>'demo']);
                    $payment=IncomePayment::create(['income_id'=>$id,'payment_method_type_id'=>$method,'date_of_payment'=>'2026-10-26','payment'=>$amount]);
                    $type=$db->table('payment_method_types')->where('id',$method)->value('collection_kind') === 'cash' ? Cash::class : BankAccount::class;
                    $destination=$type===Cash::class ? $cash->id : BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Cuenta '.$currency,'number'=>'TEST-'.$currency,'currency_type_id'=>$currency,'initial_balance'=>'0'])->id;
                }
                $global=$payment->global_payment()->create(['destination_type'=>$type,'destination_id'=>$destination,'user_id'=>$input['user_id'],'fiscal_environment'=>'demo']);
                self::assertSame($cash->id,$global->cash_session_id);
            });
        }
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','cash_currency_type_id'=>'USD','is_cash'=>1]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'account','cash_currency_type_id'=>null,'is_cash'=>0]);
        $report=CashLedger::report($cash);
        $movementRows=collect($report['rows'])->filter(fn($row)=>!$row['opening']);
        self::assertSame('cash',$movementRows->first(fn($row)=>$row['method']['id']==='04')['method']['collection_kind']);
        self::assertSame('account',$movementRows->first(fn($row)=>$row['method']['id']==='01')['method']['collection_kind']);
        self::assertSame('120',$report['balances_by_currency']['VES']['final_balance']);
        self::assertSame('13',$report['balances_by_currency']['USD']['final_balance']);
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $incomeId=$db->table('income')->orderBy('id')->value('id');
        $db->table('income_items')->insert(['income_id'=>$incomeId,'description'=>'Servicio de prueba','total'=>'50']);
        $globals=\Modules\Finance\Models\GlobalPayment::query()->orderBy('id')->get();
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $openingGlobals=$globals->filter(fn($global)=>$global->report_context['opening'] ?? false)->values();
        $openingRows=(new \Modules\Finance\Http\Resources\GlobalPaymentCollection($openingGlobals))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'));
        self::assertSame(['Saldo inicial VES','Saldo inicial USD'],$openingRows->pluck('number_full')->all());
        self::assertSame(['VES','USD'],$openingRows->pluck('currency_type_id')->all());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        $movementCollection=new \Modules\Finance\Http\Resources\MovementCollection($globals);
        $movementRequest=\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]);
        $rows=$movementCollection->toArray($movementRequest);
        self::assertCount(8,$rows);
        self::assertSame('Servicio de prueba',$rows->first(fn($row)=>$row['instance_type']==='income')['items'][0]['description']);
        $repeated=$movementCollection->toArray($movementRequest);
        self::assertSame('Servicio de prueba',$repeated->first(fn($row)=>$row['instance_type']==='income')['items'][0]['description']);
        self::assertSame(['VES','USD','VES','USD','VES','USD','VES','USD'], $rows->pluck('currency_type_id')->all());
        self::assertSame('320.00',$rows->where('currency_type_id','VES')->last()['balance']);
        self::assertSame('33.00',$rows->where('currency_type_id','USD')->last()['balance']);
        $mobileRequest=\Illuminate\Http\Request::create('/', 'GET', ['date_start'=>'2026-10-26','date_end'=>'2026-10-26']);
        $mobileSummary=(new \Modules\MobileApp\Http\Controllers\Api\FinanceController())->movementsSummary($mobileRequest)['data'];
        self::assertNull($mobileSummary['balance']);
        self::assertSame('320',$mobileSummary['totals_by_currency']['VES']['balance']);
        self::assertSame('33',$mobileSummary['totals_by_currency']['USD']['balance']);
        self::assertSame(0,$mobileSummary['unclassified_count']);
        $urls=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $urls->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$urls);
        $mobileRequest->merge(['limit'=>100]);
        $scroll=(new \Modules\MobileApp\Http\Controllers\Api\FinanceController())->movementsByScroll($mobileRequest);
        self::assertTrue($scroll['success']);
        $mobileRows=$scroll['data']->toArray($mobileRequest);
        self::assertCount(8,$mobileRows);
        self::assertSame(['USD','VES'],$mobileRows->pluck('currency_type_id')->unique()->values()->all());
        $incomeGlobal=$globals->first(fn($global)=>$global->instance_type==='income');
        $incomeGlobal->payment->associated_record_payment->setRelation('items',collect([['description'=>'Partida en arreglo']]));
        $arrayWeb=(new \Modules\Finance\Http\Resources\MovementCollection(collect([$incomeGlobal])))->toArray($movementRequest);
        $arrayMobile=(new \Modules\MobileApp\Http\Resources\Api\FinanceMovementCollection(collect([$incomeGlobal])))->toArray($mobileRequest);
        self::assertSame('Partida en arreglo',$arrayWeb[0]['items'][0]['description']);
        self::assertSame('Partida en arreglo',$arrayMobile[0]['items'][0]['description']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        $closed=CashLedger::close($cash);
        self::assertSame($report['balances_by_currency'],$closed['balances_by_currency']);
        self::assertSame('120',$cash->fresh()->final_balance);
        foreach (['VES'=>['120','4'],'USD'=>['13','4']] as $currency=>[$physical,$count]) {
            $data=(new \Modules\Finance\Http\Controllers\BalanceController())->records(new \Illuminate\Http\Request([
                'currency_type_id'=>$currency,'period'=>'date','date_start'=>'2026-10-26','date_end'=>'2026-10-26','month_start'=>'2026-10','month_end'=>'2026-10',
            ]));
            self::assertCount((int)$count,$data['classified']);
            self::assertSame($currency,$data['currency_type_id']);
            $cashGroups=array_filter($data['classified'],fn($row)=>$row['method']['collection_kind']==='cash');
            self::assertSame($physical,\App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::sum(array_map(fn($row)=>\App\Support\Exact\Decimal::readRequired($row,'balance'),$cashGroups))));
            self::assertCount(0,$data['records']);
        }
        foreach (['cash_summary','cash_simple','general_with_payments','income_egress','income_summary','payments_associated','summary_daily_operations'] as $type) {
            $payload=(new \Modules\CashReport\Services\ClassifiedCashReport())->build($cash,$type,[]);
            self::assertSame($report['balances_by_currency'],$payload['classified']['balances_by_currency']);
            if (in_array($type,['general_with_payments','income_egress','payments_associated'],true)) {
                self::assertNotEmpty($payload['classified']['groups']);
                foreach ($payload['classified']['groups'] as $group) self::assertSame('cash',$group['method']['collection_kind']);
                foreach ($payload['classified']['rows'] as $row) self::assertSame('cash',$row['method']['collection_kind']);
            }
            if ($type==='income_summary') {
                foreach ($payload['classified']['groups'] as $group) self::assertSame('0',$group['egress']);
                foreach ($payload['classified']['rows'] as $row) self::assertSame('income',$row['direction']);
            }
            if ($type==='cash_summary') {
                self::assertContains('account',array_column(array_column($payload['classified']['groups'],'method'),'collection_kind'));
            }
        }
        $app->instance('files',new \Illuminate\Filesystem\Filesystem());
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/views','view.paths'=>[]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $app['view']->addNamespace('cashreport',base_path('modules/CashReport/Resources/views'));
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $app['view']->addNamespace('finance',base_path('modules/Finance/Resources/views'));
        $app['view']->addLocation(base_path('resources/views'));
        $establishment=(object)['address'=>'Sede','department'=>(object)['description'=>'Estado'],'district'=>(object)['description'=>'Parroquia']];
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $methodId=$incomeGlobal->report_context['method']['id'];
        $historicName=$incomeGlobal->report_context['method']['description'];
        $db->table('payment_method_types')->where('id',$methodId)->update(['description'=>'Catálogo modificado']);
        $globalReport=\Modules\Finance\Models\GlobalPayment::query()->whereKey($incomeGlobal->id)->get();
        \App\Services\Payments\Movement::preload($globalReport);
        $bankGlobal=$globals->first(fn($global)=>($global->report_context['method']['id'] ?? null)==='01' && $global->instance_type==='income');
        $historicDestination=$bankGlobal->report_context['destination_label'];
        $historicContext=$bankGlobal->getRawOriginal('report_context');
        $db->table('bank_accounts')->where('id',$bankGlobal->destination_id)->update(['description'=>'Cuenta renombrada']);
        $bankReport=\Modules\Finance\Models\GlobalPayment::query()->whereKey($bankGlobal->id)->get();
        \App\Services\Payments\Movement::preload($bankReport);
        self::assertSame($historicContext,$bankReport->first()->getRawOriginal('report_context'));
        $globalCollection=new \Modules\Finance\Http\Resources\GlobalPaymentCollection($bankReport);
        $globalRow=$globalCollection->toArray(\Illuminate\Http\Request::create('/', 'GET'))->first();
        self::assertSame($historicDestination,$globalRow['destination_description']);
        foreach (['report_pdf','report_excel'] as $view) {
            $openingHtml=$app['view']->make('finance::global_payments.'.$view,['records'=>$openingGlobals,
                'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
            $openingDocument=new \DOMDocument();
            @$openingDocument->loadHTML($openingHtml);
            $openingXpath=new \DOMXPath($openingDocument);
            foreach (['Saldo inicial VES','Saldo inicial USD'] as $i=>$expectedOpening) {
                $openingCell=$openingXpath->query('//tbody/tr['.($i+1).']/td[5]')->item(0);
                self::assertNotNull($openingCell);
                self::assertSame($expectedOpening,trim($openingCell->textContent));
            }
            $bankHtml=$app['view']->make('finance::global_payments.'.$view,['records'=>$bankReport,
                'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
            $bankDocument=new \DOMDocument();
            @$bankDocument->loadHTML($bankHtml);
            $bankDestination=(new \DOMXPath($bankDocument))->query('//tbody/tr[1]/td[8]')->item(0);
            self::assertNotNull($bankDestination);
            self::assertSame($historicDestination,trim($bankDestination->textContent));
            self::assertStringNotContainsString('Cuenta renombrada',$bankDestination->textContent);
            self::assertStringContainsString('Cuenta/Caja actual',$bankHtml);
            $html=$app['view']->make('finance::global_payments.'.$view,['records'=>$globalReport,
                'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
            $document=new \DOMDocument();
            @$document->loadHTML($html);
            $xpath=new \DOMXPath($document);
            $methodCell=$xpath->query('//tbody/tr[1]/td[11]')->item(0);
            self::assertNotNull($methodCell);
            self::assertSame($historicName,trim($methodCell->textContent));
            self::assertStringNotContainsString('Catálogo modificado', $methodCell->textContent);
            if ($view === 'report_pdf') $globalPdfHtml=$html;
        }
        $globalPdf=new \Dompdf\Dompdf();
        $globalPdf->setPaper('A4','landscape');
        $globalPdf->loadHtml($globalPdfHtml);
        $globalPdf->render();
        self::assertStringStartsWith('%PDF-', $globalPdf->output());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        foreach (['new_report_pdf','new_report_excel'] as $view) {
            $html=$app['view']->make('finance::movements.'.$view,['records'=>$rows,'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
            self::assertStringContainsString('Totales VES',$html);
            self::assertStringContainsString('Totales USD',$html);
            self::assertStringContainsString('Bs.320.00',$html);
            self::assertStringContainsString('$33.00',$html);
            $tableDocument=new \DOMDocument();
            @$tableDocument->loadHTML($html);
            $xpath=new \DOMXPath($tableDocument);
            $headerCount=$xpath->query('//table[thead]/thead/tr/th')->length;
            self::assertSame($view==='new_report_pdf' ? 13 : 15,$headerCount);
            foreach ($xpath->query('//table[thead]/tfoot/tr[td[contains(., "Totales")]]') as $totalRow) {
                $cells=$xpath->query('./td',$totalRow);
                self::assertSame($headerCount,(int)$cells->item(0)->getAttribute('colspan')+$cells->length-1);
            }
        }
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $globalExport=(new \Modules\Finance\Exports\GlobalPaymentExport())->records($globalReport->concat($bankReport)->concat($openingGlobals))
            ->company(\App\Models\Tenant\Company::firstOrFail())->establishment($establishment);
        $globalFile=(new \Maatwebsite\Excel\Writer($temporaryFiles))->export($globalExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $globalBook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($globalFile->getLocalPath());
            $globalCells=collect($globalBook->getActiveSheet()->toArray(null,true,false,false))->flatten()->map(fn($value)=>(string)$value)->all();
            self::assertContains($historicName,$globalCells);
            self::assertContains($historicDestination,$globalCells);
            self::assertContains('Saldo inicial VES',$globalCells);
            self::assertContains('Saldo inicial USD',$globalCells);
            self::assertNotContains('Cuenta renombrada',$globalCells);
            self::assertNotContains('Catálogo modificado',$globalCells);
            $globalBook->disconnectWorksheets();
        } finally {
            $globalFile->delete();
        }
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
        $legacyWithoutAmount=$rows->first();
        $legacyWithoutAmount['id']='historico-sin-importe';
        $legacyWithoutAmount['payments']=null;
        $legacyWithoutAmount['calculation_snapshot']=null;
        $legacyWithoutAmount['currency_type_id']=null;
        $exportRows=$rows->concat([$legacyWithoutAmount]);
        $movementExport=(new \Modules\Finance\Exports\MovementExport())->records($exportRows)
            ->company(\App\Models\Tenant\Company::firstOrFail())->establishment($establishment)->setNewFormat(true);
        $movementFile=(new \Maatwebsite\Excel\Writer($temporaryFiles))->export($movementExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $workbook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($movementFile->getLocalPath());
            $cells=collect($workbook->getActiveSheet()->toArray(null,true,false,false))->flatten()->map(fn($value)=>(string)$value)->all();
            foreach (['Totales VES','Totales USD','Bs.320.00','$33.00','- Servicio de prueba'] as $expected) {
                self::assertContains($expected,$cells);
            }
            $sheet=$workbook->getActiveSheet();
            $headerRow=null; $totalRows=[];
            foreach ($sheet->getRowIterator() as $row) {
                $number=$row->getRowIndex();
                $label=(string)$sheet->getCell('A'.$number)->getValue();
                if ($label==='ID movimiento') $headerRow=$number;
                if (in_array($label,['Totales VES','Totales USD'],true)) $totalRows[substr($label,-3)]=$number;
            }
            self::assertNotNull($headerRow);
            self::assertSame((string)$rows->first()['id'],(string)$sheet->getCell('A'.($headerRow+1))->getValue());
            foreach (['M'=>'Ingresos','N'=>'Gastos','O'=>'Saldo'] as $column=>$label) {
                self::assertSame($label,$sheet->getCell($column.$headerRow)->getValue());
            }
            foreach (['VES','USD'] as $currency) {
                self::assertArrayHasKey($currency,$totalRows);
                $totals=['input'=>\App\Support\Exact\Decimal::of(0),'output'=>\App\Support\Exact\Decimal::of(0)];
                foreach ($rows->where('currency_type_id',$currency) as $row) {
                    $amount=$row['calculation_snapshot']['values']['amount'] ?? $row['payments'];
                    $totals[$row['type_movement']]=$totals[$row['type_movement']]->plus(\App\Support\Exact\Decimal::of($amount));
                }
                $symbol=$currency==='VES' ? 'Bs.' : '$';
                foreach (['M'=>$totals['input'],'N'=>$totals['output'],'O'=>$totals['input']->minus($totals['output'])] as $column=>$amount) {
                    self::assertSame($symbol.\App\Support\Exact\Decimal::format($amount,2,'.',''),$sheet->getCell($column.$totalRows[$currency])->getValue());
                }
            }
            $fractionSheet=$workbook->getSheetByName('Fracciones exactas');
            self::assertNotNull($fractionSheet);
            $fractionRows=$fractionSheet->toArray(null,true,false,false);
            $expectedRows=$rows->filter(fn($row)=>isset($row['calculation_snapshot']['values']['amount']) || isset($row['payments']));
            self::assertCount($expectedRows->count()+1,$fractionRows);
            self::assertNotContains('historico-sin-importe',array_column($fractionRows,0));
            foreach ($expectedRows->values() as $index=>$row) {
                $fraction=\App\Support\Exact\Decimal::fraction($row['calculation_snapshot']['values']['amount'] ?? $row['payments']);
                self::assertSame([(string)$row['id'],'amount',\App\Support\Exact\Decimal::represent($fraction),$fraction['n'],$fraction['d']],$fractionRows[$index+1]);
            }
            $workbook->disconnectWorksheets();
        } finally {
            $movementFile->delete();
        }
        config(['tenant.items_per_page'=>1]);
        $filters=['payment_type'=>null,'destination_type'=>null,'last_cash_opening'=>'false','period'=>'date',
            'date_start'=>'2026-10-26','date_end'=>'2026-10-26','month_start'=>'2026-10','month_end'=>'2026-10'];
        $controller=new \Modules\Finance\Http\Controllers\MovementController();
        $ordered=$controller->getRecords($filters,\Modules\Finance\Models\GlobalPayment::class)->get();
        $all=(new \Modules\Finance\Http\Resources\MovementCollection($ordered))->toArray(\Illuminate\Http\Request::create('/', 'GET', $filters+['page'=>1]));
        self::assertGreaterThan(1,$all->count());
        $ids=$all->pluck('id')->all();
        $sortedIds=$ids; sort($sortedIds);
        self::assertSame($sortedIds,$ids);
        $job=new \Modules\Finance\Jobs\ProcessMovementsReport((object)[],0,0);
        $jobQuery=new \ReflectionMethod($job,'getRecords');
        $jobQuery->setAccessible(true);
        $exportIds=$jobQuery->invoke($job,(object)($filters+['user_id'=>$input['user_id']]),\Modules\Finance\Models\GlobalPayment::class)->pluck('id')->all();
        self::assertSame($ids,$exportIds);
        $running=new \App\Services\Payments\MovementBalances();
        foreach ($ordered as $global) {
            $movement=\App\Services\Payments\Movement::row($global);
            if (!$movement || !in_array($movement['currency_type_id'],['VES','USD'],true)) continue;
            $row=$all->first(fn($item)=>$item['id']===$global->id);
            self::assertSame(\App\Support\Exact\Decimal::format($running->applyExact($movement),2,'.',''),$row['balance']);
        }
        $page=$controller->getRecords($filters,\Modules\Finance\Models\GlobalPayment::class)->paginate(1,['*'],'page',2);
        $later=(new \Modules\Finance\Http\Resources\MovementCollection($page))->toArray(\Illuminate\Http\Request::create('/', 'GET', $filters+['page'=>2]));
        self::assertSame($all[1]['id'],$later[0]['id']);
        self::assertSame($all[1]['balance'],$later[0]['balance']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        foreach (['classified_a4','classified_ticket','classified_excel'] as $view) {
            $html=$app['view']->make('cashreport::reports.'.$view,$payload)->render();
            self::assertStringContainsString('120.00',$html);self::assertStringContainsString('13.00',$html);
        }
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $bankA=BankAccount::where('currency_type_id','USD')->firstOrFail();
        $bankB=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'Otro USD','number'=>'TR-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $transfers=$db->transaction(function () use ($input,$bankA,$bankB) {
            $base=['origin_type'=>BankAccount::class,'destiny_type'=>BankAccount::class,'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']];
            return [
                \App\Models\Tenant\TransferAccountPayment::create($base+['origin_id'=>$bankA->id,'destiny_id'=>$bankB->id,'amount'=>'7']),
                \App\Models\Tenant\TransferAccountPayment::create($base+['origin_id'=>$bankB->id,'destiny_id'=>$bankA->id,'amount'=>'-7']),
            ];
        });
        $transferRows=(new \Modules\Finance\Http\Resources\MovementCollection(collect(array_map(fn($transfer)=>$transfer->global_payment,$transfers))))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]));
        self::assertSame(['USD','USD'],$transferRows->pluck('currency_type_id')->all());
        self::assertSame(['input','output'],$transferRows->pluck('type_movement')->all());
        self::assertSame(['7.00','0.00'],$transferRows->pluck('balance')->all());
        $mobileTransfers=(new \Modules\MobileApp\Http\Resources\Api\FinanceMovementCollection(collect(array_map(fn($transfer)=>$transfer->global_payment,$transfers))))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'));
        self::assertSame(['input','output'],$mobileTransfers->pluck('type_movement')->all());
        self::assertSame(['7','7'],$mobileTransfers->pluck('amount_decimal')->all());
        self::assertSame([null,null],$mobileTransfers->pluck('amount_pen')->all());
        $context=$transfers[0]->global_payment->report_context;
        $context['reversal']=['reason'=>'Prueba'];
        $db->table('global_payments')->where('id',$transfers[0]->global_payment->id)->update(['report_context'=>json_encode($context,JSON_THROW_ON_ERROR)]);
        $after=(new \Modules\Finance\Http\Resources\MovementCollection(\Modules\Finance\Models\GlobalPayment::whereIn('id',array_map(fn($transfer)=>$transfer->global_payment->id,$transfers))->orderBy('id')->get()))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]));
        self::assertCount(1,$after);
        self::assertSame('-7.00',$after[0]['balance']);
        $unknownContext=$transfers[1]->global_payment->report_context;
        $unknownContext['currency_type_id']=null;
        $db->table('global_payments')->where('id',$transfers[1]->global_payment->id)->update(['report_context'=>json_encode($unknownContext,JSON_THROW_ON_ERROR)]);
        $unknown=(new \Modules\Finance\Http\Resources\MovementCollection(\Modules\Finance\Models\GlobalPayment::whereKey($transfers[1]->global_payment->id)->get()))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]));
        self::assertNull($unknown[0]['currency_type_id']);
        self::assertSame('-',$unknown[0]['balance']);
        $unknownHtml=$app['view']->make('finance::movements.new_report_excel',['records'=>$unknown,'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
        self::assertStringContainsString('Sin moneda',$unknownHtml);
        self::assertStringContainsString('no se incluyen en los totales',$unknownHtml);
        $db->table('global_payments')->where('id',$transfers[1]->global_payment->id)->update(['report_context'=>json_encode(['version'=>1],JSON_THROW_ON_ERROR)]);
        $legacy=(new \Modules\Finance\Http\Resources\MovementCollection(\Modules\Finance\Models\GlobalPayment::whereKey($transfers[1]->global_payment->id)->get()))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET', ['page'=>1]));
        self::assertCount(1,$legacy);
        self::assertNull($legacy[0]['currency_type_id']);
        self::assertNull($legacy[0]['payments']);
        self::assertSame('-',$legacy[0]['balance']);
        $mobileLegacy=(new \Modules\MobileApp\Http\Resources\Api\FinanceMovementCollection(\Modules\Finance\Models\GlobalPayment::whereKey($transfers[1]->global_payment->id)->get()))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'));
        self::assertCount(1,$mobileLegacy);
        self::assertNull($mobileLegacy[0]['amount']);
        self::assertNull($mobileLegacy[0]['currency_type_id']);
        $legacyHtml=$app['view']->make('finance::movements.new_report_excel',['records'=>$legacy,'company'=>\App\Models\Tenant\Company::firstOrFail(),'establishment'=>$establishment])->render();
        self::assertStringContainsString('Sin moneda',$legacyHtml);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        if ($directory=getenv('PRO9_PAYMENTS_VISUAL_OUTPUT_DIR')) {
            if (!is_dir($directory)) mkdir($directory,0777,true);
            foreach (['a4','ticket80','ticket58'] as $paper) file_put_contents($directory.'/cash-'.$paper.'.pdf',
                (new \Modules\CashReport\Services\CashReportRenderer())->pdfContent('cash_summary',$cash,['paper'=>$paper]));
        }
        $expenseGlobal=$globals->first(fn($global)=>$global->payment_type===ExpensePayment::class);
        $expensePaymentId=$expenseGlobal->payment_id;
        self::assertTrue((new \Modules\Expense\Http\Controllers\ExpensePaymentController())->destroy($expensePaymentId)['success']);
        self::assertNull(ExpensePayment::find($expensePaymentId));
        self::assertArrayHasKey('reversal',$expenseGlobal->fresh()->report_context);
        self::assertNull(Movement::row($expenseGlobal->fresh()));
    }

    public function test_transfers_capture_the_currency_of_each_destination_and_reject_stale_cash_currency(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CASH-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \Modules\Finance\Http\Controllers\BalanceController();
        $data=['from'=>'B:'.$bank->id,'to'=>'C:'.$cash->id,'amount_transform_temp'=>'10','amount_transform'=>'4000','cash_currency_type_id'=>'VES'];
        $controller->makeTransfer(\Illuminate\Http\Request::create('/', 'POST', ['data'=>$data]));
        $legs=\App\Models\Tenant\TransferAccountPayment::orderBy('id')->get();
        self::assertSame(['VES','USD'],$legs->pluck('currency_type_id')->all());
        self::assertSame(['VES','USD'],$legs->map(fn($leg)=>$leg->global_payment->report_context['currency_type_id'])->all());
        self::assertSame(['4000','-10'],$legs->pluck('amount')->all());
        self::assertSame('4000',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        $data=['from'=>'C:'.$cash->id,'to'=>'B:'.$bank->id,'amount_transform_temp'=>'400','amount_transform'=>'1','cash_currency_type_id'=>'VES'];
        $controller->makeTransfer(\Illuminate\Http\Request::create('/', 'POST', ['data'=>$data]));
        $newLegs=\App\Models\Tenant\TransferAccountPayment::orderBy('id')->get()->slice(2)->values();
        self::assertSame(['USD','VES'],$newLegs->pluck('currency_type_id')->all());
        self::assertSame('3600',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        foreach ([null,'USD'] as $currency) {
            $data['cash_currency_type_id']=$currency;
            try {
                $controller->makeTransfer(\Illuminate\Http\Request::create('/', 'POST', ['data'=>$data]));
                self::fail('No se permite registrar la moneda de caja ausente o desactualizada.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('cash_currency_type_id',$error->errors());
            }
        }
        self::assertSame(4,$db->table('transfer_account_payments')->count());
        try {
            $db->transaction(fn()=>\App\Models\Tenant\TransferAccountPayment::create(['origin_id'=>$cash->id,'origin_type'=>Cash::class,
                'destiny_id'=>$bank->id,'destiny_type'=>BankAccount::class,'currency_type_id'=>'VES','amount'=>'1',
                'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]));
            self::fail('El traslado a una cuenta USD no puede declararse en VES.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame(4,$db->table('transfer_account_payments')->count());
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>0]);
        $data['cash_currency_type_id']='VES';
        try {
            $controller->makeTransfer(\Illuminate\Http\Request::create('/', 'POST', ['data'=>$data]));
            self::fail('Una moneda inactiva no puede utilizarse en un traslado nuevo.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        $db->table('cat_currency_types')->where('id','USD')->update(['active'=>1]);
        try {
            $db->transaction(fn()=>\App\Models\Tenant\TransferAccountPayment::create(['origin_id'=>$bank->id,'origin_type'=>BankAccount::class,
                'destiny_id'=>$cash->id,'destiny_type'=>Cash::class,'amount'=>'1',
                'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]));
            self::fail('Un traslado nuevo hacia caja requiere identificar su moneda.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame(4,$db->table('transfer_account_payments')->count());
        $direct=$db->transaction(fn()=>\App\Models\Tenant\TransferAccountPayment::create(['origin_id'=>$cash->id,'origin_type'=>Cash::class,
            'destiny_id'=>$bank->id,'destiny_type'=>BankAccount::class,'amount'=>'1',
            'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]));
        self::assertSame('USD',$direct->fresh()->currency_type_id);
        self::assertSame('USD',$direct->global_payment->report_context['currency_type_id']);
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        $db->transaction(fn()=>\App\Models\Tenant\TransferAccountPayment::create(['origin_id'=>$bank->id,'origin_type'=>BankAccount::class,
            'destiny_id'=>$cash->id,'destiny_type'=>Cash::class,'currency_type_id'=>'USD','amount'=>'2',
            'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]));
        $db->table('transfer_account_payments')->insert(['origin_id'=>$bank->id,'origin_type'=>BankAccount::class,
            'destiny_id'=>$cash->id,'destiny_type'=>Cash::class,'currency_type_id'=>null,'amount'=>'900',
            'date_of_movement'=>'2026-10-26 10:00:00','user_id'=>$input['user_id']]);
        self::assertSame('3600',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash,'VES')));
        self::assertSame('2',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash,'USD')));
        self::assertSame('0',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($cash)));
        self::assertSame('-8',\App\Support\Exact\Decimal::represent($controller->getTransferAccountPayment($bank)));
        self::assertSame('-8',$controller->getBalanceBySingleBankAcount($bank)['balance']);
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        self::assertNotEmpty($db->select("SHOW INDEX FROM transfer_account_payments WHERE Key_name = 'transfer_account_currency_fk'"));
    }

    public function test_payment_link_with_banking_method_does_not_create_a_cash_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $db->table('payment_configurations')->update(['default_payment_for_payment_links'=>'04']);
        $link=\Modules\Payment\Models\PaymentLink::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$input['user_id'],
            'person_id'=>$input['customer_id'],'payment_link_type_id'=>$db->table('payment_link_types')->value('id'),
            'fiscal_environment'=>'demo','total'=>'100','status'=>'pending']);
        $link->payments()->create(['total'=>'100','status'=>'pending']);
        try {
            (new \Modules\Payment\Http\Controllers\PaymentLinkController())->confirmPayment(\Illuminate\Http\Request::create('/', 'POST', ['id'=>$link->id]));
            self::fail('Un método bancario no puede registrarse como efectivo desde un enlace.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_destination_id',$error->errors());
        }
        self::assertSame('pending',$link->fresh()->status);
        self::assertSame('pending',$link->payments()->firstOrFail()->status);
        self::assertSame(0,$db->table('income')->count());
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_opened_cash_is_returned_by_the_paginated_cash_listing(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        config(['tenant.items_per_page' => 20]);
        $controller = new \App\Http\Controllers\Tenant\CashController();
        $request = \App\Http\Requests\Tenant\CashRequest::create('/cash', 'POST', [
            'user_id' => $input['user_id'], 'reference_number' => 'TEST-CAJA',
            'opening_balances' => ['VES' => '100', 'USD' => '10'],
        ]);
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>0]);
        try {
            $controller->store($request);
            self::fail('La apertura con USD requiere un método de efectivo activo.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_method_type_id',$error->errors());
        }
        self::assertSame(0,$db->table('cash')->count());
        self::assertSame(0,$db->table('cash_currency_balances')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        try {
            (new \Modules\Restaurant\Http\Controllers\CashController())->store($request);
            self::fail('La apertura de restaurante debe rechazar efectivo USD no disponible.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_method_type_id',$error->errors());
        }
        self::assertSame(0,$db->table('cash')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        try {
            \App\Services\Payments\RestaurantCashImport::store([
                'dateOpening'=>'2026-10-26','timeOpening'=>'09:00:00','internalsId'=>[],
                'beginningBalance'=>'100','opening_balances'=>['VES'=>'100','USD'=>'10'],
            ]);
            self::fail('La importación de restaurante debe revertir una apertura incompatible.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_method_type_id',$error->errors());
        }
        self::assertSame(0,$db->table('cash')->count());
        self::assertSame(0,$db->table('cash_currency_balances')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','10')->update(['is_active'=>1]);
        $opened = $controller->store($request);
        self::assertTrue($opened['success']);
        $listing = $controller->records(\Illuminate\Http\Request::create('/cash/records', 'GET', ['column' => 'income', 'value' => '']));
        $rows = $listing->toArray(\Illuminate\Http\Request::create('/cash/records'));
        self::assertCount(1, $rows);
        $row = $rows->first();
        self::assertSame($opened['data']['cash_id'], $row['id']);
        self::assertSame('TEST-CAJA', $row['reference_number']);
        self::assertSame('Aperturada', $row['state_description']);
        self::assertSame('100', $row['balances_by_currency']['VES']['beginning_balance']);
        self::assertSame('10', $row['balances_by_currency']['USD']['beginning_balance']);
    }

    public function test_payment_classification_is_ready_from_the_fresh_tenant_schema(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        self::assertFalse($db->getSchemaBuilder()->hasTable('payment_reporting_settings'));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('cash','report_version'));
        self::assertSame('account',$db->table('payment_method_types')->where('id','11')->value('collection_kind'));
        self::assertSame('USD',$db->table('payment_method_types')->where('id','10')->value('cash_currency_type_id'));
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,['opening_balances'=>['VES'=>'100','USD'=>'10']]));
        self::assertSame('100',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame('10',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
    }

    public function test_classified_invoice_receipts_keep_received_currency_and_bank_money_outside_physical_cash(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $input['payments']=[];$input['exchange_rate_sale']='100';
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $row=['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'10','payment'=>'100','currency_type_id'=>'USD',
            'original_amount'=>'1','exchange_rate'=>'100.00000000','operation_key'=>(string)\Illuminate\Support\Str::uuid(),'payment_destination_id'=>'cash'];
        $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row);
        self::assertSame('1',Movement::row($payment->global_payment)['amount']);
        $account=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'USD-test','currency_type_id'=>'USD','initial_balance'=>'0']);
        $row=array_replace($row,['payment_method_type_id'=>'04','payment'=>'132','original_amount'=>'1.32','payment_destination_id'=>$account->id,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        try {
            \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row);
            self::fail('Un medio VES no puede registrar un cobro USD.');
        } catch (\Illuminate\Validation\ValidationException $expected) {
            self::assertArrayHasKey('currency_type_id', $expected->errors());
        }
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1,'iva_rate_type_id'=>'IGTF']);
        $row['payment_method_type_id']='13';
        $bank=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row);
        self::assertSame('USD',$bank->payment_method_snapshot['currency_type_id']);
        self::assertSame('IGTF',$bank->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000',$bank->payment_method_snapshot['iva_rate_percentage']);
        self::assertSame('3.000000000000',$bank->global_payment->report_context['method']['iva_rate_percentage']);
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES','iva_rate_type_id'=>null]);
        self::assertSame($bank->id,\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$row)->id);
        self::assertSame('IGTF',$bank->fresh()->payment_method_snapshot['iva_rate_type_id']);
        self::assertSame('3.000000000000',$bank->fresh()->payment_method_snapshot['iva_rate_percentage']);
        self::assertSame(2,$db->table('global_payments')->count());
        $report=CashLedger::report($cash);
        self::assertSame('1',$report['balances_by_currency']['USD']['final_balance']);self::assertSame('0',$report['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($document->fresh()->exactBalance()->isZero());
        $groups=\App\Services\Payments\FinancialReport::groups('2026-10-26','2026-10-26');
        self::assertCount(2,$groups);self::assertSame(['USD','USD'],array_column($groups,'currency_type_id'));
        $records=(new \Modules\Finance\Http\Controllers\GlobalPaymentController())->getRecords([
            'payment_type'=>null,'destination_type'=>null,'period'=>'date','date_start'=>'2026-10-26','date_end'=>'2026-10-26','month_start'=>'2026-10','month_end'=>'2026-10',
        ],\Modules\Finance\Models\GlobalPayment::class)->get();
        self::assertCount(2,$records);
        foreach ($records as $global) self::assertNotNull(Movement::row($global));
        $resource=new \Modules\Finance\Http\Resources\GlobalPaymentCollection($records);
        self::assertCount(2,$resource->toArray(new \Illuminate\Http\Request()));
        self::assertSame('2.32',$resource->with(new \Illuminate\Http\Request())['totals_by_version'][0]['total']);
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        // In this temporary fixture, model two snapshots of the same account before and after a rename.
        $oldContext=$bank->global_payment->report_context;
        $oldContext['destination_label']='Cuenta anterior';
        $newContext=$oldContext;
        $newContext['destination_label']='Cuenta nueva';
        $db->table('global_payments')->where('id',$bank->global_payment->id)->update(['report_context'=>json_encode($oldContext,JSON_THROW_ON_ERROR)]);
        $db->table('global_payments')->where('id',$payment->global_payment->id)->update([
            'destination_type'=>BankAccount::class,'destination_id'=>$account->id,'report_context'=>json_encode($newContext,JSON_THROW_ON_ERROR),
        ]);
        $historicalGroups=collect(\App\Services\Payments\FinancialReport::groups('2026-10-26','2026-10-26'));
        self::assertCount(2,$historicalGroups);
        self::assertSame(['Cuenta nueva','Cuenta anterior'],$historicalGroups->pluck('destination_label')->all());
        self::assertSame(['Bancario / electrónico','Bancario / electrónico'],$historicalGroups->pluck('method_kind_label')->all());
        self::assertSame(['1','1.32'],$historicalGroups->pluck('income')->all());
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
    }

    public function test_company_api_includes_active_classified_custom_method_with_retired_id(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        if (!class_exists('Auth')) class_alias(\Illuminate\Support\Facades\Auth::class, 'Auth');
        $db->table('payment_method_types')->insert([
            ['id'=>'07','description'=>'Personalizado','hka_code'=>'99','collection_kind'=>'account','currency_type_id'=>'USD','is_credit'=>false,'is_cash'=>false,'is_active'=>true],
            ['id'=>'14','description'=>'Inactivo','hka_code'=>'99','collection_kind'=>'unclassified','currency_type_id'=>'VES','is_credit'=>false,'is_cash'=>false,'is_active'=>false],
        ]);
        $data=(new \App\Http\Controllers\Tenant\Api\CompanyController())->record(\Illuminate\Http\Request::create('/api/company','GET'));
        $methods=$data['payment_method_types']->keyBy('id');
        self::assertTrue($methods->has('07'));
        self::assertSame('USD',$methods->get('07')->currency_type_id);
        self::assertFalse($methods->has('14'));
        // Los flags son proyecciones de compatibilidad; la naturaleza manda incluso
        // si una lectura encuentra datos desincronizados.
        $db->table('payment_method_types')->where('id','07')->update(['is_credit'=>1]);
        $db->table('payment_method_types')->where('id','09')->update(['is_credit'=>0]);
        self::assertTrue(\App\Models\Tenant\PaymentMethodType::notCredit()->where('id','07')->exists());
        self::assertFalse(\App\Models\Tenant\PaymentMethodType::notCredit()->where('id','09')->exists());
        self::assertTrue(\App\Models\Tenant\PaymentMethodType::credit()->where('id','09')->exists());
        self::assertTrue(\App\Models\Tenant\PaymentMethodType::findOrFail('09')->isCredit());
    }

    public function test_payment_dialog_opens_when_the_stored_document_rate_is_invalid(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payment_condition_id']='02';
        $input['payments']=[];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        $db->table('documents')->where('id',$document->id)->update(['exchange_rate_sale'=>'0']);
        $response=(new \App\Http\Controllers\Tenant\DocumentPaymentController())->document($document->id);
        self::assertNull($response['exchange_rate_sale']);
        self::assertSame('VES',$response['currency_type_id']);
        self::assertSame('232',$response['total_difference']);
    }

    public function test_initial_invoice_rejects_method_currency_before_conversion_and_rolls_back(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $input['payments']=[['payment'=>'50','payment_method_type_id'=>'04',
            'date_of_payment'=>'2026-10-26','currency_type_id'=>'USD']];
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input);
            self::fail('Un método en VES no puede recibir USD.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('currency_type_id',$error->errors());
        }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $input['payments']=[['payment'=>'50','payment_method_type_id'=>'01','document_id'=>123,
            'date_of_payment'=>'2026-10-26','currency_type_id'=>'VES']];
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input);
            self::fail('No se puede copiar un cobro con documento de origen no identificado.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
    }

    public function test_classified_sale_note_conversion_reuses_a_receipt_without_second_cash_income(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[['payment'=>'50','payment_method_type_id'=>'01','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26']]);
        $source=$note->payments()->firstOrFail();
        $input['sale_note_id']=$note->id;
        $input['payments']=[['payment'=>'50','payment_method_type_id'=>'01','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26','source_sale_note_payment_id'=>$source->id]];
        $tampered=$input;
        $tampered['payments'][0]['original_amount']='100';
        try { (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($tampered); self::fail('El importe recibido no puede superar el cobro de origen en la misma moneda.'); }
        catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('source_sale_note_payment_id',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame((string)$source->id,$document->payments->first()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
        self::assertSame('50',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
    }

    public function test_credit_invoice_installments_rollback_invalid_amount_and_accept_paid_balance(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CREDIT-PAID-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $input['exchange_rate_sale']='10';
        $input['payment_condition_id']='02';
        $input['payments']=[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'23.20','exchange_rate'=>'10','payment'=>'232']];
        $input['fee']=[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'-1']];
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input);
            self::fail('La cuota negativa debe rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('fee.amount',$error->errors()); }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('document_payments')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $input['fee'][0]['amount']='0';
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame('0',$document->fee()->firstOrFail()->amount);
        self::assertSame('0',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        self::assertSame(1,$db->table('document_payments')->count());
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_usd_invoice_installments_discount_ves_bank_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'VES','number'=>'CREDIT-VES-BANK','currency_type_id'=>'VES','initial_balance'=>'0']);
        $input['currency_type_id']='USD';
        $input['exchange_rate_sale']='10';
        $input['payment_condition_id']='02';
        $input['payments']=[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'VES','original_amount'=>'50.10','exchange_rate'=>'10','payment'=>'5.01']];
        $input['fee']=[['date'=>'2026-11-26','currency_type_id'=>'USD','amount'=>'113.50','allocation_weight'=>'1'],
            ['date'=>'2026-12-26','currency_type_id'=>'USD','amount'=>'113.49','allocation_weight'=>'1']];
        $wrongBank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CREDIT-USD-BANK','currency_type_id'=>'USD','initial_balance'=>'0']);
        $wrongInput=$input;
        $wrongInput['payments'][0]['payment_destination_id']=$wrongBank->id;
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($wrongInput);
            self::fail('Una cuenta USD no puede recibir el cobro VES.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payment_destination_id',$error->errors());
        }
        self::assertSame(0,$db->table('documents')->count());
        self::assertSame(0,$db->table('global_payments')->count());
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame('226.99',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        self::assertSame(['113.5','113.49'],$document->fee()->orderBy('date')->pluck('amount')->all());
        $payment=$document->payments()->firstOrFail();
        self::assertSame('VES',$payment->currency_type_id);
        self::assertSame('50.1',$payment->original_amount);
        self::assertSame('5.01',$payment->payment);
        self::assertSame('10.00000000',$payment->exchange_rate);
        $movement=Movement::row($payment->global_payment);
        self::assertSame('VES',$movement['currency_type_id']);
        self::assertSame('50.1',$movement['amount']);
        self::assertSame('account',$movement['method']['collection_kind']);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'cash','currency_type_id'=>'USD','cash_currency_type_id'=>'USD','is_cash'=>1]);
        $after=Movement::row($payment->global_payment->fresh());
        self::assertSame('VES',$after['currency_type_id']);
        self::assertSame('account',$after['method']['collection_kind']);
        self::assertSame('50.1',$after['amount']);
        self::assertSame('226.99',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document->fresh()])[$document->id]));
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_invoice_installments_discount_cross_currency_receipt_and_guarantee_fund(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'FUND-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $input['exchange_rate_sale']='10';
        $input['payment_condition_id']='02';
        $input['payments']=[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,
            'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50']];
        $input['guarantee_fund']=['base'=>'100','percentage'=>'10','amount'=>'10'];
        $input['fee']=[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'172']];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame('172',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        self::assertSame('172',$document->fee()->firstOrFail()->amount);
        self::assertSame('10',$document->guarantee_fund()->firstOrFail()->amount);
        self::assertSame('50',$document->payments()->firstOrFail()->payment);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    public function test_credit_sale_note_installments_discount_cross_currency_receipt(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CREDIT-NOTE-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV',
            'exchange_rate_sale'=>'10','payment_condition_id'=>'02',
            'fee'=>[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'60'],
                ['date'=>'2026-12-26','currency_type_id'=>'VES','amount'=>'122']],
            'payments'=>[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,
                'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50']]]);
        $result=$controller->storeWithData($saleInput);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota.');
        $note=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id']);
        self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());
        $receipt=$note->payments()->firstOrFail();
        self::assertSame('50',$receipt->payment);
        self::assertSame(1,$db->table('global_payments')->count());
        $snapshot=$receipt->payment_method_snapshot;
        $edit=array_replace($saleInput,['id'=>$note->id,'number'=>$note->number,'payments'=>$note->getTransformPayments()->all()]);
        $updated=$controller->storeWithData($edit);
        self::assertTrue($updated['success'],$updated['message'] ?? 'No se pudo editar la nota.');
        self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());
        self::assertSame($snapshot,$receipt->fresh()->payment_method_snapshot);
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','01')->update(['description'=>'Nombre nuevo','collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
        $reportNotes=\App\Models\Tenant\SaleNote::whereKey($note->id)->get();
        $reportRows=(new \Modules\Report\Http\Resources\SaleNoteCollection($reportNotes))->toArray(\Illuminate\Http\Request::create('/'));
        self::assertSame($snapshot['description'],$reportRows[0]['payment_form']);
        self::assertStringContainsString('Recibido USD 5',$reportRows[0]['payment_method']);
        self::assertStringContainsString('Aplicado VES 50',$reportRows[0]['payment_method']);
        self::assertStringContainsString('Cuota #1',$reportRows[0]['payment_method']);
        self::assertSame(['n'=>'232','d'=>'1'],$reportRows[0]['report_total_fractions']['total']);
        $voidedNote=$note->fresh(); $voidedNote->state_type_id='11';
        $voidedRows=(new \Modules\Report\Http\Resources\SaleNoteCollection(new \Illuminate\Database\Eloquent\Collection([$voidedNote])))
            ->toArray(\Illuminate\Http\Request::create('/'));
        foreach (['total','total_taxed','total_igv','total_charge'] as $field) self::assertSame('0.00',$voidedRows[0][$field]);
        self::assertSame(['n'=>'232','d'=>'1'],$voidedRows[0]['report_total_fractions']['total']);
        $secondSale=$controller->storeWithData(array_replace($saleInput,['payments'=>[],'fee'=>[]]));
        self::assertTrue($secondSale['success'],$secondSale['message'] ?? 'No se pudo guardar la segunda nota.');
        $db->enableQueryLog();
        $measureNoteRelations=function (array $ids) use ($db) {
            $db->flushQueryLog();
            $rows=\App\Models\Tenant\SaleNote::whereIn('id',$ids)->get();
            (new \Modules\Report\Http\Resources\SaleNoteCollection($rows))->toArray(\Illuminate\Http\Request::create('/'));
            return count(array_filter($db->getQueryLog(),fn ($query)=>preg_match('/`(sale_note_payments|global_payments|payment_method_types|sale_note_fees|cat_currency_types)`/i',$query['query'])));
        };
        self::assertSame($measureNoteRelations([$note->id]),$measureNoteRelations([$note->id,$secondSale['data']['id']]),
            'El reporte debe cargar pagos, métodos, movimientos y cuotas por lote.');
        $db->disableQueryLog();
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/credit-note-views', 'view.paths'=>[base_path('resources/views')],
            'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $printNote=$note->fresh();
        $customer=(array)$printNote->customer; $customer['identity_document_type']=['description'=>'Venezolano'];
        foreach (['district_id','province_id','department_id'] as $field) $customer[$field]='-';
        $printNote->customer=$customer;
        $establishment=(array)$printNote->establishment;
        foreach (['district_id','province_id','department_id'] as $field) $establishment[$field]='-';
        $printNote->establishment=$establishment;
        foreach ($printNote->items as $item) {
            $itemData=(array)$item->item; $itemData['lots']=[]; $itemData['is_set']=$itemData['is_set'] ?? 0; $item->item=$itemData;
        }
        foreach ([['default','a4'],['Plantilla_personalizable','a4'],['Plantilla_personalizable','ticket'],
            ['Plantilla_personalizable','ticket_58'],['Plantilla_personalizable','ticket_80'],['modern-2027','a4'],
            ['modern-2027','ticket'],['modern-2027','ticket_58'],['modern-2027','ticket_80']] as [$template,$format]) {
            $html=(new \App\CoreFacturalo\Template())->pdf($template,'sale_note',\App\Models\Tenant\Company::active(),$printNote,$format,['is_preview'=>false]);
            self::assertStringContainsString('Recibido USD 5.00',$html,$template.'/'.$format);
            self::assertStringContainsString('Aplicado VES 50.00',$html,$template.'/'.$format);
            self::assertStringNotContainsString('<h4>COTIZACIÓN</h4>',$html,$template.'/'.$format);
            self::assertStringContainsString('26-11-2026',$html,$template.'/'.$format);
            self::assertStringContainsString('26-12-2026',$html,$template.'/'.$format);
            $printNote->payment_condition_id='03';
            $installmentHtml=(new \App\CoreFacturalo\Template())->pdf($template,'sale_note',\App\Models\Tenant\Company::active(),$printNote,$format,['is_preview'=>false]);
            self::assertStringContainsString('26-11-2026',$installmentHtml,$template.'/'.$format.' crédito con cuotas');
            self::assertStringContainsString('26-12-2026',$installmentHtml,$template.'/'.$format.' crédito con cuotas');
            self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$template.'/'.$format.' crédito con cuotas');
            $printNote->payment_condition_id='02';
            if ($template==='modern-2027' && str_starts_with($format,'ticket')) {
                self::assertStringNotContainsString('Recibido:</td>',$html);
                self::assertStringNotContainsString('Vuelto:</td>',$html);
            }
            $paper=$format==='a4' ? 'A4' : [$format==='ticket_58' ? 56 : 78,300];
            $options=['tempDir'=>sys_get_temp_dir(),'format'=>$paper,'default_font'=>'arial'];
            if (str_starts_with($format,'ticket')) $options+=['margin_top'=>0,'margin_right'=>1,'margin_bottom'=>0,'margin_left'=>1];
            $pdf=new \Mpdf\Mpdf($options);
            $pdf->WriteHTML(file_get_contents(app_path('CoreFacturalo/Templates/pdf/'.$template.'/style.css')),\Mpdf\HTMLParserMode::HEADER_CSS);
            $pdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);
            $bytes=$pdf->Output('','S');
            self::assertStringStartsWith('%PDF-',$bytes);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/note-'.$template.'-'.$format.'.pdf',$bytes);
        }
        $db->table('establishments')->where('id',$note->establishment_id)->update(['template_ticket_pdf'=>'modern-2027']);
        $productionPdf=(new \App\CoreFacturalo\Facturalo())->createPdf($printNote,'sale_note','ticket_58','string');
        self::assertStringStartsWith('%PDF-',$productionPdf);
        if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/note-modern-production-58.pdf',$productionPdf);
        $sourceRow=$printNote->payments->first();
        $displayPayments=collect([$sourceRow]);
        foreach ([1,2] as $i) {
            $copy=clone $sourceRow;
            $copy->payment_method_snapshot=['description'=>'Medio bancario de prueba con descripción extensa número '.$i];
            $copy->reference='REFERENCIA-LARGA-CODEX-'.$i;
            $displayPayments->push($copy);
        }
        $printNote->setRelation('payments',new \Illuminate\Database\Eloquent\Collection($displayPayments->all()));
        $multiPdf=(new \App\CoreFacturalo\Facturalo())->createPdf($printNote,'sale_note','ticket_58','string');
        self::assertStringStartsWith('%PDF-',$multiPdf);
        if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/note-modern-multiple-58.pdf',$multiPdf);
    }

    public function test_saved_credit_installments_preserve_cross_currency_receipt_through_conversion_and_pdf(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CREDIT-03-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $controller=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $fees=[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'60'],
            ['date'=>'2026-12-26','currency_type_id'=>'VES','amount'=>'122']];
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV',
            'exchange_rate_sale'=>'10','payment_condition_id'=>'02','fee'=>$fees,
            'payments'=>[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01','payment_destination_id'=>$bank->id,
                'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50']]]);
        $result=$controller->storeWithData($saleInput);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la nota.');
        $note=\App\Models\Tenant\SaleNote::findOrFail($result['data']['id']);
        self::assertSame('02',$note->payment_condition_id);
        self::assertSame(['60','122'],$note->fee()->orderBy('date')->pluck('amount')->all());
        $receipt=$note->payments()->firstOrFail();
        self::assertSame('USD',$receipt->currency_type_id);
        self::assertSame('5',$receipt->original_amount);
        self::assertSame('50',$receipt->payment);
        $input['exchange_rate_sale']='10';
        $input['sale_note_id']=$note->id;
        $input['payment_condition_id']='02';
        $input['fee']=$fees;
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$receipt]))->first()];
        $result=(new \App\Http\Controllers\Tenant\DocumentController())->storeWithData($input,true);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo guardar la factura.');
        $document=\App\Models\Tenant\Document::findOrFail($result['data']['id']);
        self::assertSame('02',$document->payment_condition_id);
        self::assertSame(['60','122'],$document->fee()->orderBy('date')->pluck('amount')->all());
        self::assertSame('182',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        self::assertSame((string)$receipt->id,$document->payments()->firstOrFail()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        self::assertSame('account',Movement::row($receipt->fresh()->global_payment)['method']['collection_kind']);

        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/credit03-views','view.paths'=>[base_path('resources/views')],
            'filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $db->table('establishments')->where('id',$note->establishment_id)->update(['template_ticket_pdf'=>'modern-2027']);
        foreach (['sale_note'=>$note->fresh(),'invoice'=>$document->fresh()] as $type=>$printDocument) {
            $customer=(array)$printDocument->customer; $customer['identity_document_type']=['description'=>'Venezolano'];
            foreach (['district_id','province_id','department_id'] as $field) $customer[$field]='-';
            $printDocument->customer=$customer;
            $establishment=(array)$printDocument->establishment;
            foreach (['district_id','province_id','department_id'] as $field) $establishment[$field]='-';
            $printDocument->establishment=$establishment;
            foreach ($printDocument->items as $item) {
                $itemData=(array)$item->item; $itemData['lots']=[]; $itemData['is_set']=$itemData['is_set'] ?? 0; $item->item=$itemData;
            }
            $html=(new \App\CoreFacturalo\Template())->pdf('modern-2027',$type,\App\Models\Tenant\Company::active(),$printDocument,'ticket_58',['is_preview'=>false]);
            foreach (['26-11-2026','26-12-2026','Recibido USD 5.00','Aplicado VES 50.00'] as $text) self::assertStringContainsString($text,$html,$type);
            $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>[56,300],'margin_top'=>0,'margin_right'=>1,'margin_bottom'=>0,'margin_left'=>1,'default_font'=>'arial']);
            $pdf->WriteHTML($html);
            $bytes=$pdf->Output('','S');
            self::assertStringStartsWith('%PDF-',$bytes);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/credit-03-'.$type.'.pdf',$bytes);
            $production=(new \App\CoreFacturalo\Facturalo())->createPdf($printDocument,$type,'ticket_58','string');
            self::assertStringStartsWith('%PDF-',$production);
            self::assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$production),$type.' debe caber en una página de ticket.');
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/credit-03-'.$type.'-production.pdf',$production);
        }
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        $itemFilters=['type'=>'sale','document_type_id'=>'01','date_start'=>'2026-01-01','date_end'=>'2027-01-01'];
        $itemRecords=(new \Modules\Report\Http\Controllers\ReportGeneralItemController())->getRecordsItems($itemFilters)->get();
        self::assertCount(1,$itemRecords);
        self::assertTrue($itemRecords->first()->document->relationLoaded('payments'));
        self::assertTrue($itemRecords->first()->document->relationLoaded('fee'));
        $export=(new \Modules\Report\Exports\GeneralItemExport())->records($itemRecords)->type('sale')
            ->document_type_id('01')->request_apply_conversion_to_pen('false');
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $file=(new \Maatwebsite\Excel\Writer($temporaryFiles))->export($export,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $book=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file->getLocalPath());
            $text=implode("\n",collect($book->getActiveSheet()->toArray(null,true,false,false))->flatten()->map(fn($v)=>(string)$v)->all());
            foreach (['Recibido USD 5','Aplicado VES 50','Cuota #1 — VES 60 — Vence 2026-11-26',
                'Cuota #2 — VES 122 — Vence 2026-12-26'] as $expected) self::assertStringContainsString($expected,$text);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) copy($file->getLocalPath(),$dir.'/general-items-receipt.xlsx');
            $book->disconnectWorksheets();
        } finally { $file->delete(); }
    }

    public function test_credit_invoice_from_sale_note_reuses_cross_currency_receipt_and_keeps_balance(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update([
            'collection_kind'=>'account','currency_type_id'=>'USD','cash_currency_type_id'=>null,'is_cash'=>0,'is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'CREDIT-CONVERSION-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[['date_of_payment'=>'2026-10-26','payment_method_type_id'=>'01',
            'payment_destination_id'=>$bank->id,'currency_type_id'=>'USD','original_amount'=>'5','exchange_rate'=>'10','payment'=>'50',
            'reference'=>'CREDIT-REF-001']]);
        $source=$note->payments()->firstOrFail();
        $input['sale_note_id']=$note->id;
        $input['payment_condition_id']='02';
        $input['fee']=[['date'=>'2026-11-26','currency_type_id'=>'VES','amount'=>'182']];
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$source]))->first()];
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $httpCalls=0;
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function () use (&$httpCalls) {
            $httpCalls++;
            return \Illuminate\Support\Facades\Http::response([],500);
        });
        $result=(new \App\Http\Controllers\Tenant\DocumentController())->storeWithData($input,true);
        self::assertTrue($result['success']);
        self::assertSame(0,$httpCalls);
        $document=\App\Models\Tenant\Document::findOrFail($result['data']['id']);
        self::assertSame('02',$document->payment_condition_id);
        self::assertSame('182',\App\Support\Exact\Decimal::represent(\App\Support\Exact\Receivables::balancesForDocuments([$document])[$document->id]));
        self::assertSame('182',$document->fee()->firstOrFail()->amount);
        $applied=$document->payments()->firstOrFail();
        self::assertSame('USD',$applied->currency_type_id);
        self::assertSame('5',$applied->original_amount);
        self::assertSame('50',$applied->payment);
        self::assertSame((string)$source->id,$applied->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
        $db->table('payment_method_types')->where('id','01')->update(['description'=>'Nombre posterior','collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES']);
        $details=\App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($document->fresh());
        self::assertSame($source->payment_method_snapshot['description'],$details['PAGOS'][0]['description']);
        self::assertSame('Cobro previo',$details['PAGOS'][0]['kind']);
        self::assertStringContainsString('Cobro previo',$details['PAGOS'][0]['display']);
        self::assertSame('USD',$details['PAGOS'][0]['currency_type_id']);
        self::assertSame('5',$details['PAGOS'][0]['original_amount']);
        self::assertSame('50',$details['PAGOS'][0]['amount']);
        self::assertStringContainsString('Recibido USD 5',$details['PAGOS'][0]['display']);
        self::assertStringContainsString('Aplicado VES 50',$details['PAGOS'][0]['display']);
        self::assertSame('Cuota #1',$details['CUOTA'][0]['description']);
        self::assertSame('182',$details['CUOTA'][0]['amount']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/credit-ticket-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        foreach ([['default','ticket_58'],['nrus','ticket_58'],['marca_de_agua','ticket_50'],
            ['marca_de_agua','ticket_58'],['Plantilla_personalizable','ticket_58']] as [$template,$format]) {
            $printDocument=$document->fresh();
            $customer=(array)$printDocument->customer;
            $customer['identity_document_type']=['description'=>'Venezolano'];
            foreach (['district_id','province_id','department_id'] as $field) $customer[$field]='-';
            $printDocument->customer=$customer;
            $establishment=(array)$printDocument->establishment;
            foreach (['district_id','province_id','department_id'] as $field) $establishment[$field]='-';
            $printDocument->establishment=$establishment;
            try {
                $html=(new \App\CoreFacturalo\Template())->pdf($template,'invoice',\App\Models\Tenant\Company::active(),$printDocument,$format,['is_preview'=>false]);
            } catch (\Throwable $e) {
                while ($e->getPrevious()) $e=$e->getPrevious();
                throw new \RuntimeException($e->getMessage().' en '.$e->getFile().':'.$e->getLine(),0,$e);
            }
            self::assertStringContainsString('CONDICIÓN: CRÉDITO',$html);
            self::assertStringContainsString('Cuota #1',$html);
            self::assertStringContainsString('Recibido USD 5.00',$html);
            self::assertStringContainsString('Aplicado VES 50.00',$html);
            $printDocument->payment_condition_id='03';
            $installmentHtml=(new \App\CoreFacturalo\Template())->pdf($template,'invoice',\App\Models\Tenant\Company::active(),$printDocument,$format,['is_preview'=>false]);
            self::assertStringContainsString('Cuota #1',$installmentHtml,$template.'/'.$format.' crédito con cuotas');
            self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$template.'/'.$format.' crédito con cuotas');
            $printDocument->payment_condition_id='02';
            $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>[$format==='ticket_50' ? 50 : 56,250],
                'margin_top'=>0,'margin_right'=>1,'margin_bottom'=>0,'margin_left'=>1,'default_font'=>'arial']);
            $pdf->WriteHTML($html);
            $bytes=$pdf->Output('','S');
            self::assertStringStartsWith('%PDF-',$bytes);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/'.$template.'-'.$format.'.pdf',$bytes);
        }
        config(['filesystems.disks.public'=>['driver'=>'local','root'=>sys_get_temp_dir()]]);
        foreach ($printDocument->items as $item) {
            $itemData=(array)$item->item;
            $itemData['lots']=[];
            $itemData['is_set']=$itemData['is_set'] ?? 0;
            $item->item=$itemData;
        }
        foreach ([['default','a4'],['default','a5'],['default','ticket'],['nrus','ticket'],
            ['marca_de_agua','a4'],['marca_de_agua','a5'],['marca_de_agua','ticket'],
            ['Plantilla_personalizable','a4'],['Plantilla_personalizable','a5'],['Plantilla_personalizable','ticket'],
            ['modern-2027','a4'],['modern-2027','a5'],['modern-2027','ticket'],['modern-2027','ticket_58']] as [$template,$format]) {
            $view=(new \App\CoreFacturalo\Template())->validate_template($template,'invoice',$format);
            $html=view($view,['company'=>\App\Models\Tenant\Company::active(),'document'=>$printDocument,'configuration'=>['is_preview'=>false]])->render();
            self::assertStringContainsString('Recibido USD 5.00',$html,$view);
            self::assertStringContainsString('Aplicado VES 50.00',$html,$view);
            self::assertStringContainsString('26-11-2026',$html,$view);
            $printDocument->payment_condition_id='03';
            $installmentHtml=view($view,['company'=>\App\Models\Tenant\Company::active(),'document'=>$printDocument,'configuration'=>['is_preview'=>false]])->render();
            self::assertStringContainsString('26-11-2026',$installmentHtml,$view.' crédito con cuotas');
            self::assertStringContainsString('Recibido USD 5.00',$installmentHtml,$view.' crédito con cuotas');
            $printDocument->payment_condition_id='02';
            if ($template==='modern-2027' && str_starts_with($format,'ticket')) {
                self::assertStringNotContainsString('Recibido:</td>',$html,$view);
                self::assertStringNotContainsString('Vuelto:</td>',$html,$view);
            }
            $paper=$format==='a4' ? 'A4' : ($format==='a5' ? 'A5' : [$format==='ticket_58' ? 56 : 78,250]);
            $options=['tempDir'=>sys_get_temp_dir(),'format'=>$paper,'default_font'=>'arial'];
            if (str_starts_with($format,'ticket')) $options+=['margin_top'=>0,'margin_right'=>1,'margin_bottom'=>0,'margin_left'=>1];
            $pdf=new \Mpdf\Mpdf($options);
            $pdf->WriteHTML($html);
            $bytes=$pdf->Output('','S');
            self::assertStringStartsWith('%PDF-',$bytes,$view);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/'.$template.'-'.$format.'.pdf',$bytes);
        }
        foreach (['default','nrus','marca_de_agua','Plantilla_personalizable','modern-2027'] as $productionTemplate) {
            $db->table('establishments')->where('id',$document->establishment_id)->update(['template_ticket_pdf'=>$productionTemplate]);
            $productionPdf=(new \App\CoreFacturalo\Facturalo())->createPdf($printDocument,'invoice','ticket_58','string');
            self::assertStringStartsWith('%PDF-',$productionPdf,$productionTemplate);
            self::assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$productionPdf),$productionTemplate.' debe mantener esta muestra en una página.');
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/'.$productionTemplate.'-production-58.pdf',$productionPdf);
        }
        $secondInput=$input;
        $secondInput['sale_note_id']=null; $secondInput['payments']=[]; $secondInput['fee']=[];
        $second=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($secondInput)->getDocument();
        $filters=['period'=>'between_dates','date_start'=>'2026-01-01','date_end'=>'2027-01-01','document_type_id'=>'01'];
        $report=new \Modules\Report\Http\Controllers\ReportDocumentController();
        $db->enableQueryLog();
        $measure=function (array $ids) use ($db,$report,$filters) {
            $db->flushQueryLog();
            $records=$report->getRecords($filters,\App\Models\Tenant\Document::class)->whereIn('id',$ids)->get();
            foreach ($records as $record) \App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($record);
            return count($db->getQueryLog());
        };
        $oneQueries=$measure([$document->id]);
        $twoQueries=$measure([$document->id,$second->id]);
        self::assertSame($oneQueries,$twoQueries,'El reporte debe cargar pagos y cuotas por lote.');
        $db->disableQueryLog();
        $app['view']->addNamespace('report',base_path('modules/Report/Resources/views'));
        $records=$report->getRecords($filters,\App\Models\Tenant\Document::class)->where('id',$document->id)->get();
        $screen=(new \Modules\Report\Http\Resources\DocumentCollection($records))
            ->toArray(\Illuminate\Http\Request::create('/','GET'));
        self::assertStringContainsString('Recibido USD 5',$screen[0]['payment_method']);
        self::assertSame($applied->payment_method_snapshot['description'],$screen[0]['payment_form']);
        self::assertSame(['n'=>'232','d'=>'1'],$screen[0]['report_total_fractions']['total']);
        $apiRecords=$report->getRecords($filters,\App\Models\Tenant\Document::class)->where('id',$document->id)->get();
        $apiRows=(new \Modules\Report\Http\Controllers\Api\ReportController())->getCollectionDocuments($apiRecords);
        self::assertSame($applied->payment_method_snapshot['description'],$apiRows[0]['payments']['PAGOS'][0]['description']);
        self::assertStringContainsString('Recibido USD 5',$apiRows[0]['payments']['PAGOS'][0]['display']);
        self::assertStringContainsString('Aplicado VES 50',$apiRows[0]['payments']['PAGOS'][0]['display']);
        self::assertSame('Cuota #1',$apiRows[0]['payments']['CUOTA'][0]['description']);
        $records=$report->getRecords($filters,\App\Models\Tenant\Document::class)->where('id',$document->id)->get();
        $company=\App\Models\Tenant\Company::active();
        $establishment=\App\Models\Tenant\Establishment::findOrFail($document->establishment_id);
        $filters+=['seller_id'=>null,'person_id'=>null,'user_type'=>'admin'];
        $categories=collect(); $categories_services=collect();
        foreach (['report_pdf','report_excel','report_excel_standard'] as $reportView) {
            $templateSource=file_get_contents(base_path('modules/Report/Resources/views/documents/'.$reportView.'.blade.php'));
            preg_match_all('/\\$columns->([a-zA-Z_]+)/',$templateSource,$matches);
            $columns=new \stdClass();
            foreach (array_unique($matches[1]) as $name) $columns->$name=(object)['title'=>$name,'visible'=>in_array($name,['payment_form','payment_method'],true)];
            $html=view('report::documents.'.$reportView,compact('records','company','establishment','filters','columns','categories','categories_services'))->render();
            self::assertStringContainsString('Recibido USD 5',$html,$reportView);
            self::assertStringContainsString('Aplicado VES 50',$html,$reportView);
            self::assertStringContainsString($applied->payment_method_snapshot['description'],$html,$reportView);
            if ($reportView==='report_pdf') {
                $pdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>'A4-L']);
                $pdf->WriteHTML($html);
                $bytes=$pdf->Output('','S');
                self::assertStringStartsWith('%PDF-',$bytes);
                if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/document-report.pdf',$bytes);
            } else {
                $workbook=(new \PhpOffice\PhpSpreadsheet\Reader\Html())->loadFromString($html);
                $file=tempnam(sys_get_temp_dir(),'report-xlsx-');
                try {
                    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save($file);
                    $reopened=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
                    $text=implode(' ',array_map(fn ($row)=>implode(' ',array_map(fn ($cell)=>(string)$cell,$row)),
                        $reopened->getActiveSheet()->toArray()));
                    self::assertStringContainsString('Recibido USD 5',$text,$reportView);
                    self::assertStringContainsString('Aplicado VES 50',$text,$reportView);
                    $reopened->disconnectWorksheets();
                } finally {
                    $workbook->disconnectWorksheets();
                    unlink($file);
                }
            }
        }
        $db->table('documents')->where('id',$second->id)->update(['currency_type_id'=>'USD','total'=>'77.77','calculation_snapshot'=>null]);
        $mixedRecords=$report->getRecords($filters,\App\Models\Tenant\Document::class)->whereIn('id',[$document->id,$second->id])->get();
        foreach (['report_pdf','report_excel'] as $reportView) {
            $templateSource=file_get_contents(base_path('modules/Report/Resources/views/documents/'.$reportView.'.blade.php'));
            preg_match_all('/\$columns->([a-zA-Z_]+)/',$templateSource,$matches);
            $columns=new \stdClass();
            foreach (array_unique($matches[1]) as $name) $columns->$name=(object)['title'=>$name,'visible'=>in_array($name,['payment_form','payment_method'],true)];
            $mixedHtml=view('report::documents.'.$reportView,['records'=>$mixedRecords,'company'=>$company,'establishment'=>$establishment,
                'filters'=>$filters,'columns'=>$columns,'categories'=>$categories,'categories_services'=>$categories_services])->render();
            $dom=new \DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$mixedHtml);
            $xpath=new \DOMXPath($dom);
            $totalRows=$xpath->query('//tr[td[contains(normalize-space(.), "TOTAL GENERAL")]]');
            self::assertSame(1,$totalRows->length,$reportView);
            $cells=$xpath->query('./td',$totalRows->item(0));
            self::assertSame('232.00',trim($cells->item(1)->textContent),$reportView.' VES');
            self::assertSame('77.77',trim($cells->item(2)->textContent),$reportView.' USD');
            if ($reportView==='report_pdf') {
                $mixedPdf=new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>'A4-L']);
                $mixedPdf->WriteHTML($mixedHtml);
                $bytes=$mixedPdf->Output('','S');
                self::assertStringStartsWith('%PDF-',$bytes);
                if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/mixed-currency-report.pdf',$bytes);
            }
        }
        foreach (['report_pdf_standard','report_pdf_simple'] as $reportView) {
            $mixedHtml=view('report::documents.'.$reportView,['records'=>$mixedRecords,'company'=>$company,
                'establishment'=>$establishment,'filters'=>$filters])->render();
            self::assertStringContainsString('Cobro previo',$mixedHtml,$reportView);
            self::assertStringContainsString('Recibido USD 5',$mixedHtml,$reportView);
            self::assertStringContainsString('Aplicado VES 50',$mixedHtml,$reportView);
            $dom=new \DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$mixedHtml);
            $xpath=new \DOMXPath($dom);
            foreach (['VES'=>'232.00','USD'=>'77.77'] as $currency=>$expected) {
                $rows=$xpath->query('//tr[td[contains(normalize-space(.), "Totales '.$currency.'")]]');
                self::assertSame(1,$rows->length,$reportView.' '.$currency);
                $cells=$xpath->query('./td',$rows->item(0));
                self::assertSame($reportView==='report_pdf_standard' ? '17' : '16',$cells->item(0)->getAttribute('colspan'));
                self::assertSame($expected,trim($cells->item(4)->textContent),$reportView.' '.$currency);
            }
            $pdf=new \Dompdf\Dompdf(); $pdf->setPaper('A4','landscape'); $pdf->loadHtml($mixedHtml); $pdf->render();
            $bytes=$pdf->output(); self::assertStringStartsWith('%PDF-',$bytes);
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) file_put_contents($dir.'/'.$reportView.'-mixed.pdf',$bytes);
        }
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $writer=new \Maatwebsite\Excel\Writer($temporaryFiles);
        $standard=(new \Modules\Report\Exports\DocumentExportStandard())->records($mixedRecords)->company($company)
            ->establishment($establishment)->filters($filters)->categories($categories)->categories_services($categories_services);
        $standardFile=$writer->export($standard,\Maatwebsite\Excel\Excel::XLSX);
        try {
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) copy($standardFile->getLocalPath(),$dir.'/mixed-currency-standard.xlsx');
            $standardWorkbook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($standardFile->getLocalPath());
            $sheet=$standardWorkbook->getActiveSheet(); $found=[];
            foreach ($sheet->getRowIterator() as $row) {
                $label=$sheet->getCell('Y'.$row->getRowIndex())->getValue();
                if (in_array($label,['Totales VES','Totales USD'],true)) $found[$label]=(string) $sheet->getCell('AF'.$row->getRowIndex())->getValue();
            }
            self::assertSame(['Totales VES'=>'232','Totales USD'=>'77.77'],$found);
            $standardWorkbook->disconnectWorksheets();
        } finally {
            $standardFile->delete();
        }
        $db->table('documents')->where('id',$second->id)->update(['currency_type_id'=>'VES']);
        $html=view('report::state_account.report_excel',compact('records','company','establishment','filters','categories','categories_services'))->render();
        self::assertStringContainsString('Recibido USD 5',$html);
        self::assertStringContainsString('Aplicado VES 50',$html);
        self::assertStringContainsString('CREDIT-REF-001',$html);
        self::assertStringContainsString('Cuota #1',$html);
        $general=new \Modules\Report\Http\Controllers\ReportGeneralItemController();
        $itemFilters=['type'=>'sale','document_type_id'=>'01','date_start'=>'2026-01-01','date_end'=>'2027-01-01'];
        $itemRecords=$general->getRecordsItems($itemFilters)->where('document_id',$document->id)->get();
        $html=view('report::general_items.report_excel',['records'=>$itemRecords,'type'=>'sale',
            'document_type_id'=>'01','request_apply_conversion_to_pen'=>'false'])->render();
        self::assertStringContainsString('Recibido USD 5',$html);
        self::assertStringContainsString('Aplicado VES 50',$html);
        self::assertStringContainsString($applied->payment_method_snapshot['description'],$html);
        $itemExport=(new \Modules\Report\Exports\GeneralItemExport())
            ->records($itemRecords)->type('sale')->document_type_id('01')->request_apply_conversion_to_pen('false');
        $temporaryFiles=new \Maatwebsite\Excel\Files\TemporaryFileFactory(sys_get_temp_dir());
        $app->instance(\Maatwebsite\Excel\Files\TemporaryFileFactory::class,$temporaryFiles);
        $writer=new \Maatwebsite\Excel\Writer($temporaryFiles);
        $itemFile=$writer->export($itemExport, \Maatwebsite\Excel\Excel::XLSX);
        try {
            if ($dir=getenv('PRO9_TICKET_EVIDENCE_DIR')) copy($itemFile->getLocalPath(),$dir.'/items-report.xlsx');
            $reopened=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($itemFile->getLocalPath());
            $text=implode(' ',array_map(fn ($row)=>implode(' ',array_map(fn ($cell)=>(string)$cell,$row)),
                $reopened->getActiveSheet()->toArray()));
            self::assertStringContainsString('Recibido USD 5',$text);
            self::assertStringContainsString('Aplicado VES 50',$text);
            $detailColumn=null;
            foreach ($reopened->getActiveSheet()->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    if (strpos((string) $cell->getValue(),'Recibido USD 5') !== false) $detailColumn=$cell->getColumn();
                }
            }
            self::assertNotNull($detailColumn);
            self::assertGreaterThan(13,$reopened->getActiveSheet()->getColumnDimension($detailColumn)->getWidth());
            $reopened->disconnectWorksheets();
        } finally {
            $itemFile->delete();
        }
        $db->table('company_accounts')->insert([
            'subtotal_pen'=>'701','total_pen'=>'121','igv_pen'=>'401',
            'subtotal_usd'=>'702','total_usd'=>'122','igv_usd'=>'402',
        ]);
        $accounting=new \Modules\Account\Http\Controllers\AccountController();
        $accountingDocument=$document->fresh();
        // La condición se cambia sólo en memoria para ejercitar la exportación de contado.
        $accountingDocument->payment_condition_id='01';
        $ejbMethod=new \ReflectionMethod($accounting,'getStructureEjbExcel');
        $ejbRows=$ejbMethod->invoke($accounting,collect([$accountingDocument]));
        self::assertSame('104101',$ejbRows[0]['automatic_payment_account']);
        $ejbExport=(new \Modules\Account\Exports\ReportAccountingEjbExport())->data(['records'=>$ejbRows]);
        $ejbFile=$writer->export($ejbExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $ejbWorkbook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($ejbFile->getLocalPath());
            self::assertSame('104101',(string) $ejbWorkbook->getActiveSheet()->getCell('Z2')->getValue());
            $ejbWorkbook->disconnectWorksheets();
        } finally {
            $ejbFile->delete();
        }
        $contasisMethod=new \ReflectionMethod($accounting,'getStructureContasis');
        $contasisRows=$contasisMethod->invoke($accounting,collect([$accountingDocument]));
        self::assertSame('CON',$contasisRows[0]['payment_condition']);
        self::assertSame('',$contasisRows[0]['payment_method']);
        $app['view']->addNamespace('account',base_path('modules/Account/Resources/views'));
        $contasisExport=(new \Modules\Account\Exports\ReportAccountingContasisExport())->data(['records'=>$contasisRows]);
        $contasisFile=$writer->export($contasisExport,\Maatwebsite\Excel\Excel::XLSX);
        try {
            $contasisWorkbook=(new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($contasisFile->getLocalPath());
            self::assertSame('CON',$contasisWorkbook->getActiveSheet()->getCell('X1')->getValue());
            self::assertEmpty($contasisWorkbook->getActiveSheet()->getCell('AM1')->getValue());
            $contasisWorkbook->disconnectWorksheets();
        } finally {
            $contasisFile->delete();
        }
    }

    public function test_sale_note_reuses_a_cross_currency_bank_receipt_without_a_second_movement(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD','number'=>'SN-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10.00000000';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $row=['payment'=>'50','original_amount'=>'5','currency_type_id'=>'USD','exchange_rate'=>'10.00000000',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'date_of_payment'=>'2026-10-26'];
        try {
            $controller->savePayments($note,[array_replace($row,['id'=>123])]);
            self::fail('Un cobro de cotización no debe copiarse como nueva recepción.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame(0,$db->table('global_payments')->count());
        $controller->savePayments($note,[$row]);
        $source=$note->payments()->firstOrFail();
        self::assertSame('50',$source->payment);
        self::assertSame('5',$source->original_amount);
        self::assertSame('USD',$source->currency_type_id);
        self::assertSame('5',Movement::row($source->global_payment)['amount']);
        foreach ([\App\Http\Resources\Tenant\SaleNoteResource::class, \App\Http\Resources\Tenant\SaleNoteResource2::class, \App\Http\Resources\Tenant\SaleNoteGenerateDocumentResource::class] as $resourceClass) {
            $responseRow=$resourceClass::getTransformPayments(collect([$source]))->first();
            self::assertSame('USD',$responseRow['currency_type_id']);
            self::assertSame('5',$responseRow['original_amount']);
            self::assertSame('10.00000000',$responseRow['exchange_rate']);
        }
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'VES','description'=>'Nombre actualizado']);
        $frozen=$source->payment_method_snapshot;
        $responseAfterChange=\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$source->fresh()]))->first();
        self::assertSame($frozen['description'],$responseAfterChange['payment_method_snapshot']['description']);
        $savedRows=$note->getTransformPayments()->all();
        $newRows=$db->transaction(fn()=>\App\Services\Payments\ExistingReceipts::synchronize($note,$savedRows,'VES'));
        self::assertSame([],$newRows);
        self::assertSame($frozen,$source->fresh()->payment_method_snapshot);
        self::assertSame('USD',$source->fresh()->currency_type_id);
        $url=$this->createMock(\Illuminate\Contracts\Routing\UrlGenerator::class);
        $url->method('to')->willReturn('http://fiscal-schema.example.test');
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class,$url);
        $record=(new \App\Http\Resources\Tenant\SaleNoteResource($note))->toArray(new \Illuminate\Http\Request());
        $historic=$record['recorded_payments']->first();
        self::assertSame('USD',$historic['currency_type_id']);
        self::assertSame('5',$historic['original_amount']);
        self::assertSame(\App\Services\Payments\ReceiptPresentation::destination($source),$historic['payment_destination_description']);
        $editController=$this->getMockBuilder(\App\Http\Controllers\Tenant\SaleNoteController::class)->onlyMethods(['createPdf'])->getMock();
        $edit=array_replace($saleInput,['id'=>$note->id,'number'=>$note->number,'payments'=>$savedRows]);
        $result=$editController->storeWithData($edit);
        self::assertTrue($result['success'],$result['message'] ?? 'No se pudo editar la nota de venta.');
        self::assertSame($frozen,$source->fresh()->payment_method_snapshot);
        self::assertSame('USD',$source->fresh()->currency_type_id);
        self::assertSame(1,$db->table('global_payments')->count());
        $input['sale_note_id']=$note->id;
        $input['payments']=[\App\Http\Resources\Tenant\SaleNoteResource::getTransformPayments(collect([$source->fresh()]))->first()];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame((string)$source->id,$document->payments->first()->payment_method_snapshot['receipt_origin']['id']);
        self::assertSame(1,$db->table('global_payments')->count());
        $reused=\App\Services\Payments\DocumentPaymentReport::row($document->payments()->firstOrFail());
        self::assertSame('Cobro reutilizado',$reused['kind']);
        self::assertSame('0',$reused['payment']);
        self::assertSame('50',$reused['applied_payment']);
        self::assertSame('USD',$reused['currency_type_id']);
        self::assertSame('VES',$reused['document_currency_type_id']);
        self::assertSame('10.00000000',$reused['exchange_rate']);
        self::assertSame($frozen['description'],$reused['payment_method_type_description']);
        $documentPayment=$document->payments()->firstOrFail();
        $finance = new class { use \Modules\Finance\Traits\FinanceTrait; };
        $finance->finance_cash_document(new \App\Models\Tenant\Document(), $document->id);
        $finance->createCashDocumentPayment($documentPayment, true);
        self::assertFalse($db->table('cash_document_payments')->where('document_payment_id', $documentPayment->id)->exists());
        $resource=(new \App\Http\Resources\Tenant\DocumentPaymentCollection(collect([$documentPayment])))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'))->first();
        self::assertTrue($resource['receipt_reused']);
        self::assertSame('10.00000000',(string)$resource['exchange_rate']);
        self::assertSame($frozen['description'],$resource['payment_method_type_description']);
        $saleResource=(new \App\Http\Resources\Tenant\SaleNotePaymentCollection(collect([$source->fresh()])))
            ->toArray(\Illuminate\Http\Request::create('/', 'GET'))->first();
        self::assertSame($frozen['description'],$saleResource['payment_method_type_description']);
        self::assertSame('USD · USD',$saleResource['destination_description']);
        $db->table('payment_method_types')->where('id','13')->update(['currency_type_id'=>'USD']);
        $normalPayment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,['date_of_payment'=>'2026-10-26',
            'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'currency_type_id'=>'USD',
            'original_amount'=>'6.60','exchange_rate'=>'10','igtf_status'=>'not_applicable','operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $db->table('payment_method_types')->where('id','13')->update(['description'=>'Nombre posterior']);
        $normalPayment=$normalPayment->fresh();
        $normal=\App\Services\Payments\DocumentPaymentReport::row($normalPayment);
        self::assertSame('Cobro',$normal['kind']);
        self::assertSame('6.6',$normal['payment']);
        self::assertSame('66',$normal['applied_payment']);
        self::assertSame('Nombre actualizado',$normal['payment_method_type_description']);
        self::assertSame('Vigente',$normal['status']);
        $normalPayment->reversed_at=now();
        $reversed=\App\Services\Payments\DocumentPaymentReport::row($normalPayment);
        self::assertSame('Revertido',$reversed['status']);
        self::assertSame('0',$reversed['payment']);
        self::assertSame('0',$reversed['applied_payment']);
        $app->instance('files',new \Illuminate\Filesystem\Filesystem());
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/report-views','view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'),0777,true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $html=$app['view']->make('tenant.document_payments.report',['records'=>collect([$reused,$normal])])->render();
        self::assertStringContainsString('Moneda del documento',$html);
        self::assertStringContainsString('Tasa Bs./USD',$html);
        self::assertStringContainsString('Cobro reutilizado',$html);
        self::assertStringContainsString('USD',$html);
        self::assertStringContainsString('VES',$html);
        self::assertStringContainsString('Nombre actualizado',$html);
        $pdf=new \Dompdf\Dompdf();
        $pdf->setPaper('a4','landscape'); $pdf->loadHtml($html); $pdf->render();
        self::assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_cash_credit_registration_uses_saved_payment_condition_instead_of_method_id(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture(false);
        Cash::create(['user_id'=>$input['user_id'], 'state'=>true, 'date_opening'=>'2026-10-26', 'time_opening'=>'09:00:00', 'beginning_balance'=>'0']);
        $controller = new \App\Http\Controllers\Tenant\SaleNoteController();
        $base = array_replace($input, ['id'=>null, 'series'=>'NV01', 'document_type_id'=>'80', 'prefix'=>'NV', 'payments'=>[]]);
        $cashNote = \App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($base, [
            'payment_condition_id'=>'01', 'payment_method_type_id'=>'09',
        ])));
        self::assertFalse($db->table('cash_document_credits')->where('sale_note_id', $cashNote->id)->exists());
        $creditNote = \App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($base, [
            'payment_condition_id'=>'02', 'payment_method_type_id'=>'01',
        ])));
        self::assertTrue($db->table('cash_document_credits')->where('sale_note_id', $creditNote->id)->exists());
        // Simular notas sin condición y flags desincronizados: manda la naturaleza.
        $db->table('payment_method_types')->where('id','09')->update(['collection_kind'=>'credit','is_credit'=>0]);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','is_credit'=>1]);
        $legacyCredit=\App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($base, [
            'payment_condition_id'=>null, 'payment_method_type_id'=>'09',
        ])));
        $legacyAccount=\App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($base, [
            'payment_condition_id'=>null, 'payment_method_type_id'=>'01',
        ])));
        self::assertTrue($db->table('cash_document_credits')->where('sale_note_id',$legacyCredit->id)->exists());
        self::assertFalse($db->table('cash_document_credits')->where('sale_note_id',$legacyAccount->id)->exists());
    }

    public function test_sale_note_cross_currency_cash_change_uses_received_currency(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $input['exchange_rate_sale']='10.00000000';
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[]]);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[[
            'payment'=>'300','original_amount'=>'30','currency_type_id'=>'USD','exchange_rate'=>'10.00000000',
            'payment_method_type_id'=>'10','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26',
        ]]);
        $source=$note->payments()->firstOrFail();
        self::assertSame('232',$source->payment);
        self::assertSame('68',$source->change);
        self::assertSame('23.2',$source->original_amount);
        $details=\App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($note->fresh());
        self::assertStringContainsString('Recibido USD 30',$details['PAGOS'][0]['display']);
        self::assertStringContainsString('Aplicado VES 232',$details['PAGOS'][0]['display']);
        self::assertStringContainsString('Vuelto USD 6.8',$details['PAGOS'][0]['display']);
        self::assertSame('23.2',Movement::row($source->global_payment)['amount']);
        self::assertSame('23.2',CashLedger::report($cash)['balances_by_currency']['USD']['final_balance']);
        $transport=(new \App\Http\Resources\Tenant\SaleNotePaymentCollection(collect([$source])))->toArray(\Illuminate\Http\Request::create('/','GET'))->first();
        self::assertSame('30',$transport['tendered_original_amount']);
        self::assertSame('6.8',$transport['received_change']);
        $editTransport=\App\Http\Resources\Tenant\SaleNoteResource2::getTransformPayments(collect([$source]))->first();
        self::assertSame('30',$editTransport['tendered_original_amount']);
        self::assertSame('6.8',$editTransport['received_change']);
        $modelTransport=$note->getTransformPayments()->first();
        self::assertSame('30',$modelTransport['tendered_original_amount']);
        self::assertSame('6.8',$modelTransport['received_change']);
        self::assertNotEmpty($modelTransport['payment_method_snapshot']);
        $legacy=clone $source;
        $legacy->calculation_snapshot=null;
        $legacyTransport=(new \App\Http\Resources\Tenant\SaleNotePaymentCollection(collect([$legacy])))->toArray(\Illuminate\Http\Request::create('/','GET'))->first();
        self::assertNull($legacyTransport['tendered_original_amount']);
        self::assertNull($legacyTransport['received_change']);
        $legacyNote=$note->fresh();
        $legacyNote->setRelation('payments',new \Illuminate\Database\Eloquent\Collection([$legacy]));
        $legacyDetails=\App\CoreFacturalo\Helpers\Template\TemplateHelper::getDetailedPayment($legacyNote);
        self::assertStringContainsString('Neto recibido USD 23.2',$legacyDetails['PAGOS'][0]['display']);
        self::assertStringNotContainsString('Vuelto USD',$legacyDetails['PAGOS'][0]['display']);
        config(['view.compiled'=>sys_get_temp_dir().'/'.$this->database.'/sale-note-views', 'view.paths'=>[base_path('resources/views')]]);
        mkdir(config('view.compiled'), 0777, true);
        $app->register(\Illuminate\View\ViewServiceProvider::class);
        $rendered = (new \App\CoreFacturalo\Template())->pdf('default', 'sale_note', \App\Models\Tenant\Company::active(), $note->fresh(), 'a4', ['is_preview'=>false]);
        self::assertStringContainsString('Recibido USD 30.00', $rendered);
        self::assertStringContainsString('Aplicado VES 232.00', $rendered);
        self::assertStringContainsString('Vuelto USD 6.80', $rendered);
        $pdf = new \Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(), 'default_font'=>'arial']);
        $pdf->WriteHTML($rendered);
        self::assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
        $rentId=$db->table('hotel_rents')->insertGetId(['customer_id'=>$input['customer_id'], 'customer'=>json_encode($input['customer']),
            'hotel_room_id'=>1,'payment_status'=>'PAID','output_date'=>'2026-10-27','output_time'=>'12:00','establishment_id'=>$input['establishment_id']]);
        $orderId=$db->table('hotel_rent_orders')->insertGetId(['hotel_rent_id'=>$rentId,'order_number'=>1,'order_status'=>'PAID',
            'sale_note_id'=>$note->id,'establishment_id'=>$input['establishment_id']]);
        $itemId=$db->table('hotel_rent_items')->insertGetId(['type'=>'PRO','hotel_rent_id'=>$rentId,'hotel_rent_order_id'=>$orderId,
            'item_id'=>$input['items'][0]['item_id'],'item'=>json_encode(['total'=>'232']),'payment_status'=>'PAID']);
        $hotelItem=\Modules\Hotel\Models\HotelRentItem::findOrFail($itemId);
        $hotelInput=['payment'=>'300','original_amount'=>'30','currency_type_id'=>'USD','exchange_rate'=>'10.00000000',
            'payment_method_type_id'=>'10','payment_destination_id'=>'cash'];
        $db->transaction(fn()=>(new \Modules\Hotel\Http\Controllers\HotelRentController())->saveHotelRentItemPayment($hotelInput,$hotelItem));
        self::assertSame((int)$source->id,(int)$hotelItem->payments()->firstOrFail()->source_sale_note_payment_id);
        self::assertSame(1,$db->table('global_payments')->count());
    }

    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    public function test_sale_note_mixed_currency_change_and_unrepresentable_change(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('payment_method_types')->where('id','13')->update(['is_active'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $bank=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'USD',
            'number'=>'MIXED-CHANGE-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
        $controller=new \App\Http\Controllers\Tenant\SaleNoteController();
        $saleInput=array_replace($input,['id'=>null,'series'=>'NV01','document_type_id'=>'80','prefix'=>'NV','payments'=>[],
            'currency_type_id'=>'USD','exchange_rate_sale'=>'400']);
        $note=\App\Models\Tenant\SaleNote::create($controller->mergeData($saleInput));
        foreach ($input['items'] as $line) $note->items()->create($line);
        $controller->savePayments($note,[
            ['payment'=>'200','original_amount'=>'80000','currency_type_id'=>'VES','exchange_rate'=>'400',
                'payment_method_type_id'=>'01','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26'],
            ['payment'=>'100','original_amount'=>'100','currency_type_id'=>'USD','exchange_rate'=>'400',
                'payment_method_type_id'=>'13','payment_destination_id'=>$bank->id,'date_of_payment'=>'2026-10-26'],
        ]);
        $payments=$note->payments()->orderBy('id')->get();
        self::assertSame(['132','100'],$payments->pluck('payment')->all());
        self::assertSame(['68','0'],$payments->pluck('change')->all());
        self::assertSame(['52800','100'],$payments->pluck('original_amount')->all());
        self::assertSame('52800',Movement::row($payments[0]->global_payment)['amount']);
        self::assertSame('100',Movement::row($payments[1]->global_payment)['amount']);
        self::assertSame('52800',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame(2,$db->table('global_payments')->count());

        $another=\App\Models\Tenant\SaleNote::create($controller->mergeData(array_replace($saleInput,[
            'currency_type_id'=>'VES','exchange_rate_sale'=>'3'])));
        foreach ($input['items'] as $line) $another->items()->create($line);
        try {
            $controller->savePayments($another,[[
                'payment'=>'300','original_amount'=>'100','currency_type_id'=>'USD','exchange_rate'=>'3',
                'payment_method_type_id'=>'10','payment_destination_id'=>'cash','date_of_payment'=>'2026-10-26',
            ]]);
            self::fail('El vuelto no expresable en céntimos de USD debe rechazarse.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame(0,$another->payments()->count());
        self::assertSame(2,$db->table('global_payments')->count());
        self::assertSame('52800',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########

    public function test_classified_invoice_change_only_reduces_cash_and_rejects_electronic_excess(): void
    {
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $db->table('payment_method_types')->where('id','01')->update(['collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_cash'=>0]);
        $db->table('payment_method_types')->where('id','04')->update(['collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_cash'=>1]);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $account=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),'description'=>'VES','number'=>'VES-test','currency_type_id'=>'VES','initial_balance'=>'0']);
        $bank=['payment'=>'200','original_amount'=>'200','currency_type_id'=>'VES','exchange_rate'=>'1','payment_method_type_id'=>'01','payment_destination_id'=>$account->id,'date_of_payment'=>'2026-10-26'];
        $input['payments']=[array_replace($bank,['change'=>'999']),array_replace($bank,['payment'=>'50','original_amount'=>'50.004','payment_method_type_id'=>'04','payment_destination_id'=>'cash','change'=>'999'])];
        $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input)->getDocument();
        self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertTrue($document->fresh()->exactBalance()->isZero());
        $bankMovement=Movement::row($document->payments()->where('payment_method_type_id','01')->firstOrFail()->global_payment);
        $cashMovement=Movement::row($document->payments()->where('payment_method_type_id','04')->firstOrFail()->global_payment);
        self::assertSame('200',$bankMovement['amount']);
        self::assertSame('account',$bankMovement['method']['collection_kind']);
        self::assertSame('32',$cashMovement['amount']);
        self::assertSame('cash',$cashMovement['method']['collection_kind']);
        $record=$document->payments()->where('payment_method_type_id','04')->firstOrFail();
        $display=\App\Services\Payments\ReceiptPresentation::tender($record);
        self::assertSame('50',$display['tendered_original_amount']);
        self::assertSame('18',$display['received_change']);
        self::assertSame('50.004',\App\Support\Exact\Decimal::represent($record->exactValue('requested_amount')));
        self::assertSame('-0.004',\App\Support\Exact\Decimal::represent($record->exactValue('cash_adjustment')));
        self::assertSame('18',\App\Support\Exact\Decimal::represent($record->change));
        $bankRecord=$document->payments()->where('payment_method_type_id','01')->firstOrFail();
        self::assertNull($bankRecord->change);
        self::assertSame('200',\App\Support\Exact\Decimal::represent($bankRecord->exactValue('requested_amount')));
        try {
            \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,array_replace($bank,['change'=>'1']));
            self::fail('El abono posterior no debe aceptar un vuelto enviado por el cliente.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('change',$error->errors()); }
        $count=$db->table('documents')->count();
        $input['payments']=[array_replace($bank,['payment'=>'250','original_amount'=>'250'])];
        try {
            (new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input);
            self::fail('Un exceso electrónico no puede convertirse en vuelto físico.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        self::assertSame($count,$db->table('documents')->count());
        self::assertSame('32',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        $oldCash=['payment'=>'50','original_amount'=>'50','currency_type_id'=>'VES','exchange_rate'=>'1',
            'payment_method_type_id'=>'04','source_sale_note_payment_id'=>123];
        try {
            \App\Services\Payments\ReceiptChange::apply([$oldCash, array_replace($bank,['payment'=>'200','original_amount'=>'200'])], '232', 'VES');
            self::fail('El efectivo histórico no puede financiar otro vuelto.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('payments',$error->errors());
        }
        $newCash=array_replace($oldCash,['payment'=>'20','original_amount'=>'20','source_sale_note_payment_id'=>null]);
        $rows=\App\Services\Payments\ReceiptChange::apply([$oldCash, $newCash], '60', 'VES');
        self::assertSame('50',$rows[0]['payment']);
        self::assertSame('10',$rows[1]['payment']);
        self::assertSame('10',$rows[1]['change']);
        $beforeSnapshot=$record->fresh()->calculation_snapshot;
        $movementsBefore=$db->table('global_payments')->count();
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($record,'Reversión de prueba');
        \App\Services\Fiscal\FiscalDocumentPersistence::reverse($record,'Reintento de reversión');
        self::assertNotNull($record->fresh()->reversed_at);
        self::assertSame($beforeSnapshot,$record->fresh()->calculation_snapshot);
        self::assertSame($movementsBefore,$db->table('global_payments')->count());
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
        self::assertSame('32',\App\Support\Exact\Decimal::represent($document->fresh()->exactBalance()));
        $retry=array_replace($bank,['payment'=>'32','original_amount'=>'32','payment_method_type_id'=>'04',
            'payment_destination_id'=>'cash','operation_key'=>$record->operation_key]);
        try {
            \App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$retry);
            self::fail('Una clave revertida no puede volver a recibir dinero.');
        } catch (\Illuminate\Validation\ValidationException $error) { self::assertArrayHasKey('operation_key',$error->errors()); }
        self::assertSame($movementsBefore,$db->table('global_payments')->count());
        self::assertSame('0',CashLedger::report($cash)['balances_by_currency']['VES']['final_balance']);
    }

    public function test_classified_cash_report_performance_against_legacy_at_1000_and_10000_receipts(): void
    {
        if (getenv('PRO9_PAYMENT_BENCHMARK') !== '1') $this->markTestSkipped('Medición explícita en MySQL temporal.');
        [$db,$app,$input]=$this->bootHkaFixture(false);
        $cash=Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
        $db->transaction(fn()=>CashLedger::open($cash,[]));
        $parent=$db->table('income')->insertGetId(['user_id'=>$input['user_id'],'income_type_id'=>$db->table('income_types')->value('id'),'income_reason_id'=>$db->table('income_reasons')->value('id'),
            'establishment_id'=>$input['establishment_id'],'customer'=>'Cliente','currency_type_id'=>'VES','external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'date_of_issue'=>'2026-10-26','time_of_issue'=>'09:00:00','exchange_rate_sale'=>'1','total'=>'100','state_type_id'=>'05','number'=>1,'fiscal_environment'=>'demo']);
        $context=json_encode(['version'=>2,'method'=>['catalog'=>'payment_method_types','id'=>'01','description'=>'Efectivo','collection_kind'=>'cash'],
            'currency_type_id'=>'VES','direction'=>'income','opening'=>false,'date'=>'2026-10-26','number'=>'I-1','exchange_rate'=>'1']);
        $results=[];$count=0;$db->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));$db->listen(function() use(&$count){$count++;});
        foreach ([1000,10000] as $target) {
            $current=$db->table('income_payments')->count();
            foreach (array_chunk(range($current+1,$target),250) as $ids) {
                $db->table('income_payments')->insert(array_map(fn($id)=>['id'=>$id,'income_id'=>$parent,'payment_method_type_id'=>'01','date_of_payment'=>'2026-10-26','payment'=>'0.01',
                    'calculation_snapshot'=>json_encode(\App\Support\Exact\Decimal::snapshot(['payment'=>'0.01']))],$ids));
                $db->table('global_payments')->insert(array_map(fn($id)=>['payment_id'=>$id,'payment_type'=>IncomePayment::class,'destination_type'=>Cash::class,'destination_id'=>$cash->id,
                    'cash_session_id'=>$cash->id,'user_id'=>$input['user_id'],'fiscal_environment'=>'demo','report_context'=>$context],$ids));
            }
            $times=[];$queries=[];
            for ($run=0;$run<5;$run++) {
                $count=0;$start=hrtime(true);$report=CashLedger::report($cash);$times[]=(hrtime(true)-$start)/1000000;$queries[]=$count;
                self::assertSame($target===1000 ? '10' : '100',$report['balances_by_currency']['VES']['income']);
            }
            $count=0;$start=hrtime(true);$legacy=(new \Tests\Support\LegacyCashSummaryBuilder())->setDataToReport($cash->id);
            $legacyMs=(hrtime(true)-$start)/1000000;$legacyQueries=$count;sort($times);
            self::assertTrue(\App\Support\Exact\Decimal::of($legacy['total_cash_payment_method_type_01'])->isEqualTo($report['balances_by_currency']['VES']['final_balance']));
            $results[]=['receipts'=>$target,'classified_p50_ms'=>$times[2],'classified_p95_ms'=>$times[4],'classified_queries'=>max($queries),
                'legacy_ms'=>$legacyMs,'legacy_queries'=>$legacyQueries,'peak_memory_bytes'=>memory_get_peak_usage(true),'scope'=>'Datos de reporte PHP/MySQL, sin HTTP ni render PDF'];
            self::assertLessThanOrEqual($legacyMs+max($legacyMs*0.2,100),$times[4]);
        }
        file_put_contents(sys_get_temp_dir().'/pro9-payment-benchmark.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
