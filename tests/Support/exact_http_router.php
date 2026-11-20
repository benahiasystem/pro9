<?php

// Test-only front controller. It is never installed in public/ or production routes.
// Browser fixtures serve static assets only from the public document root.
if (getenv('SAB_BROWSER_FIXTURE') === '1' && realpath($_SERVER['DOCUMENT_ROOT'] ?? '') === realpath(dirname(__DIR__,2).'/public')) {
    $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH);
    $file=realpath(dirname(__DIR__,2).'/public/'.$path);
    $root=realpath(dirname(__DIR__,2).'/public').DIRECTORY_SEPARATOR;
    if (preg_match('~^/(?:build|porto-light|js|css|images|img|fonts|json|vendor)/[^?]*$~',$path)
        && !preg_match('~(?:^|/)\.~',$path) && $file && str_starts_with($file,$root)
        && is_file($file) && strtolower(pathinfo($file,PATHINFO_EXTENSION)) !== 'php') return false;
}
$fixturePath=getenv('SAB_EXACT_HTTP_FIXTURE');
if (!$fixturePath || !is_file($fixturePath)) { http_response_code(503);exit; }
$fixture=json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/D',$fixture['connection']['database'] ?? '')
    || !in_array(($fixture['connection']['host'] ?? ''), ['127.0.0.1', 'postgres'], true)) { http_response_code(503);exit; }
