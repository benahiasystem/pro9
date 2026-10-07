<?php
// ######## INICIO API BCV ########
namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BcvMySqlConcurrencyTest extends TestCase
{
    public function test_parallel_queries_keep_the_first_daily_rate(): void
    {
        if (getenv('PRO9_FISCAL_MYSQL_TESTS') !== '1') $this->markTestSkipped('Requiere MySQL temporal aislado.');
        $env = \Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__, 2).'/.env'));
        $connection = ['driver' => 'mysql', 'host' => getenv('DB_HOST') ?: $env['DB_HOST'],
            'port' => $env['DB_PORT'] ?? 3306, 'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'],
            'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
        config()->set('database.connections.bcv_control', $connection);
        $control = DB::connection('bcv_control');
        $database = 'pro9_bcv_test_'.bin2hex(random_bytes(6));
        $control->statement('CREATE DATABASE `'.$database.'`');
        $connection['database'] = $database;
        config()->set('database.connections.bcv_test', $connection);
        $db = DB::connection('bcv_test');
        $processes = [];
        $secondDatabase = $database.'_b';
        $secondCreated = false;
        try {
            $db->statement('CREATE TABLE exchange_rates (date DATE PRIMARY KEY, date_original DATE NOT NULL,
                sale DECIMAL(18,8) NOT NULL, purchase DECIMAL(18,8) NOT NULL,
                sale_original DECIMAL(18,8) NOT NULL, purchase_original DECIMAL(18,8) NOT NULL,
                created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL) ENGINE=InnoDB');
            $db->beginTransaction();
            // Invisible a los SELECT de workers, pero bloquea sus INSERT hasta liberar la barrera.
            $db->table('exchange_rates')->insert(['date' => '2026-10-07', 'date_original' => '2026-10-07',
                'sale' => 1, 'purchase' => 1, 'sale_original' => 1, 'purchase_original' => 1]);
            foreach (['111.12345678', '222.45678901'] as $rate) {
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/bcv_concurrency_worker.php']);
                $process->setInput(json_encode(['connection' => $connection, 'rate' => $rate]));
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            foreach ($processes as $process) {
                while (!str_contains($process->getOutput(), "READY\n")) {
                    if (!$process->isRunning() || microtime(true) > $deadline) self::fail('Worker no llegó a la barrera BCV.');
                    usleep(10000);
                }
                self::assertTrue($process->isRunning());
            }
            $db->rollBack();
            $results = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode(substr($process->getOutput(), strlen("READY\n")), true);
            }
            self::assertSame($results[0], $results[1]);
            self::assertSame(1, $db->table('exchange_rates')->count());
            self::assertContains($results[0]['sale'], ['111.12345678', '222.45678901']);
            self::assertEquals($results[0]['sale'], $db->table('exchange_rates')->value('sale'));
            // Dos bases temporales: ninguna tasa puede compartirse entre clientes.
            $control->statement('CREATE DATABASE `'.$secondDatabase.'`');
            $secondCreated = true;
            $control->statement('CREATE TABLE `'.$secondDatabase.'`.exchange_rates LIKE `'.$database.'`.exchange_rates');
            $secondConnection = $connection;
            $secondConnection['database'] = $secondDatabase;
            config()->set('database.connections.bcv_test_b', $secondConnection);
            $active = 'bcv_test';
            $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName'])->getMock();
            $tenancy->method('tenantName')->willReturnCallback(function () use (&$active) { return $active; });
            app()->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
            \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-07 10:00:00', 'America/Caracas'));
            $client = $this->createMock(\App\Services\ExchangeRates\BcvClient::class);
            $client->expects(self::once())->method('dollarRate')->willReturn('333.78912345');
            $service = new \App\Services\ExchangeRates\TenantExchangeRateService($client);
            self::assertEquals($results[0]['sale'], $service->exchange('2026-10-07')['sale']);
            $active = 'bcv_test_b';
            self::assertSame('333.78912345', $service->exchange('2026-10-07')['sale']);
            self::assertEquals($results[0]['sale'], $db->table('exchange_rates')->value('sale'));
            self::assertSame(1, DB::connection('bcv_test_b')->table('exchange_rates')->count());
        } finally {
            if ($db->transactionLevel()) $db->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
            \Carbon\Carbon::setTestNow();
            DB::purge('bcv_test_b');
            if ($secondCreated) $control->statement('DROP DATABASE `'.$secondDatabase.'`');
            DB::purge('bcv_test');
            $control->statement('DROP DATABASE `'.$database.'`');
            DB::purge('bcv_control');
        }
    }
}
// ######## FIN API BCV ########
