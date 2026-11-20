<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Publish central metadata only after the tenant's initial business data commits. */
final class TenantOnboarding
{
    private static ?array $runs = null;
    private static $tenant = null;
    public static function active(): bool { return self::$runs !== null; }
    public static function track(string $id, int $website, array $config): void
    {
        if (!self::active()) return;
        if (self::$runs) throw new \LogicException('Un alta sólo puede crear una empresa.');
        self::$runs[] = ['id'=>$id,'website'=>$website,'config'=>$config];
    }
    public static function beginTenant(): void
    {
        if (!self::active() || !self::$runs || self::$tenant) return;
        self::$tenant = DB::connection('tenant');
        if (self::$tenant->getDatabaseName() !== self::$runs[0]['config']['database'] || self::$tenant->transactionLevel()) throw new \LogicException('El alta requiere su propia transacción tenant.');
        self::$tenant->beginTransaction();
    }
    public static function run(callable $register)
    {
        $central = DB::connection('system');
        if ($central->getDriverName() !== 'pgsql') return $register();
        if (self::active() || $central->transactionLevel()) throw new \LogicException('El alta requiere su propia transacción central.');
        $environment = app(\App\Tenancy\Environment::class); $previous = $environment->tenant();
        $default = DB::getDefaultConnection(); $committed = false;
        self::$runs = []; $central->beginTransaction();
        try {
            $result = $register();
            if (!is_array($result) || empty($result['success'])) {
                self::rollback($central);
                return $result;
            }
            if (self::$runs) {
                if (!self::$tenant || self::$tenant->transactionLevel() !== 1) throw new \LogicException('No se completó la transacción inicial de la empresa.');
                foreach (['companies','configurations','users'] as $table) if (!self::$tenant->table($table)->exists()) throw new \LogicException('Faltan los datos iniciales de '.$table.'.');
                self::$tenant->commit();
            }
            $central->commit(); $committed = true;
            foreach (self::$runs as $run) app(TenantProvisioning::class)->complete($run['id'], $run['website']);
            return $result;
        } catch (\Throwable $error) {
            if (!$committed) self::rollback($central);
            throw $error;
        } finally {
            self::$tenant = null; self::$runs = null;
            TenantJobContext::clear();if ($previous) $environment->tenant($previous); DB::setDefaultConnection($default);
        }
    }
    private static function rollback($central): void
    {
        if (self::$tenant) while (self::$tenant->transactionLevel()) self::$tenant->rollBack();
        while ($central->transactionLevel()) $central->rollBack();
        foreach (self::$runs ?? [] as $run) app(TenantProvisioning::class)->recover($run['id'], false);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
