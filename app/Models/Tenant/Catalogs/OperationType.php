<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;

class OperationType extends ModelCatalog
{
    use UsesTenantConnection;

    public const INACTIVE_INCOTERM_IDS = ['0201', '0202', '0203'];

    protected $table = "cat_operation_types";
    public $incrementing = false;
    public $timestamps = false;
}
