<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\{TransactionCommitted, TransactionCommitting, TransactionRolledBack};
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class StorageLock
{
    private static array $held = [];
    private static ?\WeakMap $dispatchers = null;

    public function run(string $scope, callable $callback, int $timeout = 10, ?Connection $transaction = null)
    {
        self::releaseFinished();
        $connection = DB::connection(config('tenancy.db.system-connection-name', 'system'));
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('El bloqueo de almacenamiento requiere PostgreSQL.');
        }
        $key = hash('sha256', $connection->getDatabaseName().':'.config('tenant_storage.connection.bucket').':'.$scope);
        $local = spl_object_id(app()).':'.$key;
        if (isset(self::$held[$local])) {
            $this->assertOwned($scope);
            return $callback();
        }

        if ($transaction && $transaction->transactionLevel() > 0) {
            $this->watch($transaction);
        } else {
            $transaction = null;
        }
        $pdo = $connection->getPdo();
        $parts = unpack('Nfirst/Nsecond', hex2bin(substr($key, 0, 16)));
        $keys = [$parts['first'] & 0x7fffffff, $parts['second'] & 0x7fffffff];
        $statement = $pdo->prepare('SELECT pg_try_advisory_lock(CAST(? AS integer), CAST(? AS integer))::int');
        $deadline = hrtime(true) + $timeout * 1_000_000_000;
        do {
            $statement->execute($keys);
            if ((int) $statement->fetchColumn() === 1) {
                break;
            }
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Otro proceso está trabajando en estos archivos; reintente.');
            }
            usleep(50_000);
        } while (true);

        self::$held[$local] = compact('pdo', 'keys', 'transaction', 'scope');
        try {
            return $callback();
        } finally {
            if (!$transaction || $transaction->transactionLevel() === 0) {
                self::release($local);
            }
        }
    }

    public function assertOwned(string $scope): void
    {
        $prefix = spl_object_id(app()).':';
        foreach (self::$held as $local => $lock) {
            if (!str_starts_with($local, $prefix) || $lock['scope'] !== $scope) {
                continue;
            }
            $statement = $lock['pdo']->prepare("SELECT EXISTS (SELECT 1 FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid() AND granted AND classid = CAST(? AS oid) AND objid = CAST(? AS oid) AND objsubid = 2)::int");
            $statement->execute($lock['keys']);
            if ((int) $statement->fetchColumn() === 1) {
                return;
            }
        }
        throw new RuntimeException('Se perdió el bloqueo de almacenamiento; reintente.');
    }

    private function watch(Connection $connection): void
    {
        $dispatcher = $connection->getEventDispatcher();
        if (!$dispatcher) {
            throw new RuntimeException('La transacción tenant requiere eventos de confirmación y reversión.');
        }
        self::$dispatchers ??= new \WeakMap();
        if (isset(self::$dispatchers[$dispatcher])) {
            return;
        }
        $dispatcher->listen(TransactionCommitting::class, function ($event): void {
            if ($event->connection->transactionLevel() === 1) {
                foreach (self::$held as $lock) {
                    if ($lock['transaction'] === $event->connection) $this->assertOwned($lock['scope']);
                }
            }
        });
        $dispatcher->listen([TransactionCommitted::class, TransactionRolledBack::class], static function ($event): void {
            if ($event->connection->transactionLevel() === 0) {
                foreach (self::$held as $local => $lock) {
                    if ($lock['transaction'] === $event->connection) {
                        self::release($local);
                    }
                }
            }
        });
        self::$dispatchers[$dispatcher] = true;
    }

    public static function releaseFinished(): void
    {
        foreach (self::$held as $local => $lock) {
            if ($lock['transaction'] && $lock['transaction']->transactionLevel() === 0) {
                self::release($local);
            }
        }
    }

    private static function release(string $local): void
    {
        $lock = self::$held[$local] ?? null;
        unset(self::$held[$local]);
        if (!$lock) {
            return;
        }
        try {
            $statement = $lock['pdo']->prepare('SELECT pg_advisory_unlock(CAST(? AS integer), CAST(? AS integer))');
            $statement->execute($lock['keys']);
        } catch (Throwable $ignored) {
            // The server releases session locks when the connection closes.
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
