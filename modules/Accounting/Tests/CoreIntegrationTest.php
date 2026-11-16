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
    use ImportIntegrationAssertions;
    use ReportIntegrationAssertions;
    use AuditIntegrationAssertions;
    use AttachmentIntegrationAssertions;
    use SourceHistoryIntegrationAssertions;
    use SourceIncidentIntegrationAssertions;
    private static Application $application;
    private static string $originalTimezone;
    private User $admin;
    private Company $a;
    private Company $b;
    public function runBare(): void
    {
        // Each case owns its consistent root; setUp/tearDown keep their rollback
        // savepoint. Production reports still reject unrelated transactions.
        DB::transaction(function () {
            parent::runBare();
        });
    }
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$originalTimezone = date_default_timezone_get();
        if (getenv('ACCOUNTING_ISOLATED_TESTS') !== '1' || getenv('ACCOUNTING_TEST_DATABASE') !== 'accounting_module_test' || getenv('ACCOUNTING_TEST_HOST') !== 'accounting-db') {
            throw new \RuntimeException('Esta suite exige la base y red aisladas del módulo.');
        }
        date_default_timezone_set('America/Caracas');
        $root = dirname(__DIR__, 3);
        $app = new Application($root);
        $config = new Repository(['app' => ['env' => 'testing', 'timezone' => 'America/Caracas'], 'accounting' => ['demo' => true], 'database' => ['default' => 'tenant']]);
        $app->instance('config', $config);
        $app->instance('date', new DateFactory());
        $events = new Dispatcher($app);
        $app->instance('events', $events);
        $app->instance('validator', new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app));
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
        NativeSourceTestSchema::create($schema);
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
    public static function tearDownAfterClass(): void
    {
        date_default_timezone_set(self::$originalTimezone);
        parent::tearDownAfterClass();
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
    public function test_prepared_auxiliary_preserves_older_debt_and_partial_history_in_its_company_snapshot(): void
    {
        $fixture = $this->initialBalance();
        $this->postPrepared($fixture['entry'], 'offline-opening');
        $item = OpenItem::firstOrFail();
        $first = app(\Modules\Accounting\Services\SettlementService::class)->prepare($this->settlementInput($item, '40', 'OFFLINE-RECEIPT-1'), $this->admin->id);
        $this->postPrepared($first, 'offline-first-receipt');
        $date = now('America/Caracas')->format('Y-m-d');
        $input = ['device_id' => (string) Str::uuid(), 'name' => 'Auxiliar ficticio sin conexión', 'period' => substr($date, 0, 7), 'from' => $date, 'to' => $date];
        $workspace = app(\Modules\Accounting\Services\WorkspaceService::class);
        $package = $workspace->prepare($this->admin, $input);
        $this->assertSame([], $package['documents']);
        $this->assertTrue($package['scope']['open_items_complete']);
        $this->assertSame($package['issued_at'], $package['scope']['open_items_as_of']);
        $this->assertCount(1, $package['open_items']);
        $copy = $package['open_items'][0];
        $this->assertSame('60.000000000000', $copy['remaining_original']);
        $this->assertSame('2400.00000000', $copy['remaining_book']);
        $this->assertSame($fixture['party']->id, $copy['party']['id']);
        $this->assertSame('80.00000000', $copy['allocations'][0]['exchange_difference']);
        $this->assertSame($first->id, $copy['allocations'][0]['entry']['id']);
        $second = app(\Modules\Accounting\Services\SettlementService::class)->prepare($this->settlementInput($item->fresh(), '60', 'OFFLINE-RECEIPT-2'), $this->admin->id);
        $this->postPrepared($second, 'offline-second-receipt');
        $this->assertSame('60.000000000000', $package['open_items'][0]['remaining_original']);
        $fresh = $workspace->prepare($this->admin, $input);
        $this->assertSame('settled', $fresh['open_items'][0]['status']);
        $this->assertSame('0.000000000000', $fresh['open_items'][0]['remaining_original']);
        app(CompanyContext::class)->set($this->b);
        $other = $workspace->prepare($this->admin, $input);
        $this->assertSame([], $other['open_items']);
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
    private function allowConflictEditing(): void
    {
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['accountant'])]);
    }
    public function test_operation_receipts_preserve_decimal_text_and_scope(): void
    {
        $service = app(\Modules\Accounting\Services\OperationService::class);
        $payload = ['amount' => '9007199254740993.120000000001'];
        $service->execute($this->admin->id, 'offline.sync', 'receipt-decimal-001', $payload, fn() => ['amount' => $payload['amount']]);
        $receipt = $service->status($this->admin->id, 'offline.sync', 'receipt-decimal-001');
        $this->assertSame('confirmed', $receipt['status']);
        $this->assertSame($payload, $receipt['request']);
        $this->assertSame($payload, $receipt['result']);
        $this->assertSame('not_found', $service->status($this->admin->id + 1000, 'offline.sync', 'receipt-decimal-001')['status']);
        app(CompanyContext::class)->set($this->b);
        $this->assertSame('not_found', $service->status($this->admin->id, 'offline.sync', 'receipt-decimal-001')['status']);
    }
    public function test_cancelled_identity_rejects_a_late_execution(): void
    {
        $service = app(\Modules\Accounting\Services\OperationService::class);
        $one = $service->cancel($this->admin->id, 'offline.sync', 'cancel-before-send-001', 'Se revisará otra versión antes de enviar.');
        $two = $service->cancel($this->admin->id, 'offline.sync', 'cancel-before-send-001', 'Segundo intento de la misma cancelación.');
        $this->assertSame('cancelled', $one['status']);
        $this->assertSame($one, $two);
        $this->assertSame(1, DB::table('accounting_audit_events')->where('company_id', $this->a->id)->where('action', 'operation.cancelled')->count());
        try {
            $service->execute($this->admin->id, 'offline.sync', 'cancel-before-send-001', ['amount' => '1.00'], function () { $this->fail('Un envío cancelado no debe ejecutarse.'); });
            $this->fail('Se esperaba una identidad cancelada.');
        } catch (ApiProblem $problem) {
            $this->assertSame('OPERATION_CANCELLED', $problem->errorCode);
        }
    }
    public function test_cancelling_an_already_confirmed_operation_returns_its_receipt(): void
    {
        $service = app(\Modules\Accounting\Services\OperationService::class);
        $result = $service->execute($this->admin->id, 'offline.sync', 'confirmed-before-cancel-001', ['amount' => '0.30'], fn() => ['id' => 'fictitious-result']);
        $receipt = $service->cancel($this->admin->id, 'offline.sync', 'confirmed-before-cancel-001', 'El cliente perdió la respuesta del envío.');
        $this->assertSame('confirmed', $receipt['status']);
        $this->assertSame($result, $receipt['result']);
        $this->assertSame(0, DB::table('accounting_audit_events')->where('company_id', $this->a->id)->where('action', 'operation.cancelled')->count());
    }
    public function test_legacy_operation_hashes_continue_to_replay(): void
    {
        $payload = ['amount' => '0.100000000000', 'version' => 1];
        \Modules\Accounting\Models\OperationResult::create(['user_id' => $this->admin->id, 'action' => 'offline.sync', 'operation_key' => 'legacy-replay-001', 'state' => 'confirmed', 'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 'result' => ['legacy' => true]]);
        $result = app(\Modules\Accounting\Services\OperationService::class)->execute($this->admin->id, 'offline.sync', 'legacy-replay-001', $payload, function () { $this->fail('Una confirmación anterior no debe ejecutarse de nuevo.'); });
        $this->assertSame(['legacy' => true], $result);
    }
    public function test_operation_cancellation_cannot_be_reopened_in_the_database(): void
    {
        app(\Modules\Accounting\Services\OperationService::class)->cancel($this->admin->id, 'offline.sync', 'immutable-cancel-001', 'Conservar la decisión y bloquear el envío anterior.');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('accounting_operation_results')->where('company_id', $this->a->id)->where('operation_key', 'immutable-cancel-001')->update(['state' => 'pending']);
    }
    public function test_a_local_identity_cannot_create_two_journal_entries(): void
    {
        $source = $this->draft();
        $service = app(EntryService::class);
        $data = ['accounting_date' => $source->accounting_date->format('Y-m-d'), 'concept' => 'Identidad local de prueba', 'local_id' => (string) Str::uuid(), 'lines' => $service->resource($source)['lines']];
        $first = $service->create($data, $this->admin->id);
        try {
            $service->create($data, $this->admin->id);
            $this->fail('Una identidad local repetida debe exigir comparación.');
        } catch (ApiProblem $problem) {
            $this->assertSame('LOCAL_ENTRY_EXISTS', $problem->errorCode);
            $this->assertSame($first->id, $problem->details['current']['id']);
        }
        $this->assertSame(1, JournalEntry::where('local_id', $data['local_id'])->count());
    }
    public function test_entry_conflict_resolution_keeps_alternatives_and_invalidates_approval(): void
    {
        $this->allowConflictEditing();
        $entry = $this->approved();
        $original = app(EntryService::class)->resource($entry);
        $local = ['accounting_date' => $entry->accounting_date->format('Y-m-d'), 'concept' => 'Versión local revisada expresamente', 'lines' => $original['lines']];
        $local['lines'][0]['debit'] = '9007199254740993.12';
        $local['lines'][1]['credit'] = '9007199254740993.12';
        $input = ['base_version' => 1, 'decision' => 'apply_local', 'reason' => 'El contador contrastó los soportes de ambas versiones.', 'local' => $local];
        $service = app(\Modules\Accounting\Services\ConflictService::class);
        $result = $service->entry($this->admin->id, $entry->id, $entry->version, $input, 'entry-resolution-001');
        $replay = $service->entry($this->admin->id, $entry->id, $entry->version, $input, 'entry-resolution-001');
        $this->assertEquals($result, $replay);
        $this->assertSame($result['entity']['total_debit'], $replay['entity']['total_debit']);
        $this->assertSame('draft', $result['entity']['status']);
        $this->assertNull($result['entity']['approved_by']);
        $this->assertSame($entry->version + 1, $result['entity']['version']);
        $this->assertSame('9007199254740993.12000000', $result['entity']['total_debit']);
        $audit = json_decode(DB::table('accounting_audit_events')->where('id', $result['resolution']['id'])->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('100.00000000', $audit['server_before']['total_debit']);
        $this->assertSame('9007199254740993.12', $audit['local_reported']['lines'][0]['debit']);
        $this->assertSame('9007199254740993.12000000', $audit['server_after']['total_debit']);
        $this->assertSame(1, DB::table('accounting_audit_events')->where('company_id', $this->a->id)->where('action', 'entry.conflict_resolved')->count());
    }
    public function test_resolution_does_not_overwrite_a_new_change_after_comparison(): void
    {
        $this->allowConflictEditing();
        $entry = $this->draft();
        $service = app(EntryService::class);
        $local = ['accounting_date' => $entry->accounting_date->format('Y-m-d'), 'concept' => 'Alternativa local conservada', 'lines' => $service->resource($entry)['lines']];
        $remote = $service->edit($entry->id, [...$local, 'concept' => 'Cambio remoto posterior'], 1, $this->admin->id);
        try {
            app(\Modules\Accounting\Services\ConflictService::class)->entry($this->admin->id, $entry->id, 1, ['base_version' => 1, 'decision' => 'apply_local', 'reason' => 'Decisión basada en una versión que ya cambió.', 'local' => $local], 'stale-resolution-001');
            $this->fail('Una decisión desactualizada debe rechazarse.');
        } catch (ApiProblem $problem) {
            $this->assertSame('VERSION_CONFLICT', $problem->errorCode);
            $this->assertSame(412, $problem->status);
        }
        $this->assertSame('Cambio remoto posterior', JournalEntry::findOrFail($entry->id)->concept);
        $this->assertSame($remote->version, JournalEntry::findOrFail($entry->id)->version);
        $this->assertSame(0, \Modules\Accounting\Models\OperationResult::where('operation_key', 'stale-resolution-001')->count());
    }
    public function test_read_only_user_can_preserve_the_server_version_without_editing_a_posted_entry(): void
    {
        $entry = $this->approved();
        app(EntryService::class)->post($entry->id, $entry->version, $this->admin->id, 'posted-before-resolution-001');
        $entry = $entry->fresh();
        DB::table('accounting_memberships')->where('company_id', $this->a->id)->where('user_id', $this->admin->id)->update(['roles' => json_encode(['viewer'])]);
        $result = app(\Modules\Accounting\Services\ConflictService::class)->entry($this->admin->id, $entry->id, $entry->version, ['base_version' => 1, 'decision' => 'keep_server', 'reason' => 'Se conserva el asiento ya contabilizado en SAB.', 'local' => ['concept' => 'Captura incompleta', 'lines' => []]], 'keep-posted-version-001');
        $this->assertSame($entry->version, $result['entity']['version']);
        $this->assertNotNull($result['entity']['posted_at']);
        $this->assertSame('keep_server', $result['resolution']['decision']);
    }
    public function test_document_resolution_preserves_twelve_decimal_places(): void
    {
        $this->allowConflictEditing();
        $date = now('America/Caracas')->format('Y-m-d');
        $input = ['kind' => 'sale_invoice', 'number' => 'DOC-ORIGINAL', 'issue_date' => $date, 'fiscal_date' => $date, 'currency' => 'VES', 'exchange_rate' => '1', 'exchange_date' => $date, 'amounts' => ['base' => '100', 'vat' => '0', 'other_taxes' => '0', 'total' => '100']];
        $service = app(DocumentService::class);
        $document = $service->create($input, $this->admin->id);
        $document = $service->edit($document->id, [...$input, 'number' => 'DOC-REMOTO'], 1, $this->admin->id);
        $local = [...$input, 'number' => 'DOC-LOCAL', 'amounts' => ['base' => '9007199254740993.120000000001', 'vat' => '0', 'other_taxes' => '0', 'total' => '9007199254740993.120000000001']];
        $result = app(\Modules\Accounting\Services\ConflictService::class)->document($this->admin->id, $document->id, 2, ['base_version' => 1, 'decision' => 'apply_local', 'reason' => 'Se contrastó la captura local con el soporte manual.', 'local' => $local], 'document-resolution-001');
        $this->assertSame(3, $result['entity']['version']);
        $this->assertSame('DOC-LOCAL', $result['entity']['number']);
        $this->assertSame('9007199254740993.120000000001', $result['entity']['amounts']['total']);
        $this->assertSame(3, SourceVersion::where('source_document_id', $document->id)->count());
    }

    private function localSelectionPacket(string $clientId): array
    {
        $selected = (string) Str::uuid();
        $other = (string) Str::uuid();
        $decision = (string) Str::uuid();
        $result = (string) Str::uuid();
        return ['schema' => 1, 'tip_id' => $decision, 'draft_id' => $result, 'decisions' => [[
            'id' => $decision, 'kind' => 'entry', 'client_id' => $clientId, 'selected_id' => $selected, 'result_id' => $result,
            'reason' => 'El contador revisó ambas alternativas locales antes de enviar.', 'reported_at' => now()->toIso8601String(), 'previous_ids' => [],
            'variants' => [
                ['id' => $selected, 'revision' => 2, 'base_version' => null, 'server_id' => null, 'entry' => ['concept' => 'Alternativa elegida', 'lines' => [['debit' => '9007199254740993.12345678', 'credit' => '0']]]],
                ['id' => $other, 'revision' => 1, 'base_version' => null, 'server_id' => null, 'entry' => ['concept' => 'Alternativa conservada', 'lines' => [['debit' => '0.100000000000', 'credit' => '0']]]],
            ],
        ]]];
    }
    public function test_local_selection_audit_preserves_reported_alternatives_without_moving_balances(): void
    {
        $entry = $this->draft();
        $client = (string) Str::uuid();
        $service = app(\Modules\Accounting\Services\LocalSelectionService::class);
        $packet = $service->validate($this->localSelectionPacket($client), 'entry', $client);
        $first = $service->record($this->admin->id, $packet, 'entry', $entry->id);
        $again = $service->record($this->admin->id, $service->validate($packet, 'entry', $client), 'entry', $entry->id);
        $this->assertEquals($first, $again);
        $this->assertSame(1, DB::table('accounting_audit_events')->where('action', 'offline.alternative_selected')->where('company_id', $this->a->id)->count());
        $payload = json_decode(DB::table('accounting_audit_events')->where('id', $first[0]['audit_id'])->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('client_reported', $payload['evidence_origin']);
        $this->assertSame('9007199254740993.12345678', $payload['reported_decision']['variants'][0]['entry']['lines'][0]['debit']);
        $this->assertSame('0.100000000000', $payload['reported_decision']['variants'][1]['entry']['lines'][0]['debit']);
        $this->assertSame('100.00000000', $entry->fresh()->total_debit);
        $this->assertSame('draft', $entry->fresh()->status);
    }
    public function test_local_selection_identity_cannot_be_reused_for_changed_evidence_or_another_entry(): void
    {
        $one = $this->draft();
        $two = $this->draft();
        $client = (string) Str::uuid();
        $service = app(\Modules\Accounting\Services\LocalSelectionService::class);
        $packet = $service->validate($this->localSelectionPacket($client), 'entry', $client);
        $service->record($this->admin->id, $packet, 'entry', $one->id);
        foreach (['changed', 'other_entry'] as $scenario) {
            $copy = $packet;
            if ($scenario === 'changed') {
                $copy['decisions'][0]['reason'] = 'Un motivo modificado con la misma identidad no reemplaza la evidencia.';
            }
            try {
                $service->record($this->admin->id, $copy, 'entry', $scenario === 'changed' ? $one->id : $two->id);
                $this->fail('La decisión anterior no debe reemplazarse ni aplicarse a otra identidad.');
            } catch (ApiProblem $problem) {
                $this->assertSame('IDEMPOTENCY_CONFLICT', $problem->errorCode);
            }
        }
    }
    public function test_local_selection_rejects_missing_or_circular_history_and_mixed_lineages(): void
    {
        $client = (string) Str::uuid();
        $service = app(\Modules\Accounting\Services\LocalSelectionService::class);
        foreach (['missing', 'cycle', 'lineage', 'selected', 'tip'] as $scenario) {
            $packet = $this->localSelectionPacket($client);
            $packet['decisions'][0]['previous_ids'] = match ($scenario) { 'missing' => [(string) Str::uuid()], 'cycle' => [$packet['tip_id']], default => [] };
            if ($scenario === 'lineage') $packet['decisions'][0]['client_id'] = (string) Str::uuid();
            if ($scenario === 'selected') $packet['decisions'][0]['selected_id'] = (string) Str::uuid();
            if ($scenario === 'tip') $packet['draft_id'] = (string) Str::uuid();
            try {
                $service->validate($packet, 'entry', $client);
                $this->fail('Un historial incompleto debe impedir la selección.');
            } catch (ApiProblem $problem) {
                $this->assertContains($problem->errorCode, ['INVALID_LOCAL_SELECTION_HISTORY', 'LOCAL_SELECTION_SCOPE', 'INVALID_LOCAL_SELECTION']);
            }
        }
    }
    public function test_a_chain_of_offline_selections_is_audited_in_dependency_order(): void
    {
        $client = (string) Str::uuid();
        $one = $this->localSelectionPacket($client);
        $two = $this->localSelectionPacket($client);
        $two['decisions'][0]['previous_ids'] = [$one['tip_id']];
        $two['decisions'][] = $one['decisions'][0];
        $service = app(\Modules\Accounting\Services\LocalSelectionService::class);
        $packet = $service->validate($two, 'entry', $client);
        $this->assertSame($one['tip_id'], $packet['decisions'][0]['id']);
        $this->assertSame($two['tip_id'], $packet['decisions'][1]['id']);
        $entry = $this->draft();
        $this->assertCount(2, $service->record($this->admin->id, $packet, 'entry', $entry->id));
    }
    public function test_explicit_server_comparison_preserves_local_selection_history(): void
    {
        $this->allowConflictEditing();
        $entry = $this->draft();
        $client = (string) Str::uuid();
        $local = ['accounting_date' => $entry->accounting_date->format('Y-m-d'), 'concept' => 'Alternativa elegida y editada después', 'local_id' => $client,
            'lines' => app(EntryService::class)->resource($entry)['lines'], 'local_selection' => $this->localSelectionPacket($client)];
        $result = app(\Modules\Accounting\Services\ConflictService::class)->entry($this->admin->id, $entry->id, $entry->version,
            ['base_version' => 1, 'decision' => 'apply_local', 'reason' => 'Revisión expresa de la alternativa local y la versión actual.', 'local' => $local], 'selection-comparison-001');
        $this->assertSame(2, $result['entity']['version']);
        $this->assertSame('draft', $result['entity']['status']);
        $this->assertSame(1, DB::table('accounting_audit_events')->where('company_id', $this->a->id)->where('action', 'offline.alternative_selected')->count());
    }

    private function closeInput(array $preview): array
    {
        return ['review_token' => $preview['review_token'], 'reason' => 'Cierre ficticio con papeles de trabajo revisados.', 'attestations' => array_fill_keys(\Modules\Accounting\Services\ClosingService::ATTESTATIONS, 'Papel de trabajo ficticio conciliado y revisado.'), 'exceptions' => array_fill_keys(array_keys($preview['pending']), 'Pendiente ficticio identificado y con tratamiento documentado.')];
    }

    private function januaryEntry(): JournalEntry
    {
        $accounts = Account::get()->keyBy('code');
        return app(EntryService::class)->create(['accounting_date' => now()->year . '-01-15', 'concept' => 'Movimiento ficticio para revisión de cierre', 'lines' => [['account_id' => $accounts['1.1.02']->id, 'debit' => '100.25', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '100.25']]], $this->admin->id);
    }

    public function test_monthly_close_preserves_reports_and_replays_the_same_operation(): void
    {
        $entry = $this->postPrepared($this->januaryEntry(), 'close-january-post');
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $preview = $service->preview($period->id);
        $this->assertSame([], $preview['blockers']);
        $this->assertTrue(\Brick\Math\BigDecimal::of($preview['trial']['totals']['debit'])->isEqualTo('100.25'));
        $input = $this->closeInput($preview);
        $closed = $service->close($period->id, 1, $input, $this->admin->id, 'monthly-close-001');
        $this->assertSame('closed', $closed['period']['status']);
        $this->assertSame(2, $closed['period']['version']);
        $this->assertEquals($closed, $service->close($period->id, 1, $input, $this->admin->id, 'monthly-close-001'));
        $snapshot = $service->snapshot($period->id, $closed['review_id']);
        $this->assertSame($closed['snapshot_sha256'], $snapshot['snapshot_sha256']);
        $this->assertSame('100.25', $snapshot['snapshot']['reports']['trial-balance']['totals']['debit']);
        $this->assertCount(1, $service->history($period->id));
        $entry->refresh();
        $this->assertSame('posted', $entry->status);
        $this->assertSame('100.25000000', $entry->total_debit);
    }

    public function test_monthly_close_rechecks_changes_after_the_review(): void
    {
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $preview = $service->preview($period->id);
        $this->januaryEntry();
        try {
            $service->close($period->id, 1, $this->closeInput($preview), $this->admin->id, 'monthly-stale-001');
            $this->fail('El cierre no puede utilizar una revisión anterior a nuevos pendientes.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CLOSE_REVIEW_CHANGED', $problem->errorCode);
        }
        $this->assertSame('open', $period->fresh()->status);
        $this->assertCount(0, $service->history($period->id));
    }

    public function test_monthly_close_requires_reconciliations_and_pending_explanations(): void
    {
        $this->januaryEntry();
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $preview = $service->preview($period->id);
        $input = $this->closeInput($preview);
        $input['attestations']['cash_bank_reconciliation'] = ' ';
        try {
            $service->close($period->id, 1, $input, $this->admin->id, 'monthly-notes-001');
            $this->fail('Debe exigir la conciliación de cajas y bancos.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CLOSE_ATTESTATION_REQUIRED', $problem->errorCode);
        }
        $input = $this->closeInput($preview);
        $input['exceptions'] = [];
        try {
            $service->close($period->id, 1, $input, $this->admin->id, 'monthly-notes-002');
            $this->fail('Debe exigir una explicación de los borradores pendientes.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CLOSE_EXCEPTION_REQUIRED', $problem->errorCode);
        }
        $this->assertSame('open', $period->fresh()->status);
    }

    public function test_approved_pending_entries_block_a_close_until_posted_or_observed(): void
    {
        $entries = app(EntryService::class);
        $entry = $this->januaryEntry();
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'submit');
        $entry = $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Autocontrol ficticio del comprobante aprobado.');
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $preview = $service->preview($period->id);
        $this->assertContains('APPROVED_ENTRIES_PENDING', array_column($preview['blockers'], 'code'));
        try {
            $service->close($period->id, 1, $this->closeInput($preview), $this->admin->id, 'monthly-approved-001');
            $this->fail('Un aprobado pendiente no se omite justificándolo.');
        } catch (ApiProblem $problem) {
            $this->assertSame('CLOSE_BLOCKED', $problem->errorCode);
        }
        $entries->post($entry->id, $entry->version, $this->admin->id, 'monthly-approved-post');
        $this->assertSame([], $service->preview($period->id)['blockers']);
    }

    public function test_reopening_invalidates_later_closes_and_preserves_the_original_snapshots(): void
    {
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $january = Period::where('month', 1)->firstOrFail();
        $february = Period::where('month', 2)->firstOrFail();
        $closed = $service->close($january->id, 1, $this->closeInput($service->preview($january->id)), $this->admin->id, 'cascade-january-001');
        $service->close($february->id, 1, $this->closeInput($service->preview($february->id)), $this->admin->id, 'cascade-february-001');
        $review = $service->reopening($january->id);
        $this->assertCount(2, $review['affected_periods']);
        $result = $service->reopen($january->id, 2, ['review_token' => $review['review_token'], 'reason' => 'Reapertura ficticia para corregir un papel de trabajo.'], $this->admin->id, 'cascade-reopen-001');
        $this->assertCount(2, $result['periods']);
        $this->assertSame(['open', 'open'], array_column($result['periods'], 'status'));
        $this->assertSame([3, 3], array_column($result['periods'], 'version'));
        $this->assertCount(2, $service->history($january->id));
        $this->assertCount(2, $service->history($february->id));
        $this->assertSame($closed['snapshot_sha256'], $service->snapshot($january->id, $closed['review_id'])['snapshot_sha256']);
        $report = app(ReportService::class)->build('trial-balance', ['from' => now()->year . '-01-01', 'to' => now()->year . '-02-28']);
        $this->assertSame([3, 3], array_column($report['period_versions'], 'version'));
    }

    public function test_a_close_revision_cannot_be_rewritten_or_deleted_in_mysql(): void
    {
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $closed = $service->close($period->id, 1, $this->closeInput($service->preview($period->id)), $this->admin->id, 'immutable-close-001');
        foreach (['update', 'delete'] as $action) {
            try {
                $query = DB::table('accounting_period_reviews')->where('id', $closed['review_id']);
                $action === 'update' ? $query->update(['reason' => 'Cambio no autorizado']) : $query->delete();
                $this->fail('La revisión conservada no permite ' . $action . '.');
            } catch (\Illuminate\Database\QueryException $error) {
                $this->assertSame('45000', $error->errorInfo[0]);
            }
        }
        $this->assertSame(1, DB::table('accounting_period_reviews')->where('id', $closed['review_id'])->count());
    }

    public function test_close_reconstructs_auxiliaries_at_the_cutoff_instead_of_using_current_balances(): void
    {
        try {
            \Illuminate\Support\Facades\Date::setTestNow(now()->year . '-01-15 10:00:00');
            $fixture = $this->initialBalance();
            $this->postPrepared($fixture['entry'], 'close-aux-opening');
        } finally {
            \Illuminate\Support\Facades\Date::setTestNow();
        }
        $item = OpenItem::firstOrFail();
        $input = $this->settlementInput($item, '40', 'COBRO-CIERRE-1');
        $input['accounting_date'] = now()->year . '-02-05';
        $input['exchange_date'] = $input['accounting_date'];
        $entry = app(\Modules\Accounting\Services\SettlementService::class)->prepare($input, $this->admin->id);
        $entry = $this->postPrepared($entry, 'close-aux-receipt');
        $reverse = app(EntryService::class)->prepareReversal($entry->id, now()->year . '-03-05', 'Reverso ficticio posterior al corte de febrero.', $this->admin->id);
        $this->postPrepared($reverse, 'close-aux-reversal');
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        foreach ([1 => '4000', 2 => '2400', 3 => '4000'] as $month => $expected) {
            $review = $service->preview(Period::where('month', $month)->firstOrFail()->id);
            $control = collect($review['auxiliaries'])->firstWhere('account_id', $item->account_id);
            $this->assertTrue(\Brick\Math\BigDecimal::of($control['auxiliary'])->isEqualTo($expected));
            $this->assertTrue(\Brick\Math\BigDecimal::of($control['difference'])->isZero());
        }
    }

    public function test_a_period_review_cannot_be_read_from_another_company(): void
    {
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $period = Period::where('month', 1)->firstOrFail();
        $closed = $service->close($period->id, 1, $this->closeInput($service->preview($period->id)), $this->admin->id, 'scope-close-001');
        app(CompanyContext::class)->set($this->b);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->snapshot($period->id, $closed['review_id']);
    }

    public function test_monthly_close_blocks_an_exact_difference_that_displays_as_zero_cents(): void
    {
        $entry = $this->januaryEntry();
        $credit = $entry->lines()->where('credit', '>', '0')->firstOrFail();
        $credit->credit = '100.24999999';
        $credit->save();
        $entry->total_credit = '100.24999999';
        $entry->posted_at = now();
        $entry->status = 'posted';
        $entry->save();
        $service = app(\Modules\Accounting\Services\ClosingService::class);
        $preview = $service->preview(Period::where('month', 1)->firstOrFail()->id);
        $this->assertTrue(\Brick\Math\BigDecimal::of($preview['trial']['totals']['balance'])->isEqualTo('0.00000001'));
        $this->assertSame('0.00', $preview['reports']['trial-balance']['totals']['balance']);
        $this->assertContains('UNBALANCED_LEDGER', array_column($preview['blockers'], 'code'));
    }

    private function annualFixture(bool $debt = false, bool $activity = true, ?int $yearOverride = null): array
    {
        $year = $yearOverride ?? (now()->year - 1);
        app(CompanyService::class)->createYear($year);
        $accounts = Account::get()->keyBy('code');
        if ($debt) {
            $party = ThirdParty::create(['name' => 'Cliente ficticio del cierre anual', 'tax_id' => 'J-11111111-1', 'kind' => 'customer']);
            $opening = app(\Modules\Accounting\Services\OpeningService::class)->prepare(['cutoff_date' => $year . '-01-01', 'reason' => 'Saldo inicial ficticio del cierre anual con moneda histórica.', 'evidence_reference' => 'Papel de trabajo ficticio de arranque',
                'balances' => [['account_id' => $accounts['1.1.03']->id, 'debit' => '4000.00', 'credit' => '0'], ['account_id' => $accounts['3.1']->id, 'debit' => '0', 'credit' => '4000.00']],
                'open_items' => [['account_id' => $accounts['1.1.03']->id, 'third_party_id' => $party->id, 'reference' => 'DEUDA-ANUAL-1', 'issue_date' => ($year - 1) . '-12-31', 'currency' => 'USD', 'original_amount' => '100', 'book_amount' => '4000.00', 'exchange_rate' => '40', 'exchange_date' => ($year - 1) . '-12-31', 'exchange_source' => 'Tasa histórica ficticia de arranque', 'evidence_reference' => 'Saldo ficticio verificado']]], $this->admin->id);
            $this->postPrepared($opening, 'annual-fixture-opening-' . $year);
        }
        foreach ($activity ? [['01-15', '4.1', '500.00', false], ['02-15', '6.1', '200.00', true]] : [] as [$date, $code, $amount, $expense]) {
            $entry = app(EntryService::class)->create(['accounting_date' => $year . '-' . $date, 'concept' => 'Operación ficticia del ejercicio para cierre anual', 'lines' => [
                ['account_id' => $accounts['1.1.02']->id, 'debit' => $expense ? '0' : $amount, 'credit' => $expense ? $amount : '0'],
                ['account_id' => $accounts[$code]->id, 'debit' => $expense ? $amount : '0', 'credit' => $expense ? '0' : $amount],
            ]], $this->admin->id);
            $this->postPrepared($entry, 'annual-fixture-' . $year . '-' . $code);
        }
        $monthly = app(\Modules\Accounting\Services\ClosingService::class);
        for ($month = 1; $month < 12; $month++) {
            $period = Period::where('year', $year)->where('month', $month)->firstOrFail();
            $monthly->close($period->id, $period->version, $this->closeInput($monthly->preview($period->id)), $this->admin->id, 'annual-month-' . $year . '-' . $month);
        }
        return ['year' => $year, 'accounts' => $accounts];
    }

    private function annualPrepared(array $fixture): array
    {
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $screen = $service->screen($fixture['year']);
        $basis = $screen['preview'];
        $this->assertSame([], $basis['review']['blockers']);
        $input = [...$this->closeInput($basis['review']), 'basis_hash' => $basis['basis_hash'], 'result_account_id' => $fixture['accounts']['3.3']->id];
        unset($input['review_token']);
        return $service->prepare($fixture['year'], $screen['fiscal_year']['version'], $input, $this->admin->id, 'annual-prepare-fixture-' . $fixture['year']);
    }

    private function approveAnnual(array $closing, bool $reopening = false, ?int $reviewer = null): array
    {
        $entries = $reopening ? $closing['reopening_review']['entries'] : $closing['entries'];
        foreach ($entries as $entry) {
            $service = app(EntryService::class);
            $entity = JournalEntry::findOrFail($entry['id']);
            if (in_array($entity->status, ['draft', 'observed'], true)) {
                $entity = $service->transition($entry['id'], $entry['version'], $this->admin->id, 'submit');
            }
            $service->transition($entity->id, $entity->version, $reviewer ?? $this->admin->id, 'approve', 'Revisión expresa del conjunto anual ficticio y sus soportes.');
        }
        return app(\Modules\Accounting\Services\YearClosingService::class)->show($closing['id']);
    }

    public function test_annual_close_transfers_profit_and_balances_without_repeating_debts_or_next_year_activity(): void
    {
        $fixture = $this->annualFixture(true);
        $accounts = $fixture['accounts'];
        $next = app(EntryService::class)->create(['accounting_date' => ($fixture['year'] + 1) . '-01-10', 'concept' => 'Ingreso nuevo ficticio del siguiente ejercicio', 'lines' => [
            ['account_id' => $accounts['1.1.02']->id, 'debit' => '10.00', 'credit' => '0'], ['account_id' => $accounts['4.1']->id, 'debit' => '0', 'credit' => '10.00'],
        ]], $this->admin->id);
        $this->postPrepared($next, 'annual-next-year-income');
        $originalItem = OpenItem::firstOrFail()->toArray();
        $prepared = $this->annualPrepared($fixture);
        $this->assertCount(3, $prepared['entries']);
        $this->assertTrue(\Brick\Math\BigDecimal::of($prepared['proposal']['result'])->isEqualTo('300'));
        $this->assertSame('500.00000000', $prepared['entries']['result']['total_debit']);
        $this->assertSame('4300.00000000', $prepared['entries']['opening']['total_debit']);
        $approved = $this->approveAnnual($prepared);
        $this->assertTrue($approved['can_post']);
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $posted = $service->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-complete-001');
        $this->assertSame('posted', $posted['status']);
        $this->assertEquals($posted, $service->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-complete-001'));
        $this->assertSame('closed', \Modules\Accounting\Models\FiscalYear::where('year', $fixture['year'])->firstOrFail()->status);
        $this->assertSame($originalItem, OpenItem::firstOrFail()->toArray());
        $this->assertSame(1, OpenItem::count());
        $reports = app(ReportService::class);
        $oldTo = $fixture['year'] . '-12-31';
        $old = $reports->build('trial-balance', ['from' => $fixture['year'] . '-01-01', 'to' => $oldTo]);
        foreach ($old['rows'] as $row) {
            $this->assertSame('0.00', $row['balance']);
        }
        $financial = $reports->build('balance-sheet', ['from' => $fixture['year'] . '-01-01', 'to' => $oldTo]);
        $this->assertSame('4300.00', $financial['totals']['assets']);
        $this->assertSame('4300.00', $financial['totals']['equity']);
        $this->assertSame('0.00', $financial['totals']['difference']);
        $nextYear = $reports->build('balance-sheet', ['from' => ($fixture['year'] + 1) . '-01-01', 'to' => ($fixture['year'] + 1) . '-01-31']);
        $this->assertSame('4310.00', $nextYear['totals']['assets']);
        $this->assertSame('4300.00', $nextYear['totals']['equity']);
        $this->assertSame('10.00', $nextYear['totals']['unclosed_result']);
        $this->assertSame('0.00', $nextYear['totals']['difference']);
        $income = $reports->build('income-statement', ['from' => ($fixture['year'] + 1) . '-01-01', 'to' => ($fixture['year'] + 1) . '-12-31']);
        $this->assertSame('10.00', $income['totals']['result']);
        $this->assertSame('0.00', $income['totals']['opening_carry_forward']);
        $period = Period::where('year', $fixture['year'])->where('month', 12)->firstOrFail();
        $saved = app(\Modules\Accounting\Services\ClosingService::class)->snapshot($period->id, $period->close_snapshot['review_id']);
        $this->assertSame('300.00', $saved['snapshot']['reports']['income-statement']['totals']['result']);
        $this->assertSame('4300.00', $saved['snapshot']['reports']['balance-sheet']['totals']['assets']);
    }

    public function test_annual_entries_cannot_be_edited_or_posted_individually(): void
    {
        $fixture = $this->annualFixture();
        $prepared = $this->annualPrepared($fixture);
        $december = Period::where('year', $fixture['year'])->where('month', 12)->firstOrFail();
        $monthly = app(\Modules\Accounting\Services\ClosingService::class)->preview($december->id);
        $this->assertContains('ANNUAL_CLOSING_ACTIVE', array_column($monthly['blockers'], 'code'));
        $entry = JournalEntry::findOrFail($prepared['entries']['result']['id']);
        try {
            app(EntryService::class)->edit($entry->id, app(EntryService::class)->resource($entry), $entry->version, $this->admin->id);
            $this->fail('Los comprobantes controlados no se editan separadamente.');
        } catch (ApiProblem $error) {
            $this->assertSame('YEAR_CLOSING_CONTROLLED', $error->errorCode);
        }
        $approved = $this->approveAnnual($prepared);
        $entry = $approved['entries']['opening'];
        try {
            app(EntryService::class)->post($entry['id'], $entry['version'], $this->admin->id, 'annual-single-forbidden');
            $this->fail('La apertura no se puede contabilizar por separado.');
        } catch (ApiProblem $error) {
            $this->assertSame('YEAR_CLOSING_BATCH_REQUIRED', $error->errorCode);
        }
        $this->assertNull(JournalEntry::findOrFail($entry['id'])->posted_at);
        $this->assertSame('prepared', \Modules\Accounting\Models\YearClosing::findOrFail($prepared['id'])->status);
    }

    public function test_annual_posting_rolls_back_every_entry_sequence_and_year_if_the_opening_fails(): void
    {
        $fixture = $this->annualFixture(true);
        $approved = $this->approveAnnual($this->annualPrepared($fixture));
        $sequences = DB::table('accounting_journal_sequences')->where('company_id', $this->a->id)->orderBy('year')->get()->toArray();
        $event = 'eloquent.updating: ' . JournalEntry::class;
        $dispatcher = self::$application->make('events');
        $previous = $dispatcher->getRawListeners()[$event] ?? [];
        $dispatcher->listen($event, function ($entry) {
            if ($entry->effect_type === 'annual_opening' && $entry->posted_at) {
                throw new \RuntimeException('Fallo ficticio de la apertura durante la transacción anual.');
            }
        });
        try {
            app(\Modules\Accounting\Services\YearClosingService::class)->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-rollback-001');
            $this->fail('El fallo de la última fase debe cancelar todo el conjunto.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo ficticio de la apertura durante la transacción anual.', $error->getMessage());
        } finally {
            $dispatcher->forget($event);
            foreach ($previous as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }
        $this->assertEquals($sequences, DB::table('accounting_journal_sequences')->where('company_id', $this->a->id)->orderBy('year')->get()->toArray());
        foreach ($approved['entries'] as $entry) {
            $this->assertNull(JournalEntry::findOrFail($entry['id'])->posted_at);
            $this->assertSame('approved', JournalEntry::findOrFail($entry['id'])->status);
        }
        $this->assertSame('prepared', \Modules\Accounting\Models\YearClosing::findOrFail($approved['id'])->status);
        $this->assertSame('open', Period::where('year', $fixture['year'])->where('month', 12)->firstOrFail()->status);
        $this->assertSame('open', \Modules\Accounting\Models\FiscalYear::where('year', $fixture['year'])->firstOrFail()->status);
        $this->assertSame('4000.00000000', OpenItem::firstOrFail()->remaining_book);
    }

    public function test_annual_review_changes_require_withdrawing_the_old_proposal_without_deleting_it(): void
    {
        $fixture = $this->annualFixture();
        $prepared = $this->approveAnnual($this->annualPrepared($fixture));
        $accounts = $fixture['accounts'];
        $adjustment = app(EntryService::class)->create(['accounting_date' => $fixture['year'] . '-12-15', 'concept' => 'Ajuste ficticio posterior a preparar el cierre anual', 'lines' => [
            ['account_id' => $accounts['6.1']->id, 'debit' => '25.00', 'credit' => '0'], ['account_id' => $accounts['1.1.02']->id, 'debit' => '0', 'credit' => '25.00'],
        ]], $this->admin->id);
        $this->postPrepared($adjustment, 'annual-later-adjustment');
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $changed = $service->show($prepared['id']);
        $this->assertFalse($changed['basis_matches']);
        $this->assertFalse($changed['can_post']);
        try {
            $service->post($prepared['id'], $prepared['version'], $prepared['review_token'], $this->admin->id, 'annual-stale-001');
            $this->fail('La revisión anterior a un ajuste no puede contabilizarse.');
        } catch (ApiProblem $error) {
            $this->assertSame('YEAR_CLOSING_REVIEW_CHANGED', $error->errorCode);
        }
        $withdrawn = $service->cancel($prepared['id'], $prepared['version'], 'Retiro ficticio para incorporar el ajuste posterior al cierre propuesto.', $this->admin->id, 'annual-withdraw-001');
        $this->assertSame('cancelled', $withdrawn['status']);
        $this->assertCount(3, $withdrawn['entries']);
        foreach ($withdrawn['entries'] as $entry) {
            $this->assertSame('withdrawn', $entry['status']);
            $this->assertNull($entry['posted_at']);
        }
        $screen = $service->screen($fixture['year']);
        $this->assertSame(0, $screen['preview']['review']['pending']['pending_entries']['count']);
        $this->assertTrue(\Brick\Math\BigDecimal::of($screen['preview']['proposal']['result'])->isEqualTo('275'));
    }

    public function test_annual_reopening_uses_approved_reversals_and_keeps_original_entries_and_debts(): void
    {
        $fixture = $this->annualFixture(true);
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $approved = $this->approveAnnual($this->annualPrepared($fixture));
        $posted = $service->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-to-reopen-001');
        $item = OpenItem::firstOrFail()->toArray();
        $prepared = $service->prepareReopening($posted['id'], $posted['version'], 'Reapertura ficticia para revisar un ajuste del ejercicio anterior.', $this->admin->id, 'annual-reopen-prepare-001');
        $this->assertFalse($prepared['can_reopen']);
        $approvedReopening = $this->approveAnnual($prepared, true);
        $this->assertTrue($approvedReopening['can_reopen']);
        $reopened = $service->reopen($prepared['id'], $approvedReopening['version'], $approvedReopening['review_token'], $this->admin->id, 'annual-reopen-001');
        $this->assertSame('reopened', $reopened['status']);
        $this->assertEquals($reopened, $service->reopen($prepared['id'], $approvedReopening['version'], $approvedReopening['review_token'], $this->admin->id, 'annual-reopen-001'));
        $this->assertSame($item, OpenItem::firstOrFail()->toArray());
        $this->assertSame(0, Period::where('year', $fixture['year'])->where('status', 'closed')->count());
        foreach ($reopened['entries'] as $entry) {
            $this->assertNotNull(JournalEntry::findOrFail($entry['id'])->posted_at);
            $this->assertSame(1, JournalEntry::where('reversal_of', $entry['id'])->whereNotNull('posted_at')->count());
        }
        $next = app(ReportService::class)->build('balance-sheet', ['from' => ($fixture['year'] + 1) . '-01-01', 'to' => ($fixture['year'] + 1) . '-01-31']);
        $this->assertSame('4300.00', $next['totals']['assets']);
        $this->assertSame('4000.00', $next['totals']['equity']);
        $this->assertSame('300.00', $next['totals']['unclosed_result']);
        $this->assertSame('0.00', $next['totals']['difference']);
    }

    public function test_year_closing_proposals_are_immutable_and_scoped_to_their_company(): void
    {
        $fixture = $this->annualFixture();
        $prepared = $this->annualPrepared($fixture);
        try {
            DB::table('accounting_year_closings')->where('id', $prepared['id'])->update(['proposal' => json_encode(['changed' => true])]);
            $this->fail('La propuesta anual no se reescribe.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertSame('45000', $error->errorInfo[0]);
        }
        app(CompanyContext::class)->set($this->b);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(\Modules\Accounting\Services\YearClosingService::class)->show($prepared['id']);
    }

    public function test_annual_approval_keeps_separate_preparer_and_reviewer(): void
    {
        $fixture = $this->annualFixture();
        $this->a->approval_mode = 'separate';
        $this->a->save();
        $prepared = $this->annualPrepared($fixture);
        $entries = app(EntryService::class);
        $entry = $prepared['entries']['result'];
        $entry = $entries->transition($entry['id'], $entry['version'], $this->admin->id, 'submit');
        try {
            $entries->transition($entry->id, $entry->version, $this->admin->id, 'approve', 'Intento ficticio de aprobación por quien preparó el conjunto.');
            $this->fail('El preparador no sustituye al aprobador independiente.');
        } catch (ApiProblem $error) {
            $this->assertSame('SEPARATE_APPROVAL_REQUIRED', $error->errorCode);
        }
        $reviewer = DB::table('users')->insertGetId(['name' => 'Revisor anual ficticio', 'email' => Str::uuid() . '@example.invalid', 'password' => password_hash('Fictitious-Annual-Review-Only', PASSWORD_BCRYPT), 'created_at' => now(), 'updated_at' => now()]);
        $approved = $this->approveAnnual(app(\Modules\Accounting\Services\YearClosingService::class)->show($prepared['id']), false, $reviewer);
        $this->assertTrue($approved['can_post']);
        foreach ($approved['entries'] as $entry) {
            $this->assertSame($reviewer, $entry['approved_by']);
            $this->assertSame($this->admin->id, $entry['created_by']);
        }
    }

    public function test_annual_loss_is_transferred_with_the_correct_sign(): void
    {
        $fixture = $this->annualFixture();
        $accounts = $fixture['accounts'];
        foreach ([['3.1', '1000.00', false], ['6.1', '350.00', true]] as [$code, $amount, $expense]) {
            $entry = app(EntryService::class)->create(['accounting_date' => $fixture['year'] . '-12-20', 'concept' => 'Movimiento ficticio del caso anual con pérdida', 'lines' => [
                ['account_id' => $accounts['1.1.02']->id, 'debit' => $expense ? '0' : $amount, 'credit' => $expense ? $amount : '0'],
                ['account_id' => $accounts[$code]->id, 'debit' => $expense ? $amount : '0', 'credit' => $expense ? '0' : $amount],
            ]], $this->admin->id);
            $this->postPrepared($entry, 'annual-loss-' . $code);
        }
        $approved = $this->approveAnnual($this->annualPrepared($fixture));
        $this->assertTrue(\Brick\Math\BigDecimal::of($approved['proposal']['result'])->isEqualTo('-50'));
        app(\Modules\Accounting\Services\YearClosingService::class)->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-loss-post');
        $balance = app(ReportService::class)->build('balance-sheet', ['from' => ($fixture['year'] + 1) . '-01-01', 'to' => ($fixture['year'] + 1) . '-01-31']);
        $this->assertSame('950.00', $balance['totals']['assets']);
        $this->assertSame('950.00', $balance['totals']['equity']);
        $this->assertSame('0.00', $balance['totals']['difference']);
    }

    public function test_empty_annual_close_does_not_invent_zero_value_entries(): void
    {
        $fixture = $this->annualFixture(false, false);
        $prepared = $this->annualPrepared($fixture);
        $this->assertCount(0, $prepared['entries']);
        $this->assertTrue($prepared['can_post']);
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $posted = $service->post($prepared['id'], $prepared['version'], $prepared['review_token'], $this->admin->id, 'annual-empty-post');
        $this->assertSame('posted', $posted['status']);
        $this->assertSame(0, JournalEntry::count());
        $reopening = $service->prepareReopening($posted['id'], $posted['version'], 'Reapertura ficticia del ejercicio sin movimientos contables.', $this->admin->id, 'annual-empty-reopening');
        $this->assertTrue($reopening['can_reopen']);
        $reopened = $service->reopen($reopening['id'], $reopening['version'], $reopening['review_token'], $this->admin->id, 'annual-empty-reopen');
        $this->assertSame('reopened', $reopened['status']);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_annual_opening_preserves_cost_centers_and_third_party_dimensions(): void
    {
        $fixture = $this->annualFixture(true);
        $accounts = $fixture['accounts'];
        $center = \Modules\Accounting\Models\CostCenter::create(['code' => 'ANUAL', 'name' => 'Centro ficticio del cierre anual']);
        $entry = app(EntryService::class)->create(['accounting_date' => $fixture['year'] . '-12-15', 'concept' => 'Ingreso ficticio con centro de costo para traslado anual', 'lines' => [
            ['account_id' => $accounts['1.1.02']->id, 'cost_center_id' => $center->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $accounts['4.1']->id, 'cost_center_id' => $center->id, 'debit' => '0', 'credit' => '100.00'],
        ]], $this->admin->id);
        $this->postPrepared($entry, 'annual-dimensions-entry');
        $prepared = $this->annualPrepared($fixture);
        $carry = collect($prepared['proposal']['phases']['opening']['lines']);
        $centerLine = $carry->firstWhere('cost_center_id', $center->id);
        $this->assertSame($accounts['1.1.02']->id, $centerLine['account_id']);
        $this->assertSame('100.00000000', $centerLine['debit']);
        $partyLine = $carry->firstWhere('account_id', $accounts['1.1.03']->id);
        $this->assertSame(OpenItem::firstOrFail()->third_party_id, $partyLine['third_party_id']);
        $this->assertSame('4000.00000000', $partyLine['debit']);
        $approved = $this->approveAnnual($prepared);
        $posted = app(\Modules\Accounting\Services\YearClosingService::class)->post($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-dimensions-post');
        $this->assertSame('posted', $posted['status']);
        $this->assertSame(1, OpenItem::count());
        $this->assertSame('100.000000000000', OpenItem::firstOrFail()->remaining_original);
    }

    public function test_two_closed_years_reopen_from_latest_to_oldest_without_repeating_their_balances(): void
    {
        $service = app(\Modules\Accounting\Services\YearClosingService::class);
        $olderFixture = $this->annualFixture(false, true, now()->year - 2);
        $older = $this->approveAnnual($this->annualPrepared($olderFixture));
        $older = $service->post($older['id'], $older['version'], $older['review_token'], $this->admin->id, 'annual-chain-older-post');
        $newerFixture = $this->annualFixture();
        $newer = $this->approveAnnual($this->annualPrepared($newerFixture));
        $this->assertSame('600.00000000', $newer['proposal']['phases']['opening']['total_debit']);
        $newer = $service->post($newer['id'], $newer['version'], $newer['review_token'], $this->admin->id, 'annual-chain-newer-post');
        try {
            $service->prepareReopening($older['id'], $older['version'], 'Intento ficticio de reabrir antes del ejercicio posterior cerrado.', $this->admin->id, 'annual-chain-blocked');
            $this->fail('Se debe reabrir primero el ejercicio más reciente.');
        } catch (ApiProblem $error) {
            $this->assertSame('LATER_YEAR_CLOSED', $error->errorCode);
        }
        $this->assertSame(0, JournalEntry::whereIn('reversal_of', array_column($older['entries'], 'id'))->count());
        foreach ([$newer, $older] as $record) {
            $prepared = $service->prepareReopening($record['id'], $record['version'], 'Reapertura ficticia de la cadena anual en el orden autorizado.', $this->admin->id, 'annual-chain-prepare-' . $record['id']);
            $approved = $this->approveAnnual($prepared, true);
            $reopened = $service->reopen($approved['id'], $approved['version'], $approved['review_token'], $this->admin->id, 'annual-chain-reopen-' . $record['id']);
            $this->assertSame('reopened', $reopened['status']);
            $this->assertSame($record['posted_snapshot_sha256'], $reopened['posted_snapshot_sha256']);
        }
        $this->assertSame('open', \Modules\Accounting\Models\FiscalYear::where('year', $olderFixture['year'])->firstOrFail()->status);
        $this->assertSame('open', \Modules\Accounting\Models\FiscalYear::where('year', $newerFixture['year'])->firstOrFail()->status);
        $balance = app(ReportService::class)->build('balance-sheet', ['from' => now()->year . '-01-01', 'to' => now()->year . '-01-31']);
        $this->assertSame('600.00', $balance['totals']['assets']);
        $this->assertSame('600.00', $balance['totals']['unclosed_result']);
        $this->assertSame('0.00', $balance['totals']['equity']);
        $this->assertSame('0.00', $balance['totals']['difference']);
    }
}
