<?php

namespace Modules\Accounting\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;
use Modules\Accounting\Support\TenantDB;
use PHPUnit\Framework\TestCase;

final class TenantDBIsolationTest extends TestCase
{
    public function test_a_retried_transaction_keeps_a_consistent_snapshot_without_changing_the_session_default(): void
    {
        if (getenv('ACCOUNTING_ISOLATED_TESTS') !== '1' || getenv('ACCOUNTING_TEST_DATABASE') !== 'accounting_module_test' || getenv('ACCOUNTING_TEST_HOST') !== 'accounting-db') {
            throw new \RuntimeException('Esta prueba exige la base temporal y la red aislada.');
        }
        $previous = Facade::getFacadeApplication();
        $container = new Container();
        $container->instance('config', new Repository(['database' => ['default' => 'tenant']]));
        $manager = new Manager($container);
        $config = ['driver' => 'mysql', 'host' => 'accounting-db', 'database' => 'accounting_module_test', 'username' => getenv('ACCOUNTING_TEST_USERNAME'), 'password' => getenv('ACCOUNTING_TEST_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        $manager->addConnection($config, 'tenant');
        $manager->addConnection($config, 'writer');
        $container->instance('db', $manager->getDatabaseManager());
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstance('db');
        $connection = $manager->getConnection('tenant');
        $writer = $manager->getConnection('writer');
        $table = 'accounting_test_snapshot_' . bin2hex(random_bytes(6));
        try {
            $connection->statement("CREATE TABLE {$table} (id INT PRIMARY KEY) ENGINE=InnoDB");
            $connection->table($table)->insert(['id' => 1]);
            // This discarded, scratch-only session deliberately differs from
            // the module's requirement. Production never sets SESSION options.
            $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $attempts = 0;
            $counts = TenantDB::transaction(function () use (&$attempts, $connection, $writer, $table) {
                ++$attempts;
                $before = $connection->table($table)->count();
                if ($attempts === 1) {
                    throw new \PDOException('Deadlock found when trying to get lock', 40001);
                }
                $writer->table($table)->insert(['id' => 2]);
                return TenantDB::readSnapshot(fn() => [$before, $connection->table($table)->count()]);
            }, 2);
            $this->assertSame(2, $attempts);
            $this->assertSame([1, 1], $counts);
            $this->assertSame(2, $connection->table($table)->count());
            $this->assertSame('READ-COMMITTED', $connection->selectOne('SELECT @@session.tx_isolation AS mode')->mode);
            try {
                $connection->transaction(fn() => TenantDB::readSnapshot(fn() => null));
                $this->fail('Una transacción ajena no acredita una lectura consistente.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('transacción ajena', $error->getMessage());
            }
        } finally {
            $connection->statement("DROP TABLE IF EXISTS {$table}");
            $manager->getDatabaseManager()->purge('tenant');
            $manager->getDatabaseManager()->purge('writer');
            Facade::setFacadeApplication($previous);
            Facade::clearResolvedInstance('db');
        }
    }
}
