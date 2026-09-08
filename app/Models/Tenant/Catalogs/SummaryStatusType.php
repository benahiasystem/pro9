<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Support\Facades\Schema;

class SummaryStatusType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_summary_status_types";
    public $incrementing = false;

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function available(array $ids = [])
    {
        if (!Schema::connection('tenant')->hasTable((new static())->getTable())) {
            return collect();
        }

        $query = static::query();
        return $ids === [] ? $query->get() : $query->whereIn('id', $ids)->get();
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
}
