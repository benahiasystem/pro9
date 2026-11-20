<?php

namespace Modules\Accounting\Support;

use Illuminate\Support\Facades\DB;
final class TenantDB
{
    private static ?\WeakMap $consistent = null;

    /** Own the isolation of this transaction, without changing session defaults. */
    public static function transaction(callable $callback, int $attempts = 1): mixed
    {
        $connection = DB::connection('tenant');
        $attempts = max(1, min(3, $attempts));
        if ($connection->transactionLevel() > 0) {
            return $connection->transaction($callback, 1);
        }
        self::$consistent ??= new \WeakMap();
        $pdo = $connection->getPdo();
        for ($attempt = 1; ; ++$attempt) {
            try {
                return $connection->transaction(function () use ($connection, $pdo, $callback) {
                    $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                    self::$consistent[$pdo] = true;
                    try {
                        return $callback();
                    } finally {
                        unset(self::$consistent[$pdo]);
                    }
                });
            } catch (\Throwable $error) {
                if ($attempt >= $attempts || !\App\Database\SqlErrors::retryable($error)) {
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
        if ($connection->transactionLevel() < 1 || self::$consistent === null || !isset(self::$consistent[$connection->getPdo()])) {
            throw new \LogicException('Inicia la lectura consistente del informe antes de una transacción ajena al módulo.');
        }
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return DB::connection('tenant')->{$method}(...$arguments);
    }
}
