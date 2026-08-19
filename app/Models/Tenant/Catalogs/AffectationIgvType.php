<?php

namespace App\Models\Tenant\Catalogs;

use App\Models\Tenant\TechnicalServiceItem;
use Hyn\Tenancy\Traits\UsesTenantConnection;

class AffectationIgvType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $fillable = [
        'active',
    ];

    protected $table = "cat_affectation_igv_types";
    public $incrementing = false;
    public $timestamps = false;

    // ########## INICIO CAMBIO AFECTACIÓN IVA
    public function scopeWhereActive($query)
    {
        return $query
            ->where('active', true)
            ->whereIn('id', \App\Support\Venezuela\Localization::selectableAffectationIds());
    }
    // ######### FIN CAMBIO AFECTACIÓN IVA

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public  function technical_service_item()
    {
        return $this->hasMany(TechnicalServiceItem::class, 'affectation_igv_type_id');
    }

    public function getRowResource()
    {
        return [
            'active' => (bool)$this->active,
            'id' => $this->id,
            'description' => $this->description,
        ];
    }
}
