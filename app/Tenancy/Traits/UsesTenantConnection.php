<?php

namespace App\Tenancy\Traits;

trait UsesTenantConnection
{
    public function getConnectionName()
    {
        return config('tenancy.db.tenant-connection-name', 'tenant');
    }
}
