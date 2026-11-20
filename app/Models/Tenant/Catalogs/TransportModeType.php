<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class TransportModeType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_transport_mode_types";
    public $incrementing = false;
    protected $keyType = 'string';
}