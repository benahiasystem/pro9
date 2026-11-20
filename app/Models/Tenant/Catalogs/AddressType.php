<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class AddressType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = 'cat_address_types';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
}