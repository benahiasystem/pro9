<?php

namespace Tests\Unit;

use App\Support\Exact\ReadSnapshot;
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactReadSnapshotTest extends TestCase
{
    private function connection()
    {
        return new class {
            public int $level = 0;
            public array $statements = [];
            private object $pdo;
            public function __construct() { $this->pdo = new \stdClass(); }
            public function getPdo() { return $this->pdo; }
            public function getDriverName() { return 'mysql'; }
            public function transactionLevel() { return $this->level; }
            public function statement($sql) { $this->statements[] = $sql; }
            public function transaction($callback) {
                $this->level++;
                try { return $callback(); } finally { $this->level--; }
            }
        };
    }

    public function test_owned_scope_can_nest_without_resetting_isolation_or_creating_another_transaction(): void
    {
        $connection = $this->connection();
        self::assertSame('value',ReadSnapshot::run($connection,fn()=>ReadSnapshot::run($connection,fn()=>'value')));
        self::assertSame(['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'],$connection->statements);
        self::assertSame(0,$connection->level);
    }

    public function test_an_existing_mysql_transaction_does_not_silently_downgrade_consistency(): void
    {
        $connection = $this->connection(); $connection->level = 1;
        $this->expectException(\LogicException::class);
        ReadSnapshot::run($connection,fn()=>self::fail('No debe leer sin controlar el corte.'));
    }

    public function test_failure_clears_the_scope_and_another_connection_cannot_inherit_it(): void
    {
        $connection = $this->connection();
        try { ReadSnapshot::run($connection,fn()=>throw new \RuntimeException('Fixture')); }
        catch (\RuntimeException $exception) { self::assertSame('Fixture',$exception->getMessage()); }
        $connection->level = 1;
        try { ReadSnapshot::run($connection,fn()=>self::fail('El marcador no debe persistir.')); }
        catch (\LogicException $exception) { self::assertStringContainsString('lectura consistente',$exception->getMessage()); }
        $connection->level = 0;
        $other = $this->connection(); $other->level = 1;
        $this->expectException(\LogicException::class);
        ReadSnapshot::run($connection,fn()=>ReadSnapshot::run($other,fn()=>self::fail('Conexiones distintas.')));
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
