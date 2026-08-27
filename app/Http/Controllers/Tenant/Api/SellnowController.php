<?php

namespace App\Http\Controllers\Tenant\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Item;
use Modules\Item\Models\Category;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Restaurant\Http\Resources\ItemCollection;
use App\Http\Controllers\Tenant\Api\ServiceController;
use App\Models\Tenant\Order;
use Exception;
use Illuminate\Support\Facades\Validator;
use stdClass;
use Illuminate\Support\Str;
use App\Models\Tenant\Configuration;


class SellnowController extends Controller
{

    public function items(Request $request){

        $warehouse_id = auth()->user()->establishment->id;

        $configuration = Configuration::first();
        $enable_list_product = (bool) optional($configuration)->enable_list_product;

        // Relaciones del $with por defecto de Item que este listado no usa: traerlas
        // significa cargar item_warehouse, item_lots e item_tags de todo el catalogo.
        $unused_relations = ['item_type', 'unit_type', 'warehouses', 'tags', 'item_lots'];

        if (!$enable_list_product) {
            $unused_relations[] = 'item_unit_types';
        }

        // Agrupar variantes es opt-in (?group_variations=1): el cliente que aun no
        // implementa el modal sigue recibiendo cada variacion como producto suelto.
        $group_variations = $request->group_variations == 1;

        $itemsQuery = Item::whereNotNull('internal_id')
            ->whereHas('warehouses', function ($query) use ($warehouse_id) {
                $query->where('warehouse_id', $warehouse_id);
            })
            ->without($unused_relations)
            // Relaciones que ItemCollection recorre fila por fila: sin eager loading
            // cada producto dispara sus propias consultas (N+1).
            ->with([
                'currency_type',
                'preparationArea',
                'modifierGroups',
                'restaurantSupplies',
                // has_sets sale de items_sets, que lee la misma tabla item_sets.
                // Los items del set arrastran el mismo $with; se les recorta igual.
                'items_sets' => function ($query) use ($unused_relations) {
                    $query->without($unused_relations)->with('restaurantSupplies');
                },
            ])
            // Valores de variacion de la propia fila (Talla M, Rojo...): sin esto
            // variation_label dispara dos consultas por variacion.
            ->with('variationValues.value.variable')
            ->orderBy('favorite','desc')
            ->whereIsActive();

        if ($group_variations) {
            // El padre pasa a ser la tarjeta y sus hijas viajan dentro, con la
            // misma forma de fila para que la elegida se use como cualquier producto.
            $itemsQuery->whereNull('parent_item_id')
                ->withCount('variations')
                ->with(['variations' => function ($query) use ($unused_relations) {
                    $query->whereIsActive()
                        ->without($unused_relations)
                        ->with([
                            'currency_type',
                            'preparationArea',
                            'modifierGroups',
                            'restaurantSupplies',
                            'variationValues.value.variable',
                            'items_sets' => function ($query) use ($unused_relations) {
                                $query->without($unused_relations)->with('restaurantSupplies');
                            },
                        ])
                        ->orderBy('id');
                }]);
        }

        if ($enable_list_product) {
            // ItemUnitType trae unit_type por defecto y aqui solo se usa la columna.
            $itemsQuery->with(['item_unit_types' => function ($query) {
                $query->without('unit_type')->with('prices.priceLabel');
            }]);

            if ($group_variations) {
                $itemsQuery->with(['variations.item_unit_types' => function ($query) {
                    $query->without('unit_type')->with('prices.priceLabel');
                }]);
            }
        }

        $items = $itemsQuery->get();

        // El stock del almacen en una sola consulta, no una por producto. Las
        // variaciones entran al mismo precargado: su stock es el que decide el modal.
        $stock_targets = $group_variations
            ? $items->concat($items->pluck('variations')->filter()->flatten())
            : $items;

        Item::preloadStockByWarehouse($stock_targets);

        $records = (new ItemCollection($items))->withConfiguration($configuration);

        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function categories(Request $request){
        $records = Category::all();
        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function setFavoriteItem(Request $request) {
        $item = Item::findOrFail($request->id);

        $item->favorite = ($item->favorite == 1) ? 0 : 1;
        $item->save();

        return [
            'success' => true,
            'message' => ($item->favorite == 1)? "Producto agregado a favoritos": "Producto quitado de favoritos"
        ];
    }

}
