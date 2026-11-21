<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class NoteCreditType extends ModelCatalog
{
    use UsesTenantConnection;
    
    protected $table = "cat_note_credit_types";
    public $incrementing = false;
    protected $keyType = 'string';

    public const PAYMENT_DATE_ADJUSTMENTS_CODE = '13';

}