<?php

// Test worker accepts only the disposable DB naming convention, never a tenant DB.
ini_set('display_errors','stderr');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Illuminate\Database\Connection::resolverFor('pgsql', static fn ($pdo,$database,$prefix,$config) => new \App\Database\PostgresConnection($pdo,$database,$prefix,$config));
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!class_exists('DB')) class_alias(Illuminate\Support\Facades\DB::class,'DB');
if (!class_exists('Log')) class_alias(Illuminate\Support\Facades\Log::class,'Log');
if (!preg_match('/\Apro9_fiscal_test_[a-f0-9]{12}\z/', $input['connection']['database'] ?? '')) exit(2);
$app=new Illuminate\Foundation\Application(dirname(__DIR__,2));
Illuminate\Container\Container::setInstance($app);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('config',new Illuminate\Config\Repository([
    'fiscal_emission'=>require dirname(__DIR__,2).'/config/fiscal_emission.php',
    'venezuela'=>require dirname(__DIR__,2).'/config/venezuela.php',
]));
$app->instance('request',Illuminate\Http\Request::create('http://sellnow-worker.example.test'));
$app->instance('files',new Illuminate\Filesystem\Filesystem());
$app->instance('cache',new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore()));
$app->instance(Hyn\Tenancy\Database\Connection::class,new class extends Hyn\Tenancy\Database\Connection {
    public function __construct() {}
    public function tenantName(): string { return 'tenant'; }
    public function systemName(): string { return 'tenant'; }
});
$manager=new Illuminate\Database\Capsule\Manager($app);
$manager->addConnection($input['connection'],'tenant');
$manager->getDatabaseManager()->setDefaultConnection('tenant');
\Tests\Support\PostgresTestEnvironment::assertServer($manager->getConnection('tenant'));
$manager->getConnection('tenant')->setTransactionManager(new Illuminate\Database\DatabaseTransactionsManager());
$manager->setEventDispatcher(new Illuminate\Events\Dispatcher($app));
$manager->bootEloquent();
$app->instance('db',$manager->getDatabaseManager());
$app->bind('db.schema',fn()=>$manager->getConnection('tenant')->getSchemaBuilder());
$app->instance('validator',new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(),'es'),$app));
$app->make('validator')->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($manager->getDatabaseManager()));
Illuminate\Http\Request::macro('validate',function ($rules) { return validator($this->all(),$rules)->validate(); });
$app->instance(Illuminate\Contracts\Routing\ResponseFactory::class,new class {
    public function json($data,$status=200) { return new Illuminate\Http\JsonResponse($data,$status); }
});
$user=App\Models\Tenant\User::findOrFail($input['user_id']);
$app->instance('auth',new class($user) {
    public function __construct(private $user) {}
    public function user() { return $this->user; }
    public function id() { return $this->user->id; }
    public function check() { return true; }
    public function guard($name=null) { return $this; }
});
$app->instance('url',new class {
    public function to(...$args) { return 'http://sellnow-worker.example.test'; }
    public function route(...$args) { return 'http://sellnow-worker.example.test'; }
    public function asset(...$args) { return 'http://sellnow-worker.example.test'; }
});
$app->instance('log',new class { public function error(...$args) {} public function warning(...$args) {} public function info(...$args) {} });
App\Models\Tenant\Document::observe(App\Observers\DocumentObserver::class);
(new Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
(new Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
Carbon\Carbon::setTestNow('2026-09-10 12:00:00');
$app->instance(App\Services\Sellnow\SaleEffects::class,new class($input) extends App\Services\Sellnow\SaleEffects {
    public function __construct(private array $options) {}
    protected function claim(App\Models\Tenant\SellnowSale $sale,string $effect): bool
    {
        $claimed=parent::claim($sale,$effect);
        if ($claimed && $effect === 'print' && !empty($this->options['print_gate'])) {
            $gate=$this->options['print_gate'];
            if (!str_starts_with($gate,sys_get_temp_dir().'/sab-sellnow-print-')) throw new RuntimeException('Invalid print test gate.');
            fwrite(STDOUT,"PRINT_CLAIMED\n");fflush(STDOUT);
            $deadline=microtime(true)+20;
            while (!is_file($gate)) {
                if (microtime(true)>$deadline) throw new RuntimeException('Print test gate timed out.');
                clearstatcache(true,$gate);usleep(10000);
            }
        }
        return $claimed;
    }
    protected function execute($sale,string $effect)
    {
        if ($effect === 'print' && !empty($this->options['print_effect'])) {
            $order=Modules\Restaurant\Models\PrintOrder::create(['name_printer'=>'LEASE TEST ONLY','status'=>0,'pdf_b64'=>'']);
            return ['auto_printed'=>true,'print_order_id'=>$order->id,'reason'=>null];
        }
        return null;
    }
});
if (!empty($input['exit_after_commit'])) {
    $app->instance(App\Services\Sellnow\ApiSaleCreation::class,new class extends App\Services\Sellnow\ApiSaleCreation {
        public function persistDocument(array $prepared): App\CoreFacturalo\Facturalo
        {
            $fact=parent::persistDocument($prepared);
            Illuminate\Support\Facades\DB::connection('tenant')->afterCommit(static function () { exit(77); });
            return $fact;
        }
    });
}
fwrite(STDOUT,"READY\n");fflush(STDOUT);
try {
    $sale=(new App\Services\Sellnow\SaleOperations())->submit($input['envelope']);
    fwrite(STDOUT,json_encode(['status'=>$sale->status,'result'=>$sale->result,'error'=>$sale->error],JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR,(string)$exception);
    exit(1);
}
