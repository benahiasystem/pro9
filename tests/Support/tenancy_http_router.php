<?php
// ########## INICIO CAMBIO POSTGRESQL 17
/** Real Hyn hostname resolution against a private central and separate tenant databases. */
$path=getenv('PRO9_TENANCY_HTTP_FIXTURE');
if (!$path || !is_file($path) || getenv('PRO9_POSTGRES_TESTS')!=='1') {http_response_code(503);exit;}
$fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^pro9_native_[a-f0-9]{10}_central$/D',$fixture['central']['database'] ?? '') || ($fixture['central']['host'] ?? '')!=='postgres') {http_response_code(503);exit;}
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';$app->useStoragePath($fixture['storage']);
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,static function ($app) use ($fixture) {
    $app['config']->set(['app.env'=>'testing','app.debug'=>false,'database.default'=>'system','database.connections.system'=>$fixture['central'],
        'database.connections.provisioning_ledger'=>$fixture['central'],'database.connections.tenant'=>[],
        'tenancy.db.tenant-division-mode'=>'database','tenancy.hostname.default'=>null,'tenancy.hostname.auto-identification'=>true,
        'tenancy.website.auto-create-tenant-directory'=>false,'cache.default'=>'array','session.driver'=>'array','queue.default'=>'sync',
        'mail.default'=>'array','tenant.force_https'=>false,'view.compiled'=>$fixture['storage'].'/framework/views',
        'logging.default'=>'single','logging.channels.single.path'=>$fixture['storage'].'/logs/laravel.log']);
});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);$request=\Illuminate\Http\Request::capture();$app->instance('request',$request);$kernel->bootstrap();
\Tests\Support\PostgresTestEnvironment::assertServer(\Illuminate\Support\Facades\DB::connection('system'));
\Illuminate\Support\Facades\Http::fake(static function () {throw new \RuntimeException('No se permite HTTP externo en la verificación tenant.');});
$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
// ######### FIN CAMBIO POSTGRESQL 17
