<?php

namespace App\Tenancy;

// ########## INICIO CAMBIO POSTGRESQL 17
class MigratesTenants extends \Hyn\Tenancy\Listeners\Database\MigratesTenants
{
    public function migrate(\Hyn\Tenancy\Abstracts\WebsiteEvent $event): bool
    {
        // PostgreSQL's driver includes migration/seeding in its compensation scope.
        if ($this->connection->generateConfigurationArray($event->website)['driver'] === 'pgsql') return true;
        return parent::migrate($event);
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
