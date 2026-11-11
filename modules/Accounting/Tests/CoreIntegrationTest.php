<?php

namespace Modules\Accounting\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Str;
use Hyn\Tenancy\Database\Connection as TenancyConnection;
use Modules\Accounting\Domain\{CompanyContext, ApiProblem, Exact};
use Modules\Accounting\Models\{Company, Account, JournalEntry, JournalLine, Period, SourceDocument, SourceVersion, ThirdParty, OpenItem, FiscalRecord};
use Modules\Accounting\Services\{CompanyService, EntryService, ReportService, DocumentService, TaxConfigurationService};
use Modules\Accounting\Support\TenantDB as DB;
use App\Models\Tenant\User;
final class CoreIntegrationTest extends TestCase
{
    private static Application $application;
    private User $admin;
    private Company $a;
    private Company $b;
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (getenv('ACCOUNTING_ISOLATED_TESTS') !== '1' || getenv('ACCOUNTING_TEST_DATABASE') !== 'accounting_module_test' || getenv('ACCOUNTING_TEST_HOST') !== 'accounting-db') {
            throw new \RuntimeException('Esta suite exige la base y red aisladas del módulo.');
        }
        $root = dirname(__DIR__, 3);
        $app = new Application($root);
        $config = new Repository(['app' => ['env' => 'testing', 'timezone' => 'America/Caracas'], 'accounting' => ['demo' => true], 'database' => ['default' => 'tenant']]);
        $app->instance('config', $config);
        $app->instance('date', new DateFactory());
        $events = new Dispatcher($app);
        $app->instance('events', $events);
        $manager = new Manager($app);
        $manager->addConnection(['driver' => 'mysql', 'host' => 'accounting-db', 'port' => 3306, 'database' => 'accounting_module_test', 'username' => getenv('ACCOUNTING_TEST_USERNAME'), 'password' => getenv('ACCOUNTING_TEST_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true], 'tenant');
        $manager->getDatabaseManager()->setDefaultConnection('tenant');
        $manager->setEventDispatcher($events);
        $manager->setAsGlobal();
        $manager->bootEloquent();
        $app->instance('db', $manager->getDatabaseManager());
        $tenant = new class extends TenancyConnection
        {
            public function __construct()
            {
            }
            public function tenantName(): string
            {
                return 'tenant';
            }
        };
        $app->instance(TenancyConnection::class, $tenant);
        $app->singleton(CompanyContext::class, fn() => new CompanyContext());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        self::$application = $app;
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('users')) {
            $schema->create('users', function ($t) {
                $t->increments('id');
                $t->string('name');
                $t->string('email')->unique();
                $t->string('password');
                $t->string('type')->default('admin');
                $t->boolean('active')->default(true);
                $t->boolean('locked')->default(false);
                $t->string('api_token')->nullable();
                $t->timestamps();
            });
        }
        $schema->disableForeignKeyConstraints();
        foreach (DB::select('SHOW TABLES') as $record) {
            $table = array_values((array) $record)[0];
            if (str_starts_with($table, 'accounting_')) {
                $schema->drop($table);
            }
        }
        $schema->enableForeignKeyConstraints();
        foreach (glob(dirname(__DIR__) . '/Database/Migrations/*.php') as $file) {
            (require $file)->up();
        }
        if (!DB::table('users')->where('email', 'contabilidad-test@example.invalid')->exists()) {
            DB::table('users')->insert(['name' => 'Contador de prueba aislada', 'email' => 'contabilidad-test@example.invalid', 'password' => password_hash('Fictitious-Accounting-Test-Only', PASSWORD_BCRYPT), 'type' => 'admin', 'active' => true, 'locked' => false, 'api_token' => str_repeat('t', 60), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->admin = User::without('establishment')->where('email', 'contabilidad-test@example.invalid')->firstOrFail();
        $organization = (string) Str::uuid();
        DB::table('accounting_organizations')->insert(['id' => $organization, 'name' => 'Pruebas aisladas', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(CompanyService::class);
        $this->a = $service->create(['name' => 'Empresa A de prueba', 'tax_id' => 'J-00000000-0', 'approval_mode' => 'self_control'], $organization, $this->admin->id);
        $this->b = $service->create(['name' => 'Empresa B de prueba', 'tax_id' => 'J-00000000-1', 'approval_mode' => 'self_control'], $organization, $this->admin->id);
        foreach ([$this->a, $this->b] as $co) {
            app(CompanyContext::class)->run($co, fn() => $service->defaultChart());
        }
        app(CompanyContext::class)->set($this->a);
    }
    protected function tearDown(): void
    {
        DB::rollBack();
        app(CompanyContext::class)->clear();
        parent::tearDown();
    }
    private function draft(): JournalEntry
    {
        $accounts = Account::get()->keyBy('code');
        return app(EntryService::class)->create(['accounting_date' => now('America/Caracas')->format('Y-m-d'), 'concept' => 'Movimiento de prueba aislada', 'lines' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '100.00', 'credit' => '0.00'], ['account_id' => $accounts['3.1']->id, 'debit' => '0.00', 'credit' => '100.00']]], $this->admin->id);
    }
    private function approved(): JournalEntry
    {
        $s = app(EntryService::class);
        $e = $this->draft();
        $e = $s->transition($e->id, $e->version, $this->admin->id, 'submit');
        return $s->transition($e->id, $e->version, $this->admin->id, 'approve', 'Autocontrol explícito del caso de prueba.');
    }
    public function test_decimal_inputs_preserve_precision_and_reject_floats(): void
    {
        $this->assertSame('0.30', Exact::total(['0.10', '0.20']));
        $this->expectException(ApiProblem::class);
        Exact::of(0.1);
    }
    public function test_administration_does_not_implicitly_authorize_posting(): void
    {
        $permissions = app(\Modules\Accounting\Domain\Permissions::class);
        $this->assertContains('users', $permissions->forMembership((object) ['role' => 'admin', 'roles' => null]));
        $this->assertNotContains('post', $permissions->forMembership((object) ['role' => 'admin', 'roles' => null]));
        $this->assertNotContains('approve', $permissions->forRole('admin'));
        $this->assertContains('post', $permissions->forMembership((object) ['role' => 'admin', 'roles' => '["admin","accountant"]']));
    }
    public function test_live_permissions_are_scoped_to_the_active_company(): void
    {
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['accountant'])]);
        app(\Modules\Accounting\Domain\Permissions::class)->require($this->admin, 'post');
        app(CompanyContext::class)->set($this->b);
        $this->expectException(ApiProblem::class);
        app(\Modules\Accounting\Domain\Permissions::class)->require($this->admin, 'post');
    }
    public function test_company_scope_prevents_reading_another_book(): void
    {
        $e = $this->draft();
        app(CompanyContext::class)->set($this->b);
        $this->assertNull(JournalEntry::find($e->id));
    }
    public function test_composite_foreign_key_prevents_cross_company_account(): void
    {
        $foreign = app(CompanyContext::class)->run($this->b, fn() => Account::where('posting', true)->firstOrFail());
        $e = $this->draft();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('accounting_journal_lines')->where('journal_entry_id', $e->id)->update(['account_id' => $foreign->id]);
    }
    public function test_posting_retries_have_a_single_effect(): void
    {
        $e = $this->approved();
        $s = app(EntryService::class);
        $one = $s->post($e->id, $e->version, $this->admin->id, 'retry-module-test-001');
        $two = $s->post($e->id, $e->version, $this->admin->id, 'retry-module-test-001');
        $this->assertSame($one['reference'], $two['reference']);
        $this->assertSame(1, JournalEntry::whereNotNull('posted_at')->count());
    }
    public function test_database_rejects_changes_to_posted_lines(): void
    {
        $e = $this->approved();
        app(EntryService::class)->post($e->id, $e->version, $this->admin->id, 'posted-immutable-001');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('accounting_journal_lines')->where('journal_entry_id', $e->id)->update(['debit' => '200.00']);
    }
    public function test_database_rejects_moving_a_draft_line_into_a_posted_entry(): void
    {
        $posted = $this->approved();
        app(EntryService::class)->post($posted->id, $posted->version, $this->admin->id, 'posted-move-target-001');
        $draft = $this->draft();
        $line = JournalLine::where('journal_entry_id', $draft->id)->firstOrFail();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('accounting_journal_lines')->where('id', $line->id)->update(['journal_entry_id' => $posted->id, 'position' => 99]);
    }
    public function test_ledger_page_balance_includes_previous_pages(): void
    {
        $s = app(EntryService::class);
        for ($i = 0; $i < 3; $i++) {
            $e = $this->approved();
            $s->post($e->id, $e->version, $this->admin->id, 'ledger-pagination-' . $i);
        }
        $report = app(ReportService::class)->build('ledger', ['account_id' => Account::where('code', '1.1.02')->firstOrFail()->id, 'per_page' => 1, 'page' => 3]);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('300.00', $report['rows'][0]['balance']);
        $this->assertSame(3, $report['total']);
    }
    public function test_closed_period_cannot_receive_an_entry(): void
    {
        $e = $this->approved();
        $p = Period::findOrFail($e->period_id);
        $p->status = 'closed';
        $p->save();
        try {
            app(EntryService::class)->post($e->id, $e->version, $this->admin->id, 'closed-period-test-001');
            $this->fail('Debe rechazar el período cerrado.');
        } catch (ApiProblem $p) {
            $this->assertSame('PERIOD_CLOSED', $p->errorCode);
        }
    }
    public function test_reversal_retains_original_and_cancels_ledger_effect(): void
    {
        $s = app(EntryService::class);
        $e = $this->approved();
        $s->post($e->id, $e->version, $this->admin->id, 'module-original-001');
        $r = $s->prepareReversal($e->id, now('America/Caracas')->format('Y-m-d'), 'Reverso de prueba', $this->admin->id);
        $r = $s->transition($r->id, $r->version, $this->admin->id, 'submit');
        $r = $s->transition($r->id, $r->version, $this->admin->id, 'approve', 'Autocontrol del reverso de prueba.');
        $s->post($r->id, $r->version, $this->admin->id, 'module-reversal-001');
        $report = app(ReportService::class)->build('ledger', ['account_id' => Account::where('code', '3.1')->firstOrFail()->id]);
        $this->assertSame('0.00', $report['totals']['balance']);
        $this->assertNotNull(JournalEntry::findOrFail($e->id)->posted_at);
        $this->assertSame(1, JournalEntry::whereHas('reversal', fn($query) => $query->whereNotNull('posted_at'))->count());
        app(CompanyContext::class)->set($this->b);
        $this->assertSame(0, JournalEntry::whereHas('reversal', fn($query) => $query->whereNotNull('posted_at'))->count());
    }
    public function test_stale_version_is_rejected_without_modification(): void
    {
        $e = $this->draft();
        $this->expectException(ApiProblem::class);
        app(EntryService::class)->version($e, $e->version + 1);
    }
    public function test_local_preparation_has_a_complete_configurable_range_and_fiscal_catalogs(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $included = $documents->create([...$configuration['input'], 'issue_date' => now()->subDays(40)->format('Y-m-d'), 'fiscal_date' => now()->subDays(40)->format('Y-m-d')], $this->admin->id);
        $documents->create([...$configuration['input'], 'number' => 'FV-FUERA', 'issue_date' => now()->subDays(150)->format('Y-m-d'), 'fiscal_date' => now()->subDays(150)->format('Y-m-d')], $this->admin->id);
        $package = app(\Modules\Accounting\Services\WorkspaceService::class)->prepare($this->admin, ['device_id' => (string) Str::uuid(), 'name' => 'Equipo ficticio del caso', 'period' => now()->format('Y-m')]);
        $this->assertSame(90, $package['scope']['maximum_days']);
        $this->assertSame(89, \Carbon\CarbonImmutable::parse($package['scope']['from'])->diffInDays($package['scope']['to']));
        $this->assertSame([$included->id], array_column($package['documents'], 'id'));
        $this->assertSame($configuration['profile']->id, $package['tax_configuration']['profiles']->first()->id);
        $this->assertArrayHasKey('trial-balance|' . now()->startOfMonth()->format('Y-m-d') . '|' . now()->endOfMonth()->format('Y-m-d'), $package['report_snapshots']);
        $this->assertArrayNotHasKey('token', $package);
        app(CompanyContext::class)->set($this->b);
        $other = app(\Modules\Accounting\Services\WorkspaceService::class)->prepare($this->admin, ['device_id' => (string) Str::uuid(), 'name' => 'Otro equipo ficticio', 'period' => now()->format('Y-m')]);
        $this->assertSame([], $other['documents']);
        $this->assertCount(0, $other['tax_configuration']['profiles']);
    }
    public function test_local_preparation_rejects_a_range_larger_than_the_company_setting(): void
    {
        $this->a->workspace_days = 7;
        $this->a->save();
        try {
            app(\Modules\Accounting\Services\WorkspaceService::class)->prepare($this->admin, ['device_id' => (string) Str::uuid(), 'name' => 'Equipo ficticio', 'period' => now()->format('Y-m'), 'from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->endOfMonth()->format('Y-m-d')]);
            $this->fail('El equipo no puede ampliar el alcance autorizado por la empresa.');
        } catch (ApiProblem $problem) {
            $this->assertSame('WORKSPACE_DATE_RANGE', $problem->errorCode);
        }
        $this->assertSame(0, \Modules\Accounting\Models\Device::count());
    }
    public function test_approval_is_invalidated_after_editing(): void
    {
        $e = $this->approved();
        $s = app(EntryService::class);
        $d = $s->resource($e);
        $e = $s->edit($e->id, ['accounting_date' => $d['accounting_date'], 'concept' => 'Concepto revisado', 'lines' => $d['lines']], $e->version, $this->admin->id);
        $this->assertSame('draft', $e->status);
        $this->assertNull($e->approved_by);
        $this->assertNull($e->approved_version);
    }
    private function source(): SourceDocument
    {
        $date = now('America/Caracas')->format('Y-m-d');
        return SourceDocument::create(['kind' => 'sale_invoice', 'number' => 'FV-PRUEBA', 'issue_date' => $date, 'fiscal_date' => $date, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_source' => 'Prueba aislada', 'exchange_date' => $date, 'amounts' => ['total' => '100.00'], 'fiscal_amounts' => [], 'status' => 'validated']);
    }
    private function initialBalance(string $side = 'receivable'): array
    {
        $date = now('America/Caracas')->format('Y-m-d');
        $before = now('America/Caracas')->subDay()->format('Y-m-d');
        $accounts = Account::get()->keyBy('code');
        $party = ThirdParty::create(['name' => 'Tercero ficticio de apertura', 'tax_id' => 'J-11111111-1', 'kind' => 'both']);
        $receivable = $side === 'receivable';
        $document = app(DocumentService::class)->create(['kind' => $receivable ? 'sale_invoice' : 'purchase_invoice', 'number' => 'INICIAL-1', 'issue_date' => $before, 'fiscal_date' => $before, 'third_party_id' => $party->id, 'currency' => 'USD', 'exchange_rate' => '40', 'exchange_source' => 'Tasa ficticia histórica', 'exchange_date' => $before, 'amounts' => ['base' => '100', 'vat' => '0', 'other_taxes' => '0', 'total' => '100']], $this->admin->id);
        $account = $accounts[$receivable ? '1.1.03' : '2.1.01'];
        $input = ['cutoff_date' => $date, 'reason' => 'Arranque ficticio a partir del saldo conciliado.', 'evidence_reference' => 'Estado anterior ficticio verificado', 'balances' => [['account_id' => $account->id, 'debit' => $receivable ? '4000.00' : '0.00', 'credit' => $receivable ? '0.00' : '4000.00'], ['account_id' => $accounts['3.1']->id, 'debit' => $receivable ? '0.00' : '4000.00', 'credit' => $receivable ? '4000.00' : '0.00']], 'open_items' => [['account_id' => $account->id, 'third_party_id' => $party->id, 'source_document_id' => $document->id, 'reference' => 'INICIAL-1', 'issue_date' => $before, 'currency' => 'USD', 'original_amount' => '100', 'book_amount' => '4000.00', 'exchange_rate' => '40', 'exchange_date' => $before, 'exchange_source' => 'Tasa ficticia histórica', 'evidence_reference' => 'Saldo anterior ficticio conciliado']]];
        $entry = app(\Modules\Accounting\Services\OpeningService::class)->prepare($input, $this->admin->id);
        return compact('entry', 'input', 'party', 'document', 'accounts');
    }
    private function postPrepared(JournalEntry $entry, string $key): JournalEntry
    {
        $service = app(EntryService::class);
        $entry = $service->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $service->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol explícito del caso aislado de auxiliares.');
        $service->post($entry->id, $entry->version, $this->admin->id, $key);
        return $entry->fresh();
    }
    private function settlementInput(OpenItem $item, string $original, string $reference, string $direction = 'receipt', bool $bankVes = false): array
    {
        $accounts = Account::get()->keyBy('code');
        return ['direction' => $direction, 'accounting_date' => now('America/Caracas')->format('Y-m-d'), 'reference' => $reference, 'third_party_id' => $item->third_party_id, 'cash_account_id' => $accounts['1.1.02']->id, 'currency' => $bankVes ? 'VES' : 'USD', 'amount' => $bankVes ? (string) \Brick\Math\BigDecimal::of($original)->multipliedBy('42') : $original, 'exchange_rate' => $bankVes ? '1' : '42', 'exchange_date' => now('America/Caracas')->format('Y-m-d'), 'exchange_source' => 'Medición didáctica de liquidación', 'evidence_reference' => 'Comprobante ficticio de banco', 'rate_reason' => 'Tasa histórica del caso didáctico de liquidación.', 'tax_review_reference' => 'Tributos del caso didáctico revisados por separado; sin inferir una exención.', 'gain_account_id' => $accounts['4.2']->id, 'loss_account_id' => $accounts['6.2']->id, 'applications' => [['open_item_id' => $item->id, 'original_amount' => $original, 'measurement_rate' => '42']]];
    }
    public function test_opening_and_partial_receipts_reconcile_without_repeating_historical_revenue(): void
    {
        $fixture = $this->initialBalance();
        $this->assertSame(0, OpenItem::count());
        $this->postPrepared($fixture['entry'], 'opening-ar-test');
        $item = OpenItem::firstOrFail();
        $this->assertTrue($item->is_opening);
        $this->assertSame('opening_antecedent', $fixture['document']->fresh()->status);
        $same = app(\Modules\Accounting\Services\OpeningService::class)->prepare($fixture['input'], $this->admin->id);
        $this->assertSame($fixture['entry']->id, $same->id);
        $this->assertSame(1, OpenItem::count());
        $service = app(\Modules\Accounting\Services\SettlementService::class);
        foreach ([['40', 'RC-PARCIAL', '60.000000000000', '2400.00000000'], ['60', 'RC-FINAL', '0.000000000000', '0.00000000']] as [$amount, $reference, $remaining, $book]) {
            $entry = $service->prepare($this->settlementInput($item, $amount, $reference), $this->admin->id);
            $this->postPrepared($entry, 'post-' . $reference);
            $item = $item->fresh();
            $this->assertSame($remaining, $item->remaining_original);
            $this->assertSame($book, $item->remaining_book);
        }
        $this->assertSame('settled', $item->status);
        $this->assertSame(2, \Modules\Accounting\Models\SettlementAllocation::count());
        $report = app(ReportService::class);
        $this->assertSame('0.00', $report->build('ledger', ['account_id' => $fixture['accounts']['1.1.03']->id])['totals']['balance']);
        $this->assertSame('0.00', $report->build('ledger', ['account_id' => $fixture['accounts']['4.1']->id])['totals']['balance']);
        $this->assertSame('-200.00', $report->build('ledger', ['account_id' => $fixture['accounts']['4.2']->id])['totals']['balance']);
        try {
            app(DocumentService::class)->propose($fixture['document']->id, $fixture['document']->fresh()->version, now()->format('Y-m-d'), [], $this->admin->id);
            $this->fail('No debe generar otra venta histórica.');
        } catch (ApiProblem $problem) {
            $this->assertSame('OPENING_ANTECEDENT', $problem->errorCode);
        }
    }
    public function test_a_payable_can_be_paid_in_ves_with_an_explicit_exchange_loss(): void
    {
        $fixture = $this->initialBalance('payable');
        $this->postPrepared($fixture['entry'], 'opening-ap-test');
        $item = OpenItem::firstOrFail();
        $entry = app(\Modules\Accounting\Services\SettlementService::class)->prepare($this->settlementInput($item, '100', 'PAGO-1', 'payment', true), $this->admin->id);
        $this->postPrepared($entry, 'payment-ap-test');
        $this->assertSame('settled', $item->fresh()->status);
        $report = app(ReportService::class);
        $this->assertSame('200.00', $report->build('ledger', ['account_id' => $fixture['accounts']['6.2']->id])['totals']['balance']);
        $this->assertSame('0.00', $report->build('ledger', ['account_id' => $fixture['accounts']['2.1.01']->id])['totals']['balance']);
        $this->assertSame('0.00', $report->build('ledger', ['account_id' => $fixture['accounts']['5.1']->id])['totals']['balance']);
    }
    public function test_a_later_settlement_cannot_use_a_stale_approved_open_balance(): void
    {
        $fixture = $this->initialBalance();
        $this->postPrepared($fixture['entry'], 'opening-stale-test');
        $item = OpenItem::firstOrFail();
        $service = app(\Modules\Accounting\Services\SettlementService::class);
        $first = $service->prepare($this->settlementInput($item, '60', 'RC-PRIMERO'), $this->admin->id);
        $second = $service->prepare($this->settlementInput($item, '60', 'RC-SEGUNDO'), $this->admin->id);
        $entries = app(EntryService::class);
        $second = $entries->transition($second->id, $second->version, $this->admin->id, 'submit');
        $second = $entries->transition($second->id, $second->version, $this->admin->id, 'approve', 'Autocontrol antes del cambio de saldo.');
        $this->postPrepared($first, 'post-first-stale-test');
        try {
            $entries->post($second->id, $second->version, $this->admin->id, 'post-second-stale-test');
            $this->fail('El saldo aprobado ya cambió.');
        } catch (ApiProblem $problem) {
            $this->assertSame('OPEN_ITEM_CHANGED', $problem->errorCode);
        }
        $this->assertNull($second->fresh()->posted_at);
        $this->assertSame('40.000000000000', $item->fresh()->remaining_original);
        $this->assertSame(1, \Modules\Accounting\Models\SettlementAllocation::count());
    }
    public function test_reversing_a_receipt_restores_the_original_and_book_balances(): void
    {
        $fixture = $this->initialBalance();
        $this->postPrepared($fixture['entry'], 'opening-receipt-reversal');
        $item = OpenItem::firstOrFail();
        $receipt = app(\Modules\Accounting\Services\SettlementService::class)->prepare($this->settlementInput($item, '40', 'RC-REVERSAR'), $this->admin->id);
        $receipt = $this->postPrepared($receipt, 'receipt-to-reverse');
        $reverse = app(EntryService::class)->prepareReversal($receipt->id, now()->format('Y-m-d'), 'Reverso ficticio del cobro para corregir su soporte.', $this->admin->id);
        $this->postPrepared($reverse, 'reverse-receipt-test');
        $this->assertSame('100.000000000000', $item->fresh()->remaining_original);
        $this->assertSame('4000.00000000', $item->fresh()->remaining_book);
        $this->assertSame('reversed', \Modules\Accounting\Models\Settlement::where('journal_entry_id', $receipt->id)->firstOrFail()->status);
        $this->assertNotNull(\Modules\Accounting\Models\SettlementAllocation::firstOrFail()->reversal_entry_id);
    }
    public function test_a_balanced_but_reduced_reversal_cannot_cancel_a_full_open_item(): void
    {
        $fixture = $this->initialBalance();
        $original = $this->postPrepared($fixture['entry'], 'opening-reversal-integrity');
        $entries = app(EntryService::class);
        $reverse = $entries->prepareReversal($original->id, now()->format('Y-m-d'), 'Reverso ficticio que debe conservar sus importes.', $this->admin->id);
        $data = $entries->resource($reverse);
        foreach ($data['lines'] as &$line) {
            $line['debit'] = (string) \Brick\Math\BigDecimal::of($line['debit'])->dividedBy('2');
            $line['credit'] = (string) \Brick\Math\BigDecimal::of($line['credit'])->dividedBy('2');
            if ($line['original_amount'] !== null) {
                $line['original_amount'] = (string) \Brick\Math\BigDecimal::of($line['original_amount'])->dividedBy('2');
            }
        }
        unset($line);
        $reverse = $entries->edit($reverse->id, $data, $reverse->version, $this->admin->id);
        try {
            $entries->transition($reverse->id, $reverse->version, $this->admin->id, 'submit');
            $this->fail('El reverso reducido no coincide con su efecto.');
        } catch (ApiProblem $problem) {
            $this->assertSame('REVERSAL_LINES_CHANGED', $problem->errorCode);
        }
        $this->assertSame('100.000000000000', OpenItem::firstOrFail()->remaining_original);
    }
    public function test_opening_income_balances_do_not_become_new_period_revenue(): void
    {
        $accounts = Account::get()->keyBy('code');
        $input = ['cutoff_date' => now()->format('Y-m-d'), 'reason' => 'Migración ficticia de saldos acumulados anteriores.', 'evidence_reference' => 'Balance anterior ficticio', 'balances' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '100.00', 'credit' => '0.00'], ['account_id' => $accounts['4.1']->id, 'debit' => '0.00', 'credit' => '100.00']], 'open_items' => []];
        $entry = app(\Modules\Accounting\Services\OpeningService::class)->prepare($input, $this->admin->id);
        $this->postPrepared($entry, 'opening-income-test');
        $report = app(ReportService::class);
        $this->assertSame('0.00', $report->build('income-statement')['totals']['result']);
        $this->assertSame('100.00', $report->build('income-statement')['totals']['opening_carry_forward']);
        $this->assertSame('0.00', $report->build('balance-sheet')['totals']['difference']);
        $reverse = app(EntryService::class)->prepareReversal($entry->id, now()->format('Y-m-d'), 'Reverso ficticio de los saldos iniciales de resultados.', $this->admin->id);
        $this->postPrepared($reverse, 'reverse-opening-income-test');
        $this->assertSame('0.00', $report->build('income-statement')['totals']['result']);
        $this->assertSame('0.00', $report->build('income-statement')['totals']['opening_carry_forward']);
    }
    public function test_a_changed_opening_antecedent_requires_a_new_review(): void
    {
        $fixture = $this->initialBalance();
        $document = $fixture['document'];
        $document->version++;
        $document->save();
        try {
            app(EntryService::class)->transition($fixture['entry']->id, $fixture['entry']->version, $this->admin->id, 'submit');
            $this->fail('El antecedente cambió después de preparar la apertura.');
        } catch (ApiProblem $problem) {
            $this->assertSame('OPENING_ANTECEDENT_CHANGED', $problem->errorCode);
        }
        $this->assertSame(0, OpenItem::count());
    }
    public function test_a_historical_invoice_can_receive_fiscal_review_without_a_second_sale(): void
    {
        $fixture = $this->initialBalance();
        $this->postPrepared($fixture['entry'], 'opening-fiscal-review');
        app(TaxConfigurationService::class)->addProfile(['profile' => 'ordinary', 'effective_from' => now()->startOfYear()->format('Y-m-d'), 'source_reference' => 'Configuración fiscal ficticia del caso de pruebas.', 'details' => ['input_tax_treatment' => 'deductible', 'spe' => false]], $this->admin->id);
        $document = $fixture['document']->fresh();
        $document = app(DocumentService::class)->validate($document->id, $document->version, ['fiscal_date' => now()->format('Y-m-d'), 'rate_evidence_reference' => 'Medición histórica ficticia respaldada.', 'rate_reason' => 'Tasa didáctica histórica del saldo inicial.', 'tax_explanation' => 'Supuesto ficticio de revisión fiscal independiente; no configura una exención real.'], $this->admin->id);
        $this->assertSame('opening_antecedent', $document->status);
        $this->assertSame(1, FiscalRecord::where('status', 'validated')->count());
        $this->assertSame(1, JournalEntry::whereNotNull('posted_at')->count());
    }
    public function test_a_manual_invoice_linked_to_a_posted_opening_keeps_its_identity(): void
    {
        $fixture = $this->initialBalance();
        $this->postPrepared($fixture['entry'], 'opening-document-immutable');
        $document = $fixture['document']->fresh();
        try {
            app(DocumentService::class)->edit($document->id, [], $document->version, $this->admin->id);
            $this->fail('El antecedente ya respalda una partida contabilizada.');
        } catch (ApiProblem $problem) {
            $this->assertSame('OPENING_SOURCE_IMMUTABLE', $problem->errorCode);
        }
        $this->assertSame(1, $document->fresh()->source_revision);
    }
    private function invoiceConfiguration(): array
    {
        $service = app(TaxConfigurationService::class);
        $year = now('America/Caracas')->format('Y');
        $profile = $service->addProfile(['profile' => 'ordinary', 'effective_from' => $year . '-01-01', 'source_reference' => 'Supuesto didáctico aislado; no configura una norma real.', 'details' => ['input_tax_treatment' => 'deductible', 'spe' => false]], $this->admin->id);
        $rule = $service->createRule(['code' => 'IVA-FICTICIO', 'name' => 'IVA de caso didáctico', 'tax' => 'IVA', 'scenario' => 'invoice', 'rate_percent' => '16.00', 'effective_from' => $year . '-01-01', 'source_reference' => 'Caso de prueba ficticio, no alícuota predeterminada del producto.'], $this->admin->id);
        $rule = $service->approveRule($rule->id, $rule->version, $this->admin->id);
        $party = ThirdParty::create(['tax_id' => 'J-11111111-1', 'name' => 'Contraparte ficticia', 'kind' => 'both']);
        $date = now('America/Caracas')->format('Y-m-d');
        return ['profile' => $profile, 'rule' => $rule, 'party' => $party, 'input' => ['kind' => 'sale_invoice', 'number' => 'FV-000001', 'issue_date' => $date, 'fiscal_date' => $date, 'third_party_id' => $party->id, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_source' => 'Moneda del libro', 'exchange_date' => $date, 'amounts' => ['base' => '1000.00', 'vat' => '160.00', 'other_taxes' => '0.00', 'total' => '1160.00'], 'tax_components' => [['tax' => 'IVA', 'rule_id' => $rule->id, 'base' => '1000.00', 'amount' => '160.00']]]];
    }
    private function invoiceAccounts(): array
    {
        return ['counterparty' => Account::where('code', '1.1.03')->firstOrFail()->id, 'base' => Account::where('code', '4.1')->firstOrFail()->id, 'vat' => Account::where('code', '2.1.02')->firstOrFail()->id, 'rounding' => Account::where('code', '6.4')->firstOrFail()->id];
    }
    public function test_manual_invoice_preserves_tax_snapshot_and_creates_one_receivable(): void
    {
        $configuration = $this->invoiceConfiguration();
        $service = app(DocumentService::class);
        $entryService = app(EntryService::class);
        $document = $service->create($configuration['input'], $this->admin->id);
        $document = $service->validate($document->id, $document->version, [], $this->admin->id);
        $entry = $service->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $this->assertSame('1160.00000000', $entry->total_debit);
        $this->assertSame($entry->total_debit, $entry->total_credit);
        $this->assertSame($configuration['profile']->id, $entry->rule_snapshot['profile']['id']);
        $entry = $entryService->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entryService->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol explícito del caso de factura ficticia.');
        $entryService->post($entry->id, $entry->version, $this->admin->id, 'posted-document-test');
        $this->assertSame('posted', SourceDocument::findOrFail($document->id)->status);
        $this->assertSame('1160.000000000000', OpenItem::where('source_document_id', $document->id)->firstOrFail()->remaining_original);
        $this->assertSame(1, FiscalRecord::where('source_document_id', $document->id)->where('status', 'validated')->count());
    }
    public function test_duplicate_invoice_number_is_detected_before_it_is_posted(): void
    {
        $configuration = $this->invoiceConfiguration();
        $service = app(DocumentService::class);
        $service->create($configuration['input'], $this->admin->id);
        $input = $configuration['input'];
        $input['number'] = '  fv-1 ';
        try {
            $service->create($input, $this->admin->id);
            $this->fail('Debe detectar la identidad repetida.');
        } catch (ApiProblem $problem) {
            $this->assertSame('POSSIBLE_DUPLICATE_DOCUMENT', $problem->errorCode);
        }
        $this->assertSame(1, SourceDocument::count());
    }
    public function test_editing_a_balanced_proposal_cannot_change_the_invoice_receivable(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $entries = app(EntryService::class);
        $document = $documents->create($configuration['input'], $this->admin->id);
        $document = $documents->validate($document->id, $document->version, [], $this->admin->id);
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $data = $entries->resource($entry);
        $data['lines'][0]['debit'] = '1150.00';
        $data['lines'][1]['credit'] = '990.00';
        $entry = $entries->edit($entry->id, $data, $entry->version, $this->admin->id);
        try {
            $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
            $this->fail('Una propuesta balanceada no puede alterar el auxiliar de la factura.');
        } catch (ApiProblem $problem) {
            $this->assertSame('INVOICE_AUXILIARY_MISMATCH', $problem->errorCode);
        }
        $this->assertSame(0, OpenItem::count());
    }
    public function test_a_fiscal_record_keeps_the_original_identity_after_the_party_changes(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $document = $documents->create($configuration['input'], $this->admin->id);
        $documents->validate($document->id, $document->version, [], $this->admin->id);
        $configuration['party']->name = 'Nombre actualizado después de la validación';
        $configuration['party']->save();
        $record = FiscalRecord::where('source_document_id', $document->id)->firstOrFail();
        $this->assertSame('Contraparte ficticia', $record->rule_snapshot['document']['party']['name']);
        $this->assertSame('FV-000001', $record->rule_snapshot['document']['number']);
    }
    public function test_revalidation_revokes_approval_and_regeneration_keeps_the_prior_evidence(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $entries = app(EntryService::class);
        $document = $documents->create($configuration['input'], $this->admin->id);
        $document = $documents->validate($document->id, $document->version, [], $this->admin->id);
        $entry = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol del caso de regeneración.');
        $document = $documents->validate($document->id, $document->fresh()->version, ['tax_explanation' => 'Aclaración posterior de la clasificación del caso.'], $this->admin->id);
        $this->assertNull($entry->fresh()->approved_version);
        $this->assertSame('observed', $entry->fresh()->status);
        try {
            $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
            $this->fail('No debe descartar silenciosamente la propuesta anterior.');
        } catch (ApiProblem $problem) {
            $this->assertSame('PROPOSAL_REPLACEMENT_REQUIRED', $problem->errorCode);
        }
        $replacement = $documents->propose($document->id, $document->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id, ['replace_existing' => true, 'reason' => 'Regeneración explícita por aclaración de la revisión fiscal.']);
        $this->assertSame($entry->id, $replacement->id);
        $this->assertSame('draft', $replacement->status);
        $this->assertEquals($document->metadata['validation'], $replacement->rule_snapshot);
        $this->assertSame(1, FiscalRecord::where('status', 'validated')->count());
        $this->assertSame(1, FiscalRecord::where('status', 'superseded')->count());
        $this->assertSame(1, \Modules\Accounting\Models\AuditEvent::where('action', 'entry.proposal_regenerated')->count());
    }
    public function test_a_later_editor_cannot_approve_their_own_preparation_in_separate_mode(): void
    {
        $this->a->approval_mode = 'separate';
        $this->a->save();
        $editor = DB::table('users')->insertGetId(['name' => 'Editor ficticio', 'email' => Str::uuid() . '@example.invalid', 'password' => password_hash('Prueba aislada', PASSWORD_BCRYPT), 'created_at' => now(), 'updated_at' => now()]);
        $entries = app(EntryService::class);
        $entry = $this->draft();
        $entry = $entries->edit($entry->id, $entries->resource($entry), $entry->version, $editor);
        $entry = $entries->transition($entry->id, $entry->version, $editor, 'submit');
        try {
            $entries->transition($entry->id, $entry->version, $editor, 'approve', 'La versión fue preparada por este editor.');
            $this->fail('La separación debe considerar al último preparador.');
        } catch (ApiProblem $problem) {
            $this->assertSame('SEPARATE_APPROVAL_REQUIRED', $problem->errorCode);
        }
        $this->assertSame('review', $entry->fresh()->status);
    }
    public function test_a_sales_note_cannot_be_applied_to_a_purchase_invoice(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $invoice = $documents->create([...$configuration['input'], 'kind' => 'purchase_invoice'], $this->admin->id);
        $note = $documents->create([...$configuration['input'], 'kind' => 'sale_credit_note', 'number' => 'NC-1', 'related_document_id' => $invoice->id], $this->admin->id);
        try {
            $documents->validate($note->id, $note->version, [], $this->admin->id);
            $this->fail('Debe respetar el tipo de factura afectada.');
        } catch (ApiProblem $problem) {
            $this->assertSame('RELATED_DOCUMENT_REQUIRED', $problem->errorCode);
        }
    }
    public function test_future_tax_rule_cannot_validate_a_current_document(): void
    {
        $configuration = $this->invoiceConfiguration();
        $service = app(TaxConfigurationService::class);
        $future = $service->createRule(['code' => 'IVA-FUTURO', 'name' => 'Supuesto futuro ficticio', 'tax' => 'IVA', 'scenario' => 'invoice', 'rate_percent' => '16.00', 'effective_from' => now()->addYear()->format('Y-m-d'), 'source_reference' => 'Caso futuro de pruebas aisladas.'], $this->admin->id);
        $future = $service->approveRule($future->id, $future->version, $this->admin->id);
        $input = $configuration['input'];
        $input['tax_components'][0]['rule_id'] = $future->id;
        $document = app(DocumentService::class)->create($input, $this->admin->id);
        try {
            app(DocumentService::class)->validate($document->id, $document->version, [], $this->admin->id);
            $this->fail('La regla no tiene vigencia actual.');
        } catch (ApiProblem $problem) {
            $this->assertSame('RULE_NOT_EFFECTIVE', $problem->errorCode);
        }
        $this->assertSame(0, FiscalRecord::count());
    }
    public function test_future_profile_does_not_rewrite_a_validated_document(): void
    {
        $configuration = $this->invoiceConfiguration();
        $service = app(DocumentService::class);
        $document = $service->create($configuration['input'], $this->admin->id);
        $document = $service->validate($document->id, $document->version, [], $this->admin->id);
        app(TaxConfigurationService::class)->addProfile(['profile' => 'formal', 'effective_from' => now()->addYear()->startOfYear()->format('Y-m-d'), 'source_reference' => 'Notificación ficticia futura de pruebas.', 'details' => ['input_tax_treatment' => 'cost', 'spe' => false]], $this->admin->id);
        $this->assertSame('ordinary', $document->fresh()->metadata['validation']['profile']['profile']);
        $this->assertSame($configuration['profile']->id, $document->fresh()->metadata['validation']['profile']['id']);
    }
    public function test_global_zero_igtf_rule_requires_explicit_conditions(): void
    {
        $service = app(TaxConfigurationService::class);
        $rule = $service->createRule(['code' => 'IGTF-CERO', 'name' => 'Regla insuficiente de prueba', 'tax' => 'IGTF', 'scenario' => 'general', 'rate_percent' => '0.00', 'effective_from' => now()->startOfYear()->format('Y-m-d'), 'source_reference' => 'Caso de rechazo, no configuración tributaria.'], $this->admin->id);
        try {
            $service->approveRule($rule->id, $rule->version, $this->admin->id);
            $this->fail('Debe delimitar el supuesto.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CONDITIONAL_IGTF_REQUIRED', $problem->errorCode);
        }
    }
    public function test_changed_source_blocks_posting_of_an_approved_version(): void
    {
        $configuration = $this->invoiceConfiguration();
        $documents = app(DocumentService::class);
        $source = $documents->create($configuration['input'], $this->admin->id);
        $source = $documents->validate($source->id, $source->version, [], $this->admin->id);
        $s = app(EntryService::class);
        $e = $documents->propose($source->id, $source->version, now('America/Caracas')->format('Y-m-d'), $this->invoiceAccounts(), $this->admin->id);
        $e = $s->transition($e->id, $e->version, $this->admin->id, 'submit');
        $e = $s->transition($e->id, $e->version, $this->admin->id, 'approve', 'Autocontrol del caso de prueba.');
        $source->source_revision = 2;
        $source->status = 'changed';
        $source->save();
        try {
            $s->post($e->id, $e->version, $this->admin->id, 'source-revalidation-test');
            $this->fail('Debe exigir validar la nueva versión de origen.');
        } catch (ApiProblem $problem) {
            $this->assertSame('SOURCE_REVALIDATION_REQUIRED', $problem->errorCode);
            $this->assertNull(JournalEntry::findOrFail($e->id)->posted_at);
        }
    }
    public function test_source_evidence_cannot_be_rewritten(): void
    {
        $source = $this->source();
        $version = SourceVersion::create(['source_document_id' => $source->id, 'revision' => 1, 'snapshot' => ['total' => '100.00'], 'hash' => hash('sha256', '100.00')]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('accounting_source_versions')->where('id', $version->id)->update(['snapshot' => json_encode(['total' => '1.00'])]);
    }
    public function test_foreign_currency_conversion_must_match_the_book(): void
    {
        $e = $this->draft();
        JournalLine::where('journal_entry_id', $e->id)->update(['original_currency' => 'USD', 'original_amount' => '1.00', 'exchange_rate' => '99.00']);
        try {
            app(EntryService::class)->transition($e->id, $e->version, $this->admin->id, 'submit');
            $this->fail('Debe detectar la conversión inconsistente.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CONVERSION_MISMATCH', $problem->errorCode);
        }
    }
}
