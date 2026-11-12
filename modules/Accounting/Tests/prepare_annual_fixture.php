<?php
/** Fictitious books only, inside the guarded private test clone. Not an API endpoint. */
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (!app()->environment('testing') || !config('accounting.isolated_tests') || getenv('ACCOUNTING_FIXTURE_LOCKED') !== '1' || !in_array($argv[1] ?? '', ['http', 'ui'], true)) {
        throw new RuntimeException('Se requiere el clon aislado y el bloqueo de la suite.');
    }
    $host = Hyn\Tenancy\Models\Hostname::with('website')->where('fqdn', 'contabilidad.localhost')->firstOrFail();
    $environment = app(Hyn\Tenancy\Environment::class);
    $environment->hostname($host);
    $environment->tenant($host->website);
    $db = Illuminate\Support\Facades\DB::connection('tenant');
    if ($db->getConfig('host') !== 'accounting-db' || $db->getDatabaseName() !== 'tenancy_bbc') {
        throw new RuntimeException('El destino no es la copia privada autorizada.');
    }
    $actors = App\Models\Tenant\User::without('establishment')->whereIn('id', [2, 3])->get();
    if ($actors->count() !== 2 || $actors->contains(fn($actor) => !str_ends_with($actor->email, '.invalid'))) {
        throw new RuntimeException('Las identidades de la fixture deben ser ficticias.');
    }
    $organization = $db->table('accounting_organization_memberships')->where('user_id', 3)->where('role', 'admin')->where('active', true)->value('organization_id');
    if (!$organization) {
        throw new RuntimeException('La identidad ficticia no tiene su organización de pruebas.');
    }
    $year = now('America/Caracas')->year - 1;
    $company = app(Modules\Accounting\Services\CompanyService::class)->create(['name' => 'Cierre anual ficticio ' . strtoupper($argv[1]) . ' ' . Illuminate\Support\Str::random(8), 'tax_id' => 'J-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT) . '-0', 'tax_profile' => 'ordinary', 'approval_mode' => 'separate'], $organization, 3, $year);
    app(Modules\Accounting\Domain\CompanyContext::class)->set($company);
    $db->table('accounting_memberships')->where('company_id', $company->id)->where('user_id', 3)->update(['roles' => json_encode(['admin', 'accountant'])]);
    $db->table('accounting_memberships')->insert(['id' => (string) Illuminate\Support\Str::uuid(), 'organization_id' => $organization, 'company_id' => $company->id, 'user_id' => 2, 'role' => 'accountant', 'roles' => json_encode(['accountant']), 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $accounts = app(Modules\Accounting\Services\CompanyService::class)->defaultChart();
    $entries = app(Modules\Accounting\Services\EntryService::class);
    foreach ([['01-15', '4.1', '1000.00', false], ['02-15', '6.1', '250.00', true]] as [$date, $code, $amount, $expense]) {
        $entry = $entries->create(['accounting_date' => $year . '-' . $date, 'concept' => 'Operación ficticia para verificar el cierre anual', 'lines' => [
            ['account_id' => $accounts['1.1.02']->id, 'debit' => $expense ? '0' : $amount, 'credit' => $expense ? $amount : '0'],
            ['account_id' => $accounts[$code]->id, 'debit' => $expense ? $amount : '0', 'credit' => $expense ? '0' : $amount],
        ]], 2);
        $entry = $entries->transition($entry->id, $entry->version, 2, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, 3, 'approve', 'Revisión independiente del caso ficticio anual.');
        $entries->post($entry->id, $entry->version, 3, 'annual-fixture:' . $entry->id);
    }
    $monthly = app(Modules\Accounting\Services\ClosingService::class);
    for ($month = 1; $month < 12; $month++) {
        $period = Modules\Accounting\Models\Period::where('year', $year)->where('month', $month)->firstOrFail();
        $review = $monthly->preview($period->id);
        $monthly->close($period->id, $period->version, ['review_token' => $review['review_token'], 'reason' => 'Cierre mensual ficticio que prepara la revisión anual.', 'attestations' => array_fill_keys(Modules\Accounting\Services\ClosingService::ATTESTATIONS, 'Papel de trabajo ficticio conciliado para la fixture anual.'), 'exceptions' => array_fill_keys(array_keys($review['pending']), 'Operaciones ficticias con sus soportes identificados y revisados.')], 3, 'annual-fixture-month:' . $period->id);
    }
    $result = ['company_id' => $company->id, 'company_name' => $company->name, 'year' => $year, 'result' => '750.00', 'result_account_id' => $accounts['3.3']->id];
    $path = dirname(__DIR__, 3) . '/.accounting-isolated/annual-' . $argv[1] . '-fixture.json';
    file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    chmod($path, 0600);
    echo "Fixture anual ficticia preparada dentro del clon privado.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
