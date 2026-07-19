<?php

namespace Modules\Marketplace\Models;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Un pulgar arriba de un visitante sobre un producto.
 *
 * A diferencia de una denuncia, es reversible: la fila se crea al recomendar y
 * se borra al quitar. Su existencia es la única fuente de verdad de si este
 * visitante ya recomendó este producto — de ahí que el pulgar pueda reflejar
 * siempre la realidad, sin adivinar desde localStorage.
 */
class Recommendation extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_recommendations';

    // Sin updated_at: una recomendación no se edita, se pone o se quita.
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'item_id',
        'visitor_id',
        'ip',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'item_id' => 'integer',
    ];

    /**
     * Cuáles de estos productos ya recomendó el visitante. Lo usan el feed y la
     * página de tienda para encender el pulgar correcto: es la única forma de
     * que el estado sea real y no una suposición guardada en el navegador.
     */
    public static function recommendedItemIds(?string $visitorId, array $itemIds): array
    {
        if ($visitorId === null || $itemIds === []) {
            return [];
        }

        return static::where('visitor_id', $visitorId)
            ->whereIn('item_id', $itemIds)
            ->pluck('item_id')
            ->all();
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
