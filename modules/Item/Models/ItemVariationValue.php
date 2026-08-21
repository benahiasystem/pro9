<?php

namespace Modules\Item\Models;

use App\Models\Tenant\Item;
use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registra qué valor de variable representa un item derivado (variación).
 * Un item variación tiene una fila por variable (ej. Talla=M, Color=Rojo).
 */
class ItemVariationValue extends ModelTenant
{
    protected $fillable = [
        'item_id',
        'product_variable_id',
        'product_variable_value_id',
    ];

    /**
     * @return BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo
     */
    public function variable()
    {
        return $this->belongsTo(ProductVariable::class, 'product_variable_id');
    }

    /**
     * @return BelongsTo
     */
    public function value()
    {
        return $this->belongsTo(ProductVariableValue::class, 'product_variable_value_id');
    }
}
