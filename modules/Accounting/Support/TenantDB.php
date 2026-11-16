<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
final class TenantDB
{
    use \Illuminate\Database\DetectsConcurrencyErrors;
    private static ?\WeakMap $consistent = null;

    /** Own the isolation of this transaction, without changing session defaults. */
    public static function transaction(callable $callback, int $attempts = 1): mixed
    {
        $connection = DB::connection('tenant');
        if ($connection->transactionLevel() > 0) {
            return $connection->transaction($callback, $attempts);
        }
        self::$consistent ??= new \WeakMap();
        $pdo = $connection->getPdo();
        for ($attempt = 1; ; ++$attempt) {
            // MySQL consumes SET TRANSACTION at BEGIN. Reapply it for every
            // deadlock retry, even when SAB's session default differs.
            if ($connection->getDriverName() === 'mysql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            try {
                return $connection->transaction(function () use ($pdo, $callback) {
                    self::$consistent[$pdo] = true;
                    try {
                        return $callback();
                    } finally {
                        unset(self::$consistent[$pdo]);
                    }
                });
            } catch (\Throwable $error) {
                if ($attempt >= $attempts || !(new self())->causedByConcurrencyError($error)) {
                    throw $error;
                }
            }
        }
    }

    public static function readSnapshot(callable $read): mixed
    {
        $connection = DB::connection('tenant');
        if ($connection->transactionLevel() > 0) {
            self::assertReadSnapshot();
            return $read();
        }
        return self::transaction($read);
    }

    public static function assertReadSnapshot(): void
    {
        $connection = DB::connection('tenant');
        if ($connection->transactionLevel() < 1 || $connection->getDriverName() === 'mysql' && (self::$consistent === null || !isset(self::$consistent[$connection->getPdo()]))) {
            throw new \LogicException('Inicia la lectura consistente del informe antes de una transacción ajena al módulo.');
        }
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return DB::connection('tenant')->{$method}(...$arguments);
    }
}
