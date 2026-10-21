<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** A report owns its consistent MySQL read; it never changes session isolation. */
final class ReadSnapshot
{
    private static ?\WeakMap $active = null;

    public static function run($connection, callable $read)
    {
        $pdo = $connection->getPdo();
        self::$active ??= new \WeakMap();
        if ($connection->transactionLevel() > 0) {
            if ($connection->getDriverName() === 'mysql' && !isset(self::$active[$pdo])) {
                throw new \LogicException('El reporte debe iniciar su lectura consistente antes de otra transacción MySQL.');
            }
            return $read();
        }
        if ($connection->getDriverName() === 'mysql') $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        return $connection->transaction(function () use ($pdo, $read) {
            self::$active[$pdo] = true;
            try { return $read(); }
            finally { unset(self::$active[$pdo]); }
        });
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
