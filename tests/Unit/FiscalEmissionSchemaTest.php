<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
class FiscalEmissionSchemaTest extends TestCase
{
    private $previousContainer;
    private $previousFacade;
    private $capsule;
    private $database;
    private $previousResolver;

    protected function setUp(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Active PRO9_FISCAL_MYSQL_TESTS=1 para usar una base MySQL temporal aislada.');
        }
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $root = dirname(__DIR__, 2);
        $app = new Application($root);
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $app->instance('request', \Illuminate\Http\Request::create('http://fiscal-schema.example.test'));
        $guard = $this->createMock(\Illuminate\Contracts\Auth\Guard::class);
        $guard->method('check')->willReturn(false);
        $auth = $this->createMock(\Illuminate\Contracts\Auth\Factory::class);
        $auth->method('guard')->willReturn($guard);
        $app->instance('auth', $auth);
        if (!class_exists('DB')) {
            class_alias(\Illuminate\Support\Facades\DB::class, 'DB');
        }
        $app->instance('config', new Repository([
            'fiscal_emission' => require $root . '/config/fiscal_emission.php',
            'venezuela' => require $root . '/config/venezuela.php',
        ]));
        $environment = is_file($root . '/.env') ? \Dotenv\Dotenv::parse(file_get_contents($root . '/.env')) : [];
        $connection = [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: ($environment['DB_HOST'] ?? '127.0.0.1'),
            'port' => getenv('DB_PORT') ?: ($environment['DB_PORT'] ?? 3306),
            'username' => $environment['DB_USERNAME'] ?? 'root',
            'password' => $environment['DB_PASSWORD'] ?? '',
            'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ];
        $this->capsule = new Manager($app);
        $this->capsule->addConnection($connection, 'control');
        $this->database = 'pro9_fiscal_test_' . bin2hex(random_bytes(6));
        $this->capsule->getConnection('control')->statement('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection['database'] = $this->database;
        $this->capsule->addConnection($connection, 'tenant');
        $this->capsule->addConnection($connection, 'system');
        $this->capsule->getDatabaseManager()->setDefaultConnection('tenant');
        $app->instance('db', $this->capsule->getDatabaseManager());
        $app->instance('cache', new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore()));
        $app->bind('db.schema', fn () => $this->capsule->getConnection()->getSchemaBuilder());
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName', 'systemName'])->getMock();
        $tenancy->method('tenantName')->willReturn('tenant');
        $tenancy->method('systemName')->willReturn('system');
        $app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        $this->capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        if ($this->database && preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/', $this->database)) {
            $this->capsule->getConnection('control')->statement('DROP DATABASE `' . $this->database . '`');
        }
        if ($this->previousContainer) {
            if ($this->previousResolver) {
                \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->previousResolver);
            } else {
                \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
            }
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->previousFacade);
            Container::setInstance($this->previousContainer);
        }
        parent::tearDown();
    }

    private function migrations(string $pattern): array
    {
        $paths = glob(database_path($pattern));
        sort($paths, SORT_STRING);
        return array_map(function ($path) {
            $migration = require $path;
            if (!is_object($migration)) {
                self::assertSame(1, preg_match('/class\s+(\w+)\s+extends\s+\w+/', file_get_contents($path), $class), $path);
                $migration = new $class[1]();
            }
            return $migration;
        }, $paths);
    }

    public function test_fresh_tenant_migrations_seed_rollback_and_repeat(): void
    {
        $db = $this->capsule->getConnection('tenant');
        $migrations = $this->migrations('migrations/tenant/*.php');
        $snapshot = null;
        $seedSnapshot = null;
        for ($cycle = 0; $cycle < 2; $cycle++) {
            foreach ($migrations as $migration) {
                $migration->up();
            }
            (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer(Container::getInstance())->run();
            $this->assertFiscalSchema($db);
            $current = $this->schemaSnapshot($db);
            if (getenv('PRO9_EXPORT_CONTRACT') === '1') {
                file_put_contents('/tmp/pro9-fresh-final-schema.json', json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            $this->assertForeignKeyIntegrity($db);
            $currentSeeds = $this->seedSnapshot($db);
            if ($snapshot !== null) {
                self::assertSame($snapshot, $current, 'El segundo ciclo debe producir el mismo esquema.');
                self::assertSame($seedSnapshot, $currentSeeds, 'El segundo ciclo debe producir los mismos datos iniciales.');
            }
            $snapshot = $current;
            $seedSnapshot = $currentSeeds;
            $this->assertProductAndVariationPersistence($db);
            $db->table('companies')->insert([
                'id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789',
                'name' => 'Test fiscal', 'trade_name' => 'Test fiscal',
                'fiscal_environment' => 'production', 'fiscal_emission_mode' => 'digital',
            ]);
            self::assertSame(0, (int) $db->table('companies')->value('fiscal_environment_locked'));
            $db->table('fiscal_configuration_audits')->insert([
                'company_id' => 1, 'actor_type' => 'system', 'actor_id' => 1,
                'changed_fields' => '["fiscal_emission_mode"]',
                'fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'production', 'created_at' => '2026-09-10 00:00:00',
            ]);
            self::assertSame(1, $db->table('fiscal_configuration_audits')->count());
            foreach (array_reverse($migrations) as $migration) {
                $migration->down();
            }
            self::assertSame([], $db->select('SHOW TABLES'), 'Rollback completo sin tablas residuales.');
        }
    }

    private function assertProductAndVariationPersistence(Connection $db): void
    {
        $db->beginTransaction();
        try {
            $establishmentId = $db->table('establishments')->insertGetId([
                'description' => 'Establecimiento de prueba', 'country_id' => 'VE',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'address' => 'Dirección de prueba', 'telephone' => '04121234567', 'code' => '0000',
            ]);
            $warehouseId = $db->table('warehouses')->insertGetId([
                'establishment_id' => $establishmentId, 'description' => 'Almacén de prueba',
            ]);
            $service = \App\Models\Tenant\Item::where('internal_id', 'DELIVERY-ECOM')->firstOrFail();
            $newService = $service->replicate();
            $newService->internal_id = 'TEST-SERVICE';
            $newService->description = 'Servicio de prueba';
            $newService->save();
            $parent = $service->replicate();
            $parent->fill([
                'internal_id' => 'TEST-PARENT', 'description' => 'Producto de prueba',
                'unit_type_id' => 'NIU', 'sale_unit_price' => 116,
                'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
            ]);
            $parent->save();
            $variation = $parent->replicate();
            $variation->internal_id = 'TEST-VARIATION';
            $parent->variations()->save($variation);

            $variable = \Modules\Item\Models\ProductVariable::create([
                'name' => 'Talla prueba', 'value_type' => 'list', 'active' => true,
            ]);
            $value = $variable->values()->create(['value' => 'M', 'position' => 1, 'active' => true]);
            $assignment = \Modules\Item\Models\ItemVariationValue::create([
                'item_id' => $variation->id, 'product_variable_id' => $variable->id,
                'product_variable_value_id' => $value->id,
            ]);

            $warehouse = \App\Models\Tenant\ItemWarehouse::create([
                'item_id' => $variation->id, 'warehouse_id' => $warehouseId, 'stock' => 5,
            ]);
            $warehouse->addStock(-2)->save();
            $loaded = \App\Models\Tenant\Item::withCount('variations')->findOrFail($parent->id);
            self::assertSame(1, $loaded->variations_count);
            self::assertSame($parent->id, $variation->parent->id);
            self::assertSame($value->id, $assignment->value->id);
            self::assertSame($variable->id, $assignment->variable->id);
            self::assertEquals(3, $variation->fresh()->warehouses->first()->stock);
            self::assertEquals(116, $loaded->sale_unit_price);
            self::assertSame('10', $loaded->sale_affectation_igv_type_id);
            self::assertSame('ZZ', $service->unit_type_id);
            self::assertSame('ZZ', $newService->fresh()->unit_type_id);
        } finally {
            $db->rollBack();
        }
        self::assertFalse($db->table('items')->whereIn('internal_id', ['TEST-PARENT', 'TEST-VARIATION', 'TEST-SERVICE'])->exists());
    }

    public function test_fresh_system_migrations_do_not_create_fiscal_transport_configuration(): void
    {
        $this->capsule->getDatabaseManager()->setDefaultConnection('system');
        foreach ($this->migrations('migrations/*.php') as $migration) {
            $migration->up();
        }
        $db = $this->capsule->getConnection('system');
        self::assertTrue($db->getSchemaBuilder()->hasTable('configurations'));
        $this->assertNoTransportColumns($db);
        self::assertFalse($db->getSchemaBuilder()->hasColumn('configurations', 'fiscal_environment'));
        foreach (['xml_link', 'cdr_link', 'estado_sunat', 'mensaje_sunat'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('massive_invoices', $column), $column);
        }
        foreach (['estado_emision', 'mensaje_emision'] as $column) {
            self::assertTrue($db->getSchemaBuilder()->hasColumn('massive_invoices', $column), $column);
        }
        self::assertSame(0, $db->table('module_levels')->whereIn('value', ['dispatch_carrier', 'carrier_dispatches'])->count());
        self::assertSame(1, $db->table('module_levels')->where('value', 'dispatches')->count());
    }

    private function assertFiscalSchema(Connection $db): void
    {
        self::assertSame(['demo', 'production'], $db->table('fiscal_environments')->orderBy('id')->pluck('id')->all());
        $this->assertNoTransportColumns($db);
        foreach (['companies', 'documents', 'fiscal_configuration_audits'] as $table) {
            $column = $db->selectOne('SELECT IS_NULLABLE AS nullable, CHARACTER_MAXIMUM_LENGTH AS length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, $table, 'fiscal_emission_mode']);
            self::assertSame('NO', $column->nullable, $table);
            self::assertSame(32, (int) $column->length, $table);
        }
        foreach (['fiscal_configuration', 'fiscal_credentials'] as $name) {
            $column = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'companies', $name]);
            self::assertSame('YES', $column->nullable);
            self::assertSame('text', $column->type);
        }
        self::assertNotNull($db->selectOne('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?', [$this->database, 'fiscal_configuration_audits', 'company_id', 'companies']));
        foreach (['digital_certificate_qztray', 'private_certificate_qztray'] as $column) {
            self::assertTrue($db->getSchemaBuilder()->hasColumn('companies', $column));
        }
        self::assertSame(0, $db->table('format_templates')->where('formats', 'legend_amazonia')->count());
        self::assertSame(0, $db->table('system_activity_log_types')->where('id', 'like', 'companies_soap_%')->count());
    }

    private function assertNoTransportColumns(Connection $db): void
    {
        self::assertFalse($db->getSchemaBuilder()->hasTable('soap_types'));
        foreach (['has_advanced_statuses', 'legend_footer', 'legend_forest_to_xml', 'default_document_type_03', 'name_product_pdf_to_xml', 'send_auto', 'sunat_alternate_server', 'auto_send_dispatchs_to_sunat'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('configurations', $column), $column);
        }
        self::assertFalse($db->getSchemaBuilder()->hasColumn('companies', 'operation_amazonia'));
        foreach (['sire_client_id', 'sire_client_secret', 'sire_username', 'sire_password'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('companies', $column), $column);
        }
        self::assertFalse($db->getSchemaBuilder()->hasColumn('document_items', 'name_product_xml'));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('configuration_mi_tienda_pe', 'series_document_bt_id'));
        foreach ([
            'documents' => ['hash', 'qr', 'has_xml', 'has_cdr', 'send_server', 'shipping_status', 'sunat_shipping_status', 'query_status', 'success_shipping_status', 'success_sunat_shipping_status', 'success_query_status', 'regularize_shipping', 'response_regularize_shipping'],
            'dispatches' => ['sunat_error_response', 'has_xml', 'has_cdr', 'ticket', 'reception_date'],
            'perceptions' => ['hash', 'has_xml', 'has_cdr'],
            'retentions' => ['hash', 'has_xml', 'has_cdr'],
            'purchase_settlements' => ['hash', 'has_xml', 'has_cdr'],
            'voided' => ['ticket', 'has_ticket', 'has_cdr'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                self::assertFalse($db->getSchemaBuilder()->hasColumn($table, $column), "{$table}.{$column}");
            }
        }
        if ($db->getSchemaBuilder()->hasTable('module_levels')) {
            self::assertSame(0, $db->table('module_levels')->whereIn('value', ['document_not_sent', 'regularize_shipping'])->count());
        }
        self::assertSame([], $db->select("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND (COLUMN_NAME LIKE 'soap_%' OR COLUMN_NAME IN ('certificate', 'certificate_due', 'send_to_pse', 'response_signature_pse', 'response_send_cdr_pse', 'config_system_env'))", [$this->database]));
    }

    // ######## INICIO INTEGRIDAD DE INSTALACIÓN NUEVA ########
    private function assertForeignKeyIntegrity(Connection $db): void
    {
        $keys = $db->select('SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION', [$this->database]);
        foreach (collect($keys)->groupBy(fn ($key) => $key->TABLE_NAME . '.' . $key->CONSTRAINT_NAME) as $name => $columns) {
            $first = $columns->first();
            $query = $db->table($first->TABLE_NAME . ' as child')->leftJoin($first->REFERENCED_TABLE_NAME . ' as parent', function ($join) use ($columns) {
                foreach ($columns as $column) {
                    $join->on('child.' . $column->COLUMN_NAME, '=', 'parent.' . $column->REFERENCED_COLUMN_NAME);
                }
            });
            foreach ($columns as $column) {
                $query->whereNotNull('child.' . $column->COLUMN_NAME);
            }
            self::assertSame(0, $query->whereNull('parent.' . $first->REFERENCED_COLUMN_NAME)->count(), $name);
        }
    }
    // ######## FIN INTEGRIDAD DE INSTALACIÓN NUEVA ########

    private function schemaSnapshot(Connection $db): array
    {
        $snapshot = [];
        foreach ($db->select('SHOW TABLES') as $row) {
            $table = array_values((array) $row)[0];
            $snapshot[$table] = array_values((array) $db->selectOne('SHOW CREATE TABLE `' . $table . '`'))[1];
        }
        ksort($snapshot);
        return $snapshot;
    }

    private function seedSnapshot(Connection $db): array
    {
        $snapshot = [];
        foreach ($db->select('SHOW TABLES') as $tableRow) {
            $table = array_values((array) $tableRow)[0];
            $rows = [];
            foreach ($db->table($table)->get() as $row) {
                $values = (array) $row;
                // Only generated audit timestamps may differ between clean seeding cycles.
                foreach (['created_at', 'updated_at'] as $column) {
                    if (isset($values[$column])) {
                        $values[$column] = '<generated timestamp>';
                    }
                }
                $rows[] = json_encode($values);
            }
            sort($rows, SORT_STRING);
            $snapshot[$table] = $rows;
        }
        ksort($snapshot);
        return $snapshot;
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
