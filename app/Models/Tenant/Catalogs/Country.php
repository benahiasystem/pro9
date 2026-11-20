<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class Country extends ModelCatalog
{
    use UsesTenantConnection;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
}