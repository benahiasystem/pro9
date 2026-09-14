<?php

namespace Tests\Support;

use App\Services\FiscalNumberingRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
abstract class FiscalDatabaseTestCase extends TestCase
{
    private $previousContainer;
    private $previousFacade;
    protected $db;
    protected FiscalNumberingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $app = new Container();
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $manager = new Manager($app);
        $this->configureConnections($manager);
        $this->db = $manager->getConnection();
        $app->instance('db', $manager->getDatabaseManager());
        $app->instance('db.schema', $this->db->getSchemaBuilder());
        $app->instance('validator', new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app));
        $app->instance('encrypter', new \Illuminate\Encryption\Encrypter(str_repeat('x', 32), 'AES-256-CBC'));
        $this->db->statement('CREATE TABLE companies (id INTEGER PRIMARY KEY, fiscal_environment TEXT, fiscal_environment_locked INTEGER DEFAULT 0)');
        $this->db->statement('CREATE TABLE establishments (id INTEGER PRIMARY KEY)');
        $this->db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, establishment_id INTEGER, name TEXT, type TEXT, active INTEGER)');
        $this->db->statement('CREATE TABLE series_device_groups (id INTEGER PRIMARY KEY, establishment_id INTEGER, name TEXT)');
        $this->db->table('series_device_groups')->insert([['id' => 1, 'establishment_id' => 1, 'name' => 'Caja 1'], ['id' => 2, 'establishment_id' => 2, 'name' => 'Caja 2']]);
        $this->db->table('companies')->insert(['id' => 1, 'fiscal_environment' => 'demo']);
        $this->db->table('establishments')->insert([['id' => 1], ['id' => 2]]);
        foreach (['337_create_fiscal_sequences', '338_create_fiscal_control_lots', '339_create_fiscal_number_reservations', '340_create_fiscal_profiles', '341_create_fiscal_numbering_audits', '342_create_fiscal_emission_attempts', '343_create_fiscal_demo_receipts'] as $migration) {
            (require dirname(__DIR__, 2) . '/database/migrations/tenant/2026_08_17_000' . $migration . '_table.php')->up();
        }
        $this->repository = new FiscalNumberingRepository($this->db);
    }

    protected function configureConnections(Manager $manager): void
    {
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
