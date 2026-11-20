<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** A report owns its consistent database read; it never changes session isolation. */
final class ReadSnapshot
{
    private static ?\WeakMap $active = null;

    public static function run($connection, callable $read)
    {
        $pdo = $connection->getPdo();
        self::$active ??= new \WeakMap();
        if ($connection->transactionLevel() > 0) {
            if ($connection->getDriverName() === 'pgsql' && !isset(self::$active[$pdo])) {
                throw new \LogicException('El reporte debe iniciar su lectura consistente antes de otra transacción.');
            }
            return $read();
        }
        return $connection->transaction(function () use ($connection, $pdo, $read) {
            if ($connection->getDriverName() === 'pgsql') $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            self::$active[$pdo] = true;
            try { return $read(); }
            finally { unset(self::$active[$pdo]); }
        });
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
