<?php
// Keep PHP diagnostics separate from the READY/JSON protocol consumed by the parent.
ini_set('display_errors', 'stderr');
require dirname(__DIR__,2).'/vendor/autoload.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/\Apro9_fiscal_test_[a-f0-9]{12}\z/',$input['connection']['database'] ?? '')) exit(2);
if (!class_exists('DB')) class_alias(Illuminate\Support\Facades\DB::class,'DB');
$app=new Illuminate\Foundation\Application(dirname(__DIR__,2));
Illuminate\Container\Container::setInstance($app);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('config',new Illuminate\Config\Repository(['fiscal_emission'=>require dirname(__DIR__,2).'/config/fiscal_emission.php','venezuela'=>require dirname(__DIR__,2).'/config/venezuela.php']));
if (($input['data']['credit_invoice_verified'] ?? false) === true) config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
$app->instance('validator',new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(),'es'),$app));
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->instance('files',new Illuminate\Filesystem\Filesystem());
config(['filesystems.disks.tenant'=>['driver'=>'local','root'=>sys_get_temp_dir().'/'.$input['connection']['database']]]);
$app->instance('filesystem',new Illuminate\Filesystem\FilesystemManager($app));
$app->instance('cache',new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore()));
$app->instance(Hyn\Tenancy\Database\Connection::class,new class extends Hyn\Tenancy\Database\Connection {
    public function __construct() {}
    public function tenantName(): string {return 'tenant';}
    public function systemName(): string {return 'tenant';}
});
$manager=new Illuminate\Database\Capsule\Manager($app);
$manager->addConnection($input['connection'],'tenant');
$manager->getDatabaseManager()->setDefaultConnection('tenant');
$manager->getConnection('tenant')->setTransactionManager(new Illuminate\Database\DatabaseTransactionsManager());
$manager->setEventDispatcher(new Illuminate\Events\Dispatcher($app));$manager->bootEloquent();
$app->instance('db',$manager->getDatabaseManager());
$app->make('validator')->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($manager->getDatabaseManager()));
$user=new App\Models\Tenant\User(['id'=>1,'type'=>'admin','establishment_id'=>1]);
$app->instance('auth',new class($user) {
    private $user;
    public function __construct($user) {$this->user=$user;}
    public function user() {return $this->user;}
    public function id() {return 1;}
    public function check() {return true;}
});
App\Models\Tenant\Document::observe(App\Observers\DocumentObserver::class);
(new Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
(new Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
$manager->getConnection('tenant')->getPdo();
// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
if (in_array($input['operation'] ?? '', ['subscription-edit','cash-document-link','cash-opening','bank-currency-edit','currency-deactivate','method-reclassify','method-activate','method-deactivate','quotation-receipt','quotation-apply-note','quotation-conversion-preflight','technical-apply-note','technical-receipt','sale-note-receipt','sale-note-batch','sale-note-delete','sale-note-reuse-invoice','document-receipt','receipt-attach'], true)) {
    $connectionId=$manager->getConnection('tenant')->selectOne('SELECT CONNECTION_ID() AS id')->id;
    fwrite(STDERR,"LOCK_TEST_CONNECTION={$connectionId}\n");
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
fwrite(STDOUT,"READY\n");fflush(STDOUT);
try {
    if (($input['operation'] ?? '') === 'whatsapp-draft') {
        $draft=$input['data']['draft'];
        $user=\App\Models\Tenant\User::findOrFail($input['data']['user_id']);
        $document=\Modules\WhatsAppBot\Services\Facturalo\DraftOperation::run($draft,$user,function () use ($draft,$user) {
            $payload=(new \Modules\WhatsAppBot\Services\Facturalo\FacturaloDraftMapper())->map($draft,$user);
            $document=(new \App\CoreFacturalo\Facturalo())->save($payload)->getDocument();
            return ['success'=>true,'data'=>['id'=>$document->id]];
        });
        fwrite(STDOUT,json_encode(['id'=>$document->id],JSON_THROW_ON_ERROR));exit(0);
    }
    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
    if (in_array(($input['operation'] ?? ''), ['receipt-attach','document-attach'], true)) {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            $model=($input['operation'] ?? '') === 'document-attach' ? \App\Models\Tenant\DocumentPayment::class : \Modules\Sale\Models\QuotationPayment::class;
            $payment=$model::findOrFail($input['data']['payment_id']);
            \App\Services\Payments\ReceiptDestination::attach($payment,['payment_destination_id'=>$input['data']['destination_id']]);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'cash-opening') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            \DB::connection('tenant')->table('payment_method_types')->count();
            $cash=\App\Models\Tenant\Cash::findOrFail($input['data']['cash_id']);
            \App\Services\Payments\CashLedger::open($cash,$input['data']);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'method-deactivate') {
        \DB::connection('tenant')->table('payment_method_types')->where('id',$input['data']['id'])->update(['is_active'=>0]);
        fwrite(STDOUT,json_encode(['success'=>true],JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'subscription-edit') {
        $app->instance(\Modules\Suscription\Services\SubscriptionSaleNoteController::class, new class extends \Modules\Suscription\Services\SubscriptionSaleNoteController {
            public function createPdf($sale_note=null,$format_pdf=null,$filename=null,$output='pdf') {}
        });
        $app->instance(\Illuminate\Contracts\Routing\UrlGenerator::class, new class implements \Illuminate\Contracts\Routing\UrlGenerator {
            public function full() { return 'http://example.test'; }
            public function current() { return 'http://example.test'; }
            public function previous($fallback = false) { return 'http://example.test'; }
            public function to($path, $extra = [], $secure = null) { return 'http://example.test'; }
            public function secure($path, $parameters = []) { return 'http://example.test'; }
            public function asset($path, $secure = null) { return 'http://example.test'; }
            public function secureAsset($path) { return 'http://example.test'; }
            public function route($name, $parameters = [], $absolute = true) { return 'http://example.test'; }
            public function action($action, $parameters = [], $absolute = true) { return 'http://example.test'; }
            public function forceScheme($scheme) {}
            public function getRootControllerNamespace() { return ''; }
            public function setRootControllerNamespace($rootNamespace) {}
        });
        $request=\Modules\Suscription\Http\Requests\PaymentsSuscriptionRequest::create('/', 'POST', $input['data']);
        $output=\DB::connection('tenant')->transaction(function () use ($request) {
            \DB::connection('tenant')->table('user_rel_suscription_plans')->count();
            (new \Modules\Suscription\Http\Controllers\PaymentsSuscriptionController())->store($request);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'cash-document-link') {
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new class {
            public function json($data = [], $status = 200, array $headers = [], $options = 0) {
                return new \Illuminate\Http\JsonResponse($data, $status, $headers, $options);
            }
        });
        $request=\Illuminate\Http\Request::create('/', 'POST', $input['data']);
        $output=\DB::connection('tenant')->transaction(function () use ($request) {
            \DB::connection('tenant')->table('cash_documents')->count();
            return (new \App\Http\Controllers\Tenant\Api\CashController())->cash_document($request)->getData(true);
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'bank-currency-edit') {
        $request=\App\Http\Requests\Tenant\BankAccountRequest::create('/', 'POST', $input['data']);
        $output=(new \App\Http\Controllers\Tenant\BankAccountController())->store($request);
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'currency-deactivate') {
        \DB::connection('tenant')->table('cat_currency_types')->where('id',$input['data']['currency'])->update(['active'=>0]);
        fwrite(STDOUT,json_encode(['success'=>true],JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'method-reclassify') {
        $request=\Modules\Sale\Http\Requests\PaymentMethodTypeRequest::create('/', 'PUT', $input['data']);
        $output=(new \Modules\Sale\Http\Controllers\PaymentMethodTypeController())->update($request,$input['data']['id']);
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'method-activate') {
        \Illuminate\Http\Request::macro('validate', function ($rules) {
            return app('validator')->make($this->all(), $rules)->validate();
        });
        $request=\Illuminate\Http\Request::create('/', 'POST', $input['data']);
        $output=(new \Modules\Sale\Http\Controllers\PaymentMethodTypeController())->active($request);
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'quotation-receipt') {
        $request=\Modules\Sale\Http\Requests\QuotationPaymentRequest::create('/', 'POST', $input['data']);
        $operation=fn()=>(new \Modules\Sale\Http\Controllers\QuotationPaymentController())->store($request);
        $output=!empty($input['pre_read']) ? \DB::connection('tenant')->transaction(function () use ($operation) {
            \DB::connection('tenant')->table('quotation_payments')->count();
            return $operation();
        }) : $operation();
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'quotation-conversion-preflight') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            \DB::connection('tenant')->table('quotation_payments')->count();
            \App\Services\Payments\QuotationReceiptsRequired::assertPresent($input['data']['quotation_id'],[]);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (in_array($input['operation'] ?? '', ['quotation-apply-note', 'technical-apply-note'], true)) {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            // Establish an old repeatable-read snapshot before locking the quotation.
            \DB::connection('tenant')->table('sale_note_payments')->count();
            \DB::connection('tenant')->table('document_payments')->count();
            $note=\App\Models\Tenant\SaleNote::withoutEagerLoads()->findOrFail($input['document_id']);
            (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments($note, [$input['data']]);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'sale-note-delete') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            \DB::connection('tenant')->table('document_payments')->count();
            $controller=new class extends \App\Http\Controllers\Tenant\SaleNotePaymentController {
                public function createPdf($sale_note_id, $format = null) { return null; }
            };
            return $controller->destroy($input['data']['payment_id']);
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'sale-note-reuse-invoice') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            \DB::connection('tenant')->table('document_payments')->count();
            $document=(new \App\CoreFacturalo\Facturalo())->forWebDocuments()->save($input['data'])->getDocument();
            return ['success'=>true,'id'=>$document->id];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'technical-receipt') {
        $request=\Modules\Sale\Http\Requests\TechnicalServicePaymentRequest::create('/', 'POST', $input['data']);
        $operation=fn()=>(new \Modules\Sale\Http\Controllers\TechnicalServicePaymentController())->store($request);
        $output=!empty($input['pre_read']) ? \DB::connection('tenant')->transaction(function () use ($operation) {
            \DB::connection('tenant')->table('technical_service_payments')->count();
            return $operation();
        }) : $operation();
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'sale-note-receipt') {
        $request=\App\Http\Requests\Tenant\SaleNotePaymentRequest::create('/', 'POST', $input['data']);
        $controller=new class extends \App\Http\Controllers\Tenant\SaleNotePaymentController {
            public function createPdf($sale_note_id, $format = null) { return null; }
        };
        $operation=fn()=>$controller->store($request);
        $output=!empty($input['pre_read']) ? \DB::connection('tenant')->transaction(function () use ($operation) {
            \DB::connection('tenant')->table('sale_note_payments')->count();
            return $operation();
        }) : $operation();
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'sale-note-batch') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            \DB::connection('tenant')->table('payment_method_types')->count();
            $note=\App\Models\Tenant\SaleNote::findOrFail($input['data']['note_id']);
            (new \App\Http\Controllers\Tenant\SaleNoteController())->savePayments($note,$input['data']['payments']);
            return ['success'=>true];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'document-receipt') {
        $output=\DB::connection('tenant')->transaction(function () use ($input) {
            $document=\App\Models\Tenant\Document::query()->findOrFail($input['document_id']);
            $payment=\App\Services\Fiscal\FiscalDocumentPersistence::payment($document,$input['data']);
            return ['id'=>$payment->id];
        });
        fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));exit(0);
    }
    // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    if (($input['operation'] ?? '') === 'mobile-capture') {
        $request=\Modules\MobileApp\Http\Requests\Api\ReceivedPaymentStoreRequest::create('/', 'POST', $input['data']);
        $request->setContainer($app); $request->validateResolved();
        $response=(new \Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController())->store($request);
        fwrite(STDOUT,json_encode($response, JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '') === 'hotel-retry') {
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new class {
            public function json($data = [], $status = 200, array $headers = [], $options = 0) {
                return new \Illuminate\Http\JsonResponse($data, $status, $headers, $options);
            }
        });
        $request=\Modules\Hotel\Http\Requests\HotelRentItemRequest::create('/', 'POST', $input['data']);
        $response=(new \Modules\Hotel\Http\Controllers\HotelRentController())->addProductsToRoom($request,$input['document_id']);
        fwrite(STDOUT,json_encode($response->getData(true), JSON_THROW_ON_ERROR));exit(0);
    }
    if (($input['operation'] ?? '')==='dispatch-create') {
        $order=(new App\CoreFacturalo\Facturalo)->save(App\CoreFacturalo\Requests\Inputs\DispatchInput::set($input['data']))->getDocument();
        fwrite(STDOUT,json_encode(['id'=>$order->id,'number'=>$order->number]));exit(0);
    }
    $document=App\Models\Tenant\Document::findOrFail($input['document_id']);
    $service=App\Services\Fiscal\FiscalDocumentPersistence::class;
    if (($input['operation'] ?? '') === 'note-create') {
        $note=(new App\CoreFacturalo\Facturalo)->forWebDocuments()->save($input['data'])->getDocument();
        $output=['id'=>$note->id];
    } elseif (($input['operation'] ?? '') === 'note-refund') {
        $refund=app(App\Services\Fiscal\NoteRefund::class)->register($document,$input['data']);
        $output=['id'=>$refund->id];
    } elseif (($input['operation'] ?? '') === 'note-annul') {
        $app->instance('encrypter',new Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC'));
        config(['app.key'=>str_repeat('k',32),'hka_notes.contract_probe'=>true]);
        $http=new Illuminate\Http\Client\Factory();$app->instance(Illuminate\Http\Client\Factory::class,$http);$http->preventStrayRequests();
        $http->fake(function($request) use($input,$manager) {
            if ($manager->getConnection('tenant')->transactionLevel()) throw new RuntimeException('HTTP while holding locks');
            if (str_ends_with($request->url(),'/Autenticacion')) return Illuminate\Support\Facades\Http::response(['token'=>'fake-worker-jwt','expiracion'=>date('c',time()+3600)]);
            if (!str_ends_with($request->url(),'/Anular')) throw new RuntimeException('Unexpected fiscal request');
            file_put_contents($input['data']['log'],"annul\n",FILE_APPEND|LOCK_EX);usleep(500000);
            return Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[]]);
        });
        $output=app(App\Services\Fiscal\NoteAnnulment::class)->request($document,$input['data']);
    } elseif (($input['operation'] ?? '') === 'hka-edit-race' && ($input['data']['action'] ?? '') === 'edit') {
        $engine = new App\CoreFacturalo\Facturalo;
        if (!empty($input['data']['web_export'])) $engine->forWebDocuments();
        $engine->update($input['data']['edit'], $document->id);
        $output = ['status' => 'edited'];
    } elseif (in_array($input['operation'] ?? '', ['hka', 'hka-mail', 'hka-auto-mail', 'hka-edit-race'], true)) {
        $app->instance('encrypter',new Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC'));
        config(['app.key'=>str_repeat('k',32)]);
        $http=new Illuminate\Http\Client\Factory();
        $app->instance(Illuminate\Http\Client\Factory::class,$http);
        $http->preventStrayRequests();
        $http->fake(function ($request) use ($input) {
            if (str_ends_with($request->url(),'/Autenticacion')) return Illuminate\Support\Facades\Http::response([
                'token'=>'fake-worker-jwt','expiracion'=>date('c',time()+3600)],200);
            if (str_ends_with($request->url(),'/DescargaArchivo')) { $pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,15,'Concurrent test PDF');return Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]); }
            if (str_ends_with($request->url(),'/EstadoDocumento')) return Illuminate\Support\Facades\Http::response([],500);
            if (str_ends_with($request->url(), '/Correo/Rastreo')) return Illuminate\Support\Facades\Http::response(['codigo'=>'200','rastreos'=>[]]);
            if (str_ends_with($request->url(), '/Correo/Enviar')) {
                file_put_contents($input['data']['log'], "mail\n", FILE_APPEND|LOCK_EX);
                usleep(500000);
                return Illuminate\Support\Facades\Http::response(['codigo'=>'200']);
            }
            file_put_contents($input['data']['log'],"emission\n",FILE_APPEND|LOCK_EX);
            usleep(500000);
            $identity=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
            return Illuminate\Support\Facades\Http::response(['codigo'=>'200','resultado'=>$identity+[
                'numeroControl'=>'00-'.str_pad($identity['numeroDocumento'],8,'0',STR_PAD_LEFT)]],200);
        });
        if ($input['operation'] === 'hka-auto-mail') $output = app(App\Services\Fiscal\HkaMail::class)->sendAutomatic($document);
        else $output=$input['operation']==='hka-mail' ? app(App\Services\Fiscal\HkaMail::class)->send($document, ['mail-test@example.test'], $input['data']['uuid']) : app(App\Services\Fiscal\HkaEmission::class)->send($document);
    } else {
        if (($input['operation'] ?? '') === 'iva-mutations' || (($input['operation'] ?? '') === 'retention' && in_array($input['data']['tax_kind'] ?? '',['IVA','ISLR'],true))) {
            $app->instance('encrypter',new \Illuminate\Encryption\Encrypter(str_repeat('k',32),'AES-256-CBC'));
            config(['app.key'=>str_repeat('k',32)]);
            $http = new \Illuminate\Http\Client\Factory();
            $app->instance(\Illuminate\Http\Client\Factory::class,$http);
            $http->preventStrayRequests();
            $http->fake(fn($r)=>\Illuminate\Support\Facades\Http::response(str_ends_with($r->url(),'/Autenticacion')
                ? ['token'=>'fake-worker-jwt','expiracion'=>date('c',time()+3600)] : ['codigo'=>'200','infoRetencion'=>null]));
        }
        if (($input['operation'] ?? '') === 'iva-mutations') {
            $data=$input['data'];
            if ($data['action'] === 'reverse') {
                $row=$document->received_retentions()->findOrFail($data['retention_id']);
                ($row->tax_kind === 'ISLR' ? \App\Services\Fiscal\IslrRetentionService::class : \App\Services\Fiscal\IvaRetentionService::class)::reverse($row,$data['input']);
                $result=$row->fresh();
            } else $result=$data['action']==='register' ? $service::retention($document,$data['input']) : $service::payment($document,$data['input']);
        } else $result=($input['operation'] ?? 'payment')==='retention' ? $service::retention($document,$input['data']) : $service::payment($document,$input['data']);
        $output=['id'=>$result->id];
    }
} catch (Illuminate\Validation\ValidationException $e) {$output=['rejected'=>array_keys($e->errors())];}
catch (Throwable $e) {fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString());exit(1);}
fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));
