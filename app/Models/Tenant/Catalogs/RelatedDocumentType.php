<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Support\Facades\Schema;

class RelatedDocumentType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_related_documents_types";
    public $incrementing = false;

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function available()
    {
        return Schema::connection('tenant')->hasTable((new static())->getTable())
            ? static::query()->get()
            : collect();
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
}
