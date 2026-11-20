<?php

namespace App\Tenancy;

// ########## INICIO CAMBIO POSTGRESQL 17
final class TenantReadiness
{
    public static function ensure(string $name): void
    {
        if (TenantOnboarding::active() || config('tenancy.db.tenant-division-mode') === 'bypass') return;
        $central = \Illuminate\Support\Facades\DB::connection('system');
        if ($central->getDriverName() !== 'pgsql' || !$central->getSchemaBuilder()->hasTable('tenant_provisioning_runs')) return;
        if ($central->table('tenant_provisioning_runs')->where('tenant_name', $name)->whereNotIn('state', ['ready','compensated'])->exists()) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'La empresa está completando una operación de base de datos.', null, ['Retry-After'=>'10']);
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
