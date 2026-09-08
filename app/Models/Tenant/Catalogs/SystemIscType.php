<?php

namespace App\Models\Tenant\Catalogs;

use App\Models\Tenant\TechnicalServiceItem;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Support\Facades\Schema;

class SystemIscType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_system_isc_types";
    public $incrementing = false;

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function available()
    {
        if (!Schema::connection('tenant')->hasTable((new static())->getTable())) {
            return collect();
        }

        return static::whereActive()->orderByDescription()->get();
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public  function technical_service_item()
    {
        return $this->hasMany(TechnicalServiceItem::class, 'system_isc_type_id');
    }
}
