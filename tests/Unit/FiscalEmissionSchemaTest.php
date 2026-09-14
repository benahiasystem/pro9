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
    private $httpSystemDatabase;
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
        if ($this->httpSystemDatabase && preg_match('/^pro9_fiscal_test_[a-f0-9]{12}$/', $this->httpSystemDatabase)) {
            $this->capsule->getConnection('control')->statement('DROP DATABASE `' . $this->httpSystemDatabase . '`');
        }
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
            if (preg_match('/class\s+(\w+)\s+extends\s+\w+/', file_get_contents($path), $existingClass) && class_exists($existingClass[1], false)) return new $existingClass[1]();
            $migration = require $path;
            if (!is_object($migration)) {
                self::assertSame(1, preg_match('/class\s+(\w+)\s+extends\s+\w+/', file_get_contents($path), $class), $path);
                $migration = new $class[1]();
            }
            return $migration;
        }, $paths);
    }

    // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
    public function test_http_kernel_configures_and_emits_fiscal_documents_without_duplicate_effects(): void
    {
        foreach ($this->migrations('migrations/tenant/*.php') as $migration) $migration->up();
        $app = Container::getInstance();
        (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer($app)->run();
        $db = $this->capsule->getConnection('tenant');
        $this->httpSystemDatabase = 'pro9_fiscal_test_' . bin2hex(random_bytes(6));
        $this->capsule->getConnection('control')->statement('CREATE DATABASE `' . $this->httpSystemDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $systemConfig = array_replace($db->getConfig(), ['database' => $this->httpSystemDatabase]);
        $this->capsule->getDatabaseManager()->purge('system');
        $this->capsule->addConnection($systemConfig, 'system');
        $this->capsule->getDatabaseManager()->setDefaultConnection('system');
        foreach ($this->migrations('migrations/*.php') as $migration) $migration->up();
        $system = $this->capsule->getConnection('system');
        $system->table('hostnames')->insert(['id' => 1, 'fqdn' => 'fiscal-http.example.test']);
        $plan = $system->table('plans')->insertGetId(['name' => 'HTTP test plan', 'pricing' => 0, 'limit_users' => 10, 'limit_documents' => 0, 'plan_documents' => json_encode(['01', '80'])]);
        $system->table('clients')->insert(['hostname_id' => 1, 'number' => 'J123456789', 'name' => 'HTTP client', 'email' => 'tenant@example.test', 'token' => 'test-only', 'plan_id' => $plan]);
        $this->capsule->getDatabaseManager()->setDefaultConnection('tenant');
        $db->table('configurations')->insert(['quantity_documents' => 0, 'quantity_sales_notes' => 0, 'locked_tenant' => false, 'locked_users' => false]);
        $db->table('companies')->insert(['id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789', 'name' => 'HTTP fiscal test', 'trade_name' => 'HTTP fiscal test', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
        $establishment = $db->table('establishments')->insertGetId(['description' => 'HTTP fiscal test', 'country_id' => 'VE', 'department_id' => '14', 'province_id' => '0229', 'district_id' => '000619', 'address' => 'Test', 'telephone' => '04121234567', 'code' => '0000']);
        $admin = $db->table('users')->insertGetId(['name' => 'HTTP admin', 'email' => 'http-admin@example.test', 'password' => 'not-a-login-hash', 'type' => 'admin', 'establishment_id' => $establishment]);
        $seller = $db->table('users')->insertGetId(['name' => 'HTTP seller', 'email' => 'http-seller@example.test', 'password' => 'not-a-login-hash', 'type' => 'seller', 'establishment_id' => $establishment]);
        $db->table('offline_configurations')->insert(['is_client' => false]);
        $warehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $establishment, 'description' => 'HTTP warehouse']);
        $item = \App\Models\Tenant\Item::where('internal_id', 'MOCK-ITEM-VES-001')->firstOrFail();
        $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $warehouse, 'stock' => 10]);
        $customer = \App\Models\Tenant\Person::where('number', 'MOCK-CLIENTE-VE')->firstOrFail();
        $invoice = [
            'operation_key' => 'http-invoice-retry', 'document_type_id' => '01', 'group_id' => '01', 'type' => 'invoice',
            'establishment_id' => $establishment, 'customer_id' => $customer->id,
            'date_of_issue' => '2026-09-13', 'time_of_issue' => '12:00:00', 'date_of_due' => '2026-09-13',
            'currency_type_id' => 'VES', 'exchange_rate_sale' => 1, 'operation_type_id' => '0101',
            'total_taxed' => 200, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232,
            'payments' => [['date_of_payment' => '2026-09-13', 'payment_method_type_id' => '01', 'payment' => 232]],
            'items' => [['item_id' => $item->id, 'quantity' => 2, 'warehouse_id' => $warehouse,
                'unit_value' => 100, 'unit_price' => 116, 'price_type_id' => '01', 'affectation_igv_type_id' => '10',
                'total_base_igv' => 200, 'percentage_igv' => 16, 'total_igv' => 32, 'total_taxes' => 32, 'total_value' => 200, 'total' => 232]],
        ];
        $apiToken = 'HTTP-API-TEST-TOKEN';
        $integrator = $db->table('users')->insertGetId(['name' => 'HTTP integrator', 'email' => 'http-api@example.test', 'password' => 'not-a-login-hash', 'api_token' => $apiToken, 'type' => 'integrator', 'establishment_id' => $establishment]);
        $db->table('users')->insert(['name' => 'Other integrator', 'email' => 'http-other@example.test', 'password' => 'not-a-login-hash', 'api_token' => 'OTHER-HTTP-TEST-TOKEN', 'type' => 'integrator', 'establishment_id' => $establishment]);
        $apiInvoice = [
            'clave_operacion' => 'http-api-retry', 'codigo_tipo_documento' => '01', 'codigo_tipo_operacion' => '0101',
            'fecha_de_emision' => '2026-09-13', 'hora_de_emision' => '12:00:00', 'fecha_de_vencimiento' => '2026-09-13',
            'codigo_tipo_moneda' => 'VES', 'factor_tipo_de_cambio' => 1,
            'datos_del_cliente_o_receptor' => ['codigo_tipo_documento_identidad' => '6', 'numero_documento' => 'J123456789', 'apellidos_y_nombres_o_razon_social' => 'HTTP API customer', 'codigo_pais' => 'VE', 'ubigeo' => '000619', 'direccion' => 'Test address'],
            'totales' => ['total_operaciones_gravadas' => 200, 'total_igv' => 32, 'total_impuestos' => 32, 'total_valor' => 200, 'total_venta' => 232],
            'pagos' => [['codigo_metodo_pago' => '01', 'codigo_destino_pago' => null, 'monto' => 232]],
            'acciones' => ['enviar_email' => false, 'formato_pdf' => 'a4', 'auto_print' => false],
            'items' => [['codigo_interno' => $item->internal_id, 'descripcion' => $item->description, 'unidad_de_medida' => 'UND', 'cantidad' => 2,
                'valor_unitario' => 100, 'precio_unitario' => 116, 'codigo_tipo_precio' => '01', 'codigo_tipo_afectacion_igv' => '10',
                'total_base_igv' => 200, 'porcentaje_igv' => 16, 'total_igv' => 32, 'total_impuestos' => 32, 'total_valor_item' => 200, 'total_item' => 232]],
        ];
        $path = '/establishments/' . $establishment . '/fiscal-numbering';
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, dirname(__DIR__) . '/Support/fiscal_http_worker.php']);
        $process->setInput(json_encode(['connection' => $db->getConfig(), 'system_connection' => $systemConfig, 'requests' => [
            ['path' => $path],
            ['path' => $path, 'user_id' => $seller],
            ['path' => $path, 'user_id' => $admin],
            ['path' => $path . '/sequences', 'method' => 'POST', 'user_id' => $admin, 'body' => ['document_type_id' => '01', 'series_code' => '', 'initial_number' => 0, 'centralized' => true]],
            ['path' => $path . '/sequences', 'method' => 'POST', 'user_id' => $admin, 'body' => ['document_type_id' => '01', 'series_code' => '', 'initial_number' => 1, 'centralized' => true]],
            ['path' => $path . '/profiles', 'method' => 'POST', 'user_id' => $admin, 'body' => ['name' => 'HTTP demo profile', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital', 'sequence_id' => 1, 'provider' => 'simulator', 'configuration' => [], 'credentials' => 'HTTP-TEST-SECRET', 'active' => true]],
            ['path' => $path, 'user_id' => $admin],
            ['path' => '/documents', 'method' => 'POST', 'user_id' => $admin, 'body' => $invoice],
            ['path' => '/documents', 'method' => 'POST', 'user_id' => $admin, 'body' => $invoice],
            ['path' => '/documents/1/fiscal', 'user_id' => $admin],
            ['path' => $path . '/archive', 'method' => 'POST', 'user_id' => $admin, 'body' => ['entity' => 'profile', 'id' => 1]],
            ['path' => $path, 'user_id' => $admin],
            ['path' => $path . '/lots', 'method' => 'POST', 'user_id' => $admin, 'body' => ['start' => '00-1', 'end' => '00-2', 'printer_name' => 'HTTP test printer', 'printer_rif' => 'J000000000', 'authorization' => 'TEST ONLY', 'authorization_date' => '2026-09-01', 'prepared_at' => '2026-09-01']],
            ['path' => $path . '/profiles', 'method' => 'POST', 'user_id' => $admin, 'body' => ['name' => 'HTTP free form', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'free_form', 'sequence_id' => 1, 'control_lot_id' => 1, 'provider' => 'none', 'configuration' => ['page_capacity' => 10], 'active' => true]],
            ['path' => '/documents', 'method' => 'POST', 'user_id' => $admin, 'body' => array_replace($invoice, ['operation_key' => 'http-free-form'])],
            ['path' => '/documents', 'method' => 'POST', 'user_id' => $admin, 'body' => array_replace($invoice, ['operation_key' => 'http-free-form'])],
            ['path' => '/documents/2/fiscal', 'user_id' => $admin],
            ['path' => '/documents/2/fiscal/confirm-print', 'method' => 'POST', 'user_id' => $admin],
            ['path' => '/documents/2/fiscal/confirm-print', 'method' => 'POST', 'user_id' => $admin],
            ['path' => '/documents/2/fiscal', 'user_id' => $admin],
            ['path' => $path . '/profiles', 'method' => 'POST', 'user_id' => $admin, 'body' => ['name' => 'HTTP API digital', 'channel' => 'digital', 'document_type_id' => '01', 'mode' => 'digital', 'sequence_id' => 1, 'provider' => 'simulator', 'configuration' => ['emitter_user_id' => $integrator], 'active' => true]],
            ['path' => '/api/documents', 'method' => 'POST', 'body' => $apiInvoice],
            ['path' => '/api/documents', 'method' => 'POST', 'api_token' => 'WRONG-TEST-TOKEN', 'body' => $apiInvoice],
            ['path' => '/api/documents', 'method' => 'POST', 'api_token' => $apiToken, 'body' => $apiInvoice],
            ['path' => '/api/documents', 'method' => 'POST', 'api_token' => $apiToken, 'body' => $apiInvoice],
            ['path' => '/api/documents/3/fiscal', 'api_token' => $apiToken],
            ['path' => '/api/documents', 'method' => 'POST', 'api_token' => $apiToken, 'body' => array_replace_recursive($apiInvoice, ['datos_del_cliente_o_receptor' => ['apellidos_y_nombres_o_razon_social' => 'Rejected API change']])],
            ['path' => '/api/documents', 'method' => 'POST', 'api_token' => $apiToken, 'body' => array_replace($apiInvoice, ['clave_operacion' => 'ecommerce-order-25-invoice'])],
            ['path' => '/api/documents/3/fiscal', 'api_token' => 'OTHER-HTTP-TEST-TOKEN'],
            ['path' => $path . '/profiles', 'method' => 'POST', 'user_id' => $admin, 'body' => ['name' => 'HTTP contingency', 'channel' => 'contingency', 'document_type_id' => '01', 'mode' => 'free_form', 'sequence_id' => 1, 'control_lot_id' => 1, 'provider' => 'none', 'configuration' => ['page_capacity' => 10], 'active' => true]],
            ['path' => '/fixture-register-only', 'method' => 'POST', 'register_only' => true, 'user_id' => $integrator, 'body' => array_replace($apiInvoice, ['clave_operacion' => 'http-contingency-source'])],
            ['path' => '/documents/4/fiscal/contingency', 'method' => 'POST', 'user_id' => $seller, 'body' => ['profile_id' => 4, 'reason' => 'Interrupción antes de envío']],
            ['path' => '/documents/4/fiscal/contingency', 'method' => 'POST', 'user_id' => $admin, 'body' => ['profile_id' => 4, 'reason' => 'Interrupción antes de envío']],
            ['path' => '/documents/4/fiscal/contingency', 'method' => 'POST', 'user_id' => $admin, 'body' => ['profile_id' => 4, 'reason' => 'Interrupción antes de envío']],
            ['path' => '/documents/4/fiscal', 'user_id' => $admin],
            ['path' => '/documents/4/fiscal/confirm-print', 'method' => 'POST', 'user_id' => $admin],
            ['path' => '/documents/4/fiscal', 'user_id' => $admin],
            ['path' => '/fixture-register-only', 'method' => 'POST', 'register_only' => true, 'user_id' => $integrator, 'body' => array_replace($apiInvoice, ['clave_operacion' => 'http-contingency-source'])],
        ]], JSON_THROW_ON_ERROR));
        $process->setTimeout(60);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $output = explode("\nFISCAL_HTTP_RESULT\n", $process->getOutput(), 2);
        self::assertCount(2, $output, substr($process->getOutput(), -2000));
        $responses = json_decode($output[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([401, 403, 200, 422, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 200, 401, 401, 200, 200, 200, 422, 422, 404, 200, 201, 403, 200, 200, 200, 200, 200, 201], array_column($responses, 'status'), json_encode($responses));
        self::assertTrue($responses[7]['body']['success'], json_encode($responses[7]));
        self::assertTrue($responses[8]['body']['success'], json_encode($responses[8]));
        self::assertSame($responses[7]['body']['data']['id'], $responses[8]['body']['data']['id']);
        self::assertTrue($responses[14]['body']['success'], json_encode($responses[14]));
        self::assertSame($responses[14]['body']['data']['id'], $responses[15]['body']['data']['id']);
        self::assertSame('awaiting_print', $responses[16]['body']['data']['status']);
        self::assertSame('00-00000001', $responses[16]['body']['data']['control_number']);
        self::assertSame('issued', $responses[19]['body']['data']['status']);
        self::assertSame(2, $responses[19]['pdf_count']);
        self::assertSame(3, (int) $db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame(6, (int) $db->table('fiscal_sequences')->value('next_number'));
        self::assertArrayNotHasKey('data', $responses[28]['body']);
        self::assertSame(0, $db->table('persons')->where('name', 'Rejected API change')->count());
        self::assertSame(0, $db->table('fiscal_number_reservations')->where('operation_key', 'ecommerce-order-25-invoice')->count());
        self::assertTrue($responses[23]['body']['success'], json_encode($responses[23]));
        self::assertSame($responses[23]['body']['data']['id'], $responses[24]['body']['data']['id']);
        self::assertFalse($responses[23]['body']['data']['replayed']);
        self::assertTrue($responses[24]['body']['data']['replayed']);
        self::assertSame('issued', $responses[25]['body']['data']['status']);
        self::assertSame(3, $responses[25]['pdf_count']);
        self::assertSame('awaiting_print', $responses[34]['body']['data']['status']);
        self::assertSame('5', $responses[34]['body']['data']['document_number']);
        self::assertSame('00-00000002', $responses[34]['body']['data']['control_number']);
        self::assertSame('Interrupción antes de envío', $responses[34]['body']['data']['contingency']['reason']);
        self::assertSame('issued', $responses[36]['body']['data']['status']);
        self::assertSame(4, $responses[36]['pdf_count']);
        self::assertSame($responses[30]['body']['document_id'], $responses[37]['body']['document_id']);
        self::assertSame(5, $db->table('fiscal_number_reservations')->count());
        self::assertSame('contingency', $db->table('fiscal_number_reservations')->where('document_id', 4)->value('status'));
        self::assertSame(1, $db->table('fiscal_numbering_audits')->where('action', 'start_contingency')->count());
        $apiCustomer = $db->table('persons')->where('name', 'HTTP API customer')->first();
        self::assertNotNull($apiCustomer);
        self::assertSame('6', $apiCustomer->identity_document_type_id);
        self::assertSame(['14', '0229', '000619'], [$apiCustomer->department_id, $apiCustomer->province_id, $apiCustomer->district_id]);
        self::assertSame(4, $db->table('documents')->count());
        self::assertSame(4, $db->table('document_payments')->count());
        self::assertSame(4, $db->table('inventory_kardex')->count());
        self::assertEquals(2, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
        self::assertSame('issued', $responses[9]['body']['data']['status']);
        self::assertSame(1, $responses[9]['pdf_count']);
        self::assertSame(1, $db->table('fiscal_sequences')->count());
        self::assertSame(1, (int) $db->table('fiscal_sequences')->value('initial_number'));
        self::assertSame(4, $db->table('fiscal_profiles')->count());
        self::assertSame(0, (int) $db->table('fiscal_profiles')->value('active'));
        self::assertNotEmpty($db->table('fiscal_profiles')->value('credentials'));
        self::assertNotSame('HTTP-TEST-SECRET', $db->table('fiscal_profiles')->value('credentials'));
        self::assertStringNotContainsString('HTTP-TEST-SECRET', json_encode($responses));
        self::assertTrue($responses[6]['body']['data']['profiles'][0]['credentials_configured']);
        self::assertFalse((bool) $responses[11]['body']['data']['profiles'][0]['active']);
    }

    public function test_facturalo_persists_real_payment_and_inventory_once_on_fiscal_retry(): void
    {
        foreach ($this->migrations('migrations/tenant/*.php') as $migration) $migration->up();
        $app = Container::getInstance();
        (new \Database\Seeders\TenancyDatabaseSeeder())->setContainer($app)->run();
        $db = $this->capsule->getConnection('tenant');
        $app->instance('validator', new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app));
        $app->instance('files', new \Illuminate\Filesystem\Filesystem());
        $db->table('companies')->insert(['id' => 1, 'identity_document_type_id' => '6', 'number' => 'J123456789', 'name' => 'Test fiscal', 'trade_name' => 'Test fiscal', 'fiscal_environment' => 'demo', 'fiscal_emission_mode' => 'digital']);
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
        $repository = new \App\Services\FiscalNumberingRepository($db);
        $profile = (new \App\Services\FiscalProfileService($db))->save($establishment, [
            'name' => 'Integration demo', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $repository->createSequence('01', '', 1, $establishment), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], $userId)['id'];
        $input = [
            'type' => 'invoice', 'user_id' => $userId, 'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'establishment_id' => $establishment, 'establishment' => (array) $db->table('establishments')->find($establishment),
            'state_type_id' => '01', 'ubl_version' => '2.1', 'group_id' => '01', 'document_type_id' => '01',
            'series' => '', 'number' => '#', 'date_of_issue' => '2026-09-10', 'time_of_issue' => '12:00:00',
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
        $previousDispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
        \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        try {
            \App\Models\Tenant\Document::observe(\App\Observers\DocumentObserver::class);
            (new \Modules\Inventory\Providers\InventoryKardexServiceProvider($app))->boot();
            (new \Modules\Inventory\Providers\InventoryVoidedServiceProvider($app))->boot();
            $firstRegistration = (new \App\CoreFacturalo\Facturalo())->saveFiscal($input, $profile, 'integration-sale', hash('sha256', 'integration-sale'), 'presential');
            $first = $firstRegistration->getDocument();
            self::assertTrue($firstRegistration->wasNewFiscalRegistration());
            $retryRegistration = (new \App\CoreFacturalo\Facturalo())->saveFiscal($input, $profile, 'integration-sale', hash('sha256', 'integration-sale'), 'presential');
            $retry = $retryRegistration->getDocument();
            self::assertFalse($retryRegistration->wasNewFiscalRegistration());
            self::assertSame($first->id, $retry->id);
            self::assertSame(1, $db->table('documents')->count());
            self::assertSame(1, $db->table('document_items')->count());
            self::assertSame(1, $db->table('document_payments')->count());
            self::assertEquals(232, $db->table('document_payments')->sum('payment'));
            self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
            self::assertSame('issued', (new \App\Services\Fiscal\FiscalEmissionService($db))->process((int) $db->table('fiscal_number_reservations')->value('id'))->status);
            $noteProfiles = [];
            foreach (['07', '08'] as $type) {
                $noteProfiles[$type] = (new \App\Services\FiscalProfileService($db))->save($establishment, [
                    'name' => 'Note integration ' . $type, 'channel' => 'presential', 'document_type_id' => $type, 'mode' => 'digital',
                    'sequence_id' => $repository->createSequence($type, '', 1, $establishment), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
                ], $userId)['id'];
            }
            foreach ([['07', '01', 10], ['07', '04', 8], ['07', '09', 8], ['08', '02', 8]] as [$type, $reason, $stock]) {
                $db->beginTransaction();
                try {
                    $noteInput = $input;
                    $noteInput['type'] = $type === '07' ? 'credit' : 'debit';
                    $noteInput['document_type_id'] = $type;
                    $noteInput['external_id'] = \Illuminate\Support\Str::uuid()->toString();
                    $noteInput['note'] = [
                        'note_type' => $noteInput['type'], 'note_credit_type_id' => $type === '07' ? $reason : null,
                        'note_debit_type_id' => $type === '08' ? $reason : null, 'note_description' => 'Integration test', 'affected_document_id' => $first->id,
                    ];
                    $key = 'integration-note-' . $type . '-' . $reason;
                    $note = (new \App\CoreFacturalo\Facturalo())->saveFiscal($noteInput, $noteProfiles[$type], $key, hash('sha256', $key), 'presential')->getDocument();
                    $noteRetry = (new \App\CoreFacturalo\Facturalo())->saveFiscal($noteInput, $noteProfiles[$type], $key, hash('sha256', $key), 'presential')->getDocument();
                    self::assertSame($note->id, $noteRetry->id);
                    self::assertSame(1, $db->table('notes')->count());
                    self::assertSame(1, $db->table('document_payments')->count());
                    self::assertEquals($stock, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                    self::assertSame($stock === 10 ? 2 : 1, $db->table('inventory_kardex')->count());
                    $snapshot = json_decode($db->table('fiscal_number_reservations')->where('document_id', $note->id)->value('fiscal_snapshot'), true);
                    self::assertSame((int) $first->id, $snapshot['affected_document']['document_id']);
                    self::assertSame('1', $snapshot['affected_document']['number']);
                    $note->state_type_id = '09';
                    $note->save();
                    self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                    $note->state_type_id = '11';
                    $note->save();
                    self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                    $note->additional_information = 'Unrelated update after void';
                    $note->save();
                    self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                } finally {
                    $db->rollBack();
                }
            }
            self::assertSame(1, $db->table('inventory_kardex')->count());
            self::assertSame(2, (int) $db->table('fiscal_sequences')->value('next_number'));
            $reservationId = (int) $db->table('fiscal_number_reservations')->value('id');
            $emission = new \App\Services\Fiscal\FiscalEmissionService($db);
            self::assertSame('issued', $emission->process($reservationId)->status);
            self::assertSame('issued', $emission->process($reservationId)->status);
            self::assertSame(1, $db->table('fiscal_demo_receipts')->count());

            // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
            $db->beginTransaction();
            try {
                $orderWarehouse = $db->table('warehouses')->insertGetId(['establishment_id' => $establishment, 'description' => 'Reserved secondary warehouse']);
                $db->table('item_warehouse')->insert(['item_id' => $item->id, 'warehouse_id' => $orderWarehouse, 'stock' => 20]);
                $purchase = ['total' => 232, 'codigo_tipo_documento' => '01'];
                $orderId = $db->table('orders')->insertGetId([
                    'external_id' => \Illuminate\Support\Str::uuid()->toString(), 'customer' => '{}',
                    'items' => json_encode([['id' => $item->id, 'cantidad' => 2]]), 'total' => 232, 'reference_payment' => 'integration',
                    'purchase' => json_encode($purchase),
                ]);
                \App\Services\Fiscal\FiscalOrderStockReservation::change($db, $orderId, (int) $input['establishment_id'], [['id' => $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $orderWarehouse)->value('id'), 'cantidad' => 2]], 'status_order_id', 1);
                self::assertEquals(18, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $orderWarehouse)->value('stock'));
                $source = ['id' => $orderId, 'purchase_fingerprint' => \App\Services\Fiscal\FiscalOrderConversion::fingerprint($purchase)];
                $key = 'ecommerce-order-' . $orderId . '-invoice';
                $orderInput = $input;
                $orderInput['external_id'] = \Illuminate\Support\Str::uuid()->toString();
                $orderInvoice = (new \App\CoreFacturalo\Facturalo())->saveFiscal($orderInput, $profile, $key, hash('sha256', $key), 'presential', null, $source)->getDocument();
                $orderRetry = (new \App\CoreFacturalo\Facturalo())->saveFiscal($orderInput, $profile, $key, hash('sha256', $key), 'presential', null, $source)->getDocument();
                self::assertSame($orderInvoice->id, $orderRetry->id);
                self::assertSame($orderWarehouse, (int) $db->table('document_items')->where('document_id', $orderInvoice->id)->value('warehouse_id'));
                self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
                self::assertSame(0, (int) $db->table('orders')->where('id', $orderId)->value('stock_discounted'));
                self::assertNull($db->table('orders')->where('id', $orderId)->value('stock_reservation'));
                self::assertSame($orderInvoice->external_id, $db->table('orders')->where('id', $orderId)->value('document_external_id'));
                self::assertSame($orderInvoice->number_full, $db->table('orders')->where('id', $orderId)->value('number_document'));
                self::assertSame(2, $db->table('documents')->count());
                self::assertSame(2, $db->table('document_payments')->count());
                self::assertSame(2, $db->table('inventory_kardex')->count());
                self::assertEquals(18, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $orderWarehouse)->value('stock'));
            } finally {
                $db->rollBack();
            }
            // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

            $db->table('inventory_configurations')->update(['stock_control' => true]);
            $failed = $input;
            $failed['external_id'] = \Illuminate\Support\Str::uuid()->toString();
            $failed['items'][0]['quantity'] = 9;
            foreach (['total_taxed' => 900, 'total_igv' => 144, 'total_taxes' => 144, 'total_value' => 900, 'total' => 1044] as $field => $amount) $failed[$field] = $amount;
            foreach (['total_base_igv' => 900, 'total_igv' => 144, 'total_taxes' => 144, 'total_value' => 900, 'total' => 1044] as $field => $amount) $failed['items'][0][$field] = $amount;
            $failed['payments'][0]['payment'] = 1044;
            try {
                (new \App\CoreFacturalo\Facturalo())->saveFiscal($failed, $profile, 'insufficient-stock', hash('sha256', 'insufficient-stock'), 'presential');
                self::fail('Insufficient stock must reject the whole transaction.');
            } catch (\Exception $exception) {
                self::assertStringContainsString('suficiente stock', $exception->getMessage());
            }
            self::assertSame(1, $db->table('documents')->count());
            self::assertSame(1, $db->table('document_payments')->count());
            self::assertSame(1, $db->table('inventory_kardex')->count());
            self::assertSame(1, $db->table('fiscal_number_reservations')->count());
            self::assertSame(2, (int) $db->table('fiscal_sequences')->value('next_number'));
            self::assertEquals(8, $db->table('item_warehouse')->where('item_id', $item->id)->where('warehouse_id', $warehouse)->value('stock'));
        } finally {
            if ($previousDispatcher) \Illuminate\Database\Eloquent\Model::setEventDispatcher($previousDispatcher);
            else \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
        }
    }
    // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########

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
        self::assertSame(0, $db->table('cat_unit_types')->whereIn('id', ['NIU', 'ZZ'])->count());
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