$benchmarkCentral=$fixture['benchmark_central'] ?? null;
if ($benchmarkCentral !== null && (getenv('SAB_POSTGRES_TESTS')!=='1'
    || !preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/D',$benchmarkCentral['database'] ?? '')
    || ($benchmarkCentral['database'] ?? null)===$fixture['connection']['database']
    || ($benchmarkCentral['host'] ?? null)!==$fixture['connection']['host']
    || ($benchmarkCentral['driver'] ?? null)!==$fixture['connection']['driver']
    || !preg_match('/^[a-f0-9]{64}$/D',$fixture['benchmark_tenancy_key'] ?? ''))) {
    http_response_code(503);exit;
}
require dirname(__DIR__,2).'/vendor/autoload.php';
if (isset($fixture['controller_reference'])) require $fixture['controller_reference'];
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->useStoragePath($fixture['storage']);
$bootstrapQueries=[];$bootstrapStages=[];$capturingBootstrap=true;
if (($fixture['profile_sql'] ?? false) === true) {
    foreach ([\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class] as $stage) {
        $app->beforeBootstrapping($stage,static function () use (&$bootstrapStages,$stage): void {
            $bootstrapStages[$stage]=hrtime(true);
        });
        $app->afterBootstrapping($stage,static function () use (&$bootstrapStages,$stage): void {
            $bootstrapStages[$stage]=(hrtime(true)-$bootstrapStages[$stage])/1000000;
        });
    }
    $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        static function ($app) use (&$bootstrapQueries,&$capturingBootstrap): void {
            $app['events']->listen(\Illuminate\Database\Events\QueryExecuted::class,
                static function ($event) use (&$bootstrapQueries,&$capturingBootstrap): void {
                    if ($capturingBootstrap) $bootstrapQueries[]=['sql'=>$event->sql,'ms'=>$event->time];
                });
        });
}
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,static function ($app) use ($fixture,$benchmarkCentral): void {
    $app['config']->set([
        'app.env'=>'testing','app.debug'=>true,'app.key'=>'base64:'.base64_encode(str_repeat('k',32)),
        'database.default'=>'system','database.connections.system'=>$fixture['connection'],
        'database.connections.provisioning_ledger'=>$fixture['connection'],'database.connections.tenant'=>$fixture['connection'],
        'tenancy.db.tenant-division-mode'=>'bypass','tenancy.website.disk'=>'sab-test',
        'tenancy.website.auto-create-tenant-directory'=>false,'tenancy.hostname.default'=>null,
        'cache.default'=>'array','session.driver'=>'file','session.files'=>$fixture['storage'].'/framework/sessions',
        'session.cookie'=>'sab_exact_http_session','session.secure'=>false,
        'queue.default'=>'sync','mail.driver'=>'array','mail.default'=>'array',
        'tenant.force_https'=>false,'filesystems.disks.sab-test'=>['driver'=>'local','root'=>$fixture['storage'].'/tenant'],
        'view.compiled'=>$fixture['storage'].'/framework/views',
        'logging.default'=>'single','logging.channels.single.path'=>$fixture['storage'].'/logs/laravel.log',
    ]);
    if ($benchmarkCentral !== null) {
        $app['config']->set([
            'database.connections.system'=>$benchmarkCentral,
            'database.connections.provisioning_ledger'=>$benchmarkCentral,
            'database.connections.tenant'=>[],
            'tenancy.key'=>$fixture['benchmark_tenancy_key'],
            'tenancy.db.tenant-division-mode'=>'database',
            'tenancy.hostname.auto-identification'=>true,
        ]);
    }
    if (isset($fixture['public_search_enabled'])) {
        $app['config']->set('tenant.public_document_search_enabled', $fixture['public_search_enabled'] === true);
    }
});
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
$request=\Illuminate\Http\Request::capture();
if (getenv('SAB_POSTGRES_TESTS')==='1'
    && ((($fixture['strict_import_diagnostics'] ?? false) === true && $request->getPathInfo()==='/api/documents')
        || (($fixture['strict_editor_diagnostics'] ?? false) === true
            && preg_match('~\A/documents/[1-9][0-9]*/update\z~',$request->getPathInfo())))) {
    $request->attributes->set('sab.strict_input_exceptions',true);
}
$app->instance('request',$request);
$cpuMilliseconds=static function (): float {
    $usage=getrusage();
    return ($usage['ru_utime.tv_sec']+$usage['ru_stime.tv_sec'])*1000
        +($usage['ru_utime.tv_usec']+$usage['ru_stime.tv_usec'])/1000;
};
$bootCpuStart=$cpuMilliseconds();
$bootStart=hrtime(true);$kernel->bootstrap();$bootMs=(hrtime(true)-$bootStart)/1000000;
$bootCpuMs=$cpuMilliseconds()-$bootCpuStart;
$capturingBootstrap=false;
// No provider request is permitted, even when a branch unexpectedly asks for it.
\Illuminate\Support\Facades\Http::fake(static function () { throw new \RuntimeException('La aceptación HTTP no permite red externa.'); });
$queries=0;$sqlMs=0;$queryProfile=[];$profileSql=($fixture['profile_sql'] ?? false) === true;
\Illuminate\Support\Facades\DB::listen(static function ($event) use (&$queries,&$sqlMs,&$queryProfile,$profileSql): void {
    $queries++;$sqlMs+=$event->time;
    // Private diagnostic data only: bindings (tokens, passwords and row values) are never recorded.
    if ($profileSql) $queryProfile[]=['sql'=>$event->sql,'ms'=>$event->time];
});
$handlerCpuStart=$cpuMilliseconds();
$handleStart=hrtime(true);$response=$kernel->handle($request);
$handlerCpuMs=$cpuMilliseconds()-$handlerCpuStart;
if (getenv('SAB_BROWSER_FIXTURE') === '1' && $response->getStatusCode() >= 400) {
    error_log('Browser fixture HTTP '.$response->getStatusCode().' '.$request->method().' '.$request->getPathInfo());
}
if (getenv('SAB_BROWSER_FIXTURE') === '1') {
    // The browser must not send synthetic test data to telemetry or integrations.
    $response->headers->set('Content-Security-Policy', "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data: blob:; font-src 'self' data:");
}
$response->headers->set('X-Sab-Test-Handler-Ms',(string)((hrtime(true)-$handleStart)/1000000));
$response->headers->set('X-Sab-Test-SQL-Ms',(string)$sqlMs);
$response->headers->set('X-Sab-Test-Boot-Ms',(string)$bootMs);
$response->headers->set('X-Sab-Test-Boot-Cpu-Ms',(string)$bootCpuMs);
$response->headers->set('X-Sab-Test-Handler-Cpu-Ms',(string)$handlerCpuMs);
$response->headers->set('X-Sab-Test-Queries',(string)$queries);
$response->headers->set('X-Sab-Test-Peak-Memory',(string)memory_get_peak_usage(true));
if ($profileSql) file_put_contents($fixture['storage'].'/query-profile.json',json_encode(
    ['path'=>$request->getPathInfo(),'queries'=>$queryProfile,'bootstrap_queries'=>$bootstrapQueries,
        'bootstrap_stages_ms'=>$bootstrapStages,
        'opcache'=>function_exists('opcache_get_status') ? opcache_get_status(false) : false],JSON_THROW_ON_ERROR));
$response->send();
$kernel->terminate($request,$response);
