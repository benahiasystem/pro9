<?php

namespace Modules\PseService\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class PseProvider extends ModelTenant
{

    protected $fillable = ['name', 'description', 'active'];

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public static function available()
    {
        if (!Schema::connection('tenant')->hasTable((new static())->getTable())) {
            return collect();
        }

        return static::select('id', 'name', 'description', 'active')->where('active', true)->get();
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    protected static function newFactory()
    {
        return \Modules\PseService\Database\factories\PseProviderFactory::new();
    }
}
