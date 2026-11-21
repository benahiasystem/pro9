<?php
/** Fictitious books only, inside the guarded private clone. Not an endpoint. */
require dirname(__DIR__, 3) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 3) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Modules\Accounting\Models\{Account, ThirdParty, CostCenter, OpenItem, Incident};
use Modules\Accounting\Services\{CompanyService, EntryService, OpeningService, DocumentService, SettlementService};

try {
    if (!app()->environment('testing') || !config('accounting.isolated_tests') || getenv('ACCOUNTING_FIXTURE_LOCKED') !== '1' || !in_array($argv[1] ?? '', ['http', 'ui'], true)) {
        throw new RuntimeException('Se requiere el clon aislado y el bloqueo de la suite.');
    }
    $host = App\Tenancy\Models\Hostname::with('website')->where('fqdn', 'contabilidad.localhost')->firstOrFail();
    $environment = app(App\Tenancy\Environment::class);
    $environment->hostname($host);
    $environment->tenant($host->website);
    $db = Illuminate\Support\Facades\DB::connection('tenant');
    if ($db->getConfig('host') !== 'accounting-db' || $db->getDatabaseName() !== 'tenancy_bbc') {
        throw new RuntimeException('El destino no es la copia privada autorizada.');
    }
    $actors = App\Models\Tenant\User::without('establishment')->whereIn('id', [2, 3])->get();
    if ($actors->count() !== 2 || $actors->contains(fn($actor) => !str_ends_with($actor->email, '.invalid'))) {
        throw new RuntimeException('Las identidades deben ser ficticias.');
    }
    $organization = $db->table('accounting_organization_memberships')->where('user_id', 3)->where('role', 'admin')->where('active', true)->value('organization_id');
    if (!$organization) { throw new RuntimeException('Falta la organización de pruebas.'); }
    $year = now('America/Caracas')->year;
    $service = app(CompanyService::class);
    $company = $service->create(['name' => 'Exportaciones ficticias ' . strtoupper($argv[1]) . ' ' . Illuminate\Support\Str::random(8), 'tax_id' => 'J-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT) . '-0', 'tax_profile' => 'ordinary', 'approval_mode' => 'self_control'], $organization, 3, $year);
    app(Modules\Accounting\Domain\CompanyContext::class)->set($company);
    $db->table('accounting_memberships')->where('company_id', $company->id)->where('user_id', 3)->update(['roles' => json_encode(['admin', 'accountant'])]);
    $db->table('accounting_memberships')->insert(['id' => (string) Illuminate\Support\Str::uuid(), 'organization_id' => $organization, 'company_id' => $company->id, 'user_id' => 2, 'role' => 'viewer', 'roles' => json_encode(['viewer']), 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $accounts = $service->defaultChart();
    $service->createYear($year - 1);
    $party = ThirdParty::create(['name' => 'Servicios Mérida ficticios, C.A.', 'tax_id' => 'J-00000123-0', 'kind' => 'both']);
    $center = CostCenter::create(['code' => 'CC-ADM', 'name' => 'Administración ficticia']);
    $entries = app(EntryService::class);
    $post = function ($entry) use ($entries) {
        $entry = $entries->transition($entry->id, $entry->version, 3, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, 3, 'approve', 'Autocontrol explícito del libro ficticio para pruebas de informes.');
        $entries->post($entry->id, $entry->version, 3, 'reports-fixture:' . $entry->id);
        return $entry->fresh();
    };
    $historical = ($year - 1) . '-01-14';
    $document = app(DocumentService::class)->create(['kind' => 'sale_invoice', 'number' => 'INICIAL-REPORTES', 'issue_date' => $historical, 'fiscal_date' => $historical, 'third_party_id' => $party->id, 'currency' => 'USD', 'exchange_rate' => '40', 'exchange_date' => $historical, 'exchange_source' => 'Tasa histórica ficticia', 'amounts' => ['base' => '100', 'vat' => '0', 'other_taxes' => '0', 'total' => '100']], 3);
    $post(app(OpeningService::class)->prepare(['cutoff_date' => ($year - 1) . '-01-15', 'reason' => 'Saldo anterior ficticio conciliado para informes.', 'evidence_reference' => 'Estado previo ficticio verificado', 'balances' => [['account_id' => $accounts['1.1.03']->id, 'debit' => '4000', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '4000']], 'open_items' => [['account_id' => $accounts['1.1.03']->id, 'third_party_id' => $party->id, 'source_document_id' => $document->id, 'reference' => 'INICIAL-REPORTES', 'issue_date' => $historical, 'currency' => 'USD', 'original_amount' => '100', 'book_amount' => '4000', 'exchange_rate' => '40', 'exchange_date' => $historical, 'exchange_source' => 'Tasa histórica ficticia', 'evidence_reference' => 'Saldo anterior ficticio conciliado']]], 3));
    $manual = function (string $date, string $amount, string $code, bool $expense = false) use ($entries, $post, $accounts, $party, $center) {
        return $post($entries->create(['accounting_date' => $date, 'concept' => $expense ? 'Gasto administrativo ficticio' : 'Venta ficticia de servicios', 'lines' => [
            ['account_id' => $accounts['1.1.02']->id, 'debit' => $expense ? '0' : $amount, 'credit' => $expense ? $amount : '0', 'third_party_id' => $party->id, 'cost_center_id' => $center->id],
            ['account_id' => $accounts[$code]->id, 'debit' => $expense ? $amount : '0', 'credit' => $expense ? '0' : $amount, 'third_party_id' => $party->id, 'cost_center_id' => $center->id],
        ]], 3));
    };
    $manual(($year - 1) . '-03-10', '1000', '4.1');
    $manual($year . '-11-10', '2000', '4.1');
    $manual($year . '-11-10', '250', '6.1', true);
    $lines = [];
    for ($i = 0; $i < 250; ++$i) {
        $lines[] = ['account_id' => $accounts['1.1.02']->id, 'debit' => '0.01', 'credit' => '0'];
        $lines[] = ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '0.01'];
    }
    $post($entries->create(['accounting_date' => $year . '-11-11', 'concept' => 'Comprobante ficticio de 500 líneas', 'lines' => $lines], 3));
    $item = OpenItem::firstOrFail();
    $settlement = $post(app(SettlementService::class)->prepare(['direction' => 'receipt', 'accounting_date' => $year . '-11-12', 'reference' => 'RC-REPORTES', 'third_party_id' => $party->id, 'cash_account_id' => $accounts['1.1.02']->id, 'currency' => 'USD', 'amount' => '40', 'exchange_rate' => '42', 'exchange_date' => $year . '-11-12', 'exchange_source' => 'Medición ficticia para informes', 'evidence_reference' => 'Banco ficticio de pruebas', 'rate_reason' => 'Tasa didáctica para comprobar saldos históricos.', 'tax_review_reference' => 'Tributos ficticios revisados por separado; sin inferir exenciones.', 'gain_account_id' => $accounts['4.2']->id, 'loss_account_id' => $accounts['6.2']->id, 'applications' => [['open_item_id' => $item->id, 'original_amount' => '40', 'measurement_rate' => '42']]], 3));
    $post($entries->prepareReversal($settlement->id, $year . '-12-05', 'Reverso ficticio posterior al corte que no altera noviembre.', 3));
    $pending = app(DocumentService::class)->create(['kind' => 'sale_invoice', 'number' => 'MANUAL-REPORTES', 'issue_date' => $year . '-11-10', 'fiscal_date' => $year . '-11-10', 'third_party_id' => $party->id, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_date' => $year . '-11-10', 'exchange_source' => 'Moneda del libro', 'amounts' => ['base' => '1000', 'vat' => '160', 'other_taxes' => '0', 'total' => '1160']], 3);
    Incident::create(['kind' => 'source_changed', 'resource_type' => 'source_document', 'resource_id' => $pending->id, 'title' => 'Documento ficticio pendiente de revisión', 'details' => ['private_token' => 'REPORT_PRIVATE_HTTP_SENTINEL'], 'status' => 'open']);
    $entries->create(['accounting_date' => $year . '-11-10', 'concept' => 'Borrador ficticio excluido de los libros', 'lines' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '99', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '99']]], 3);
    $foreign = $service->create(['name' => 'Empresa ajena ficticia para informes', 'tax_id' => 'J-' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT) . '-0', 'tax_profile' => 'ordinary'], $organization, 3, $year);
    $foreignAccount = app(Modules\Accounting\Domain\CompanyContext::class)->run($foreign, fn() => $service->defaultChart()['1.1.02']->id);
    $result = ['company_id' => $company->id, 'company_name' => $company->name, 'company_rif' => $company->tax_id, 'year' => $year, 'from' => $year . '-01-01', 'to' => $year . '-11-30', 'bank_id' => $accounts['1.1.02']->id, 'party_id' => $party->id, 'center_id' => $center->id, 'foreign_company_id' => $foreign->id, 'foreign_account_id' => $foreignAccount, 'document_id' => $pending->id, 'journal_rows' => 507, 'debit' => '3932.50', 'result' => '1830.00', 'assets' => '6832.50'];
    $path = dirname(__DIR__, 3) . '/.accounting-isolated/reports-' . $argv[1] . '-fixture.json';
    file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); chmod($path, 0600);
    echo "Fixture de informes ficticios preparada dentro del clon privado.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n"); exit(1);
}
