<?php
namespace Tests\Unit;

use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Process\Process;
use Tests\Support\SeriesDatabaseTestCase;

/** @group postgres-integration */
class SeriesMySqlConcurrencyTest extends SeriesDatabaseTestCase
{
    private $database;
    private $connection = [];

    protected function configureConnections(Manager $manager): void
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1') $this->markTestSkipped('Requires an isolated temporary PostgreSQL database.');
        $config = \Tests\Support\PostgresTestEnvironment::connection();
        $manager->addConnection($config, 'control');
        \Tests\Support\PostgresTestEnvironment::assertServer($manager->getConnection('control'));
        $this->database = 'sab_series_test_' . bin2hex(random_bytes(6));
        $manager->getConnection('control')->statement('CREATE DATABASE "' . $this->database . '" TEMPLATE template0');
        $config['database'] = $this->database;
        $this->connection = $config;
        $manager->addConnection($config, 'tenant');
    }

    protected function createTable(string $table, string $columns): void
    {
        $columns = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'BIGSERIAL PRIMARY KEY', $columns);
        $this->db->statement("CREATE TABLE $table ($columns)");
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database && preg_match('/\Asab_series_test_[a-f0-9]{12}\z/', $this->database)) {
                $this->manager->getConnection('tenant')->disconnect();
                $this->manager->getConnection('control')->statement('DROP DATABASE "' . $this->database . '" WITH (FORCE)');
            }
        } finally { parent::tearDown(); }
    }

    /** Observe the real PostgreSQL wait, rather than merely a connected worker. */
    private function waitForLock(Process $process, float $deadline): string
    {
        $header = null;
        while (microtime(true) < $deadline) {
            if (preg_match('/\AREADY ([1-9][0-9]*)\n/', $process->getOutput(), $match)) {
                $header = $match[0];
                $activity = $this->manager->getConnection('control')->selectOne(
                    'SELECT wait_event_type, datname FROM pg_stat_activity WHERE pid = ?', [$match[1]]
                );
                if ($activity && $activity->datname === $this->database && $activity->wait_event_type === 'Lock') {
                    self::assertTrue($process->isRunning());
                    self::assertSame($header, $process->getOutput());
                    return $header;
                }
            }
            if (!$process->isRunning()) break;
            usleep(10000);
        }
        self::fail('Worker did not reach its PostgreSQL lock barrier. Exit: '.var_export($process->getExitCode(), true)
            .' Output: '.$process->getOutput().' Error: '.$process->getErrorOutput());
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
            for ($i = 0; $i < 20; $i++) {
                $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/series_concurrency_worker.php']);
                $process->setInput(json_encode(['connection' => $this->connection, 'number' => $requested, 'series' => $code, 'branch' => $separateBranches ? ($i % 2) + 1 : 1], JSON_THROW_ON_ERROR));
                $process->setTimeout(90); $process->start(); $processes[] = $process;
            }
            $deadline = microtime(true) + 60;
            $headers = [];
            foreach ($processes as $index => $process) {
                $headers[$index] = $this->waitForLock($process, $deadline);
            }
            self::assertCount(20, array_unique($headers), 'Each worker must own an independent PostgreSQL connection.');
            $this->db->commit();
            $results = [];
            foreach ($processes as $index => $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode(substr($process->getOutput(), strlen($headers[$index])), true, 512, JSON_THROW_ON_ERROR);
            }
            $numbers = $this->db->table('documents')->orderBy('number')->pluck('number')->map(fn ($n) => (int) $n)->all();
            self::assertSame($separateBranches ? ($requested === '#' ? array_merge(...array_map(static fn ($n)=>[$n,$n],range(100,109))) : [100,100]) : ($requested === '#' ? range(100,119) : [100]), $numbers);
            self::assertSame($requested === '#' ? 0 : ($separateBranches ? 18 : 19), count(array_filter($results, fn ($r) => !empty($r['duplicate']))));
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
            $deadline = microtime(true) + 60;
            $header = $this->waitForLock($worker, $deadline);
            $this->db->commit();
            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $result = json_decode(substr($worker->getOutput(), strlen($header)), true, 512, JSON_THROW_ON_ERROR);
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
