<?php
// Keep PHP diagnostics separate from the READY/JSON protocol consumed by the parent.
ini_set('display_errors', 'stderr');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo,$database,$prefix,$config) => new \App\Database\PostgresConnection($pdo,$database,$prefix,$config));
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/\Asab_fiscal_test_[a-f0-9]{12}\z/',$input['connection']['database'] ?? '')) exit(2);
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
$app->instance(App\Tenancy\Database\Connection::class,new class extends App\Tenancy\Database\Connection {
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
(new App\Providers\KardexServiceProvider($app))->boot();
(new Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
(new Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
$manager->getConnection('tenant')->getPdo();
// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
if (in_array($input['operation'] ?? '', ['store-order-receipt','store-coupon-order','subscription-edit','cash-document-link','cash-opening','bank-currency-edit','currency-deactivate','method-reclassify','method-activate','method-deactivate','quotation-receipt','quotation-apply-note','quotation-conversion-preflight','technical-apply-note','technical-receipt','sale-note-receipt','sale-note-batch','sale-note-delete','sale-note-reuse-invoice','document-receipt','receipt-attach'], true)) {
    $connectionId=$manager->getConnection('tenant')->selectOne('SELECT pg_backend_pid() AS id')->id;
    fwrite(STDERR,"LOCK_TEST_CONNECTION={$connectionId}\n");
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########

\Tests\Support\PostgresTestEnvironment::assertServer($manager->getConnection('tenant'));
fwrite(STDOUT,'READY '.$manager->getConnection('tenant')->selectOne('SELECT pg_backend_pid() AS id')->id."\n");fflush(STDOUT);
try {
    $manager->getConnection('tenant')->transaction(function () use ($input) {
        $facturalo=new \App\CoreFacturalo\Facturalo();
        $facturalo->setDocument(new \App\Models\Tenant\Document($input['context']));
        (new \ReflectionMethod($facturalo,'updatePrepaymentDocuments'))->invoke($facturalo,['prepayments'=>[$input['reference']]]);
    });
    fwrite(STDOUT,json_encode(['ok'=>true],JSON_THROW_ON_ERROR));
} catch (Throwable $e) {fwrite(STDERR,get_class($e).': '.$e->getMessage());exit(1);}
