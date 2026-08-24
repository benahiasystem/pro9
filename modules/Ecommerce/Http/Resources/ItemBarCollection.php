<?php

namespace Modules\Ecommerce\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Controllers\Tenant\Api\ServiceController;
use Illuminate\Support\Str;

class ItemBarCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {

        $exchange_rate_sale = $this->getExchangeRateSale();

        return $this->collection->transform(function($row, $key) use($exchange_rate_sale){

            $has_igv_description = null;
            $affectation_igv_types_exonerated_unaffected = ['20','21','30','31','32','33','34','35','36','37'];

            if(in_array($row->sale_affectation_igv_type_id, $affectation_igv_types_exonerated_unaffected)) {

                $has_igv_description = 'No';

            }else{

                $has_igv_description = ((bool) $row->has_igv) ? 'Si':'No';

            }

            // ########## INICIO CAMBIO AFECTACIÓN IVA
            $sale_unit_price = ($row->has_igv) ? $row->sale_unit_price : $row->sale_unit_price * \App\Support\Venezuela\Localization::taxMultiplier();
            // ######### FIN CAMBIO AFECTACIÓN IVA

            return [
                'id' => $row->id,
                'slug' => $row->id . '/' . Str::slug($row->description),
                'unit_type_id' => $row->unit_type_id,
                'description' => $row->description,
                'brand' => $row->brand && $row->brand->id ? [
                    'id' => $row->brand->id,
                    'name' => $row->brand->name,
                    'slug' => Str::slug($row->brand->name),
                ] : null,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'warehouse_id' => $row->warehouse_id,
                'internal_id' => $row->internal_id,
                'item_code' => $row->item_code,
                'item_code_gs1' => $row->item_code_gs1,
                'stock' => $row->getStockByWarehouse(),
                'stock_min' => $row->stock_min,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'amount_sale_unit_price' => $sale_unit_price,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'has_igv_description' => $has_igv_description,
                // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
                'sale_unit_price' => sprintf(
                    '%s %s',
                    $row->currency_type->symbol ?? \App\Support\Venezuela\Localization::currencySymbol($row->currency_type_id),
                    round($sale_unit_price, 2)
                ),
                // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
                // 'sale_unit_price' => "{$row->currency_type->symbol} {$row->sale_unit_price}",
                'purchase_unit_price' => "{$row->currency_type->symbol} {$row->purchase_unit_price}",
                'created_at' => ($row->created_at) ? $row->created_at->format('Y-m-d H:i:s') : '',
                'updated_at' => ($row->created_at) ? $row->updated_at->format('Y-m-d H:i:s') : '',
                'warehouses' => collect($row->warehouses)->transform(function($row) {
                    return [
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                    ];
                }),
                'apply_store' => (bool)$row->apply_store,
                'image_url' => ($row->image !== 'imagen-no-disponible.jpg') ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image) : asset("/logo/{$row->image}"),
                'image_url_medium' => ($row->image_medium !== 'imagen-no-disponible.jpg') ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_medium) : asset("/logo/{$row->image_medium}"),
                'image_url_small' => ($row->image_small !== 'imagen-no-disponible.jpg') ? asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_small) : asset("/logo/{$row->image_small}"),
                'tags' => $row->tags,
                'tags_id' => $row->tags->pluck('tag_id'),



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
