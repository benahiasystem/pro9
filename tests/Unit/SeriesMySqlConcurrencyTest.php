<?php
namespace Tests\Unit;

use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Process\Process;
use Tests\Support\SeriesDatabaseTestCase;

class SeriesMySqlConcurrencyTest extends SeriesDatabaseTestCase
{
    private $database;
    private $connection = [];

    protected function configureConnections(Manager $manager): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requires an isolated temporary MySQL database.');
        $env = \Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2) . '/.env'));
        $config = ['driver' => 'mysql', 'host' => getenv('DB_HOST') ?: $env['DB_HOST'], 'port' => $env['DB_PORT'] ?? 3306,
            'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'], 'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
        $manager->addConnection($config, 'control');
        $this->database = 'pro9_series_test_' . bin2hex(random_bytes(6));
        $manager->getConnection('control')->statement('CREATE DATABASE `' . $this->database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $config['database'] = $this->database;
        $this->connection = $config;
        $manager->addConnection($config, 'tenant');
    }

    protected function createTable(string $table, string $columns): void
    {
        $columns = str_replace(['AUTOINCREMENT', ' TEXT'], ['AUTO_INCREMENT', ' VARCHAR(255)'], $columns);
        $this->db->statement("CREATE TABLE $table ($columns) ENGINE=InnoDB");
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database && preg_match('/^pro9_series_test_[a-f0-9]{12}$/', $this->database)) {
                $this->manager->getConnection('tenant')->disconnect();
                $this->manager->getConnection('control')->statement('DROP DATABASE `' . $this->database . '`');
            }
        } finally { parent::tearDown(); }
    }

    /** @dataProvider requestedNumbers */
    public function test_parallel_saves_wait_for_the_issuer_and_never_duplicate($requested, string $code, bool $separateBranches): void
    {
        $this->db->table('series')->insert(['id' => 1, 'establishment_id' => 1, 'document_type_id' => '01', 'number' => $code]);
        $this->db->table('series_configurations')->insert(['series_id' => 1, 'document_type_id' => '01', 'series' => $code, 'number' => 100]);
        if ($separateBranches) {
            $this->db->table('series')->insert(['id' => 2, 'establishment_id' => 2, 'document_type_id' => '01', 'number' => $code]);
            $this->db->table('series_configurations')->insert(['series_id' => 2, 'document_type_id' => '01', 'series' => $code, 'number' => 100]);
        }
        $processes = [];
        $this->db->beginTransaction();
        try {
            $this->db->table('companies')->where('id', 1)->lockForUpdate()->first();
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/series_concurrency_worker.php']);
                $process->setInput(json_encode(['connection' => $this->connection, 'number' => $requested, 'series' => $code, 'branch' => $separateBranches ? $i + 1 : 1], JSON_THROW_ON_ERROR));
                $process->setTimeout(20); $process->start(); $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(), "READY\n")) {
                    if (!$process->isRunning() || microtime(true) > $deadline) self::fail('Worker failed before lock barrier.');
                    usleep(10000);
                }
                self::assertTrue($process->isRunning());
                self::assertSame("READY\n", $process->getOutput());
            }
            $this->db->commit();
            $results = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode(substr($process->getOutput(), strlen("READY\n")), true, 512, JSON_THROW_ON_ERROR);
            }
            $numbers = $this->db->table('documents')->orderBy('number')->pluck('number')->map(fn ($n) => (int) $n)->all();
            self::assertSame($separateBranches ? [100, 100] : ($requested === '#' ? [100, 101] : [100]), $numbers);
            self::assertSame(($separateBranches || $requested === '#') ? 0 : 1, count(array_filter($results, fn ($r) => !empty($r['duplicate']))));
        } finally {
            if ($this->db->transactionLevel()) $this->db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
    }

    public function test_delete_waits_for_an_in_progress_document_and_preserves_the_used_series(): void
    {
        $this->db->table('series')->insert(['id' => 1, 'establishment_id' => 1, 'document_type_id' => '01', 'number' => 'FF01']);
        $this->db->beginTransaction();
        $worker = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/series_concurrency_worker.php']);
        $worker->setInput(json_encode(['connection' => $this->connection, 'operation' => 'delete'], JSON_THROW_ON_ERROR));
        $worker->setTimeout(20);
        try {
            $model = new class extends \Illuminate\Database\Eloquent\Model {
                protected $connection = 'tenant';
                protected $table = 'documents';
            };
            $model->fiscal_environment = 'demo';
            $number = \App\Services\SeriesNumbering::next($model, '01', 'FF01', '#', 1);
            $this->db->table('documents')->insert(['document_type_id' => '01', 'series' => 'FF01', 'number' => $number, 'fiscal_environment' => 'demo', 'establishment_id' => 1]);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (!str_contains($worker->getOutput(), "READY\n")) {
                if (!$worker->isRunning() || microtime(true) > $deadline) self::fail('Delete worker failed before lock barrier.');
                usleep(10000);
            }
            self::assertTrue($worker->isRunning());
            self::assertSame("READY\n", $worker->getOutput());
            $this->db->commit();
            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $result = json_decode(substr($worker->getOutput(), strlen("READY\n")), true, 512, JSON_THROW_ON_ERROR);
            self::assertFalse($result['success']);
            self::assertSame(1, $this->db->table('series')->count());
            self::assertSame(1, $this->db->table('documents')->count());
        } finally {
            if ($this->db->transactionLevel()) $this->db->rollBack();
            if ($worker->isRunning()) $worker->stop();
        }
    }

    public static function requestedNumbers(): array { return [['#', 'FF01', false], [100, 'FF01', false], ['#', '', false], [100, '', false], ['#', '', true], [100, '', true]]; }
}
