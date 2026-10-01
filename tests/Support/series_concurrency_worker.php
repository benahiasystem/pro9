<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/\Apro9_series_test_[a-f0-9]{12}\z/', $input['connection']['database'] ?? '')) throw new RuntimeException('Temporary series database required.');
$app = new Illuminate\Foundation\Application(dirname(__DIR__, 2));
Illuminate\Container\Container::setInstance($app);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('config', new Illuminate\Config\Repository(['fiscal_emission' => ['operation_tables' => []]]));
$app->instance('validator', new Illuminate\Validation\Factory(new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'es'), $app));
$app->instance('request', Illuminate\Http\Request::create('/'));
$app->instance('auth', new class($input['operation'] ?? 'save') {
    private $operation;
    public function __construct($operation) {$this->operation = $operation;}
    public function guard() {return $this;}
    public function check() {return $this->operation === 'delete';}
    public function user() {return $this->operation === 'delete' ? (object) ['type' => 'admin', 'establishment_id' => 1] : null;}
});
$app->instance(Hyn\Tenancy\Database\Connection::class, new class extends Hyn\Tenancy\Database\Connection {
    public function __construct() {}
    public function tenantName(): string {return 'tenant';}
});
$manager = new Illuminate\Database\Capsule\Manager($app);
$manager->addConnection($input['connection'], 'tenant');
$manager->getDatabaseManager()->setDefaultConnection('tenant');
$manager->bootEloquent();
$app->instance('db', $manager->getDatabaseManager());
App\Models\Tenant\Company::addGlobalScope('worker_without_relations', fn ($q) => $q->without(['identity_document_type']));
$db = $manager->getConnection('tenant');
$db->getPdo();
fwrite(STDOUT, "READY\n");fflush(STDOUT);
try {
    if (($input['operation'] ?? 'save') === 'delete') {
        $result = (new App\Http\Controllers\Tenant\SeriesController())->destroy(1);
    } else $result = $db->transaction(function () use ($db, $input) {
        $model = new class extends Illuminate\Database\Eloquent\Model {
            protected $connection = 'tenant';
            protected $table = 'documents';
        };
        $model->fiscal_environment = 'demo';
        $number = App\Services\SeriesNumbering::next($model, '01', $input['series'] ?? 'FF01', $input['number'] ?? '#', $input['branch'] ?? 1);
        $db->table('documents')->insert(['document_type_id' => '01', 'series' => $input['series'] ?? 'FF01', 'number' => $number, 'fiscal_environment' => 'demo', 'establishment_id' => $input['branch'] ?? 1]);
        return ['number' => $number];
    });
} catch (Illuminate\Validation\ValidationException $e) { $result = ['duplicate' => true]; }
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR));
