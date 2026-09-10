<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Configuration;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\PriceLabel;

class PosCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {

            $configuration = Configuration::first();
            $sale_unit_price = $this->getSaleUnitPrice($row, $configuration);

            $currency = $row->currency_type;
            if(empty($currency )){
                $currency = CurrencyType::first();
            }

            $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
            $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
                ? asset('logo/imagen-no-disponible.jpg')
                : asset('storage/defaults/' . $defaultImage);

            $allPricesLabel = PriceLabel::all();


            return [
                'stock' => $row->getStockByWarehouse(),
                'id' => $row->id,
                'item_id' => $row->id,
                'parent_item_id' => $row->parent_item_id,
                'variations_count' => (int) ($row->variations_count ?? 0),
                'variations_stock' => !is_null($row->variations_stock) ? (float) $row->variations_stock : null,
                'variations' => ($row->relationLoaded('variations') && ((int) ($row->variations_count ?? 0)) > 0)
                    ? collect($row->variations)->map(function ($variation) use ($defaultImagePath) {
                        return [
                            'id' => $variation->id,
                            'description' => $variation->description,
                            'variation_label' => $variation->variation_label,
                            'variation_attributes' => $variation->getVariationAttributesData(),
                            'internal_id' => $variation->internal_id,
                            'barcode' => $variation->barcode,
                            'stock' => $variation->getStockByWarehouse(),
                            'stock_min' => (float) $variation->stock_min,
                            'sale_unit_price' => (float) $variation->sale_unit_price,
                            'image_url' => ($variation->image && $variation->image !== 'imagen-no-disponible.jpg')
                                ? asset('storage/uploads/items/' . $variation->image)
                                : $defaultImagePath,
                        ];
                    })->values()
                    : [],
                'full_description' => ($row->internal_id) ? $row->internal_id . ' - ' . $row->description : $row->description,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'description' => ($row->brand->name) ? $row->description.' - '.$row->brand->name : $row->description,
                'currency_type_id' => $row->currency_type_id,
                'internal_id' => $row->internal_id,
                'currency_type_symbol' => $currency->symbol,
                'sale_unit_price' => $sale_unit_price,
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'aux_unit_type_id' => $row->unit_type_id,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'is_set' => (bool) $row->is_set,
                'active' => $row->active,
                'edit_unit_price' => false,
                'aux_quantity' => 1,
                'edit_sale_unit_price' => $sale_unit_price,
                'aux_sale_unit_price' => $sale_unit_price,
                'image_url' => ($row->image && $row->image !== 'imagen-no-disponible.jpg')
                    ? asset('storage/uploads/items/' . $row->image)
                    : $defaultImagePath,
                'warehouses' => collect($row->warehouses)->transform(function ($row) {
                    return [
                        'warehouse_description' => $row->warehouse->description,
                        'stock' => $row->stock,
                    ];
                }),
                'category_id' => ($row->category) ? $row->category->id : null,
                'sets' => collect($row->sets)->transform(function ($r) {
                    return [
                        $r->individual_item->description,
                    ];
                }),
                'item_unit_types' => $row->getItemUnitTypesForPos($configuration, $allPricesLabel),
                'unit_type' => $row->item_unit_types,
                'category' => ($row->category) ? $row->category->name : null,
                'brand' => ($row->brand) ? $row->brand->name : null,

                'exchange_points' => $row->exchange_points,
                'quantity_of_points' => $row->quantity_of_points,
                'exchanged_for_points' => false, //para determinar si desea canjear el producto
                'used_points_for_exchange' => null, //total de puntos
                'original_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'restrict_sale_cpe' => $row->restrict_sale_cpe,
            ];
        });
    }


    private function getSaleUnitPrice($row, $configuration){

        $sale_unit_price = number_format($row->sale_unit_price, $configuration->decimal_quantity, ".", "");

        if($configuration->active_warehouse_prices){

            $warehouse_price = $row->warehousePrices()->where('warehouse_id', auth()->user()->establishment->warehouse->id)->first();

            if($warehouse_price){

                $sale_unit_price = number_format($warehouse_price->price, $configuration->decimal_quantity, ".", "");

            }else{

                if($row->warehousePrices()->count() > 0){
                    $sale_unit_price = number_format($row->warehousePrices()->first()->price, $configuration->decimal_quantity, ".", "");
                }

            }

        }

        return $sale_unit_price;
    }

}
