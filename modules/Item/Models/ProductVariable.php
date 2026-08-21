<?php

namespace Modules\Item\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Variable de producto (Talla, Color, Material...) usada para
 * generar variaciones (items derivados) desde el formulario de items.
 */
class ProductVariable extends ModelTenant
{
    public const VALUE_TYPE_LIST = 'list';
    public const VALUE_TYPE_COLOR = 'color';

    protected $fillable = [
        'name',
        'value_type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * @return HasMany
     */
    public function values()
    {
        return $this->hasMany(ProductVariableValue::class)->orderBy('position');
    }

    /**
     * @return HasMany
     */
    public function itemVariationValues()
    {
        return $this->hasMany(ItemVariationValue::class, 'product_variable_id');
    }

    public function scopeWhereActive($query)
    {
        return $query->where('active', true);
    }
}
