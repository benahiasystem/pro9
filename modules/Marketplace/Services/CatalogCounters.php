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
    /** Productos activos de una tienda y su ranking de recomendaciones. */
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

        self::syncStoreVisitors($storeId);
    }

    /**
     * Recalcula ambos contadores tras poner o quitar un pulgar: el del producto
     * y el de su tienda. Por recuento y no por incremento, para que queden
     * exactos sin depender de que cada +1/-1 acierte.
     *
     * Ambos van como UPDATE … SET x = (SELECT …), en una sola sentencia: es la
     * ruta caliente y dos pulgares concurrentes a la misma tienda podrían
     * pisarse si se contara en PHP y se guardara después (lost update). El
     * recuento y la escritura tienen que ser atómicos.
     */
    public static function refreshRecommendations(Item $item): void
    {
        DB::connection('system')->statement('
            UPDATE marketplace_items i
            SET i.recommendations_count = (
                SELECT COUNT(*) FROM marketplace_recommendations r WHERE r.item_id = i.id
            )
            WHERE i.id = ?
        ', [$item->id]);

        self::syncStoreVisitors($item->store_id);
    }

    /**
     * El ranking de la tienda: cuántos visitantes DISTINTOS han recomendado
     * algo suyo. No es la suma de sus productos —así un solo entusiasta que
     * recorra el catálogo entero sigue contando como uno—, que es lo que hace
     * del número un aval y no una puntuación inflable.
     *
     * Se excluyen los productos bloqueados: lo que el admin retiró por una razón
     * no debería seguir sosteniendo la posición de la tienda. Los inactivos sí
     * cuentan: un producto que salió del catálogo no borra el reconocimiento ya
     * ganado.
     *
     * Atómico por lo mismo que refreshRecommendations: es el contador que más
     * se toca y no puede sufrir lost updates entre recuento y escritura.
     */
    private static function syncStoreVisitors(int $storeId): void
    {
        DB::connection('system')->statement('
            UPDATE marketplace_stores s
            SET s.recommendations_count = (
                SELECT COUNT(DISTINCT r.visitor_id)
                FROM marketplace_recommendations r
                INNER JOIN marketplace_items i ON i.id = r.item_id
                WHERE r.store_id = s.id
                  AND i.status != ?
            )
            WHERE s.id = ?
        ', [Item::STATUS_BLOCKED, $storeId]);
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
