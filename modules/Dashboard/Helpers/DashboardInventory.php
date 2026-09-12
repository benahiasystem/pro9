<?php

namespace Modules\Dashboard\Helpers;

use Modules\Inventory\Models\ItemWarehouse;
use Modules\Dashboard\Http\Resources\DashboardInventoryCollection;

class DashboardInventory
{

    public function data($request)
    {
        $establishment_id = $request->establishment_id;
        $date_start = $request->date_start;
        $date_end = $request->date_end;

        return $this->products_date_of_due($establishment_id, $date_start, $date_end);
    }

    
    private function products_date_of_due($establishment_id, $date_start, $date_end)
    {

        $filter_establishment = function($query) use($establishment_id){
            $query->whereHas('warehouse',function($query) use($establishment_id){
                $query->where('establishment_id', $establishment_id);
            });
        };

        if ($date_start && $date_end) {
        $products = ItemWarehouse::whereHas('item',function($query) use($date_start,$date_end){

                        $query->whereNotIsSet();
                        $query->where('unit_type_id','!=', 'SERV');
                        $query->whereBetween('date_of_due', [$date_start, $date_end]);

                    })
                    ->when($establishment_id, $filter_establishment)
                    ->paginate(config('tenant.items_per_page_simple_d_table'));
        }else{
            $products = ItemWarehouse::whereHas('item',function($query){

                $query->whereNotIsSet();
                $query->where('unit_type_id','!=', 'SERV');

            })
            ->when($establishment_id, $filter_establishment)
            ->paginate(config('tenant.items_per_page_simple_d_table'));
        }
                   
        return new DashboardInventoryCollection($products);
    }
 
}