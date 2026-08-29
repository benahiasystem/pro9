<?php

namespace Modules\MobileApp\Http\Resources\Api;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Inventory\Models\Warehouse;


class ItemSaleCollection extends ResourceCollection
{
    
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        
        // Inventario (app): `stock` se calcula para el almacen pedido; por defecto el del establecimiento del usuario
        $warehouse_id = $request->input('warehouse_id');
        $warehouse = $warehouse_id
            ? Warehouse::find($warehouse_id)
            : Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

        return $this->collection->transform(function($row, $key) use($warehouse){
            return $row->getSaleApiRowResource($warehouse);
        });

    }
    
}
