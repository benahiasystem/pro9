<?php

/** Fixture run inside a private Compose project, with its own central database and storage. */
declare(strict_types=1);

use App\Database\SchemaInspector;
use App\Database\SequenceSynchronizer;
use App\Http\Controllers\System\ClientController;
use App\Http\Requests\System\ClientRequest;
use App\Models\System\User;
use App\Tenancy\Database\Connection as TenantConnection;
use App\Tenancy\Models\Website;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function verifyRecovery(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function recoveryData(Connection $db): array
{
    $tables = [];
    foreach (SchemaInspector::tables($db) as $table) {
        $rows = $db->table($table)->get()->map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->all();
        sort($rows, SORT_STRING);
        $tables[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
    return $tables;
}

function tenantRecoveryConnection(Website $website): Connection
{
    config(['database.connections.recovery_tenant' => app(TenantConnection::class)->generateConfigurationArray($website)]);
    DB::purge('recovery_tenant');
    return DB::connection('recovery_tenant');
}

function recoveryFile(string $relativePath, string $contents): void
{
    $file = storage_path($relativePath);
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0750, true);
    verifyRecovery(file_put_contents($file, $contents) === strlen($contents), 'No se pudo escribir el archivo temporal.');
}

$project = getenv('SAB_RECOVERY_PROJECT') ?: '';
$mode = $argv[1] ?? '';
verifyRecovery(PHP_SAPI === 'cli' && getenv('APP_ENV') === 'testing'
    && preg_match('/^sab-infra-[a-f0-9]{12}(?:-destination)?$/D', $project) === 1
    && getenv('DB_HOST') === 'postgres'
    && getenv('DB_DATABASE') === (str_ends_with($project, '-destination') ? 'sab_infra_destination' : 'sab_infra_central'),
    'Use únicamente el proyecto Compose temporal del sondeo.');
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
$runtime = DB::connection('system');
$marker = $runtime->selectOne("SELECT current_setting('sab.test_run', true) AS marker, current_setting('server_version_num') AS version");
verifyRecovery($marker->marker === $project && (int) $marker->version === 170011, 'La conexión no pertenece al sondeo temporal previsto.');
$destination = str_ends_with($project, '-destination');
$central = DB::connection('central_migrator');
$companies = ['one' => 'J123456789', 'two' => 'J987654321'];
$token = static fn (string $slug) => substr(hash('sha256', 'temporary-recovery-fixture-'.$slug), 0, 60);

if ($mode === 'seed') {
    verifyRecovery(!$destination, 'El fixture se genera sólo en el origen.');
    Http::fake(static function () { throw new RuntimeException('El sondeo no usa HTTP externo.'); });
    Auth::guard('admin')->setUser(User::firstOrFail());
    foreach ($companies as $slug => $number) {
        $request = ClientRequest::create('/clients', 'POST', [
            'name' => 'Empresa recuperación '.$slug, 'email' => $slug.'@recovery.example.test', 'number' => $number,
            'password' => 'Fictional-recovery-password-17', 'subdomain' => $slug, 'plan_id' => $runtime->table('plans')->where('name', 'Ilimitado')->value('id'), 'plan_period_id' => 1,
            'price' => '99', 'locked_emission' => false, 'enable_list_product' => true, 'type' => 'admin',
            'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'free_form', 'fiscal_configuration' => [],
            'fiscal_credentials' => null, 'modules' => [], 'levels' => [],
        ]);
        $created = app(ClientController::class)->store($request);
        verifyRecovery($created['success'] ?? false, $created['message'] ?? 'Falló el alta temporal.');
        $website = Website::where('uuid', 'sab_recovery_'.$slug)->firstOrFail();
        $db = tenantRecoveryConnection($website);
        $db->table('users')->update(['api_token' => $token($slug), 'email_verified_at' => '2026-11-17 12:00:00']);
        $db->statement('CREATE TABLE recovery_amounts (id bigserial PRIMARY KEY, company_id bigint NOT NULL REFERENCES companies(id), amount numeric(30,12) NOT NULL, snapshot json NOT NULL)');
        $db->table('recovery_amounts')->insert(['id' => 40, 'company_id' => 1,
            'amount' => $slug === 'one' ? '9007199254740993.300000000001' : '0.300000000002',
            'snapshot' => '{"n":"1","d":"3"}']);
        SequenceSynchronizer::sync($db);
        app(\App\Services\Storage\TenantContext::class)->run($website->uuid, function () use ($slug) {
            $disk = app(\App\Services\Storage\TenantStorage::class)->disk();
            $disk->put('probe/receipt.txt', 'fixture-'.$slug);
            verifyRecovery($disk->get('probe/receipt.txt') === 'fixture-'.$slug, 'No se pudo leer el objeto privado.');
        });
        recoveryFile('app/tenancy/tenants/'.$website->uuid.'/receipt.pdf', "%PDF-1.4\n% Fixture {$slug}\n%%EOF\n");
    }
    recoveryFile('app/public/uploads/logos/recovery.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRj0AAAAASUVORK5CYII=', true));
    exit(0);
}

if ($mode === 'http') {
    verifyRecovery($destination && !$app->isDownForMaintenance(), 'HTTP sólo se comprueba al finalizar la recuperación.');
    foreach ($companies as $host => $number) {
        foreach ($companies as $user => $unused) {
            $response = Http::withHeaders(['Host' => $host.'.recovery.example.test'])->withToken($token($user))
                ->acceptJson()->timeout(30)->get('http://'.$project.'-http/api/company');
            verifyRecovery($response->status() === ($host === $user ? 200 : 401), 'Estado HTTP inesperado en '.$host.' para '.$user.': '.$response->status());
            if ($host === $user) verifyRecovery(str_contains($response->body(), $number), 'HTTP no leyó la empresa esperada.');
        }
    }
    echo json_encode(['same_tenant_200' => 2, 'cross_tenant_401' => 2], JSON_THROW_ON_ERROR);
    exit(0);
}

verifyRecovery(in_array($mode, ['central', 'tenants'], true), 'Modo de sondeo inválido.');
verifyRecovery($runtime->table('plans')->count() === 1 && $runtime->table('websites')->count() === 2, 'El rol runtime no puede leer ambos tenants y planes recuperados.');
$centralTables = recoveryData($central);
$ledger = $centralTables['tenant_provisioning_runs'];
unset($centralTables['tenant_provisioning_runs']);
$result = ['central_data' => $centralTables, 'central_schema' => hash('sha256', json_encode(SchemaInspector::snapshot($central), JSON_THROW_ON_ERROR)),
    'ledger' => $ledger, 'ledger_count' => $central->table('tenant_provisioning_runs')->count()];
if ($mode === 'tenants') {
    $shared = storage_path('app/public/uploads/logos/recovery.png');
    verifyRecovery(is_file($shared), 'Falta el upload compartido.');
    $result['shared_file_sha256'] = hash_file('sha256', $shared);
    if ($destination) verifyRecovery((fileperms($shared) & 0777) === 0644, 'Permisos del upload restaurado inválidos.');
    foreach ($companies as $slug => $number) {
        $website = Website::where('uuid', 'sab_recovery_'.$slug)->firstOrFail();
        $db = tenantRecoveryConnection($website);
        verifyRecovery($db->table('companies')->where('id', 1)->value('number') === $number, 'No coincidió la identidad del tenant.');
        $file = storage_path('app/tenancy/tenants/'.$website->uuid.'/receipt.pdf');
        verifyRecovery(is_file($file), 'Falta el adjunto privado.');
        $result['tenants'][$website->uuid] = ['data' => recoveryData($db),
            'schema' => hash('sha256', json_encode(SchemaInspector::snapshot($db), JSON_THROW_ON_ERROR)),
            'attachment_sha256' => hash_file('sha256', $file)];
        app(\App\Services\Storage\TenantContext::class)->run($website->uuid, function () use ($slug) {
            $disk = app(\App\Services\Storage\TenantStorage::class)->disk();
            verifyRecovery($disk->get('probe/receipt.txt') === 'fixture-'.$slug, 'No coincide el objeto privado restaurado.');
        });
        if (!$destination) continue;
        verifyRecovery((fileperms($file) & 0777) === 0600, 'Permisos del adjunto restaurado inválidos.');
        $acl = $db->selectOne("SELECT pg_get_userbyid(d.datdba) AS owner, r.rolsuper, r.rolcreatedb, r.rolcreaterole,
            has_database_privilege(current_user, ?, 'CONNECT') AS central_access,
            has_database_privilege(current_user, ?, 'CONNECT') AS other_access
            FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database()",
            [$central->getDatabaseName(), 'sab_recovery_'.($slug === 'one' ? 'two' : 'one')]);
        verifyRecovery($acl->owner === $website->uuid && !$acl->rolsuper && !$acl->rolcreatedb && !$acl->rolcreaterole
            && !$acl->central_access && !$acl->other_access, 'Propiedad o aislamiento incorrecto en '.$website->uuid);
        $db->beginTransaction();
        try {
            $next = $db->table('recovery_amounts')->insertGetId(['company_id' => 1, 'amount' => '0.1', 'snapshot' => '{}']);
            verifyRecovery($next === 41, 'La secuencia restaurada no sigue a los datos.');
        } finally {
            $db->rollBack();
        }
    }
    if ($destination) {
        $next = $runtime->selectOne("SELECT nextval(pg_get_serial_sequence('public.plans', 'id')) AS id");
        verifyRecovery($next->id > $runtime->table('plans')->max('id'), 'La secuencia central o su permiso no se restauró.');
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR);
