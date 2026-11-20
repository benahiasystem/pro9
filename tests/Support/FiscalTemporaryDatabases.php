<?php

namespace Tests\Support;

/** Sólo registra bases creadas por este proceso; nunca busca ni elimina otras. */
final class FiscalTemporaryDatabases
{
    private static array $owned = [];
    private static ?int $ownerPid = null;

    public static function register(string $name, \PDO $control): void
    {
        if (!preg_match('/^sab_fiscal_test_[a-f0-9]{12}$/D', $name)) throw new \InvalidArgumentException('Nombre de base temporal inválido.');
        if (self::$ownerPid === null) {
            self::$ownerPid = getmypid();
            register_shutdown_function(static function () use ($control): void {
                if (getmypid() !== self::$ownerPid) return;
                foreach (array_keys(self::$owned) as $database) {
                    try { $control->exec('DROP DATABASE IF EXISTS "' . $database . '" WITH (FORCE)'); }
                    catch (\Throwable $error) { error_log('No se pudo eliminar la base fiscal temporal propia: ' . $database); }
                }
            });
        }
        self::$owned[$name] = true;
    }

    public static function forget(string $name): void
    {
        unset(self::$owned[$name]);
    }
}
