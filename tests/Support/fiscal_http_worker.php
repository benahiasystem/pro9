<?php

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/\Apro9_fiscal_test_[a-f0-9]{12}\z/', $input['connection']['database'] ?? '')) throw new RuntimeException('Only temporary fiscal databases are allowed.');
if (!preg_match('/\Apro9_fiscal_test_[a-f0-9]{12}\z/', $input['system_connection']['database'] ?? '')) throw new RuntimeException('Only temporary system databases are allowed.');
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->bootstrapWith([Illuminate\Foundation\Bootstrap\LoadConfiguration::class]);
$config = $app->make('config');
$config->set('app.env', 'testing');
$app->instance('env', 'testing');
$config->set('app.debug', false);
$config->set('app.key', 'base64:' . base64_encode(str_repeat('h', 32)));
$config->set('app.url', 'http://fiscal-http.example.test');
$config->set('database.default', 'tenant');
$config->set('database.connections', ['tenant' => $input['connection'], 'mysql' => $input['connection'], 'system' => $input['system_connection']]);
$config->set('cache.default', 'array');
$config->set('session.driver', 'array');
$config->set('queue.default', 'sync');
$config->set('tenant.force_https', false);
$config->set('tenancy.hostname.auto-identification', false);
$config->set('tenancy.hostname.early-identification', false);
$config->set('tenancy.database.auto-create-tenant-database', false);
$config->set('tenancy.database.auto-create-tenant-database-user', false);
$config->set('logging.default', 'stderr');
$hostname = new Hyn\Tenancy\Models\Hostname();
$hostname->setRawAttributes(['id' => 1, 'fqdn' => 'fiscal-http.example.test']);
$app->resolving(Hyn\Tenancy\Environment::class, function () use ($app, $hostname) {
    $app->instance(Hyn\Tenancy\Contracts\CurrentHostname::class, $hostname);
});
$app->instance('request', Illuminate\Http\Request::create('http://fiscal-http.example.test'));
$app->bootstrapWith([
    Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    Illuminate\Foundation\Bootstrap\BootProviders::class,
]);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$temporaryFiles = sys_get_temp_dir() . '/' . $input['connection']['database'] . '_http_files';
$app->make('config')->set('filesystems.disks.tenant', ['driver' => 'local', 'root' => $temporaryFiles]);
$app->make('filesystem')->forgetDisk('tenant');
register_shutdown_function(fn () => (new Illuminate\Filesystem\Filesystem())->deleteDirectory($temporaryFiles));
$responses = [];
foreach ($input['requests'] as $case) {
    $app->make('auth')->forgetGuards();
    $app->make('auth')->shouldUse('web');
    if (isset($case['user_id'])) $app->make('auth')->guard('web')->setUser(App\Models\Tenant\User::findOrFail($case['user_id']));
    $request = Illuminate\Http\Request::create('http://fiscal-http.example.test' . $case['path'], $case['method'] ?? 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'], json_encode($case['body'] ?? []));
    if (isset($case['api_token'])) $request->headers->set('Authorization', 'Bearer ' . $case['api_token']);
    // Test fixture for a committed commercial operation interrupted before provider dispatch.
    // This branch is not an HTTP endpoint and does not claim HTTP registration coverage.
    if (!empty($case['register_only'])) {
        $response = (new App\CoreFacturalo\InputRequest())->handle($request, function ($prepared) {
            $data = $prepared->all();
            $fact = (new App\CoreFacturalo\Facturalo())->saveFiscal($data, (int) $data['fiscal_profile_id'],
                $data['operation_key'], $data['fiscal_fingerprint'], $data['fiscal_channel'], $data['fiscal_device_group_id'] ?? null);
            return response()->json(['success' => true, 'document_id' => $fact->getDocument()->id], 201);
        }, 'document', 'api');
    } else {
        $response = $kernel->handle($request);
    }
    $pdfFiles = is_dir($temporaryFiles) ? array_values(array_filter((new Illuminate\Filesystem\Filesystem())->allFiles($temporaryFiles), fn ($file) => $file->getExtension() === 'pdf')) : [];
    $responses[] = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'pdf_count' => count($pdfFiles)];
    $kernel->terminate($request, $response);
}
fwrite(STDOUT, "\nFISCAL_HTTP_RESULT\n" . json_encode($responses, JSON_THROW_ON_ERROR));
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
