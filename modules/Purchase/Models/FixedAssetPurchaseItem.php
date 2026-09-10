<?php

namespace Modules\Purchase\Models;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\ModelTenant;
use App\Traits\AttributePerItems;

class FixedAssetPurchaseItem extends ModelTenant
{

    use AttributePerItems;
    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    // ISC se conserva como relación histórica, pero no se precarga en tenants sin su catálogo.
    protected $with = ['affectation_igv_type', 'price_type'];
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    public $timestamps = false;

    protected $fillable = [
        'fixed_asset_purchase_id',
        'fixed_asset_item_id',
        'item',
        'quantity',
        'unit_value',

        'affectation_igv_type_id',
        'total_base_igv',
        'percentage_igv',
        'total_igv',

        'total_base_other_taxes',
        'percentage_other_taxes',
        'total_other_taxes',
        'total_taxes',

        'price_type_id',
        'unit_price',

        'total_value',
        'total',

        'attributes',
        'charges',
        'discounts'
    ];

    public function getItemAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setItemAttribute($value)
    {
        $this->attributes['item'] = (is_null($value))?null:json_encode($value);
    }

    public function getAttributesAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setAttributesAttribute($value)
    {
        $this->attributes['attributes'] = (is_null($value))?null:json_encode($value);
    }

    public function getChargesAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setChargesAttribute($value)
    {
        $this->attributes['charges'] = (is_null($value))?null:json_encode($value);
    }

    public function getDiscountsAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setDiscountsAttribute($value)
    {
        $this->attributes['discounts'] = (is_null($value))?null:json_encode($value);
    }

    public function affectation_igv_type()
    {
        return $this->belongsTo(AffectationIgvType::class, 'affectation_igv_type_id');
    }

    public function price_type()
    {
        return $this->belongsTo(PriceType::class, 'price_type_id');
    }

    public function fixed_asset_purchase()
    {
        return $this->belongsTo(FixedAssetPurchase::class);
    }

    public function relation_item()
    {
        return $this->belongsTo(FixedAssetItem::class, 'fixed_asset_item_id');
    }
}
