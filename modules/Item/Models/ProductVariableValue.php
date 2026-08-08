<?php

namespace Modules\Item\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor de una variable de producto (S, M, L, Rojo, Azul...).
 */
class ProductVariableValue extends ModelTenant
{
    protected $fillable = [
        'product_variable_id',
        'value',
        'color',
        'position',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * @return BelongsTo
     */
    public function variable()
    {
        return $this->belongsTo(ProductVariable::class, 'product_variable_id');
    }

    public function scopeWhereActive($query)
    {
        return $query->where('active', true);
    }
}
