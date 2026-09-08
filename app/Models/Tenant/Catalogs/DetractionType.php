<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Support\Facades\Schema;

class DetractionType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_detraction_types";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'active',
        'percentage',
        'operation_type_id',
        'description',
    ];

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function available()
    {
        return Schema::connection('tenant')->hasTable((new static())->getTable())
            ? static::whereActive()->get()
            : collect();
    }

    public static function findAvailable($id)
    {
        return Schema::connection('tenant')->hasTable((new static())->getTable())
            ? static::query()->find($id)
            : null;
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

}
