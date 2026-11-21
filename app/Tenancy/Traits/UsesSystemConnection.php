<?php

namespace App\Tenancy\Traits;

trait UsesSystemConnection
{
    public function getConnectionName()
    {
        return config('tenancy.db.system-connection-name', 'system');
    }
}
