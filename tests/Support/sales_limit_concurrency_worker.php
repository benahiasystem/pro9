<?php
ini_set('display_errors','stderr');
require dirname(__DIR__,2).'/vendor/autoload.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/\Asab_fiscal_test_[a-f0-9]{12}\z/',$input['connection']['database'] ?? '')) exit(2);
\Illuminate\Database\Connection::resolverFor('pgsql',static fn($pdo,$database,$prefix,$config)=>new \App\Database\PostgresConnection($pdo,$database,$prefix,$config));
$app=new \Illuminate\Foundation\Application(dirname(__DIR__,2));
\Illuminate\Container\Container::setInstance($app); \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$app->instance('config',new \Illuminate\Config\Repository());
$app->instance(\Hyn\Tenancy\Database\Connection::class,new class extends \Hyn\Tenancy\Database\Connection {
    public function __construct() {}
    public function tenantName(): string {return 'tenant';}
    public function systemName(): string {return 'tenant';}
});
$manager=new \Illuminate\Database\Capsule\Manager($app); $manager->addConnection($input['connection'],'tenant');
$manager->getDatabaseManager()->setDefaultConnection('tenant');
$manager->setEventDispatcher(new \Illuminate\Events\Dispatcher($app)); $manager->bootEloquent();
$app->instance('db',$manager->getDatabaseManager()); $db=$manager->getConnection('tenant');
\Tests\Support\PostgresTestEnvironment::assertServer($db);
$db->beginTransaction();
try {
    $db->table('documents')->insert($input['row']);
    fwrite(STDOUT,"READY\n"); fflush(STDOUT);
    \App\Services\Plans\SalesLimit::defer($input['start'],$input['end'],true,$input['limit']);
    $db->commit();
    fwrite(STDOUT,json_encode(['allowed'=>true,'transaction_level'=>$db->transactionLevel()],JSON_THROW_ON_ERROR));
} catch (\Throwable $error) {
    if ($db->transactionLevel()) $db->rollBack();
    if ($error instanceof \RuntimeException && $error->getMessage()==='Ha superado el límite de ventas permitido.') {
        fwrite(STDOUT,json_encode(['allowed'=>false,'transaction_level'=>$db->transactionLevel()],JSON_THROW_ON_ERROR));
    } else {fwrite(STDERR,(string)$error);exit(1);}
}
