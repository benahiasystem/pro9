<?php
namespace Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

abstract class SeriesDatabaseTestCase extends TestCase
{
    protected $db;
    protected $manager;
    protected $user;
    protected $tenantConnection = 'tenant';
    private $previousContainer, $previousFacade, $previousResolver, $previousDispatcher;

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
        $app->instance('config', new \Illuminate\Config\Repository([
            'fiscal_emission' => ['operation_tables' => ['documents', 'dispatches', 'sale_notes', 'guides', 'inventories_transfer']],
        ]));
        $app->instance('validator', new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app));
        $app->instance('cache', new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore()));
        $app->instance('cookie', new \Illuminate\Cookie\CookieJar());
        $app->instance('request', \Illuminate\Http\Request::create('/'));
        \Illuminate\Http\Request::macro('validate', function ($rules) { return app('validator')->make($this->all(), $rules)->validate(); });
        $this->user = new \App\Models\Tenant\User();
        $this->user->setRawAttributes(['id' => 1, 'type' => 'admin', 'establishment_id' => 1]);
        $guard = $this->createMock(\Illuminate\Contracts\Auth\Guard::class);
        $guard->method('check')->willReturnCallback(fn () => $this->user !== null);
        $guard->method('user')->willReturnCallback(fn () => $this->user);
        $guard->method('id')->willReturnCallback(fn () => $this->user->id ?? null);
        $auth = $this->getMockBuilder(\Illuminate\Auth\AuthManager::class)->disableOriginalConstructor()->onlyMethods(['guard'])->getMock();
        $auth->method('guard')->willReturn($guard);
        $app->instance('auth', $auth);
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
        $tenancy->method('tenantName')->willReturnCallback(fn () => $this->tenantConnection);
        $app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        $this->manager = new Manager($app);
        $this->configureConnections($this->manager);
        $this->manager->getDatabaseManager()->setDefaultConnection('tenant');
        $this->manager->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $this->manager->bootEloquent();
        $app->instance('db', $this->manager->getDatabaseManager());
        $this->db = $this->manager->getConnection('tenant');
        $app->make('validator')->setPresenceVerifier(new \Illuminate\Validation\DatabasePresenceVerifier($this->manager->getDatabaseManager()));
        \App\Models\Tenant\Company::addGlobalScope('test_without_relations', fn ($query) => $query->without(['identity_document_type']));
        $ddl = [
            'companies' => 'id INTEGER PRIMARY KEY, fiscal_environment TEXT, fiscal_emission_mode TEXT, fiscal_environment_locked INTEGER DEFAULT 0, number TEXT, created_at TEXT, updated_at TEXT',
            'establishments' => 'id INTEGER PRIMARY KEY',
            'user_default_document_types' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, document_type_id TEXT, series_id INTEGER',
            'configurations' => 'id INTEGER PRIMARY KEY, enable_dedicated_series INTEGER DEFAULT 1, is_nrus INTEGER DEFAULT 0',
            'series' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, establishment_id INTEGER, document_type_id TEXT, number TEXT, dedicated INTEGER DEFAULT 0, contingency INTEGER DEFAULT 0, in_use INTEGER DEFAULT 0, series_device_group_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(establishment_id, document_type_id, number)',
            'series_configurations' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, series_id INTEGER UNIQUE, document_type_id TEXT, series TEXT, number INTEGER, created_at TEXT, updated_at TEXT',
            'series_device_groups' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, establishment_id INTEGER, name TEXT, module_value TEXT, bound_device_name TEXT, bound_user_id INTEGER, bound_at TEXT, created_at TEXT, updated_at TEXT',
        ];
        foreach ($ddl as $table => $columns) $this->createTable($table, $columns);
        foreach (['documents', 'dispatches', 'sale_notes', 'guides', 'inventories_transfer'] as $table) {
            $this->createTable($table, "id INTEGER PRIMARY KEY AUTOINCREMENT, fiscal_environment TEXT, fiscal_emission_mode TEXT, establishment_id INTEGER, document_type_id TEXT, series TEXT, number INTEGER, control_number TEXT, created_at TEXT, updated_at TEXT, UNIQUE(establishment_id, fiscal_environment, document_type_id, series, number)");
        }
        $this->db->table('companies')->insert(['id' => 1, 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital', 'number' => 'J-00000000-0']);
        $this->db->table('establishments')->insert([['id' => 1], ['id' => 2]]);
        $this->db->table('configurations')->insert(['id' => 1]);
    }

    protected function configureConnections(Manager $manager): void
    {
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'tenant');
    }

    protected function createTable(string $table, string $columns): void
    {
        $this->db->statement("CREATE TABLE $table ($columns)");
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver); else Model::unsetConnectionResolver();
        if ($this->previousDispatcher) Model::setEventDispatcher($this->previousDispatcher); else Model::unsetEventDispatcher();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }
}
