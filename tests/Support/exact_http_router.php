<?php

// Test-only front controller. It is never installed in public/ or production routes.
$fixturePath=getenv('PRO9_EXACT_HTTP_FIXTURE');
if (!$fixturePath || !is_file($fixturePath)) { http_response_code(503);exit; }
$fixture=json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/D',$fixture['connection']['database'] ?? '')
    || ($fixture['connection']['host'] ?? '') !== '127.0.0.1') { http_response_code(503);exit; }
require dirname(__DIR__,2).'/vendor/autoload.php';
if (isset($fixture['controller_reference'])) require $fixture['controller_reference'];
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->useStoragePath($fixture['storage']);
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,static function ($app) use ($fixture): void {
    $app['config']->set([
        'app.env'=>'testing','app.debug'=>true,'app.key'=>'base64:'.base64_encode(str_repeat('k',32)),
        'database.default'=>'system','database.connections.system'=>$fixture['connection'],
        'database.connections.mysql'=>$fixture['connection'],'database.connections.tenant'=>$fixture['connection'],
        'tenancy.db.tenant-division-mode'=>'bypass','tenancy.website.disk'=>false,
        'tenancy.website.auto-create-tenant-directory'=>false,'tenancy.hostname.default'=>null,
        'cache.default'=>'array','session.driver'=>'file','session.files'=>$fixture['storage'].'/framework/sessions',
        'session.cookie'=>'pro9_exact_http_session','session.secure'=>false,
        'queue.default'=>'sync','mail.driver'=>'array','mail.default'=>'array',
        'tenant.force_https'=>false,'filesystems.disks.tenant'=>['driver'=>'local','root'=>$fixture['storage'].'/tenant'],
        'view.compiled'=>$fixture['storage'].'/framework/views',
        'logging.default'=>'single','logging.channels.single.path'=>$fixture['storage'].'/logs/laravel.log',
    ]);
});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
$request=\Illuminate\Http\Request::capture();
$app->instance('request',$request);
$bootStart=hrtime(true);$kernel->bootstrap();$bootMs=(hrtime(true)-$bootStart)/1000000;
// No provider request is permitted, even when a branch unexpectedly asks for it.
\Illuminate\Support\Facades\Http::fake(static function () { throw new \RuntimeException('La aceptación HTTP no permite red externa.'); });
$queries=0;$sqlMs=0;
\Illuminate\Support\Facades\DB::listen(static function ($event) use (&$queries,&$sqlMs): void { $queries++;$sqlMs+=$event->time; });
$handleStart=hrtime(true);$response=$kernel->handle($request);
$response->headers->set('X-Pro9-Test-Handler-Ms',(string)((hrtime(true)-$handleStart)/1000000));
$response->headers->set('X-Pro9-Test-SQL-Ms',(string)$sqlMs);
$response->headers->set('X-Pro9-Test-Boot-Ms',(string)$bootMs);
$response->headers->set('X-Pro9-Test-Queries',(string)$queries);
$response->headers->set('X-Pro9-Test-Peak-Memory',(string)memory_get_peak_usage(true));
$response->send();
$kernel->terminate($request,$response);
