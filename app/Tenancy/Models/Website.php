<?php

namespace App\Tenancy\Models;

use App\Tenancy\Abstracts\SystemModel;
use App\Tenancy\Contracts\Tenant as ActiveTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tenancy\Identification\Concerns\AllowsTenantIdentification;
use Tenancy\Identification\Contracts\Tenant;

class Website extends SystemModel implements Tenant, ActiveTenant
{
    use AllowsTenantIdentification, SoftDeletes;

    public function hostnames(): HasMany
    {
        return $this->hasMany(Hostname::class);
    }
}
