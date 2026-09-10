<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\FiscalEmissionController;
use App\Models\Tenant\Company;
use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;
use App\Services\FiscalEmissionSettings;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
class FiscalEmissionSettingsTest extends TestCase
{
    protected $capsule;
    private $previousContainer;
    private $previousFacade;
    private $previousResolver;
    private $previousDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $app = new \Illuminate\Foundation\Application(dirname(__DIR__, 2));
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturn('tenant');
        $app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        $app->instance('config', new Repository([
            'tenancy.db.tenant-connection-name' => 'tenant',
            'fiscal_emission' => ['operation_tables' => ['documents', 'purchases', 'inventory_kardex']],
        ]));
        $app->instance('validator', new Factory(new Translator(new ArrayLoader(), 'es'), $app));
        $app->instance('encrypter', new Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
        $this->capsule = new Manager($app);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'tenant');
        $this->capsule->getDatabaseManager()->setDefaultConnection('tenant');
        $this->capsule->setEventDispatcher(new Dispatcher($app));
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $app->instance('db', $this->capsule->getDatabaseManager());
        $db = $this->capsule->getConnection('tenant');
        $db->statement('CREATE TABLE companies (id INTEGER PRIMARY KEY, fiscal_emission_mode TEXT NOT NULL, fiscal_environment TEXT NOT NULL, fiscal_configuration TEXT NULL, fiscal_credentials TEXT NULL, fiscal_environment_locked INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)');
        $db->statement('CREATE TABLE fiscal_configuration_audits (id INTEGER PRIMARY KEY, company_id INTEGER, actor_type TEXT, actor_id INTEGER, changed_fields TEXT, fiscal_emission_mode TEXT, fiscal_environment TEXT, created_at TEXT)');
        $db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, fiscal_environment TEXT, fiscal_emission_mode TEXT)');
        $db->statement('CREATE TABLE purchases (id INTEGER PRIMARY KEY, fiscal_environment TEXT)');
        $db->statement('CREATE TABLE inventory_kardex (id INTEGER PRIMARY KEY)');
        $db->statement('CREATE TABLE fiscal_environments (id TEXT PRIMARY KEY, description TEXT)');
        $db->table('fiscal_environments')->insert([['id' => 'demo', 'description' => 'Demo'], ['id' => 'production', 'description' => 'Producción']]);
        $db->table('companies')->insert(['id' => 1, 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'free_form']);
        Company::addGlobalScope('test_without_relations', fn ($query) => $query->without(['identity_document_type']));
    }

    protected function tearDown(): void
    {
        Company::clearBootedModels();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_all_modes_and_environments_allow_progressive_configuration(): void
    {
        foreach (array_keys(FiscalEmissionSettings::MODES) as $mode) {
            foreach (array_keys(FiscalEmissionSettings::ENVIRONMENTS) as $environment) {
                $company = FiscalEmissionSettings::update(Company::firstOrFail(), [
                    'fiscal_emission_mode' => $mode, 'fiscal_environment' => $environment,
                ], 'system', 12);
                self::assertSame($mode, $company->fiscal_emission_mode);
                self::assertSame($environment, $company->fiscal_environment);
            }
        }
        self::assertSame(6, $this->capsule->getConnection('tenant')->table('fiscal_configuration_audits')->count());
    }

    public function test_environment_relations_use_string_keys_when_eager_loaded(): void
    {
        $company = Company::with('fiscal_environment_type')->firstOrFail();
        self::assertSame('Demo', $company->fiscal_environment_type->description);
        $company = FiscalEmissionSettings::update($company, ['fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'production'], 'system', 1);
        self::assertSame('Producción', $company->load('fiscal_environment_type')->fiscal_environment_type->description);
    }

    public function test_unknown_top_level_settings_are_not_persisted(): void
    {
        $company = FiscalEmissionSettings::update(Company::firstOrFail(), [
            'fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'demo',
            'unrecognized_setting' => 'do-not-store',
        ], 'tenant', 1);
        self::assertArrayNotHasKey('unrecognized_setting', $company->getAttributes());
        self::assertStringNotContainsString('do-not-store', json_encode($company->toArray()));
    }

    public function test_initial_selection_is_audited_without_optional_parameters(): void
    {
        FiscalEmissionSettings::update(Company::firstOrFail(), [
            'fiscal_emission_mode' => 'free_form', 'fiscal_environment' => 'demo',
        ], 'system', 5, true);
        $audit = $this->capsule->getConnection('tenant')->table('fiscal_configuration_audits')->first();
        self::assertSame(['fiscal_emission_mode', 'fiscal_environment'], json_decode($audit->changed_fields));
        self::assertSame(5, (int) $audit->actor_id);
    }

    /** @dataProvider invalidSettings */
    public function test_invalid_settings_are_rejected_before_persistence(array $extra): void
    {
        $this->expectException(ValidationException::class);
        FiscalEmissionSettings::update(Company::firstOrFail(), array_replace([
            'fiscal_emission_mode' => 'free_form', 'fiscal_environment' => 'demo',
        ], $extra), 'tenant', 1);
    }

    public static function invalidSettings(): array
    {
        return [
            [['fiscal_emission_mode' => null]], [['fiscal_emission_mode' => 'invalid']],
            [['fiscal_environment' => 'invalid']],
            [['fiscal_configuration' => ['port' => 'COM1']]],
            [['fiscal_configuration' => ['range_start' => 20, 'range_end' => 10]]],
            [['fiscal_configuration' => ['range_start' => -1]]],
            [['fiscal_credentials' => 'not-for-free-form']],
            [['fiscal_credentials' => '0']],
            [['fiscal_emission_mode' => 'digital', 'fiscal_credentials' => '0', 'clear_fiscal_credentials' => true]],
        ];
    }

    public function test_credentials_are_encrypted_hidden_preserved_and_cleared_on_mode_change(): void
    {
        $input = ['fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'demo', 'fiscal_credentials' => 'provider-secret'];
        $company = FiscalEmissionSettings::update(Company::firstOrFail(), $input, 'tenant', 9);
        self::assertSame('provider-secret', $company->fiscal_credentials);
        self::assertStringNotContainsString('provider-secret', $company->getRawOriginal('fiscal_credentials'));
        self::assertArrayNotHasKey('fiscal_credentials', $company->toArray());
        self::assertTrue(FiscalEmissionSettings::publicData($company)['fiscal_credentials_configured']);
        $audit = $this->capsule->getConnection('tenant')->table('fiscal_configuration_audits')->first();
        self::assertStringNotContainsString('provider-secret', json_encode($audit));
        $input['fiscal_credentials'] = '';
        $company = FiscalEmissionSettings::update($company, $input, 'tenant', 9);
        self::assertSame('provider-secret', $company->fiscal_credentials);
        $input['fiscal_emission_mode'] = 'fiscal_machine';
        $company = FiscalEmissionSettings::update($company, $input, 'tenant', 9);
        self::assertNull($company->fiscal_credentials);
    }

    /** @dataProvider operationTables */
    public function test_operations_block_environment_changes_for_all_actors(string $table, string $actor): void
    {
        $this->capsule->getConnection('tenant')->table($table)->insert(['id' => 1]);
        $this->expectException(ValidationException::class);
        FiscalEmissionSettings::update(Company::firstOrFail(), [
            'fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'production',
        ], $actor, 1);
    }

    public static function operationTables(): array
    {
        return [['documents', 'tenant'], ['purchases', 'system'], ['inventory_kardex', 'tenant']];
    }

    public function test_new_invoice_requires_mode_and_snapshots_server_configuration(): void
    {
        $this->capsule->getConnection('tenant')->table('companies')->update(['fiscal_emission_mode' => 'invalid']);
        try {
            (new FiscalTestDocument())->save();
            self::fail('An unconfigured company must not register an invoice.');
        } catch (ValidationException $exception) {
            self::assertSame(0, $this->capsule->getConnection('tenant')->table('documents')->count());
            self::assertFalse(Company::firstOrFail()->fiscal_environment_locked);
        }
        FiscalEmissionSettings::update(Company::firstOrFail(), ['fiscal_emission_mode' => 'free_form', 'fiscal_environment' => 'demo'], 'tenant', 1);
        $document = new FiscalTestDocument(['fiscal_environment' => 'production', 'fiscal_emission_mode' => 'digital']);
        $document->save();
        self::assertSame('demo', $document->fiscal_environment);
        self::assertSame('free_form', $document->fiscal_emission_mode);
        self::assertTrue(Company::firstOrFail()->fiscal_environment_locked);
        $document->delete();
        self::assertTrue(FiscalEmissionSettings::hasOperations(Company::firstOrFail()));
    }

    public function test_non_administrators_are_rejected_even_with_ajax_header(): void
    {
        $request = Request::create('/companies/fiscal-emission', 'POST', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $request->setUserResolver(fn () => new User(['type' => 'seller']));
        try {
            (new FiscalEmissionController())->store($request);
            self::fail('Expected a forbidden response.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('free_form', Company::firstOrFail()->fiscal_emission_mode);
        }
    }

    public function test_administrator_endpoint_uses_resolved_tenant_and_ignores_foreign_company_id(): void
    {
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'other_tenant');
        $other = $this->capsule->getConnection('other_tenant');
        $other->statement('CREATE TABLE companies (id INTEGER PRIMARY KEY, fiscal_emission_mode TEXT, fiscal_environment TEXT)');
        $other->table('companies')->insert(['id' => 1, 'fiscal_emission_mode' => 'free_form', 'fiscal_environment' => 'production']);
        $request = Request::create('/companies/fiscal-emission', 'POST', [
            'id' => 999,
            'tenant' => 'other_tenant',
            'fiscal_emission_mode' => 'digital',
            'fiscal_environment' => 'demo',
            'fiscal_configuration' => ['provider' => 'Proveedor de prueba'],
            'fiscal_credentials' => 'secret-for-current-tenant',
        ]);
        $request->setUserResolver(fn () => (new User())->forceFill(['id' => 7, 'type' => 'admin']));
        $response = (new FiscalEmissionController())->store($request);
        self::assertTrue($response['success']);
        self::assertSame('digital', Company::firstOrFail()->fiscal_emission_mode);
        self::assertSame('free_form', $other->table('companies')->value('fiscal_emission_mode'));
        self::assertStringNotContainsString('secret-for-current-tenant', json_encode($response));
        self::assertTrue($response['data']['fiscal_credentials_configured']);
        self::assertSame('Proveedor de prueba', $response['data']['fiscal_configuration']->provider);
        self::assertSame('not_integrated', $response['data']['fiscal_integration_status']);
    }

    public function test_guest_and_ecommerce_identity_cannot_access_configuration(): void
    {
        foreach ([null, (object) ['type' => 'admin']] as $identity) {
            $request = Request::create('/companies/fiscal-emission', 'GET');
            $request->setUserResolver(fn () => $identity);
            try {
                (new FiscalEmissionController())->record($request);
                self::fail('Only tenant administrators can access fiscal configuration.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_explicit_secret_clear_and_failed_save_are_atomic(): void
    {
        $input = ['fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'demo', 'fiscal_credentials' => 'secret'];
        $company = FiscalEmissionSettings::update(Company::firstOrFail(), $input, 'tenant', 1);
        $company = FiscalEmissionSettings::update($company, array_replace($input, ['fiscal_credentials' => '', 'clear_fiscal_credentials' => true]), 'tenant', 1);
        self::assertNull($company->fiscal_credentials);
        try {
            // Duplicate primary key causes a real database failure after the company lock is set.
            $db = $this->capsule->getConnection('tenant');
            $db->table('documents')->insert(['id' => 1]);
            $document = new FiscalTestDocument();
            $document->id = 1;
            $document->save();
            self::fail('Expected a duplicate key failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            self::assertFalse(Company::firstOrFail()->fiscal_environment_locked);
            self::assertSame(1, $db->table('documents')->count());
        }
    }
}

class FiscalTestDocument extends ModelTenant
{
    protected $table = 'documents';
    protected $fillable = ['fiscal_environment', 'fiscal_emission_mode'];
    public $timestamps = false;
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
