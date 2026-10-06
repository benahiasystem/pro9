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
    $result=($input['operation'] ?? 'payment')==='retention' ? $service::retention($document,$input['data']) : $service::payment($document,$input['data']);
    $output=['id'=>$result->id];
} catch (Illuminate\Validation\ValidationException $e) {$output=['rejected'=>array_keys($e->errors())];}
catch (Throwable $e) {fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString());exit(1);}
fwrite(STDOUT,json_encode($output,JSON_THROW_ON_ERROR));
