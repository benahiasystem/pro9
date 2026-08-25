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
            ->orderBy('favorite','desc')
            ->whereIsActive();

        if ($enable_list_product) {
            // ItemUnitType trae unit_type por defecto y aqui solo se usa la columna.
            $itemsQuery->with(['item_unit_types' => function ($query) {
                $query->without('unit_type')->with('prices.priceLabel');
            }]);
        }

        $items = $itemsQuery->get();

        // El stock del almacen en una sola consulta, no una por producto.
        Item::preloadStockByWarehouse($items);

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
