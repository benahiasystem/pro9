<?php

namespace App\Tenancy;

use Hyn\Tenancy\Database\Connection;
use Hyn\Tenancy\Events\Websites\Created;
use Hyn\Tenancy\Events\Websites\Deleted;
use Hyn\Tenancy\Events\Websites\Updated;
use Hyn\Tenancy\Contracts\Website;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Hyn extension; never replace the package's vendor files. */
class PostgresDriver implements \Hyn\Tenancy\Contracts\Webserver\DatabaseGenerator
{
    public function created(Created $event, array $config, Connection $connection): bool
    {
        // Hyn changes host to '%' for MySQL grants; recover the actual host.
        $config = $connection->generateConfigurationArray($event->website);
        $id = app(TenantProvisioning::class)->create($config, $event->website->id, function () use ($event, $connection) {
            if (!$connection->migrate($event->website, database_path('migrations/tenant'))) throw new \RuntimeException('No se completaron las migraciones tenant.');
            $class = config('tenancy.db.tenant-seed-class');
            if ($class && !$connection->seed($event->website, $class)) throw new \RuntimeException('No se completaron las semillas tenant.');
        });
        TenantOnboarding::track($id, $event->website->id, $config);
        return true;
    }
    public function updated(Updated $event, array $config, Connection $connection): bool
    {
        $old = $event->dirty['uuid'] ?? null;
        if ($old) {
            $connection->purge();
            $previous=clone $event->website;$previous->uuid=$old;
            app(TenantProvisioning::class)->rename($old, $connection->generateConfigurationArray($event->website),$connection->generateConfigurationArray($previous)['password']);
        }
        return true;
    }
    public function deleted(Deleted $event, array $config, Connection $connection): bool
    {
        $connection->purge();
        app(TenantProvisioning::class)->delete($event->website->uuid, (bool) config('tenancy.db.auto-delete-tenant-database-user'));
        return true;
    }
    public function updatePassword(Website $website, array $config, Connection $connection): bool
    {
        app(TenantProvisioning::class)->password($website->uuid, $config['password']);
        $connection->purge();
        return true;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
