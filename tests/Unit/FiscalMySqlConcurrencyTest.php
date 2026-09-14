<?php

namespace Tests\Unit;

use App\Services\FiscalProfileService;
use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Process\Process;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalMySqlConcurrencyTest extends FiscalDatabaseTestCase
{
    private ?Manager $manager = null;
    private ?string $database = null;
    private array $connection = [];

    protected function configureConnections(Manager $manager): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Requires PRO9_FISCAL_MYSQL_TESTS=1 and an isolated temporary MySQL database.');
        }
        $root = dirname(__DIR__, 2);
        $environment = is_file($root . '/.env') ? \Dotenv\Dotenv::parse(file_get_contents($root . '/.env')) : [];
        $config = [
            'driver' => 'mysql', 'host' => getenv('DB_HOST') ?: ($environment['DB_HOST'] ?? '127.0.0.1'),
            'port' => getenv('DB_PORT') ?: ($environment['DB_PORT'] ?? 3306),
            'username' => $environment['DB_USERNAME'] ?? 'root', 'password' => $environment['DB_PASSWORD'] ?? '',
            'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ];
        $this->manager = $manager;
        $manager->addConnection($config, 'control');
        $this->database = 'pro9_fiscal_concurrency_' . bin2hex(random_bytes(6));
        $manager->getConnection('control')->statement('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $config['database'] = $this->database;
        $this->connection = $config;
        $manager->addConnection($config);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database && preg_match('/\Apro9_fiscal_concurrency_[a-f0-9]{12}\z/', $this->database)) {
                $this->manager->getConnection()->disconnect();
                $this->manager->getConnection('control')->statement('DROP DATABASE `' . $this->database . '`');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function profile(int $lastControl = 100): int
    {
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTO_INCREMENT, external_id VARCHAR(36), series VARCHAR(32), number BIGINT, document_type_id VARCHAR(2), establishment_id INTEGER, fiscal_environment VARCHAR(20), fiscal_emission_mode VARCHAR(20)) ENGINE=InnoDB');
        $this->db->statement('CREATE TABLE commercial_effects (id INTEGER PRIMARY KEY AUTO_INCREMENT, subject_id INTEGER, kind VARCHAR(20)) ENGINE=InnoDB');
        $lot = $this->repository->createLot([
            'establishment_id' => 1, 'printer_name' => 'Test printer', 'printer_rif' => 'J000000000',
            'authorization' => 'TEST ONLY', 'authorization_date' => '2026-09-01', 'prepared_at' => '2026-09-01',
            'start' => '00-1', 'end' => '00-' . $lastControl,
        ]);
        return (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Concurrent test', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'free_form',
            'sequence_id' => $this->repository->createSequence('01', '', 1, 1), 'control_lot_id' => $lot,
            'provider' => 'none', 'configuration' => ['page_capacity' => 10], 'active' => true,
        ], 1)['id'];
    }

    private function race(int $profile, array $operations): array
    {
        $processes = [];
        $this->db->beginTransaction();
        try {
            $this->db->table('companies')->where('id', 1)->lockForUpdate()->first();
            foreach ($operations as $operation) {
                [$key, $payload] = $operation;
                $kind = $operation[2] ?? 'sale';
                $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/fiscal_concurrency_worker.php']);
                $process->setInput(json_encode(['connection' => $this->connection, 'profile' => $profile, 'key' => $key, 'payload' => $payload, 'kind' => $kind], JSON_THROW_ON_ERROR));
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(), "READY\n")) {
                    if (!$process->isRunning() || microtime(true) > $deadline) {
                        $this->fail('Worker failed to reach the synchronization barrier.');
                    }
                    usleep(10000);
                }
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->isRunning(), 'The operation must wait while the issuer is locked.');
                $this->assertSame("READY\n", $process->getOutput());
            }
            $this->db->commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), 'Worker execution failed.');
                $results[] = json_decode(substr($process->getOutput(), strlen("READY\n")), true, 512, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if ($this->db->transactionLevel() > 0) $this->db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
    }

    private function orderFixture(): void
    {
        $this->db->statement('CREATE TABLE orders (id INTEGER PRIMARY KEY, purchase JSON, items JSON, stock_discounted INTEGER DEFAULT 0, stock_reservation JSON, document_external_id VARCHAR(36), number_document VARCHAR(80), status_order_id INTEGER, deleted_at DATETIME, updated_at DATETIME) ENGINE=InnoDB');
        $this->db->statement('CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, order_id INTEGER) ENGINE=InnoDB');
        $this->db->statement('CREATE TABLE warehouses (id INTEGER PRIMARY KEY, establishment_id INTEGER) ENGINE=InnoDB');
        $this->db->statement('CREATE TABLE items (id INTEGER PRIMARY KEY, unit_type_id VARCHAR(4), is_set INTEGER DEFAULT 0) ENGINE=InnoDB');
        $this->db->statement('CREATE TABLE item_warehouse (id INTEGER PRIMARY KEY, item_id INTEGER, warehouse_id INTEGER, stock DECIMAL(12,4)) ENGINE=InnoDB');
        $this->db->table('orders')->insert(['id' => 25, 'purchase' => json_encode(['total' => 232]), 'items' => json_encode([['id' => 1, 'cantidad' => 2]])]);
        $this->db->table('warehouses')->insert(['id' => 1, 'establishment_id' => 1]);
        $this->db->table('items')->insert(['id' => 1, 'unit_type_id' => 'UND']);
        $this->db->table('item_warehouse')->insert(['id' => 1, 'item_id' => 1, 'warehouse_id' => 1, 'stock' => 10]);
    }

    /** @dataProvider orderStockActions */
    public function test_order_stock_action_and_invoice_share_one_physical_discount(string $action): void
    {
        $profile = $this->profile();
        $this->orderFixture();
        if ($action === 'order-release') {
            \App\Services\Fiscal\FiscalOrderStockReservation::change($this->db, 25, 1, [['id' => 1, 'cantidad' => 2]], 'status_order_id', 2);
        }
        $results = $this->race($profile, [['stock-action', 'stock', $action], ['ecommerce-order-25-invoice', 'invoice', 'order-invoice']]);
        $this->assertArrayHasKey('document_id', $results[1]);
        $this->assertTrue(isset($results[0]['stock_changed']) || isset($results[0]['rejected']));
        $this->assertSame('8.0000', $this->db->table('item_warehouse')->value('stock'));
        $this->assertSame(1, $this->db->table('documents')->count());
        $this->assertSame(2, $this->db->table('commercial_effects')->count());
        $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        $order = $this->db->table('orders')->find(25);
        $this->assertSame('order-invoice-uuid', $order->document_external_id);
        $this->assertSame(0, (int) $order->stock_discounted);
        $this->assertNull($order->stock_reservation);
    }

    public static function orderStockActions(): array
    {
        return [['order-stock'], ['order-release']];
    }

    /** @dataProvider contingencyReasons */
    public function test_contingency_race_reserves_one_physical_document(string $secondReason): void
    {
        $physical = $this->profile();
        $this->db->table('fiscal_profiles')->where('id', $physical)->update(['channel' => 'contingency']);
        $this->db->statement('ALTER TABLE documents ADD state_type_id VARCHAR(2) DEFAULT "01"');
        $this->db->statement('CREATE TABLE document_items (id INTEGER PRIMARY KEY, document_id INTEGER) ENGINE=InnoDB');
        $this->db->table('users')->insert(['id' => 1, 'name' => 'Admin', 'type' => 'admin', 'active' => 1, 'establishment_id' => 1]);
        $originalProfile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Original', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', 'D', 1, 1), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1)['id'];
        $original = $this->repository->reserveForProfile($originalProfile, 'sale-original', hash('sha256', 'sale'), 1, 'presential');
        $this->db->table('documents')->insert(['id' => 1, 'establishment_id' => 1, 'document_type_id' => '01', 'fiscal_environment' => 'demo']);
        $this->db->table('document_items')->insert(['id' => 1, 'document_id' => 1]);
        $this->db->table('fiscal_number_reservations')->where('id', $original->id)->update(['document_id' => 1]);
        $results = $this->race($physical, [['first', 'Interrupción', 'contingency'], ['second', $secondReason, 'contingency']]);
        $accepted = array_filter($results, fn ($result) => isset($result['id']));
        self::assertCount($secondReason === 'Interrupción' ? 2 : 1, $accepted);
        self::assertCount(1, array_unique(array_column($accepted, 'id')));
        self::assertSame(1, $this->db->table('documents')->count());
        self::assertSame(2, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame('contingency', $this->db->table('fiscal_number_reservations')->where('id', 1)->value('status'));
        self::assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'start_contingency')->count());
    }

    public static function contingencyReasons(): array
    {
        return [['Interrupción'], ['Otra causa']];
    }

    public function test_concurrent_distinct_sales_receive_distinct_numbers_and_controls(): void
    {
        $results = $this->race($this->profile(), [['sale-a', 'a'], ['sale-b', 'b']]);
        $this->assertCount(2, array_unique(array_column($results, 'number')));
        $this->assertCount(2, array_unique(array_column($results, 'control')));
        $this->assertSame(2, $this->db->table('documents')->count());
        $this->assertSame(4, $this->db->table('commercial_effects')->count());
        $this->assertSame(3, (int) $this->db->table('fiscal_sequences')->value('next_number'));
    }

    public function test_concurrent_retries_share_one_document_control_and_writer(): void
    {
        $results = $this->race($this->profile(), [['same-sale', 'same'], ['same-sale', 'same']]);
        $this->assertSame($results[0], $results[1]);
        $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        $this->assertSame(1, $this->db->table('documents')->count());
        $this->assertSame(2, $this->db->table('commercial_effects')->count());
        $this->assertSame(2, (int) $this->db->table('fiscal_sequences')->value('next_number'));
    }

    public function test_concurrent_conflicting_payloads_reject_one_without_second_effects(): void
    {
        $results = $this->race($this->profile(), [['same-sale', 'first'], ['same-sale', 'different']]);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['rejected'] ?? false));
        $this->assertSame(1, $this->db->table('documents')->count());
        $this->assertSame(2, $this->db->table('commercial_effects')->count());
    }

    public function test_only_one_concurrent_sale_can_consume_the_last_control(): void
    {
        $results = $this->race($this->profile(1), [['sale-a', 'a'], ['sale-b', 'b']]);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['rejected'] ?? false));
        $this->assertSame(1, $this->db->table('documents')->count());
        $this->assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        $this->assertSame(2, $this->db->table('commercial_effects')->count());
        $this->assertSame(2, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        $this->assertSame('00-00000001', $this->db->table('fiscal_number_reservations')->value('control_number'));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
