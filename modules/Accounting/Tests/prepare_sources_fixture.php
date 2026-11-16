<?php
/** Fictitious source cases inserted only into the guarded private SAB clone. */
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;
use Modules\Accounting\Domain\CompanyContext;
use Modules\Accounting\Services\{CompanyService, TaxConfigurationService, DocumentService, EntryService, Pro9SourceReader};

try {
    if (!app()->environment('testing') || !config('accounting.isolated_tests') || getenv('ACCOUNTING_FIXTURE_LOCKED') !== '1' || !in_array($argv[1] ?? '', ['http', 'ui'], true)) { throw new RuntimeException('Se requiere el clon aislado y el bloqueo de la suite.'); }
    $host = Hyn\Tenancy\Models\Hostname::with('website')->where('fqdn', 'contabilidad.localhost')->firstOrFail();
    $environment = app(Hyn\Tenancy\Environment::class); $environment->hostname($host); $environment->tenant($host->website);
    $db = Illuminate\Support\Facades\DB::connection('tenant');
    if ($db->getConfig('host') !== 'accounting-db' || $db->getDatabaseName() !== 'tenancy_bbc') { throw new RuntimeException('El destino no es la copia privada autorizada.'); }
    $actors = App\Models\Tenant\User::without('establishment')->whereIn('id', [2, 3])->get();
    if ($actors->count() !== 2 || $actors->contains(fn($actor) => !str_ends_with($actor->email, '.invalid'))) { throw new RuntimeException('Las identidades deben ser ficticias.'); }
    $organization = $db->table('accounting_organization_memberships')->where('user_id', 3)->where('role', 'admin')->where('active', true)->value('organization_id');
    if (!$organization) { throw new RuntimeException('Falta la organización de pruebas.'); }
    $year = (int) now('America/Caracas')->year; $date = $year . '-11-10'; $companies = app(CompanyService::class);
    $makeCompany = function (string $name) use ($organization, $year, $db, $companies) {
        $company = $companies->create(['name' => $name . ' ' . Str::random(7), 'tax_id' => 'J-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT) . '-0', 'tax_profile' => 'ordinary', 'approval_mode' => 'self_control'], $organization, 3, $year);
        app(CompanyContext::class)->set($company);
        $db->table('accounting_memberships')->where('company_id', $company->id)->where('user_id', 3)->update(['roles' => json_encode(['admin', 'accountant'])]);
        $db->table('accounting_memberships')->insert(['id' => (string) Str::uuid(), 'organization_id' => $organization, 'company_id' => $company->id, 'user_id' => 2, 'role' => 'auxiliary', 'roles' => json_encode(['auxiliary']), 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return $company;
    };
    $company = $makeCompany('Origen ficticio ' . strtoupper($argv[1])); $accounts = $companies->defaultChart();
    // SQL templates stay private. The reader exposes only explicitly replaced,
    // fictitious fields; no production operation or emission is executed.
    $nativeCompany = (array) $db->table('companies')->first(); unset($nativeCompany['id']);
    $nativeCompany['name'] = 'Origen nativo ficticio'; $nativeCompany['number'] = $company->tax_id;
    $nativeId = $db->table('companies')->insertGetId($nativeCompany);
    $company->settings = [...($company->settings ?? []), 'source_company_id' => $nativeId]; $company->save(); app(CompanyContext::class)->set($company->fresh());
    $tax = app(TaxConfigurationService::class);
    $tax->addProfile(['profile' => 'ordinary', 'effective_from' => $year . '-01-01', 'source_reference' => 'Supuesto ficticio aislado. No configura una norma.', 'details' => ['input_tax_treatment' => 'deductible', 'spe' => false]], 3);
    $rule = $tax->createRule(['code' => 'IVA-FICTICIO', 'name' => 'IVA del ejemplo ficticio', 'tax' => 'IVA', 'scenario' => 'invoice', 'rate_percent' => '16', 'effective_from' => $year . '-01-01', 'source_reference' => 'Prueba ficticia, sin alícuota predeterminada del producto.'], 3); $rule = $tax->approveRule($rule->id, $rule->version, 3);
    $template = (array) $db->table('documents')->first(); unset($template['id']);
    if (!$template) { throw new RuntimeException('La copia privada necesita una estructura de documento para estos casos.'); }
    $result = ['company_id' => $company->id, 'company_name' => $company->name, 'date' => $date, 'cases' => []];
    foreach (['metadata', 'monetary', 'missing'] as $index => $kind) {
        $native = array_replace($template, ['external_id' => (string) Str::uuid(), 'unique_filename' => 'ficticio-origen-' . Str::uuid(), 'series' => 'ACCT-' . strtoupper(Str::random(6)), 'number' => $index + 1, 'document_type_id' => '01', 'date_of_issue' => $date, 'state_type_id' => '01', 'currency_type_id' => 'VES',
            'customer' => json_encode(['name' => 'Cliente ficticio de origen', 'number' => 'J-11111111-1']), 'total' => '1160', 'total_value' => '1000', 'total_igv' => '160', 'total_taxes' => '160', 'exchange_rate_sale' => '1', 'calculation_snapshot' => null, 'fiscal_emission_mode' => 'local', 'control_number' => null, 'created_at' => $date . ' 08:00:00', 'updated_at' => $date . ' 08:00:00']);
        $id = $db->table('documents')->insertGetId($native);
        $document = app(Pro9SourceReader::class)->receive('sales', $id, 3)['data'];
        $document = app(DocumentService::class)->validate($document->id, $document->version, ['source_confirmed' => true, 'tax_components' => [['tax' => 'IVA', 'rule_id' => $rule->id, 'base' => '1000', 'amount' => '160']]], 3);
        $entry = app(DocumentService::class)->propose($document->id, $document->version, $date, ['counterparty' => $accounts['1.1.03']->id, 'base' => $accounts['4.1']->id, 'vat' => $accounts['2.1.02']->id, 'rounding' => $accounts['6.4']->id], 3);
        $entry = app(EntryService::class)->transition($entry->id, $entry->version, 3, 'submit');
        $entry = app(EntryService::class)->transition($entry->id, $entry->version, 3, 'approve', 'Autocontrol explícito del origen ficticio y su efecto contable.');
        app(EntryService::class)->post($entry->id, $entry->version, 3, 'source-fixture-post-' . Str::uuid()); $entry->refresh();
        $case = ['document_id' => $document->id, 'entry_id' => $entry->id, 'entry_reference' => $entry->reference, 'native_id' => $id];
        if ($kind === 'missing') { $db->table('documents')->where('id', $id)->delete(); }
        else {
            $changes = ['updated_at' => $date . ' 09:00:00'];
            if ($kind === 'monetary') { $changes += ['total' => '2320', 'total_value' => '2000', 'total_igv' => '320', 'total_taxes' => '320']; }
            $db->table('documents')->where('id', $id)->update($changes);
            $received = app(Pro9SourceReader::class)->receive('sales', $id, 3); $case['incident_id'] = $received['incident_id'];
        }
        $result['cases'][$kind] = $case;
    }
    $foreign = $makeCompany('Empresa ajena ficticia de origen'); $result['foreign_company_id'] = $foreign->id;
    $file = dirname(__DIR__, 3) . '/.accounting-isolated/sources-' . $argv[1] . '-fixture.json'; file_put_contents($file, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); chmod($file, 0600);
    echo "Fixture de origen ficticio preparada en el clon privado.\n";
} catch (Throwable $error) {
    $message = $error instanceof Illuminate\Database\QueryException ? 'La estructura nativa del clon rechazó el fixture. Código SQL: ' . ($error->errorInfo[1] ?? 'desconocido') : $error->getMessage();
    fwrite(STDERR, $message . "\n"); exit(1);
}
