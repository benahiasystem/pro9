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
        config(['filesystems.disks.tenant' => ['driver' => 'local', 'root' => sys_get_temp_dir().'/'.$this->database]]);
        $app->instance('filesystem', new \Illuminate\Filesystem\FilesystemManager($app));
        $this->capsule->addConnection($connection, 'tenant');
        $this->capsule->addConnection($connection, 'system');
        $this->capsule->getDatabaseManager()->setDefaultConnection('tenant');
        $this->capsule->getConnection('tenant')->setTransactionManager(new \Illuminate\Database\DatabaseTransactionsManager());
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
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory(sys_get_temp_dir().'/'.$this->database);
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
            unset($paymentRules['document_id']); // This assertion isolates payment-method eligibility.
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
            foreach (['retentions', 'perceptions', 'purchase_settlements'] as $table) {
                self::assertSame('varchar(20)', $db->select("SHOW COLUMNS FROM `$table` LIKE 'series'")[0]->Type);
            }
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
                'state_type_id' => '01', 'group_id' => '01', 'document_type_id' => '01',
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
            $order = array_replace($input, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'type' => 'dispatch', 'ubl_version' => '2.0', 'document_type_id' => '09', 'series' => 'TT01', 'id' => null,
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
            // Real commercial persistence with blank series in two branches.
            $secondBranch = $db->table('establishments')->insertGetId(['description' => 'Second branch', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0001']);
            $secondWarehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $secondBranch, 'description' => 'Second warehouse']);
            $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $secondWarehouse, 'stock' => 100]);
            $filenames = [];
            foreach ([$establishment => $warehouse, $secondBranch => $secondWarehouse] as $branch => $originWarehouse) {
                foreach (['01', '07', '08', '09', '80', 'U2', 'U3', 'U4'] as $type) {
                    $db->table('series')->insert(['establishment_id' => $branch, 'document_type_id' => $type, 'number' => '']);
                }
                $blankInput = array_replace($input, ['establishment_id' => $branch, 'series' => '', 'external_id' => \Illuminate\Support\Str::uuid()->toString()]);
                $blankInput['items'][0]['warehouse_id'] = $originWarehouse;
                $blankFirst = (new \App\CoreFacturalo\Facturalo())->save($blankInput)->getDocument();
                $blankInput['external_id'] = \Illuminate\Support\Str::uuid()->toString();
                $blankSecond = (new \App\CoreFacturalo\Facturalo())->save($blankInput)->getDocument();
                self::assertSame('1', $blankFirst->number_full);
                self::assertSame('2', $blankSecond->number_full);
                self::assertSame('', $blankFirst->series);
                self::assertNull($blankFirst->control_number);
                $filenames[] = $blankFirst->filename;
                foreach (['07' => 'credit', '08' => 'debit'] as $type => $kind) {
                    $blankNote = array_replace($blankInput, ['external_id' => \Illuminate\Support\Str::uuid()->toString(), 'document_type_id' => $type, 'type' => $kind,
                        'note' => ['affected_document_id' => $blankFirst->id, 'note_type_id' => '01', 'note_description' => 'Blank series note']]);
                    $savedNote = (new \App\CoreFacturalo\Facturalo())->save($blankNote)->getDocument();
                    self::assertSame('1', $savedNote->number_full);
                    self::assertSame($blankFirst->id, $savedNote->note->affected_document_id);
                }
                $blankOrder = array_replace($order, ['establishment_id' => $branch, 'series' => '', 'external_id' => \Illuminate\Support\Str::uuid()->toString(), 'discount_stock' => false]);
                $blankOrder['items'][0]['warehouse_id'] = $originWarehouse;
                self::assertSame('1', (new \App\CoreFacturalo\Facturalo())->save($blankOrder)->getDocument()->number_full);
                $blankSale = array_replace($saleInput, ['establishment_id' => $branch, 'series' => '']);
                self::assertSame('1', \App\Models\Tenant\SaleNote::create($saleController->mergeData($blankSale))->number_full);
                foreach (['U2', 'U3'] as $type) {
                    $transactionId = $db->table('inventory_transactions')->where('type', $type === 'U2' ? 'input' : 'output')->value('id');
                    $blankGuide = \Modules\Inventory\Models\Guide::create(['document_type_id' => $type, 'series' => '', 'number' => '#',
                        'warehouse_id' => $originWarehouse, 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00', 'inventory_transaction_id' => $transactionId]);
                    self::assertSame($branch, (int) $blankGuide->establishment_id);
                    self::assertSame('1', $blankGuide->number_full);
                }
                $blankTransfer = \Modules\Inventory\Models\InventoryTransfer::create(['document_type_id' => 'U4', 'series' => '', 'number' => '#',
                    'warehouse_id' => $originWarehouse, 'warehouse_destination_id' => $originWarehouse, 'quantity' => 1]);
                self::assertSame($branch, (int) $blankTransfer->establishment_id);
                self::assertSame('1', $blankTransfer->number_full);
            }
            self::assertCount(2, array_unique($filenames));
            $longCode = 'AB-CD123456789012345';
            $db->table('series')->insert(['establishment_id' => $establishment, 'document_type_id' => '01', 'number' => $longCode]);
            $longInput = array_replace($input, ['series' => $longCode, 'external_id' => \Illuminate\Support\Str::uuid()->toString()]);
            $longDocument = (new \App\CoreFacturalo\Facturalo())->save($longInput)->getDocument();
            self::assertSame($longCode.'-1', $longDocument->number_full);
            self::assertStringContainsString($longCode, $longDocument->filename);
            self::assertSame($longDocument->id, \App\Models\Tenant\Document::where('establishment_id', $establishment)->where('series', $longCode)->where('number', 1)->value('id'));
            $this->assertVenezuelaFiscalPersistence($db, $input);

        } finally {
            $db->rollBack();
            \Illuminate\Database\Eloquent\Model::setEventDispatcher($previousDispatcher);
            \Illuminate\Database\Eloquent\Model::clearBootedModels();
            app()->instance('auth', $previousAuth);
        }
    }

    // ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
    private function assertVenezuelaFiscalPersistence(Connection $db, array $input): void
    {
        $service = \App\Services\Fiscal\FiscalDocumentPersistence::class;
        $customer = \App\Models\Tenant\Person::findOrFail($input['customer_id']);
        $customer->number = 'V-12345678'; $customer->save();
        $input['customer'] = $customer->toArray();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $input['payments'] = [];
        $document = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument();
        self::assertEquals(232, $document->balance);
        self::assertSame('Test fiscal', $document->issuer['name']);
        self::assertSame('G', $document->items->first()->iva_rate['code']);
        self::assertEquals(32, $document->taxes()->where('tax_kind','IVA')->sum('amount'));
        $document->guarantee_fund()->create(['amount'=>10,'base'=>200,'percentage'=>5]);
        $retention = ['tax_kind'=>'IVA','voucher_number'=>'TEST-IVA-1','voucher_date'=>'2026-09-10','agent_id'=>$customer->id,
            'base'=>32,'percentage'=>75,'amount'=>24,'currency_type_id'=>'VES','exchange_rate'=>1];
        $service::retention($document,$retention);
        $service::retention($document,array_replace($retention,['tax_kind'=>'ISLR','voucher_number'=>'TEST-ISLR-1',
            'concept_id'=>$db->table('cat_retention_concept')->value('id'),'base'=>200,'percentage'=>2,'amount'=>4]));
        self::assertEquals(194,$document->fresh()->balance);
        self::assertCount(2,$document->fresh()->received_retentions);
        $retainedEdit = array_replace($input, ['id' => $document->id, 'series' => $document->series, 'number' => $document->number, 'payments' => []]);
        $retentionBefore = $db->table('document_received_retentions')->where('document_id', $document->id)->get()->toArray();
        $fundBefore = $db->table('document_guarantee_funds')->where('document_id', $document->id)->get()->toArray();
        (new \App\CoreFacturalo\Facturalo)->update($retainedEdit, $document->id);
        self::assertEquals(194, $document->fresh()->balance);
        foreach (['customer' => ['customer_id' => $customer->id + 1], 'iva' => ['items' => [array_replace($input['items'][0], ['quantity' => 1])]]] as $changes) {
            try { (new \App\CoreFacturalo\Facturalo)->update(array_replace($retainedEdit, $changes), $document->id); self::fail('Existing retention must remain supported'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        self::assertEquals($retentionBefore, $db->table('document_received_retentions')->where('document_id', $document->id)->get()->toArray());
        self::assertEquals($fundBefore, $db->table('document_guarantee_funds')->where('document_id', $document->id)->get()->toArray());

        try { $service::retention($document,$retention);self::fail('Duplicate voucher accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('voucher_number',$e->errors()); }
        $company=\App\Models\Tenant\Company::firstOrFail();
        $company->forceFill(['igtf_enabled'=>true,'igtf_rate'=>3])->save();
        $key=(string)\Illuminate\Support\Str::uuid();
        $paymentInput=['date_of_payment'=>'2026-09-11','payment_method_type_id'=>'01','original_amount'=>5,
            'currency_type_id'=>'USD','exchange_rate'=>10,'igtf_status'=>'subject','operation_key'=>$key];
        $payment=$service::payment($document,$paymentInput);
        self::assertEquals(50,$payment->payment);self::assertEquals(0.15,$payment->tax_amount);
        self::assertEquals(5.15,$payment->cash_received_amount);
        $tax=\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->firstOrFail();
        $note=\App\Models\Tenant\Document::findOrFail($tax->document_id);
        self::assertSame('08',$note->document_type_id);self::assertSame('IGTF',$note->note->note_debit_type_id);
        self::assertSame('98',$note->fiscal_data->transaction_type_id);
        self::assertCount(0,$note->items);self::assertEquals(0,$note->total_igv);
        self::assertEquals(1.5,$note->total);self::assertEquals(0,$note->balance);
        self::assertEquals(0,$note->payments->first()->cash_received_amount);
        self::assertEquals(144,$document->fresh()->balance);
        self::assertSame($payment->id,$service::payment($document,$paymentInput)->id);
        self::assertSame(1,\App\Models\Tenant\DocumentTax::where('document_payment_id',$payment->id)->count());
        $service::reverse($payment,'Prueba de reversión');
        self::assertEquals(194,$document->fresh()->balance);
        self::assertNotNull($payment->fresh()->reversed_at);
        self::assertSame('11',$note->fresh()->state_type_id);
        $fx=array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),'currency_type_id'=>'USD',
            'exchange_rate_sale'=>10.123,'payments'=>[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>232,'igtf_status'=>'subject']]]);
        $paid=(new \App\CoreFacturalo\Facturalo())->save($fx)->getDocument();
        self::assertEquals(238.96,$paid->total);self::assertTrue((bool)$paid->total_canceled);self::assertEquals(238.96,$paid->payments->sum('payment'));self::assertEquals(0,$paid->balance);
        self::assertEquals(6.96,$paid->taxes()->where('tax_kind','IGTF')->sum('amount'));
        self::assertEquals(round(238.96*10.123,2),$paid->currency_totals->total);
        self::assertEquals(232,$paid->payments->whereNull('receipt_parent_id')->first()->payment);
        $company->forceFill(['igtf_enabled'=>false])->save();
        self::assertSame($paid->payments->whereNull('receipt_parent_id')->first()->id,$service::payment($paid,[
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>232,'igtf_status'=>'subject',
            'operation_key'=>$paid->payments->whereNull('receipt_parent_id')->first()->operation_key],true)->id);
        $company->forceFill(['igtf_enabled'=>true])->save();
        $discounted=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'discounts'=>[['discount_type_id'=>'02','amount'=>20,'factor'=>0.1]],
        ]))->getDocument();
        self::assertEquals(180,$discounted->total_taxed);self::assertEquals(28.8,$discounted->total_igv);self::assertEquals(208.8,$discounted->total);
        $edit=array_replace($input,['id'=>$discounted->id,'series'=>$discounted->series,'number'=>$discounted->number,'issuer'=>['name'=>'Untrusted issuer'],'currency_type_id'=>'VES','exchange_rate_sale'=>10.1234]);
        $edit['items'][0]['quantity']=3;
        (new \App\CoreFacturalo\Facturalo())->update($edit,$discounted->id);
        self::assertEquals(324.8,$discounted->fresh()->total);
        self::assertEquals(20,$discounted->fresh()->total_discount);
        self::assertSame('10.12340000',$discounted->fresh()->exchange_rate_sale);
        self::assertEquals(324.8,$discounted->fresh()->currency_totals->total);
        self::assertSame('Test fiscal',$discounted->fresh()->issuer['name']);
        self::assertSame($discounted->external_id,$discounted->fresh()->external_id);

        $withChange=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'payments'=>[['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','payment'=>250]],
        ]))->getDocument();
        self::assertEquals(0,$withChange->balance);self::assertEquals(18,$withChange->payments->first()->change);
        self::assertEquals(232,$withChange->payments->first()->cash_received_amount);
        $other=(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,[
            'external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'taxes'=>[['code'=>'TEST_OTHER','base'=>200,'percentage'=>2]],
        ]))->getDocument();
        self::assertEquals(4,$other->taxes()->where('tax_kind','OTI')->sum('amount'));self::assertEquals(236,$other->total);
        $prepare=new \App\Services\Fiscal\HkaEmissionPreparation();
        $prepared=$prepare->prepare($paid);
        self::assertSame('prepared',$prepared->status);
        self::assertSame('01',$prepared->payload['documentoElectronico']['encabezado']['identificacionDocumento']['tipoDocumento']);
        $payload=$prepared->payload;
        $company->name='Changed company';$company->save();
        $customer->name='Changed customer';$customer->save();
        $db->table('cat_iva_rate_types')->where('id','G')->update(['percentage'=>17]);
        self::assertSame($payload,$prepare->prepare($paid->fresh())->payload);
        self::assertSame('Test fiscal',$paid->fresh()->issuer['name']);
        self::assertNotSame('Changed customer',$paid->fresh()->customer->name);
        self::assertEquals(16,$paid->items->first()->percentage_igv);
        try {$service::assertMutable($paid);self::fail('Prepared document editable');}
        catch (\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('document',$e->errors()); }
        try {$prepare->setControl($paid,'00-21');self::fail('Duplicate control accepted');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('control_number',$e->errors());}
        $prepare->setControl($paid,'00-22');self::assertSame('00-00000022',$paid->fresh()->control_number);
        $service::reverse($paid->payments->whereNull('receipt_parent_id')->first(),'Reversión del cobro inicial');
        self::assertEquals(238.96,$paid->fresh()->balance);self::assertFalse((bool)$paid->fresh()->total_canceled);
        self::assertEquals(6.96,$paid->taxes()->where('tax_kind','IGTF')->sum('amount'));
        self::assertSame($payload,$prepare->prepare($paid->fresh())->payload);
        // Whole persistence rolls back when a post-collection validation fails.
        $count=$db->table('documents')->count();
        try {(new \App\CoreFacturalo\Facturalo())->save(array_replace($input,['external_id'=>(string)\Illuminate\Support\Str::uuid(),
            'received_retentions'=>[array_replace($retention,['amount'=>999])]]));self::fail('Invalid retention persisted');}
        catch (\Illuminate\Validation\ValidationException $e) { self::assertSame($count,$db->table('documents')->count()); }
        foreach (['total','total_igv','total_discount'] as $column) {
            self::assertSame('decimal(12,2)',$db->selectOne('SHOW COLUMNS FROM documents WHERE Field = ?',[$column])->Type);
        }
        self::assertSame('decimal(18,8)',$db->selectOne("SHOW COLUMNS FROM documents WHERE Field = 'exchange_rate_sale'")->Type);
        foreach (['ubl_version','perception','total_unaffected','total_free','total_igv_free','retention','user_rel_subscription_plan_id'] as $column) {
            self::assertFalse($db->getSchemaBuilder()->hasColumn('documents',$column));
        }
    }
    // ######## FIN PERSISTENCIA FISCAL VENEZUELA ########

    public function test_concurrent_payments_igtf_and_retention_applications_are_serialized(): void
    {
        $db=$this->capsule->getConnection('tenant');
        foreach ($this->migrations('migrations/tenant/*.php') as $migration) $migration->up();
        (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer(Container::getInstance())->run();
        $db->table('companies')->insert(['id'=>1,'identity_document_type_id'=>'6','number'=>'J-12345678-9','name'=>'Fiscal concurrency',
            'fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital','igtf_enabled'=>1,'igtf_rate'=>3]);
        $db->table('configurations')->insert(['id'=>1,'quantity_documents'=>0,'quantity_sales_notes'=>0]);
        $db->table('establishments')->insert(['id'=>1,'description'=>'Concurrent','country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Test','telephone'=>'04121234567','code'=>'0000']);
        $db->table('users')->insert(['id'=>1,'name'=>'Admin','email'=>'concurrency@example.test','password'=>'not-a-login-hash','type'=>'admin','establishment_id'=>1]);
        $customer=$db->table('persons')->where('number','MOCK-CLIENTE-VE')->first();
        $db->table('persons')->where('id',$customer->id)->update(['identity_document_type_id'=>'6','number'=>'J-23456789-0']);
        $customer=$db->table('persons')->find($customer->id);
        $db->table('series')->insert(['establishment_id'=>1,'document_type_id'=>'08','number'=>'FD01']);
        $id=$db->table('documents')->insertGetId(['user_id'=>1,'external_id'=>(string)\Illuminate\Support\Str::uuid(),'establishment_id'=>1,
            'establishment'=>json_encode(['address'=>'Test','code'=>'0000']),'issuer'=>json_encode(['name'=>'Fiscal concurrency','number'=>'J-12345678-9']),
            'fiscal_environment'=>'demo','fiscal_emission_mode'=>'digital','group_id'=>'01','state_type_id'=>'01','document_type_id'=>'01','series'=>'FF01','number'=>1,
            'date_of_issue'=>'2026-09-10','time_of_issue'=>'12:00:00','customer_id'=>$customer->id,'customer'=>json_encode((array)$customer),
            'currency_type_id'=>'VES','exchange_rate_sale'=>1,'total_value'=>100,'total'=>100]);
        $key=(string)\Illuminate\Support\Str::uuid();
        $payment=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','currency_type_id'=>'USD','exchange_rate'=>10,
            'original_amount'=>8,'igtf_status'=>'subject','operation_key'=>$key];
        $result=$this->runFiscalWorkers($id,'payment',[$payment,$payment]);
        self::assertArrayHasKey('id',$result[0],json_encode($result));
        self::assertArrayHasKey('id',$result[1],json_encode($result));
        self::assertSame($result[0]['id'],$result[1]['id']);
        self::assertSame(1,$db->table('document_payments')->where('document_id',$id)->whereNull('receipt_parent_id')->count());
        self::assertSame(1,$db->table('document_taxes')->whereNotNull('document_payment_id')->count());
        self::assertSame(1,$db->table('documents')->where('document_type_id','08')->count());
        $p1=array_replace($payment,['operation_key'=>(string)\Illuminate\Support\Str::uuid(),'original_amount'=>1.5]);
        $p2=array_replace($p1,['operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
        $result=$this->runFiscalWorkers($id,'payment',[$p1,$p2]);
        self::assertCount(1,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertEquals(95,$db->table('document_payments')->where('document_id',$id)->sum('payment'));
        self::assertSame(2,$db->table('documents')->where('document_type_id','08')->count());
        $retention=['tax_kind'=>'ISLR','voucher_number'=>'CONCURRENT-1','voucher_date'=>'2026-09-10','agent_id'=>$customer->id,
            'concept_id'=>'001','base'=>100,'percentage'=>4,'amount'=>4,'currency_type_id'=>'VES','exchange_rate'=>1];
        $result=$this->runFiscalWorkers($id,'retention',[$retention,array_replace($retention,['voucher_number'=>'CONCURRENT-2'])]);
        self::assertCount(1,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertEquals(4,$db->table('document_received_retentions')->where('document_id',$id)->sum('applied_amount'));
        $retention=array_replace($retention,['voucher_number'=>'CONCURRENT-DUPLICATE','percentage'=>1,'amount'=>1]);
        $result=$this->runFiscalWorkers($id,'retention',[$retention,$retention]);
        self::assertCount(1,array_filter($result,fn ($r)=>isset($r['id'])));
        self::assertSame(2,$db->table('document_received_retentions')->where('document_id',$id)->count());
    }

    private function runFiscalWorkers(int $documentId,string $operation,array $data): array
    {
        $db=$this->capsule->getConnection('tenant');$processes=[];
        $db->beginTransaction();
        try {
            $db->table('companies')->where('id',1)->lockForUpdate()->first();
            foreach ($data as $input) {
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/fiscal_payment_concurrency_worker.php']);
                $p->setInput(json_encode(['connection'=>$db->getConfig(),'document_id'=>$documentId,'operation'=>$operation,'data'=>$input],JSON_THROW_ON_ERROR));
                $p->setTimeout(30);$p->start();$processes[]=$p;
            }
            $deadline=microtime(true)+10;
            foreach ($processes as $p) {
                while (!str_contains($p->getOutput(),"READY\n")) {
                    if (!$p->isRunning() || microtime(true)>$deadline) self::fail('Fiscal worker did not reach the lock barrier: '.$p->getErrorOutput());
                    usleep(10000);
                }
                self::assertTrue($p->isRunning());
            }
            $db->commit();$results=[];
            foreach ($processes as $p) {
                self::assertSame(0,$p->wait(),$p->getErrorOutput().$p->getOutput());
                $results[]=json_decode(substr($p->getOutput(),strlen("READY\n")),true,512,JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $p) if ($p->isRunning()) $p->stop();
        }
    }


    public function test_hka_emission_runs_after_outer_commit_and_recovers_without_duplicate_sales(): void
    {
        $db = $this->capsule->getConnection('tenant');
        foreach ($this->migrations('migrations/tenant/*.php') as $migration) $migration->up();
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
            'state_type_id' => '01', 'group_id' => '01', 'document_type_id' => '01',
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
        $app->instance('encrypter', new \Illuminate\Encryption\Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
        config(['app.key' => str_repeat('k', 32)]);
        $company = \App\Models\Tenant\Company::firstOrFail();
        $company->fiscal_credentials = json_encode(['usuario' => 'test-user', 'clave' => 'test-secret']);
        $company->save();
        $http = new \Illuminate\Http\Client\Factory();
        $app->instance(\Illuminate\Http\Client\Factory::class, $http);
        $customer->forceFill(['email' => 'receiver@example.test', 'telephone' => '04121234567'])->save();
        $input['customer'] = $customer->toArray();
        $input['payment_condition_id'] = '01';
        $mutations = 0; $queries = 0; $mode = 'success'; $active = null;
        $http->fake(function ($request) use ($db, &$mutations, &$queries, &$mode, &$active) {
            self::assertSame(0, $db->transactionLevel(), 'HTTP must occur after the outer commit and outside locks');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response([
                'token' => 'fake-jwt-private', 'expiracion' => date('c', time() + 3600)], 200);
            if (str_ends_with($request->url(), '/EstadoDocumento')) {
                $queries++;
                if (in_array($mode, ['absent', 'resend'], true)) return \Illuminate\Support\Facades\Http::response(['codigo' => '203', 'mensaje' => 'Consulta no procesada',
                    'validaciones' => ['Documento no encontrado en nuestra base de datos'], 'estado' => null], 200);
                if ($mode === 'query-failure') return \Illuminate\Support\Facades\Http::response([], 500);
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'estado' => $active + ['estadoDocumento' => 'Procesado',
                    'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]], 200);
            }
            $mutations++;
            $active = $request['documentoElectronico']['encabezado']['identificacionDocumento'];
            if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('fake-jwt-private test-secret');
            if ($mode === 'resend') {
                $inFlight = \App\Models\Tenant\Document::whereHas('emission', fn ($q) => $q->whereRaw("REPLACE(operation_key, '-', '') = ?", [$active['transaccionId']]))->firstOrFail();
                self::assertSame('pending', app(\App\Services\Fiscal\HkaEmission::class)->send($inFlight)['status']);
            }
            return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'resultado' => $active + [
                'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]], 200);
        });
        $service = app(\App\Services\Fiscal\HkaEmission::class);
        $db->beginTransaction();
        $fact = (new \App\CoreFacturalo\Facturalo())->save($input);
        self::assertSame(0, $mutations);
        self::assertSame('not_requested', $fact->getDocument()->emission->status);
        $db->commit();
        $document = $fact->getDocument()->fresh();
        $sentHeader = $document->emission->payload['documentoElectronico']['encabezado'];
        self::assertSame('Contado', $sentHeader['identificacionDocumento']['tipoDePago']);
        self::assertSame(['receiver@example.test'], $sentHeader['comprador']['correo']);
        self::assertSame(['04121234567'], $sentHeader['comprador']['telefono']);
        self::assertSame('No', $sentHeader['comprador']['notificar']);
        self::assertSame('confirmed', $document->emission->status);
        self::assertTrue($fact->getResponse()['sale_saved']);
        self::assertSame('00-00000100', $document->control_number);
        self::assertFalse($document->is_editable);
        $store = new \App\Services\Fiscal\HkaPdfStore();
        self::assertNotNull($store->read($document, 'a4'));
        self::assertNull($store->read($document, 'a5'));
        self::assertSame(1, $mutations);
        $router = new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($app), $app);
        $router->get('downloads/{model}/{type}/{external_id}/{format?}', fn () => null)->name('tenant.download.external_id');
        $router->getRoutes()->refreshNameLookups();
        $app->instance('url', new \Illuminate\Routing\UrlGenerator($router->getRoutes(), app('request')));
        // Exercise Laravel's resource wrapping, not only the DTO helper.
        $listed = (new \App\Http\Resources\Tenant\DocumentCollection(collect([$document])))->resolve();
        self::assertSame('confirmed', $listed[0]['fiscal_emission']['status']);
        self::assertSame($document->control_number, $listed[0]['fiscal_emission']['control_number']);
        self::assertTrue($listed[0]['pdf_downloads']['a4']['available']);
        self::assertSame('hka', $listed[0]['pdf_downloads']['a5']['provider']);
        self::assertStringNotContainsString('test-secret', json_encode($listed));
        $counts = [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()];
        $key = $document->emission->operation_key;
        $service->send($document); $service->send($document);
        self::assertSame(1, $mutations);
        self::assertSame($key, $document->fresh()->emission->operation_key);
        $mode = 'query-failure'; $service->query($document);
        self::assertSame('confirmed', $document->fresh()->emission->status);
        self::assertSame($counts, [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()]);

        $this->assertHkaMailDelivery($document, $db, $app, $company, $service);

        $db->beginTransaction();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        (new \App\CoreFacturalo\Facturalo())->save($input);
        $db->rollBack();
        self::assertSame(1, $mutations);
        self::assertSame($counts[0], $db->table('documents')->count());

        $mode = 'timeout'; $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $uncertain = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('uncertain', $uncertain->emission->status);
        self::assertSame(2, $mutations);
        self::assertEquals(0, $uncertain->balance);
        self::assertStringNotContainsString('test-secret', json_encode($uncertain->emission->response));
        $key = $uncertain->emission->operation_key;
        $response = $uncertain->emission->response; $response['started_at'] = time() - 60;
        $uncertain->emission->update(['status' => 'pending', 'response' => $response]);
        $mode = 'absent';
        self::assertTrue($service->query($uncertain)['can_send']);
        self::assertSame('uncertain', $uncertain->fresh()->emission->status);
        $mode = 'resend';
        $result = $service->send($uncertain);
        self::assertSame('confirmed', $result['status']);
        self::assertSame(3, $mutations);
        self::assertSame($key, $uncertain->fresh()->emission->operation_key);
        self::assertSame($counts[0] + 1, $db->table('documents')->count());
        self::assertSame($counts[1] + 1, $db->table('document_payments')->count());

        $revision = \App\Services\Fiscal\DocumentEmissionView::cacheNamespace();
        $uncertain->fresh()->emission->update(['response' => ['diagnostic' => 'test']]);
        self::assertNotSame($revision, \App\Services\Fiscal\DocumentEmissionView::cacheNamespace());
        $company->fiscal_credentials = null; $company->save();
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $notSent = (new \App\CoreFacturalo\Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('not_requested', $notSent->emission->status);
        self::assertSame(3, $mutations);
        self::assertStringContainsString('credenciales', $notSent->emission->response['diagnostic']);
        $this->assertPaidInvoiceEditing($notSent, $input, $db, $app);
        $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
        $race = (new \App\CoreFacturalo\Facturalo)->save($input)->getDocument()->fresh();
        $company->fiscal_credentials = json_encode(['usuario' => 'test-user', 'clave' => 'test-secret']); $company->save();
        $log = tempnam(sys_get_temp_dir(), 'pro9_hka_claim_');
        try {
            $results = $this->runFiscalWorkers($notSent->id, 'hka', [['log' => $log], ['log' => $log]]);
            self::assertSame("emission\n", file_get_contents($log));
            self::assertSame('confirmed', $notSent->fresh()->emission->status);
            foreach ($results as $result) self::assertContains($result['status'], ['pending', 'confirmed']);
        } finally { unlink($log); }
        $log = tempnam(sys_get_temp_dir(), 'pro9_hka_edit_race_');
        try {
            $edit = array_replace($input, ['id' => $race->id, 'series' => $race->series, 'number' => $race->number, 'payments' => []]);
            $payments = $db->table('document_payments')->where('document_id', $race->id)->get()->toArray();
            $results = $this->runFiscalWorkers($race->id, 'hka-edit-race', [['action' => 'edit', 'edit' => $edit], ['action' => 'send', 'log' => $log]]);
            $status = $race->fresh()->emission->status;
            self::assertContains($status, ['confirmed', 'not_requested']);
            self::assertSame($status === 'confirmed' ? "emission\n" : '', file_get_contents($log));
            self::assertTrue(($results[0]['status'] ?? null) === 'edited' || isset($results[0]['rejected']));
            self::assertSame($status, $results[1]['status']);
            self::assertEquals($payments, $db->table('document_payments')->where('document_id', $race->id)->get()->toArray());
        } finally { unlink($log); }
        $tenantKey = \App\Services\Fiscal\DocumentEmissionView::versionKey();
        $db->setDatabaseName('another_tenant');
        self::assertNotSame($tenantKey, \App\Services\Fiscal\DocumentEmissionView::versionKey());
        $db->setDatabaseName($this->database);
        $this->assertHkaAutomaticMail($input, $db, $app);
    }

    private function assertHkaAutomaticMail(array $input, $db, $app): void
    {
        $originalHttp = app(\Illuminate\Http\Client\Factory::class);
        $http = new \Illuminate\Http\Client\Factory();
        \Illuminate\Support\Facades\Http::swap($http);
        $http->preventStrayRequests();
        $mode = 'success'; $mailMode = 'success'; $pdfMode = 'success'; $sends = 0; $emissions = 0; $active = null; $mailFacts = [];
        $http->fake(function ($request) use ($db, &$mode, &$mailMode, &$pdfMode, &$sends, &$emissions, &$active, &$mailFacts) {
            self::assertSame(0, $db->transactionLevel(), 'Automatic email cannot run inside the sale transaction');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                if ($pdfMode === 'failure') return \Illuminate\Support\Facades\Http::response(['codigo' => '203']);
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response([
                'token' => 'automatic-private-token', 'expiracion' => date('c', time() + 3600)]);
            if (str_ends_with($request->url(), '/Correo/Rastreo')) return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'rastreos' => []]);
            if (str_ends_with($request->url(), '/Correo/Enviar')) {
                $sends++;
                $document = \App\Models\Tenant\Document::where('series', $request['serie'])->where('number', $request['numeroDocumento'])->firstOrFail();
                $mailFacts[] = [$document->emission->status, $document->control_number, $document->emission->control_number,
                    $request['correos'], $request['tipoDocumento']];
                if ($mailMode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('automatic-private-token');
                return \Illuminate\Support\Facades\Http::response(['codigo' => $mailMode === 'rejected' ? '203' : '200']);
            }
            if (str_ends_with($request->url(), '/Emision')) {
                $emissions++;
                $active = $request['documentoElectronico']['encabezado']['identificacionDocumento'];
                if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('automatic-private-token');
            }
            return \Illuminate\Support\Facades\Http::response(['codigo' => '200',
                str_ends_with($request->url(), '/EstadoDocumento') ? 'estado' : 'resultado' => $active + [
                    'estadoDocumento' => 'Procesado', 'numeroControl' => '00-'.str_pad($active['numeroDocumento'], 8, '0', STR_PAD_LEFT)]]);
        });
        $input['customer']['email'] = ' CUSTOMER@example.test ; second@example.test,customer@example.test ';
        $originalEmail = $db->table('persons')->where('id', $input['customer_id'])->value('email');
        $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $input['customer']['email']]);
        $input['actions'] = ['send_email' => false];
        $save = function () use (&$input) {
            $input['external_id'] = (string) \Illuminate\Support\Str::uuid();
            return (new \App\CoreFacturalo\Facturalo())->save($input);
        };
        try {
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => true]);
            $db->beginTransaction();
            $fact = $save();
            self::assertSame(0, $emissions); self::assertSame(0, $sends);
            self::assertSame('waiting', $fact->getDocument()->fresh()->emission->response['mail']['automatic']['status']);
            $db->commit();
            $document = $fact->getDocument()->fresh();
            self::assertSame(1, $sends);
            self::assertSame('accepted', \App\Services\Fiscal\HkaMail::view($document)['status']);
            self::assertSame(['confirmed', $document->control_number, $document->control_number,
                ['customer@example.test', 'second@example.test'], '01'], $mailFacts[0]);
            $state = [$document->control_number, $document->total, $document->balance,
                $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()];
            app(\App\Services\Fiscal\HkaEmission::class)->query($document);
            app(\App\Services\Fiscal\HkaMail::class)->sendAutomatic($document);
            self::assertSame(1, $sends);
            $fresh = $document->fresh();
            self::assertSame($state, [$fresh->control_number, $fresh->total, $fresh->balance,
                $db->table('document_payments')->count(), $db->table('inventory_kardex')->count()]);
            $recipients = ['customer@example.test', 'second@example.test'];
            self::assertSame('accepted', \App\Services\Fiscal\HkaMail::automaticDelivery($fresh, $recipients)['status']);
            self::assertNull(\App\Services\Fiscal\HkaMail::automaticDelivery($fresh, ['other@example.test']));
            $request = \App\Http\Requests\Tenant\DocumentEmailRequest::create('/documents/email', 'POST', [
                'id' => $document->id, 'customer_email' => implode(',', $recipients), 'recipients' => $recipients]);
            self::assertTrue((new \App\Http\Controllers\Tenant\DocumentController)->email($request)['success']);
            self::assertSame(1, $sends, 'Existing frontend bundles must not send a second email');

            $db->beginTransaction(); $save(); $db->rollBack();
            self::assertSame(1, $sends); self::assertSame(1, $emissions);
            $mode = 'timeout';
            $pending = $save()->getDocument()->fresh();
            self::assertSame('uncertain', $pending->emission->status); self::assertSame(1, $sends);
            $mode = 'success';
            app(\App\Services\Fiscal\HkaEmission::class)->query($pending);
            self::assertSame(2, $sends, 'A later confirmation must send the saved automatic request');
            app(\App\Services\Fiscal\HkaEmission::class)->query($pending);
            self::assertSame(2, $sends);

            $mailMode = 'timeout';
            $uncertain = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $uncertain->emission->status);
            self::assertSame('uncertain', \App\Services\Fiscal\HkaMail::view($uncertain)['status']);
            app(\App\Services\Fiscal\HkaEmission::class)->query($uncertain);
            self::assertSame(3, $sends, 'An uncertain email cannot be automatically retried');
            $mailMode = 'rejected';
            $rejected = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $rejected->emission->status);
            self::assertSame('rejected', \App\Services\Fiscal\HkaMail::view($rejected)['status']);
            app(\App\Services\Fiscal\HkaEmission::class)->query($rejected);
            self::assertSame(4, $sends);

            $mailMode = 'success';
            foreach ([null, '', 'invalid-email'] as $email) {
                $input['customer']['email'] = $email;
                $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $email]);
                $missing = $save()->getDocument()->fresh();
                self::assertSame('confirmed', $missing->emission->status);
                self::assertSame('skipped', $missing->emission->response['mail']['automatic']['status']);
                self::assertStringContainsString('correo válido', \App\Services\Fiscal\HkaMail::view($missing)['message']);
            }
            self::assertSame(4, $sends);
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => false]);
            $input['customer']['email'] = 'customer@example.test;second@example.test';
            $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $input['customer']['email']]);
            $disabled = $save()->getDocument()->fresh();
            self::assertArrayNotHasKey('mail', $disabled->emission->response);
            self::assertSame(4, $sends);
            $input['actions']['send_email'] = true;
            $explicit = $save(); $explicit->sendEmail();
            self::assertSame(5, $sends, 'Explicit API email uses HKA without an SMTP duplicate');
            self::assertStringNotContainsString('automatic-private-token', json_encode(\App\Services\Fiscal\HkaMail::view($explicit->getDocument()->fresh())));
            $emission = $explicit->getDocument()->fresh()->emission;
            $response = $emission->response;
            $response['mail'] = ['automatic' => ['request_id' => (string) \Illuminate\Support\Str::uuid(),
                'recipients' => $recipients, 'status' => 'waiting']];
            $emission->update(['response' => $response]);
            $log = tempnam(sys_get_temp_dir(), 'pro9_hka_auto_mail_');
            try {
                $results = $this->runFiscalWorkers($emission->document_id, 'hka-auto-mail', [['log' => $log], ['log' => $log]]);
                self::assertSame("mail\n", file_get_contents($log));
                foreach ($results as $result) self::assertContains($result['status'], ['pending', 'accepted']);
            } finally { unlink($log); }
            $pdfMode = 'failure';
            $input['actions']['send_email'] = false;
            $pdfPending = $save()->getDocument()->fresh();
            self::assertSame('confirmed', $pdfPending->emission->status, 'PDF failures preserve fiscal confirmation');
            self::assertNotEmpty($pdfPending->control_number);
            $store = app(\App\Services\Fiscal\HkaPdfStore::class);
            self::assertNull($store->read($pdfPending, 'a4'));
            $emittedBeforeRetry = $emissions;
            $paymentsBeforeRetry = $db->table('document_payments')->count();
            $pdfMode = 'success';
            app(\App\Services\Fiscal\HkaPdf::class)->download($pdfPending, 'a4');
            self::assertNotNull($store->read($pdfPending, 'a4'));
            self::assertSame($emittedBeforeRetry, $emissions, 'Downloading a missing PDF never re-emits');
            self::assertSame($paymentsBeforeRetry, $db->table('document_payments')->count());
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            $db->table('configurations')->where('id', 1)->update(['auto_send_pdf_email' => false]);
            $db->table('persons')->where('id', $input['customer_id'])->update(['email' => $originalEmail]);
            \Illuminate\Support\Facades\Http::swap($originalHttp);
        }
    }

    private function assertPaidInvoiceEditing($document, array $input, $db, $app): void
    {
        $edit = array_replace($input, ['id' => $document->id, 'series' => $document->series, 'number' => $document->number,
            'payments' => [], 'received_retentions' => [], 'guarantee_fund' => null]);
        $paymentSnapshot = $db->table('document_payments')->where('document_id', $document->id)->get()->toArray();
        $identity = [$document->external_id, $document->series, $document->number, $document->issuer];
        $stock = $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock');
        $db->table('persons')->where('id', $document->customer_id)->update(['address' => 'Dirección actualizada en catálogo']);
        (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
        $fresh = $document->fresh();
        self::assertSame('Dirección actualizada en catálogo', $fresh->customer->address);
        self::assertSame('V', $fresh->customer->hka_identity_code);
        self::assertSame($identity, [$fresh->external_id, $fresh->series, $fresh->number, $fresh->issuer]);
        self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock'));
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        self::assertEquals(0, $fresh->balance);
        foreach (['currency' => ['currency_type_id' => 'USD'], 'number' => ['number' => $document->number + 1],
            'payment' => ['payments' => [['id' => $fresh->payments->first()->id, 'payment' => 1]]],
            'new-payment' => ['payments' => [['payment' => 1]]],
            'total' => ['items' => [array_replace($edit['items'][0], ['quantity' => 1])]]] as $case => $changes) {
            try { (new \App\CoreFacturalo\Facturalo)->update(array_replace($edit, $changes), $document->id); self::fail('Expected rejection: '.$case); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        foreach (['prepared', 'rejected', 'uncertain'] as $status) {
            $emission = $document->fresh()->emission;
            $uuid = $emission->operation_key;
            $emission->update(['status' => $status, 'payload' => ['old' => 'snapshot'], 'response' => ['retry_allowed' => $status === 'uncertain', 'diagnostic' => 'Diagnóstico saneado']]);
            (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
            $emission = $document->fresh()->emission;
            self::assertSame('not_requested', $emission->status);
            self::assertNotSame($uuid, $emission->operation_key);
            self::assertNull($emission->payload);
            $history = $emission->response['edit_history'];
            self::assertSame($uuid, end($history)['operation_key']);
            $emission->update(['status' => 'pending', 'response' => array_replace($emission->response, ['attempt_id' => 'new-attempt'])]);
            $finish = new \ReflectionMethod(\App\Services\Fiscal\HkaEmission::class, 'finish');
            $finish->setAccessible(true);
            $finish->invoke(app(\App\Services\Fiscal\HkaEmission::class), $document, 'old-attempt', ['status' => 'confirmed', 'control_number' => '00-99999999']);
            self::assertSame('pending', $document->fresh()->emission->status);
            self::assertNull($document->fresh()->control_number);
            $emission->update(['status' => 'not_requested']);
            $beforeLateFailure = $emission->fresh()->response;
            $failure = new \ReflectionMethod(\App\Services\Fiscal\HkaEmission::class, 'failure');
            $failure->setAccessible(true);
            $failure->invoke(app(\App\Services\Fiscal\HkaEmission::class), $document, null, new \RuntimeException('old-auth-failure'), $uuid);
            self::assertSame($beforeLateFailure, $emission->fresh()->response);
            try { (new \App\Services\Fiscal\HkaEmissionPreparation)->prepare($document, $uuid); self::fail('Stale preparation accepted'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        foreach (['pending', 'uncertain', 'confirmed', 'cancelled'] as $status) {
            $document->fresh()->emission->update(['status' => $status, 'response' => ['retry_allowed' => false]]);
            try { (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id); self::fail('Expected fiscal block: '.$status); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertNotEmpty($expected->errors()); }
        }
        $document->fresh()->emission->update(['status' => 'not_requested', 'response' => []]);
        $increase = $edit; $increase['items'][0]['unit_price'] = 120;
        (new \App\CoreFacturalo\Facturalo)->update($increase, $document->id);
        self::assertEquals(240, $document->fresh()->total);
        self::assertEquals(8, $document->fresh()->balance);
        // Edits must never send HTTP even when a digital invoice is eligible.
        self::assertEquals($paymentSnapshot, $db->table('document_payments')->where('document_id', $document->id)->get()->toArray());
        (new \App\CoreFacturalo\Facturalo)->update($edit, $document->id);
        self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $input['items'][0]['item_id'])->value('stock'));
    }

    private function assertHkaMailDelivery($document, $db, $app, $company, $emissionService): void
    {
        $originalHttp = app(\Illuminate\Http\Client\Factory::class);
        $http = new \Illuminate\Http\Client\Factory();
        \Illuminate\Support\Facades\Http::swap($http);
        $mail = app(\App\Services\Fiscal\HkaMail::class);
        $mode = 'success'; $sends = 0; $tracking = []; $inFlight = null;
        $recipients = ['mail-test@example.test'];
        $http->preventStrayRequests();
        $http->fake(function ($request) use ($db, &$mode, &$sends, &$tracking, &$inFlight, $mail, $document, $recipients) {
            self::assertSame(0, $db->transactionLevel(), 'Mail HTTP must be outside all database locks');
            if (str_ends_with($request->url(), '/DescargaArchivo')) {
                self::assertSame(0, $db->transactionLevel());
                $pdf = new \FPDF(); $pdf->AddPage(); $pdf->SetFont('Helvetica', '', 12); $pdf->Text(10, 15, 'HKA confirmed test');
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'archivo' => base64_encode($pdf->Output('S'))]);
            }
            if (str_ends_with($request->url(), '/Autenticacion')) return \Illuminate\Support\Facades\Http::response(
                $mode === 'auth-failure' ? [] : ['token' => 'private-mail-token', 'expiracion' => date('c', time() + 3600)]);
            if (str_ends_with($request->url(), '/Correo/Rastreo')) return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'rastreos' => $tracking]);
            if (str_ends_with($request->url(), '/EstadoDocumento')) {
                $identity = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
                return \Illuminate\Support\Facades\Http::response(['codigo' => '200', 'estado' => $identity + ['estadoDocumento' => 'Enviada', 'numeroControl' => $document->control_number]]);
            }
            self::assertTrue(str_ends_with($request->url(), '/Correo/Enviar'));
            self::assertSame(['serie' => $document->series, 'tipoDocumento' => '01', 'numeroDocumento' => (string) $document->number, 'correos' => $recipients], $request->data());
            $sends++;
            if ($inFlight) {
                self::assertSame('pending', $mail->send($document, $recipients, $inFlight)['status']);
                self::assertSame('pending', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            }
            if ($mode === 'timeout') throw new \Illuminate\Http\Client\ConnectionException('private-mail-token test-secret');
            return \Illuminate\Support\Facades\Http::response($mode === 'rejected' ? ['codigo' => '203', 'mensaje' => 'private-mail-token'] : ['codigo' => '200']);
        });
        try {
            $operator = auth()->user();
            $oldType = $operator->type; $oldEstablishment = $operator->establishment_id;
            $operator->type = 'seller'; $operator->establishment_id = $document->establishment_id + 100;
            $callsBefore = $http->recorded()->count();
            try {
                self::assertFalse(\App\Services\Fiscal\HkaMail::view($document)['can_send']);
                self::assertFalse(\App\Services\Fiscal\HkaMail::view($document)['can_query']);
                foreach (['query', 'send'] as $action) {
                    try {
                        if ($action === 'query') (new \App\Http\Controllers\Tenant\DocumentFiscalController)->queryHkaEmail($document->id);
                        else (new \App\Http\Controllers\Tenant\DocumentController)->email(\App\Http\Requests\Tenant\DocumentEmailRequest::create('/documents/email', 'POST', ['id' => $document->id]));
                        self::fail('Another establishment must be forbidden');
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $expected) { self::assertSame(403, $expected->getStatusCode()); }
                }
                self::assertSame($callsBefore, $http->recorded()->count());
            } finally { $operator->type = $oldType; $operator->establishment_id = $oldEstablishment; }
            $before = [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count(),
                $document->control_number, $document->emission->operation_key, $document->emission->payload];
            $inFlight = (string) \Illuminate\Support\Str::uuid();
            $firstId = $inFlight;
            $revision = \App\Services\Fiscal\DocumentEmissionView::cacheNamespace();
            self::assertSame('accepted', $mail->send($document, $recipients, $firstId)['status']);
            self::assertSame('accepted', $mail->send($document, $recipients, $firstId)['status']);
            self::assertSame(1, $sends);
            self::assertNotSame($revision, \App\Services\Fiscal\DocumentEmissionView::cacheNamespace());
            $inFlight = null;
            try { $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid()); self::fail('Explicit resend must be required'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('customer_email', $expected->errors()); }
            $mode = 'rejected';
            self::assertSame('rejected', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid(), true)['status']);
            $mode = 'auth-failure'; \Illuminate\Support\Facades\Cache::flush();
            self::assertSame('rejected', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            self::assertSame(2, $sends);
            $mode = 'timeout';
            $uncertainId = (string) \Illuminate\Support\Str::uuid();
            self::assertSame('uncertain', $mail->send($document, $recipients, $uncertainId)['status']);
            self::assertFalse(\App\Services\Fiscal\HkaMail::view($document->fresh())['can_send']);
            self::assertSame('uncertain', $mail->send($document, $recipients, (string) \Illuminate\Support\Str::uuid())['status']);
            self::assertSame(3, $sends);
            $mode = 'success';
            self::assertSame('uncertain', $mail->query($document)['status']);
            $tracking = [['messageId' => 'new-mail-id', 'correo' => $recipients[0], 'status' => 'delivered', 'fecha' => date('c')],
                ['messageId' => 'other-mail-id', 'correo' => 'other@example.test', 'status' => 'delivered', 'fecha' => date('c')]];
            self::assertSame('accepted', $mail->query($document)['status']);
            self::assertCount(1, $mail->query($document)['tracking']);
            $response = $document->fresh()->emission->response;
            $response['mail']['attempts'][$uncertainId]['status'] = 'pending';
            $response['mail']['attempts'][$uncertainId]['started_at'] = time() - 60;
            $document->fresh()->emission->update(['response' => $response]);
            self::assertSame('accepted', $mail->query($document)['status']);
            $emissionService->query($document);
            self::assertSame($uncertainId, $document->fresh()->emission->response['mail']['latest']);
            self::assertSame('confirmed', $document->fresh()->emission->status);
            self::assertStringNotContainsString('private-mail-token', json_encode(\App\Services\Fiscal\HkaMail::view($document->fresh())));
            self::assertStringNotContainsString('test-secret', json_encode($document->fresh()->emission->response));
            $after = $document->fresh();
            self::assertSame($before, [$db->table('documents')->count(), $db->table('document_payments')->count(), $db->table('inventory_kardex')->count(),
                $after->control_number, $after->emission->operation_key, $after->emission->payload]);
            $fiscalStatus = $after->emission->status;
            $after->emission->update(['status' => 'prepared']);
            self::assertFalse(\App\Services\Fiscal\HkaMail::view($after->fresh())['can_send']);
            try { $mail->send($after, $recipients, (string) \Illuminate\Support\Str::uuid()); self::fail('Unconfirmed invoice must be blocked'); }
            catch (\Illuminate\Validation\ValidationException $expected) { self::assertArrayHasKey('customer_email', $expected->errors()); }
            $after->emission->update(['status' => $fiscalStatus]);
            // Two independent MySQL connections must still produce only one email mutation.
            $mailResponse = $after->fresh()->emission->response;
            unset($mailResponse['mail']); $after->fresh()->emission->update(['response' => $mailResponse]);
            $log = tempnam(sys_get_temp_dir(), 'pro9_hka_mail_');
            try {
                $uuid = (string) \Illuminate\Support\Str::uuid();
                $results = $this->runFiscalWorkers($after->id, 'hka-mail', [['log' => $log, 'uuid' => $uuid], ['log' => $log, 'uuid' => $uuid]]);
                self::assertSame("mail\n", file_get_contents($log));
                foreach ($results as $result) self::assertContains($result['status'], ['pending', 'accepted']);
            } finally { unlink($log); }
        } finally {
            \Illuminate\Support\Facades\Http::swap($originalHttp);
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
