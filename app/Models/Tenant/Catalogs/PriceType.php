<?php

namespace App\Models\Tenant\Catalogs;

use App\Models\Tenant\TechnicalServiceItem;
use App\Tenancy\Traits\UsesTenantConnection;

class PriceType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_price_types";
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public  function technical_service_item()
    {
        return $this->hasMany(TechnicalServiceItem::class, 'price_type_id');
    }
}
