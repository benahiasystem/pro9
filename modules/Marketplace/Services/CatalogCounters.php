<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;

/**
 * Recalcula los contadores denormalizados.
 *
 * Hay que llamarlo desde CUALQUIER acción que cambie qué se publica, no solo
 * desde el sync: aprobar, rechazar, deshabilitar o habilitar una tienda, y
 * bloquear o desbloquear un producto, cambian el conjunto publicado y por
 * tanto los conteos por categoría.
 *
 * Si no, el filtro del front muestra números que no cuadran con los
 * resultados, o peor: una categoría con contador > 0 que no lleva a ningún
 * producto.
 */
class CatalogCounters
{
    /** Productos activos de una tienda. */
    public static function refreshStore(int $storeId): void
    {
        $store = Store::find($storeId);

        if (! $store) {
            return;
        }

        $store->items_count = Item::unscoped()
            ->where('store_id', $storeId)
            ->where('status', Item::STATUS_ACTIVE)
            ->count();

        $store->save();
    }

    /**
     * Productos realmente publicados por categoría: activos Y de tienda
     * aprobada. Es el mismo criterio que PublishedScope, para que el número
     * del filtro coincida con lo que devuelve el feed.
     *
     * Una sola sentencia para todas las categorías: con este volumen sale más
     * barato que rastrear cuáles cambiaron.
     */
    public static function refreshCategories(): void
    {
        DB::connection('system')->statement('
            UPDATE marketplace_categories c
            SET c.items_count = (
                SELECT COUNT(*)
                FROM marketplace_items i
                INNER JOIN marketplace_stores s ON s.id = i.store_id
                WHERE i.category_id = c.id
                  AND i.status = ?
                  AND s.status = ?
            )
        ', [Item::STATUS_ACTIVE, Store::STATUS_APPROVED]);
    }

    /** Ambos, para las acciones del admin sobre un ítem. */
    public static function refreshAll(int $storeId): void
    {
        self::refreshStore($storeId);
        self::refreshCategories();
    }
}
