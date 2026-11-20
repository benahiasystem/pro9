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
    public static function retryableStates(): array { return [['40001'], ['40P01']]; }

    /** @dataProvider retryableStates */
    public function test_a_retried_transaction_keeps_a_consistent_snapshot_without_changing_the_session_default(string $state): void
    {
        if (getenv('SAB_POSTGRES_TESTS') !== '1') self::markTestSkipped('Utilice PostgreSQL temporal aislado.');
        $fixture = new \Tests\Support\AccountingPostgresFixture();
        $fixture->start(false);
        $connection = \Illuminate\Support\Facades\DB::connection('tenant');
        $config = $connection->getConfig();
        config(['database.connections.writer' => $config]);
        $writer = \Illuminate\Support\Facades\DB::connection('writer');
        \Tests\Support\PostgresTestEnvironment::assertServer($writer);
        $table = 'accounting_test_snapshot_' . bin2hex(random_bytes(6));
        try {
            $connection->statement("CREATE TABLE {$table} (id integer PRIMARY KEY)");
            $connection->table($table)->insert(['id' => 1]);
            // This discarded, scratch-only session deliberately differs from
            // the module's requirement. Production never sets SESSION options.
            $connection->statement('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $attempts = 0;
            $counts = TenantDB::transaction(function () use (&$attempts, $connection, $writer, $table, $state) {
                ++$attempts;
                $before = $connection->table($table)->count();
                if ($attempts === 1) {
                    $connection->unprepared("DO \$controlled\$ BEGIN RAISE EXCEPTION USING ERRCODE = '{$state}', MESSAGE = 'Controlled transaction retry'; END; \$controlled\$;");
                }
                $writer->table($table)->insert(['id' => 2]);
                return TenantDB::readSnapshot(fn() => [$before, $connection->table($table)->count()]);
            }, 2);
            $this->assertSame(2, $attempts);
            $this->assertSame([1, 1], $counts);
            $this->assertSame(2, $connection->table($table)->count());
            $this->assertSame('read committed', $connection->selectOne('SHOW default_transaction_isolation')->default_transaction_isolation);
            try {
                $connection->transaction(fn() => TenantDB::readSnapshot(fn() => null));
                $this->fail('Una transacción ajena no acredita una lectura consistente.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('transacción ajena', $error->getMessage());
            }
        } finally {
            $connection->statement("DROP TABLE IF EXISTS {$table}");
            $fixture->close();
        }
    }
}
