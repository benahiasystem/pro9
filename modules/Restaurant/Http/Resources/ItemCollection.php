<?php

namespace Modules\Restaurant\Http\Resources;

use App\Models\Tenant\Configuration;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ItemCollection extends ResourceCollection
{
    /**
     * Configuracion ya resuelta por el controlador, para no volver a consultarla.
     *
     * @var Configuration|null
     */
    protected $tenant_configuration = null;

    /**
     * @param  Configuration|null  $configuration
     * @return $this
     */
    public function withConfiguration($configuration)
    {
        $this->tenant_configuration = $configuration;

        return $this;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        $configuration = $this->tenant_configuration ?: Configuration::first();
        $enableListProduct = (bool) optional($configuration)->enable_list_product;

        return $this->collection->transform(function($row, $key) use ($configuration, $enableListProduct){

            // Se resuelven una sola vez por fila: antes cada uno se consultaba
            // dos veces (has_supplies/has_sets y de nuevo en restaurant_stock).
            $hasSupplies = $row->hasRestaurantSupplies();
            $hasSets = $row->hasItemSets();
            $stock = $row->getStockByWarehouse();

            $defaultImage = $configuration->product_default_image ?? 'imagen-no-disponible.jpg';
            $defaultImagePath = $defaultImage === 'imagen-no-disponible.jpg'
                ? asset('logo/imagen-no-disponible.jpg')
                : asset('storage/defaults/' . $defaultImage);


            return [
                'id' => $row->id,
                'unit_type_id' => $row->unit_type_id,
                'category_id' => $row->category_id,
                'description' => $row->description,
                'name' => $row->name,
                'second_name' => $row->second_name,
                'warehouse_id' => $row->warehouse_id,
                'internal_id' => $row->internal_id,
                'barcode' => $row->barcode,
                'item_code' => $row->item_code,
                'item_code_gs1' => $row->item_code_gs1,
                'stock' => $stock,
                'stock_min' => $row->stock_min,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_affectation_igv_type_id' => $row->sale_affectation_igv_type_id,
                'price' => $row->sale_unit_price,
                'calculate_quantity' => (bool) $row->calculate_quantity,
                'has_igv' => (bool) $row->has_igv,
                'active' => (bool) $row->active,
                'sale_unit_price' => "{$row->currency_type->symbol} {$row->sale_unit_price}",
                'purchase_unit_price' => "{$row->currency_type->symbol} {$row->purchase_unit_price}",
                'created_at' => ($row->created_at) ? $row->created_at->format('Y-m-d H:i:s') : '',
                'updated_at' => ($row->created_at) ? $row->updated_at->format('Y-m-d H:i:s') : '',
                'apply_store' => (bool)$row->apply_store,
                'apply_restaurant' => (bool)$row->apply_restaurant,
                'restaurant_favorite' => (bool)$row->restaurant_favorite,
                'image_url' => $row->image && ($row->image === 'imagen-no-disponible.jpg') ? $defaultImagePath : asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image),
                'image_url_medium' => $row->image && ($row->image_medium === 'imagen-no-disponible.jpg') ? $defaultImagePath : asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_medium),
                'image_url_small' => $row->image && ($row->image_small === 'imagen-no-disponible.jpg') ?  $defaultImagePath : asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image_small),
                'favorite' => (bool)$row->favorite,
                'area_print' => $row->preparationArea->printer ?? null,
                'has_supplies' => $hasSupplies,
                'is_dish' => (bool)$row->is_dish,
                'has_sets' => $hasSets,
                'items_sets' => $row->items_sets ?? [],
                'restaurant_stock' => $hasSupplies
                    ? $row->getRestaurantStock()
                    : ($hasSets
                        ? $row->getRestaurantStockSet()
                        : $stock),
                'modifiers' => $row->modifiers ?? [],
                'item_unit_types' => $enableListProduct
                    ? $row->item_unit_types->map(function ($unitType) {
                        return [
                            'id' => $unitType->id,
                            'description' => $unitType->description,
                            'unit_type_id' => $unitType->unit_type_id,
                            'quantity_unit' => (float) $unitType->quantity_unit,
                            'prices' => $unitType->prices
                                ->filter(function ($price) {
                                    return (float) $price->price > 0;
                                })
                                ->map(function ($price) {
                                    return [
                                        'id' => $price->price_label_id,
                                        'label' => optional($price->priceLabel)->label,
                                        'price' => (float) $price->price,
                                    ];
                                })
                                ->values(),
                        ];
                    })
                    ->filter(function ($unitType) {
                        return $unitType['prices']->isNotEmpty();
                    })
                    ->values()
                    : [],
            ];
        });
    }
}
