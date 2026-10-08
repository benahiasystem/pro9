<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/\Apro9_fiscal_test_[a-f0-9]{12}\z/',$input['connection']['database'] ?? '')) exit(2);
if (!class_exists('DB')) class_alias(Illuminate\Support\Facades\DB::class,'DB');
$app=new Illuminate\Foundation\Application(dirname(__DIR__,2));
Illuminate\Container\Container::setInstance($app);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('config',new Illuminate\Config\Repository(['fiscal_emission'=>require dirname(__DIR__,2).'/config/fiscal_emission.php','venezuela'=>require dirname(__DIR__,2).'/config/venezuela.php']));
$app->instance('validator',new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(),'es'),$app));
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->instance('files',new Illuminate\Filesystem\Filesystem());
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
$manager->getConnection('tenant')->getPdo();
fwrite(STDOUT,"READY\n");fflush(STDOUT);
try {
    $document=App\Models\Tenant\Document::findOrFail($input['document_id']);
    $service=App\Services\Fiscal\FiscalDocumentPersistence::class;
    if (($input['operation'] ?? '') === 'hka-edit-race' && ($input['data']['action'] ?? '') === 'edit') {
        (new App\CoreFacturalo\Facturalo)->update($input['data']['edit'], $document->id);
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
        $result=($input['operation'] ?? 'payment')==='retention' ? $service::retention($document,$input['data']) : $service::payment($document,$input['data']);
        $output=['id'=>$result->id];
    }
} catch (Illuminate\Validation\ValidationException $e) {$output=['rejected'=>array_keys($e->errors())];}
catch (Throwable $e) {fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString());exit(1);}
fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));
