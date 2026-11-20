<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\Document;
use App\Support\Exact\{DashboardPurchases,DashboardRankings,ReadSnapshot};

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** The three cards share an owned report snapshot and original operands. */
class DashboardSalePurchase
{
    public function data($request)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $filters=DashboardFilterHelper::resolve($request);
            $establishment=$filters['establishment_id'];$start=$filters['date_start'];$end=$filters['date_end'];
            $purchase=DashboardPurchases::chart($establishment,$start,$end);
            $products=DashboardRankings::products($establishment,$start,$end,
                filter_var($request['enabled_move_item'] ?? false,FILTER_VALIDATE_BOOLEAN));
            $page=filter_var($request['page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($page===false) throw \Illuminate\Validation\ValidationException::withMessages(['page'=>'Página fuera de rango.']);
            $items=filter_var($request['no_take'] ?? false,FILTER_VALIDATE_BOOLEAN)
                ? $products->paginate(10,$page) : $products->paginate(10,1)->items();
            return ['purchase'=>$purchase,'items_by_sales'=>$items,
                'top_customers'=>DashboardRankings::customers($establishment,$start,$end,
                    filter_var($request['enabled_transaction_customer'] ?? false,FILTER_VALIDATE_BOOLEAN))];
        });
    }

    public function purchasesTotalByRange($establishment_id, $date_start, $date_end)
    {
        return DashboardPurchases::range($establishment_id,$date_start,$date_end);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
