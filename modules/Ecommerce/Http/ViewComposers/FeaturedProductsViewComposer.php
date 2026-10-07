<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Ecommerce\Http\ViewComposers;


use App\Models\Tenant\Item;
//use App\Http\Resources\Tenant\ItemEcommerceCollection;
use App\Http\Controllers\Tenant\Api\ServiceController;


class FeaturedProductsViewComposer
{
    public function compose($view)
    {

        $exchange_rate_sale = $this->getExchangeRateSale();

        $view->items = Item::where([['apply_store', 1], ['internal_id','!=', null]])->whereDoesntHave('variations')->get()->transform(function($row, $key) use($exchange_rate_sale){

            // ########## INICIO CAMBIO AFECTACIÓN IVA
            $sale_unit_price = ($row->has_igv)
                ? $row->sale_unit_price
                : $row->sale_unit_price * \App\Support\Venezuela\Localization::taxMultiplier();
            // ######### FIN CAMBIO AFECTACIÓN IVA

            $carbon = new \Carbon\Carbon();
            $date = $carbon->now();
            $months = $date->diffInMonths($row->created_at);

            return (object)[
                'id' => $row->id,
                'internal_id' => $row->internal_id,
                'unit_type_id' => $row->unit_type_id,
                'description' => $row->description,
                'category_id' => $row->category_id,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'sale_unit_price' => ($row->currency_type_id === 'VES') ? $sale_unit_price : (float) \App\Services\ExchangeRates\ExchangeRateMath::finalAmount(\App\Services\ExchangeRates\ExchangeRateMath::rational($sale_unit_price)->multipliedBy(\App\Services\ExchangeRates\ExchangeRateMath::rational($exchange_rate_sale)), 6),
                'sale_unit' => $sale_unit_price,
                'currency_type_id' => $row->currency_type_id,
                'has_igv' => (bool) $row->has_igv,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'image' =>  $row->image,
                'image_medium' => $row->image_medium,
                'image_small' => $row->image_small,
                'tags' => $row->tags->pluck('tag_id')->toArray(),
                'is_new' => ($months > 1) ? 0 : 1,
                /*'multi_images'  => $row->images->transform(function($r){
                    return [
                        $r->image
                    ];
                })*/
            ];
        });
    }

    private function getExchangeRateSale()
    {
        try {
            $exchange_rate = app(ServiceController::class)->exchangeRateTest(date('Y-m-d'));

            return (is_array($exchange_rate) && array_key_exists('sale', $exchange_rate) && $exchange_rate['sale'])
                ? $exchange_rate['sale']
                : 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
