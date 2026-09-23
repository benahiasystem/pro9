<?php

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
require dirname(__DIR__, 2) . '/vendor/autoload.php';
// Fixed fixture issue dates must not expire when the suite runs on a later day.
Carbon\Carbon::setTestNow('2026-09-13 12:00:00');
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
    $caseBody = $case['body'] ?? [];
    $casePath = $case['path'];
    if (!empty($case['subject_operation_key'])) {
        $subjectReservation = Illuminate\Support\Facades\DB::connection('tenant')->table('fiscal_number_reservations')
            ->where('operation_key', $case['subject_operation_key'])->first();
        if (!$subjectReservation || !$subjectReservation->document_id) throw new RuntimeException('Missing fixture subject reservation.');
        $casePath = str_replace('{subject}', (string) $subjectReservation->document_id, $casePath);
    }
    if (!empty($case['fiscal_profile_name'])) {
        $caseBody['profile_id'] = Illuminate\Support\Facades\DB::connection('tenant')->table('fiscal_profiles')
            ->where('name', $case['fiscal_profile_name'])->value('id');
    }
    $request = Illuminate\Http\Request::create('http://fiscal-http.example.test' . $casePath, $case['method'] ?? 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'], json_encode($caseBody));
    if (isset($case['api_token'])) $request->headers->set('Authorization', 'Bearer ' . $case['api_token']);
    // Test fixture for a committed commercial operation interrupted before provider dispatch.
    // This branch is not an HTTP endpoint and does not claim HTTP registration coverage.
    if (!empty($case['sale_note_fixture'])) {
        // Persist a source sale through real model observers; this is not an API creation test.
        $source = App\Models\Tenant\Document::findOrFail(1);
        $db = $source->getConnection();
        $note = $db->transaction(function () use ($db, $source, $case) {
            if (!$db->table('cash')->where('user_id', auth()->id())->where('state', true)->exists()) {
                $db->table('cash')->insert(['user_id' => auth()->id(), 'date_opening' => '2026-09-13', 'time_opening' => '12:00:00', 'state' => true]);
            }
            $attributes = array_intersect_key($source->getAttributes(), array_flip($db->getSchemaBuilder()->getColumnListing('sale_notes')));
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
            $filename = 'HTTP-NV-' . Illuminate\Support\Str::uuid()->toString();
            $attributes['filename'] = $filename;
            $attributes['unique_filename'] = $filename;
            foreach ($attributes as $key => $value) if (is_numeric($value) && (str_starts_with($key, 'total_') || in_array($key, ['total', 'subtotal']))) $attributes[$key] = bcmul((string) $value, (string) ($case['amount_factor'] ?? 1), 6);
            foreach (['discount' => -1, 'charge' => 1] as $kind => $sign) {
                if (empty($case['global_' . $kind])) continue;
                $amount = (string) $case['global_' . $kind];
                $base = (string) $attributes['total'];
                $attributes['total_' . $kind] = bcadd((string) $attributes['total_' . $kind], $amount, 2);
                $attributes['total'] = $sign === -1 ? bcsub($base, $amount, 2) : bcadd($base, $amount, 2);
                $attributes[$kind . 's'] = json_encode([[$kind . '_type_id' => $kind === 'discount' ? '03' : '50',
                    'description' => 'HTTP source global ' . $kind, 'base' => $base, 'amount' => $amount, 'factor' => bcdiv($amount, $base, 6)]]);
            }
            $note = new App\Models\Tenant\SaleNote();
            $note->setRawAttributes(array_replace($attributes, ['user_id' => auth()->id(), 'external_id' => Illuminate\Support\Str::uuid()->toString(), 'prefix' => 'NV', 'series' => 'NV01', 'number' => ((int) $db->table('sale_notes')->max('number')) + 1, 'document_id' => null]));
            $note->save();
            foreach ($source->items as $item) {
                $attributes = array_intersect_key($item->getAttributes(), array_flip($db->getSchemaBuilder()->getColumnListing('sale_note_items')));
                unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
                foreach ($attributes as $key => $value) if (is_numeric($value) && (str_starts_with($key, 'total_') || in_array($key, ['total', 'unit_price', 'unit_value']))) $attributes[$key] = bcmul((string) $value, (string) ($case['amount_factor'] ?? 1), 6);
                $copy = new App\Models\Tenant\SaleNoteItem();
                $copy->setRawAttributes(array_replace($attributes, ['sale_note_id' => $note->id]));
                $copy->save();
            }
            $payment = $note->payments()->create(['date_of_payment' => '2026-09-13', 'payment_method_type_id' => '01', 'payment' => 100]);
            $cash = $db->table('cash')->where('user_id', auth()->id())->where('state', true)->first();
            $payment->global_payment()->create(['user_id' => auth()->id(), 'fiscal_environment' => 'demo', 'destination_id' => $cash->id, 'destination_type' => App\Models\Tenant\Cash::class]);
            $cashDocument = $db->table('cash_documents')->where('sale_note_id', $note->id)->first();
            $db->table('cash_document_payments')->insert(['cash_id' => $cash->id, 'cash_document_id' => $cashDocument->id, 'sale_note_payment_id' => $payment->id]);
            return $note;
        });
        $response = response()->json(['id' => $note->id], 201);
    } elseif (!empty($case['register_only'])) {
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
    $entry = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'pdf_count' => count($pdfFiles)];
    if ($response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
        $zip = new ZipArchive();
        $path = $response->getFile()->getPathname();
        if ($zip->open($path) === true) {
            $entry['xlsx_text'] = strip_tags((string) $zip->getFromName('xl/sharedStrings.xml'));
            $entry['xlsx_sheet'] = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
        }
        if (str_contains($path, 'laravel-excel')) unlink($path);
    }
    $responses[] = $entry;
    $kernel->terminate($request, $response);
}
fwrite(STDOUT, "\nFISCAL_HTTP_RESULT\n" . json_encode($responses, JSON_THROW_ON_ERROR));
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
