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
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'),
            $app
        ));
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
        $app->make('validator')->setPresenceVerifier(
            new \Illuminate\Validation\DatabasePresenceVerifier($this->capsule->getDatabaseManager())
        );
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
            foreach (['fiscal_sequences', 'fiscal_profiles', 'fiscal_control_lots', 'fiscal_number_reservations', 'fiscal_emission_attempts', 'fiscal_numbering_audits', 'fiscal_demo_receipts', 'fiscal_hka_assignments'] as $retired) {
                self::assertFalse($db->getSchemaBuilder()->hasTable($retired), $retired);
            }
            foreach (['documents', 'dispatches'] as $table) {
                self::assertTrue($db->getSchemaBuilder()->hasColumn($table, 'control_number'));
            }
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            $methods = $db->table('payment_method_types')->orderBy('id')->get(['id', 'hka_code', 'is_active']);
            self::assertCount(25, $methods);
            self::assertSame(19, $methods->pluck('hka_code')->unique()->count());
            self::assertSame(7, $methods->where('hka_code', '99')->count());
            self::assertSame(9, $methods->where('is_active', 1)->count());
            self::assertSame(0, $methods->where('id', '14')->first()->is_active);
            self::assertSame(0, $methods->where('id', '26')->first()->is_active);
            self::assertSame(9, \App\Models\Tenant\PaymentMethodType::getPaymentMethodTypes()->count());
            $paymentRules = (new \App\Http\Requests\Tenant\DocumentPaymentRequest())->rules();
            $validator = Container::getInstance()->make('validator');
            $paymentInput = [
                'date_of_payment' => '2026-09-13', 'payment_method_type_id' => '14',
                'payment_destination_id' => 'cash', 'payment' => 10,
            ];
            self::assertTrue($validator->make(array_replace($paymentInput, ['payment_method_type_id' => '01']), $paymentRules)->passes());
            self::assertTrue($validator->make($paymentInput, $paymentRules)->fails());
            \App\Models\Tenant\PaymentMethodType::assertActiveForPayment('01');
            try {
                \App\Models\Tenant\PaymentMethodType::assertActiveForPayment('14');
                self::fail('Un método HKA inactivo no puede registrarse como pago.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertArrayHasKey('payment_method_type_id', $exception->errors());
            }
            $column = $db->select("SHOW COLUMNS FROM `payment_method_types` LIKE 'hka_code'");
            self::assertSame('char(2)', $column[0]->Type);
            self::assertSame('NO', $column[0]->Null);
            $hkaIndex = $db->select("SHOW INDEX FROM `payment_method_types` WHERE Key_name = 'payment_method_types_hka_code_index'");
            self::assertCount(1, $hkaIndex);
            self::assertSame(1, (int) $hkaIndex[0]->Non_unique);
            $customMethod = \Modules\Sale\Http\Requests\PaymentMethodTypeRequest::create('/', 'POST', [
                'id' => '27', 'description' => 'Método local de prueba', 'is_active' => 1,
            ]);
            app(\Modules\Sale\Http\Controllers\PaymentMethodTypeController::class)->store($customMethod);
            self::assertSame('99', $db->table('payment_method_types')->where('id', '27')->value('hka_code'));
            $db->table('payment_method_types')->where('id', '27')->delete();
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO AFECTACIÓN IVA
            self::assertSame([
                ['A', 'Alícuota Adicional (suntuario)', '31.00', 'IVA'],
                ['E', 'Exento, Exonerado o No Gravado', '0.00', 'IVA'],
                ['G', 'Alícuota General', '16.00', 'IVA'],
                ['IGTF', 'Impuesto a las Grandes Transacciones Financieras', '3.00', 'IGTF'],
                ['P', 'Percibido', '0.00', 'IVA'],
                ['R', 'Alícuota Reducida', '8.00', 'IVA'],
            ], $db->table('cat_iva_rate_types')->orderBy('id')->get(['id', 'description', 'percentage', 'tax_kind'])
                ->map(static fn ($row): array => [$row->id, $row->description, number_format((float) $row->percentage, 2, '.', ''), $row->tax_kind])->all());
            self::assertCount(1, $db->select("SHOW INDEX FROM `cat_iva_rate_types` WHERE Key_name = 'PRIMARY'"));
            // ######### FIN CAMBIO AFECTACIÓN IVA
            self::assertTrue($db->getSchemaBuilder()->hasColumn('cat_identity_document_types', 'external_document_examples'));
            self::assertSame(9, $db->table('cat_identity_document_types')->count());
            self::assertSame(8, $db->table('cat_identity_document_types')->where('active', 1)->count());
            self::assertSame(1, $db->table('cat_identity_document_types')->whereNotNull('external_document_examples')->count());
            $nondomiciled = $db->table('cat_identity_document_types')->where('id', 'ND')->first(['active', 'description', 'external_document_examples']);
            self::assertSame(0, (int) $nondomiciled->active);
            self::assertSame('No Domiciliado', $nondomiciled->description);
            self::assertSame('RUT, NIT', $nondomiciled->external_document_examples);
            self::assertTrue($db->getSchemaBuilder()->hasColumn('cat_operation_types', 'incoterm'));
            self::assertSame([
                ['0101', 'Venta interna', 1, 0, null],
                ['0200', 'Exportación de Bienes', 0, 1, null],
                ['0201', 'Exportación FOB', 0, 1, 'FOB'],
                ['0202', 'Exportación CIF', 0, 1, 'CIF'],
                ['0203', 'Exportación EXW', 0, 1, 'EXW'],
            ], $db->table('cat_operation_types')->orderBy('id')->get(['id', 'description', 'active', 'exportation', 'incoterm'])
                ->map(static fn ($row): array => [$row->id, $row->description, (int) $row->active, (int) $row->exportation, $row->incoterm])->all());
            self::assertSame(['0101'], $db->table('cat_operation_types')->where('active', 1)->pluck('id')->all());
            $concepts = $db->table('cat_retention_concept')->orderBy('id')->get(['id', 'percentage_label']);
            self::assertCount(86, $concepts);
            self::assertSame('001', $concepts[0]->id);
            self::assertSame('Variable', $concepts[0]->percentage_label);
            self::assertSame('086', $concepts[85]->id);
            self::assertStringContainsString('22 %', $concepts[4]->percentage_label);
            self::assertSame([
                ['01', 'Dividendo en acciones', 'DA'],
                ['02', 'Dividendo en efectivo', 'DE'],
                ['03', 'Venta de acciones', 'VA'],
            ], $db->table('cat_retention_types')->orderBy('id')->get(['id', 'description', 'abbreviation'])
                ->map(static fn ($row): array => [$row->id, $row->description, $row->abbreviation])->all());
            self::assertFalse($db->getSchemaBuilder()->hasColumn('cat_retention_types', 'percentage'));
            self::assertCount(1, $db->select("SHOW INDEX FROM `cat_retention_concept` WHERE Key_name = 'PRIMARY'"));
            self::assertCount(1, $db->select("SHOW INDEX FROM `cat_retention_types` WHERE Key_name = 'PRIMARY'"));
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            $referenceCatalogs = [
                'cat_product_origins' => [
                    [1, 'Nacional', 1],
                    [2, 'Importado', 1],
                    [3, 'Nacional e Importado', 1],
                ],
                'cat_product_types' => [
                    [1, 'Alcohol', 1],
                    [2, 'Cigarrillos', 1],
                ],
                'cat_taxation_products' => [
                    [1, 'Tierra Firme', 1],
                    [2, 'Régimen Especial', 1],
                ],
            ];
            foreach ($referenceCatalogs as $table => $expectedRows) {
                $rows = $db->table($table)->orderBy('id')->get(['id', 'description', 'active'])
                    ->map(static fn ($row): array => [(int) $row->id, $row->description, (int) $row->active])
                    ->all();
                self::assertSame($expectedRows, $rows, $table);
                $columns = collect($db->select("SHOW COLUMNS FROM `{$table}`"))
                    ->map(static fn ($column): array => [
                        $column->Field,
                        preg_replace('/^tinyint\(3\) unsigned$/', 'tinyint unsigned', $column->Type),
                        $column->Null,
                        $column->Key,
                    ])
                    ->all();
                self::assertSame([
                    ['id', 'tinyint unsigned', 'NO', 'PRI'],
                    ['description', 'varchar(255)', 'NO', ''],
                    ['active', 'tinyint(1)', 'NO', ''],
                ], $columns, $table);
                $primaryKey = $db->select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'");
                self::assertCount(1, $primaryKey, $table);
                self::assertSame('id', $primaryKey[0]->Column_name, $table);
            }

            $providerRows = $db->table('cat_providers_types')->orderBy('id')->get(['id', 'code', 'description', 'active'])
                ->map(static fn ($row): array => [(int) $row->id, $row->code, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                [1, null, 'Normal', 1],
                [2, 'SR', 'Sin RIF', 1],
                [3, 'NR', 'No Residenciado', 1],
                [4, 'ND', 'No Domiciliado', 1],
            ], $providerRows);
            $providerPrimaryKey = $db->select("SHOW INDEX FROM `cat_providers_types` WHERE Key_name = 'PRIMARY'");
            self::assertCount(1, $providerPrimaryKey);
            self::assertSame('id', $providerPrimaryKey[0]->Column_name);
            $providerCodeIndex = $db->select("SHOW INDEX FROM `cat_providers_types` WHERE Key_name = 'cat_providers_types_code_unique'");
            self::assertCount(1, $providerCodeIndex);
            self::assertSame('code', $providerCodeIndex[0]->Column_name);
            self::assertSame(0, (int) $providerCodeIndex[0]->Non_unique);

            $regimeRows = $db->table('cat_special_tax_regime')->orderBy('id')->get(['id', 'description', 'active'])
                ->map(static fn ($row): array => [(int) $row->id, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                [1, 'Zonas económicas especiales', 1],
                [2, 'Zona franca de Paraguaná', 1],
                [3, 'Zona libre de Paraguaná', 1],
                [4, 'Puerto libre Santa Elena de Uairén', 1],
                [5, 'Zona Libre de Mérida', 1],
                [6, 'Puerto libre Estado Nueva Esparta', 1],
                [7, 'Dutty Free', 1],
            ], $regimeRows);
            $regimePrimaryKey = $db->select("SHOW INDEX FROM `cat_special_tax_regime` WHERE Key_name = 'PRIMARY'");
            self::assertCount(1, $regimePrimaryKey);
            self::assertSame('id', $regimePrimaryKey[0]->Column_name);

            $transactionRows = $db->table('cat_transactions_types')->orderBy('id')->get(['id', 'description', 'active'])
                ->map(static fn ($row): array => [(string) $row->id, $row->description, (int) $row->active])
                ->all();
            self::assertSame([
                ['01', 'Registro', 1],
                ['02', 'Complemento', 1],
                ['03', 'Anulación', 1],
                ['04', 'Ajuste', 1],
                ['98', 'ND por IGTF', 1],
                ['99', 'Solo cuando la factura es a Terceros', 1],
            ], $transactionRows);
            $primaryKey = $db->select("SHOW INDEX FROM `cat_transactions_types` WHERE Key_name = 'PRIMARY'");
            self::assertCount(1, $primaryKey);
            self::assertSame('id', $primaryKey[0]->Column_name);

            // ########## INICIO CAMBIO CATÁLOGO MOTIVOS DE TRASLADO VENEZUELA
            self::assertSame([
                ['04', 'Traslado entre almacenes propios', 1, 0],
                ['21', 'Reparación o perfeccionamiento', 1, 0],
                ['22', 'Almacenes, depósitos o bodegas de otros', 1, 0],
                ['23', 'Tránsito aduanero', 1, 0],
                ['24', 'Otras causas (especifique)', 1, 0],
            ], $db->table('cat_transfer_reason_types')->orderBy('id')->get(['id', 'description', 'active', 'discount_stock'])
                ->map(static fn ($row): array => [$row->id, $row->description, (int) $row->active, (int) $row->discount_stock])->all());
            $transferReasonColumn = $db->select("SHOW COLUMNS FROM `cat_transfer_reason_types` LIKE 'id'");
            self::assertSame('varchar(2)', $transferReasonColumn[0]->Type);
            self::assertSame('PRI', $transferReasonColumn[0]->Key);
            self::assertSame('NO', $transferReasonColumn[0]->Null);
            self::assertSame('varchar(2)', $db->select("SHOW COLUMNS FROM `dispatches` LIKE 'transfer_reason_type_id'")[0]->Type);
            $discountStockColumn = $db->select("SHOW COLUMNS FROM `dispatches` LIKE 'discount_stock'")[0];
            self::assertSame('tinyint(1)', $discountStockColumn->Type);
            self::assertSame('0', $discountStockColumn->Default);
            // ########## INICIO RETIRO TRASLADO M1/L1 ##########
            self::assertSame([], $db->select("SHOW COLUMNS FROM `dispatches` LIKE 'is_transport_m1l'"));
            self::assertSame([], $db->select("SHOW COLUMNS FROM `dispatches` LIKE 'license_plate_m1l'"));
            // ######### FIN RETIRO TRASLADO M1/L1 #########
            // ########## INICIO RETIRO SEMIRREMOLQUE ##########
            self::assertSame([], $db->select("SHOW COLUMNS FROM `dispatches` LIKE 'secondary_license_plates'"));
            // ######### FIN RETIRO SEMIRREMOLQUE #########
            self::assertSame('varchar(2)', $db->select("SHOW COLUMNS FROM `order_forms` LIKE 'transfer_reason_type_id'")[0]->Type);
            // ######### FIN CAMBIO CATÁLOGO MOTIVOS DE TRASLADO VENEZUELA
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
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
            $this->assertTransferReasonPersistence($db);
            if ($cycle === 0) $this->assertLegacyDocumentPersistence($db);
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

    private function assertLegacyDocumentPersistence(Connection $db): void
    {
        $previousAuth = app('auth');
        $previousDispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
        $db->beginTransaction();
        try {
            $app = Container::getInstance();
            (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer($app)->run();
            $db = $this->capsule->getConnection('tenant');
            $app->instance('files', new \Illuminate\Filesystem\Filesystem());
            $db->table('companies')->insert(['id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789', 'name' => 'Test fiscal', 'trade_name' => 'Test fiscal', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
            $db->table('configurations')->insert(['id' => 1, 'quantity_documents' => 0, 'quantity_sales_notes' => 0]);
            $establishment = $db->table('establishments')->insertGetId(['description' => 'Test fiscal', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0000']);
            $warehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $establishment, 'description' => 'Test warehouse']);
            $userId = $db->table('users')->insertGetId(['name' => 'Test operator', 'email' => 'fiscal@example.test', 'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishment]);
            $user = \App\Models\Tenant\User::findOrFail($userId);
            $app->instance('auth', new class($user) {
                private $user;
                public function __construct($user) { $this->user = $user; }
                public function user() { return $this->user; }
                public function id() { return $this->user->id; }
                public function check() { return true; }
                public function guard($name = null) { return $this; }
            });
            $item = \App\Models\Tenant\Item::where('internal_id', 'MOCK-ITEM-VES-001')->firstOrFail();
            $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $warehouse, 'stock' => 10]);
            $customer = \App\Models\Tenant\Person::where('number', 'MOCK-CLIENTE-VE')->firstOrFail();
            foreach (['01' => 'FF01', '07' => 'FC01', '08' => 'FD01', '09' => 'TT01', '80' => 'NV01', 'U2' => 'AI01', 'U3' => 'AS01', 'U4' => 'AT01'] as $type => $code) {
                $seriesId = $db->table('series')->insertGetId(['establishment_id' => $establishment, 'document_type_id' => $type, 'number' => $code]);
                $db->table('series_configurations')->insert(['series_id' => $seriesId, 'document_type_id' => $type, 'series' => $code, 'number' => 100]);
            }
            $input = [
                'type' => 'invoice', 'user_id' => $userId, 'external_id' => \Illuminate\Support\Str::uuid()->toString(),
                'establishment_id' => $establishment, 'establishment' => (array) $db->table('establishments')->find($establishment),
                'state_type_id' => '01', 'ubl_version' => '2.1', 'group_id' => '01', 'document_type_id' => '01',
                'series' => 'FF01', 'number' => '#', 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00',
                'customer_id' => $customer->id, 'customer' => $customer->toArray(), 'currency_type_id' => 'VES',
                'exchange_rate_sale' => 1, 'total_taxed' => 200, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232, 'additional_information' => '',
                'payments' => [['date_of_payment' => '2026-09-10', 'payment_method_type_id' => '01', 'payment' => 232, 'reference' => 'Test payment']],
                'fee' => [], 'hotel' => null, 'transport' => null, 'invoice' => ['date_of_due' => '2026-09-10', 'operation_type_id' => '0101'],
                'items' => [[
                    'item_id' => $item->id, 'item' => array_replace($item->toArray(), ['is_set' => false]), 'quantity' => 2, 'warehouse_id' => $warehouse,
                    'unit_value' => 100, 'unit_price' => 116, 'price_type_id' => '01', 'affectation_igv_type_id' => '10', 'additional_information' => '',
                    'total_base_igv' => 200, 'percentage_igv' => 16, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232,
                ]],
            ];
            \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
            \App\Models\Tenant\Document::observe(\App\Observers\DocumentObserver::class);
            (new \Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
            (new \Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
            $first = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
            self::assertSame('FF01-100', $first->number_full);
            $input['external_id'] = \Illuminate\Support\Str::uuid()->toString();
            $second = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
            self::assertSame('FF01-101', $second->number_full);
            self::assertSame(2, $db->table('document_payments')->count());
            self::assertEquals(464, $db->table('document_payments')->sum('payment'));
            self::assertEquals(6, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
            foreach (['07' => ['credit', 'FC01'], '08' => ['debit', 'FD01']] as $type => [$kind, $code]) {
                $note = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'document_type_id' => $type, 'type' => $kind, 'series' => $code,
                    'note' => ['affected_document_id' => $first->id, 'note_type_id' => '01', 'note_description' => 'Nota de prueba']]);
                $document = (new \App\CoreFacturalo\Facturalo())->save($note)->getDocument();
                self::assertSame($code . '-100', $document->number_full);
                self::assertSame($first->id, $document->note->affected_document_id);
            }
            $order = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'type' => 'dispatch', 'document_type_id' => '09', 'series' => 'TT01', 'id' => null,
                'date_of_shipping' => '2026-09-10', 'transshipment_indicator' => false, 'unit_type_id' => 'KG', 'total_weight' => 1, 'discount_stock' => true]);
            $dispatch = (new \App\CoreFacturalo\Facturalo())->save($order)->getDocument();
            self::assertSame('TT01-100', $dispatch->number_full);
            self::assertNull($dispatch->fiscal_identity['control_number']);
            self::assertNull($first->fiscal_identity['control_number']);
            $first->setAttribute('control_number', '00-00000021');
            $first->save();
            self::assertSame('00-00000021', \App\Services\Fiscal\FiscalPdfData::forDocument($first)['control_number']);
            self::assertSame('FF01-100', $first->number_full);
            $db->table('cash')->insert(['user_id' => $userId, 'date_opening' => '2026-09-10', 'time_opening' => '12:00:00', 'state' => true]);
            // Exercise the commercial NV input path, not a fake counter subject.
            $saleInput = array_replace($input, ['id' => null, 'series' => 'NV01', 'document_type_id' => '80', 'prefix' => 'NV']);
            $saleController = new \App\Http\Controllers\Tenant\SaleNoteController();
            $saleNote = \App\Models\Tenant\SaleNote::create($saleController->mergeData($saleInput));
            self::assertSame('NV01-100', $saleNote->number_full);
            foreach ($input['items'] as $row) $saleNote->items()->create($row);
            $saleNext = \App\Models\Tenant\SaleNote::create($saleController->mergeData($saleInput));
            self::assertSame('NV01-101', $saleNext->number_full);
            $stock = $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock');
            foreach (['sale_note_id' => $saleNote->id, 'dispatch_id' => $dispatch->id] as $source => $sourceId) {
                $convertedInput = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), $source => $sourceId]);
                $converted = (new \App\CoreFacturalo\Facturalo())->save($convertedInput)->getDocument();
                self::assertSame($sourceId, $converted->getAttribute($source));
                self::assertSame($source === 'sale_note_id' ? 102 : 103, $converted->number);
                self::assertNull($converted->fiscal_identity['control_number']);
                self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                if ($source === 'sale_note_id') {
                    $link = new \ReflectionMethod(\App\Http\Controllers\Tenant\DocumentController::class, 'associateSaleNoteToDocument');
                    $link->setAccessible(true);
                    $link->invoke(new \App\Http\Controllers\Tenant\DocumentController(), \Illuminate\Http\Request::create('/', 'POST', ['sale_note_id' => $sourceId]), $converted->id);
                    self::assertSame($converted->id, $saleNote->fresh()->document_id);
                }
            }
            \Modules\Inventory\Models\Guide::observe(\Modules\Inventory\Observers\GuideObserver::class);
            \Modules\Inventory\Models\InventoryTransfer::observe(\Modules\Inventory\Observers\InventoryTransferObserver::class);
            foreach (['U2' => 'AI01', 'U3' => 'AS01'] as $type => $code) {
                $transactionId = $db->table('inventory_transactions')->where('type', $type === 'U2' ? 'input' : 'output')->value('id');
                $guide = \Modules\Inventory\Models\Guide::create(['document_type_id' => $type, 'series' => $code, 'number' => '#',
                    'warehouse_id' => $warehouse, 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00', 'inventory_transaction_id' => $transactionId]);
                self::assertSame(100, $guide->number);
            }
            $transfer = \Modules\Inventory\Models\InventoryTransfer::create(['document_type_id' => 'U4', 'series' => 'AT01', 'number' => '#',
                'warehouse_id' => $warehouse, 'warehouse_destination_id' => $warehouse, 'quantity' => 1]);
            self::assertSame(100, $transfer->number);
        } finally {
            $db->rollBack();
            \Illuminate\Database\Eloquent\Model::setEventDispatcher($previousDispatcher);
            \Illuminate\Database\Eloquent\Model::clearBootedModels();
            app()->instance('auth', $previousAuth);
        }
    }

    private function assertTransferReasonPersistence(Connection $db): void
    {
        $db->beginTransaction();
        try {
            $establishmentId = $db->table('establishments')->insertGetId([
                'description' => 'Establecimiento motivos de traslado', 'country_id' => 'VE',
                'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619',
                'address' => 'Dirección de prueba', 'telephone' => '04121234567', 'code' => '0099',
            ]);
            $userId = $db->table('users')->insertGetId([
                'name' => 'Usuario motivos de traslado', 'email' => 'transfer-reasons@example.test',
                'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishmentId,
            ]);

            foreach (['04', '21', '22', '23', '24'] as $index => $reasonId) {
                $db->table('dispatches')->insert([
                    'user_id' => $userId,
                    'external_id' => sprintf('00000000-0000-0000-0000-%012d', $index + 1),
                    'establishment_id' => $establishmentId,
                    'establishment' => json_encode(['id' => $establishmentId, 'description' => 'Establecimiento motivos de traslado']),
                    'fiscal_environment' => 'demo',
                    'state_type_id' => '01',
                    'ubl_version' => '2.1',
                    'document_type_id' => '09',
                    'series' => 'OE',
                    'number' => $index + 1,
                    'date_of_issue' => '2026-09-13',
                    'time_of_issue' => '12:00:00',
                    'transport_mode_type_id' => '02',
                    'transfer_reason_type_id' => $reasonId,
                    'transfer_reason_description' => $reasonId === '24' ? 'Causa especial de prueba' : null,
                    'date_of_shipping' => '2026-09-13',
                    'transshipment_indicator' => 0,
                    'unit_type_id' => 'KG',
                    'total_weight' => 1,
                ]);
            }

            self::assertSame(
                ['04', '21', '22', '23', '24'],
                $db->table('dispatches')->orderBy('number')->pluck('transfer_reason_type_id')->all()
            );
            self::assertSame('Causa especial de prueba', $db->table('dispatches')->where('transfer_reason_type_id', '24')->value('transfer_reason_description'));
        } finally {
            $db->rollBack();
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
                'unit_type_id' => 'UND', 'sale_unit_price' => 116,
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
            self::assertSame('SERV', $service->unit_type_id);
            self::assertSame('SERV', $newService->fresh()->unit_type_id);
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
        // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
        self::assertSame([
            'BOL', 'BOT', 'BTO', 'CAJ', 'CM', 'DIA', 'DOC', 'GAL', 'GR', 'HR', 'JGO', 'KG',
            'KM', 'LB', 'LT', 'M', 'M2', 'M3', 'MG', 'ML', 'MM', 'PAR', 'PQT', 'PULG',
            'SAC', 'SERV', 'TON', 'UND',
        ], $db->table('cat_unit_types')->orderBy('id')->pluck('id')->all());
        self::assertSame(28, $db->table('cat_unit_types')->where('active', 1)->count());
        self::assertSame(28, $db->table('cat_unit_types')->whereColumn('id', 'symbol')->count());
        self::assertSame(0, $db->table('cat_unit_types')->whereNull('hka_code')->count());
        self::assertSame(28, $db->table('cat_unit_types')->distinct()->count('hka_code'));
        self::assertSame('XBG', $db->table('cat_unit_types')->where('id', 'BOL')->value('hka_code'));
        self::assertSame('XBE', $db->table('cat_unit_types')->where('id', 'BTO')->value('hka_code'));
        self::assertSame('PR', $db->table('cat_unit_types')->where('id', 'PAR')->value('hka_code'));
        $hkaColumn = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type, CHARACTER_MAXIMUM_LENGTH AS length, COLUMN_DEFAULT AS default_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'cat_unit_types', 'hka_code']);
        self::assertSame('NO', $hkaColumn->nullable);
        self::assertSame('varchar', $hkaColumn->type);
        self::assertSame(3, (int) $hkaColumn->length);
        self::assertNull($hkaColumn->default_value);
        self::assertSame(0, $db->table('cat_unit_types')->whereIn('id', ['NIU', 'ZZ'])->count());
        $unitController = new \App\Http\Controllers\Tenant\UnitTypeController();
        $unitRequest = \Illuminate\Http\Request::create('/unit_types/records', 'GET', ['active' => '1']);
        self::assertCount(28, $unitController->records($unitRequest)->toArray($unitRequest));
        self::assertSame('XBG', $unitController->record('BOL')->toArray($unitRequest)['hka_code']);
        self::assertFalse($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'UND', 'active' => false]))['success']);
        self::assertTrue($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'BOL', 'active' => false]))['success']);
        self::assertCount(27, $unitController->records($unitRequest)->toArray($unitRequest));
        self::assertSame('XBG', $unitController->record('BOL')->toArray($unitRequest)['hka_code']);
        self::assertTrue($unitController->active(\Illuminate\Http\Request::create('/unit_types/active', 'POST', ['id' => 'BOL', 'active' => true]))['success']);
        self::assertCount(28, $unitController->records($unitRequest)->toArray($unitRequest));
        // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
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
        $hkaAuthenticated = $db->selectOne('SELECT IS_NULLABLE AS nullable, DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$this->database, 'companies', 'hka_authenticated_at']);
        self::assertSame('YES', $hkaAuthenticated->nullable);
        self::assertSame('timestamp', $hkaAuthenticated->type);
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
