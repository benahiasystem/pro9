<?php

namespace Modules\Restaurant\Http\Resources;

use App\Models\Tenant\Configuration;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

        $this->preloadVariationRelations();

        return $this->collection->transform(function($row, $key) use ($configuration, $enableListProduct){
            return $this->transformRow($row, $configuration, $enableListProduct);
        });
    }

    /**
     * Red de seguridad para lo que el controlador no haya precargado. Precargar
     * sigue siendo del controlador (es el unico que puede aplicar whereIsActive,
     * el orden y el resto de relaciones); esto solo evita que un eager loading
     * incompleto vacie los chips en silencio, como pasaba cuando un
     * with('variations.algo') en otra llamada pisaba la closure de 'variations'.
     *
     * No fuerza la relacion variations: agruparlas sigue siendo opt-in del
     * controlador. Son consultas por lote, no una por fila.
     *
     * @return void
     */
    protected function preloadVariationRelations()
    {
        $items = EloquentCollection::make($this->collection->all());

        if ($items->isEmpty()) {
            return;
        }

        $items->loadMissing('variationValues.value.variable');

        // Hijas ya cargadas por el controlador: pueden venir sin sus valores si el
        // eager loading de 'variations' se definio en otra llamada a with().
        $variations = EloquentCollection::make(
            $items->filter(function ($item) {
                return $item->relationLoaded('variations');
            })
            ->flatMap(function ($item) {
                return $item->variations;
            })
            ->all()
        );

        if ($variations->isNotEmpty()) {
            $variations->loadMissing('variationValues.value.variable');
        }
    }

    /**
     * Fila de producto. Las variaciones usan exactamente la misma forma, para que
     * el cliente pueda tratar la variacion elegida como cualquier otro producto.
     *
     * @param  \App\Models\Tenant\Item  $row
     * @param  Configuration|null  $configuration
     * @param  bool  $enableListProduct
     * @param  bool  $with_variations  false al transformar una variacion, corta la recursion
     * @return array
     */
    protected function transformRow($row, $configuration, $enableListProduct, $with_variations = true)
    {
        // Se resuelven una sola vez por fila: antes cada uno se consultaba
        // dos veces (has_supplies/has_sets y de nuevo en restaurant_stock).
        $hasSupplies = $row->hasRestaurantSupplies();
        $hasSets = $row->hasItemSets();
        $stock = $row->getStockByWarehouse();
        $has_variation_values = $row->parent_item_id && $row->relationLoaded('variationValues');

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

            // Variantes: el padre no es vendible, expone sus hijas para el
            // modal de seleccion; la hija expone a que combinacion pertenece.
            'parent_item_id' => $row->parent_item_id,
            // Todo lo de variacion se lee solo con la relacion ya cargada: otros
            // consumidores de este resource no la precargan y seria un N+1.
            'variation_label' => $has_variation_values ? $row->variation_label : null,
            'variation_attributes' => $has_variation_values ? $row->getVariationAttributesData() : [],
            'variation_value_ids' => $has_variation_values
                ? $row->variationValues
                    ->pluck('product_variable_value_id')
                    ->map(function ($value_id) { return (int) $value_id; })
                    ->sort()
                    ->values()
                : [],
            'variations_count' => (int) ($row->variations_count ?? 0),
            'has_variations' => $with_variations && $this->loadedVariations($row)->isNotEmpty(),
            'variation_variables' => $with_variations
                ? $this->buildVariationVariables($row)
                : [],
            'variations' => $with_variations
                ? $this->loadedVariations($row)->map(function ($variation) use ($configuration, $enableListProduct) {
                    return $this->transformRow($variation, $configuration, $enableListProduct, false);
                })->values()
                : [],

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
    }

    /**
     * Variaciones ya precargadas por el controlador. Sin la relacion cargada se
     * devuelve vacio en lugar de disparar una consulta por producto.
     *
     * @param  \App\Models\Tenant\Item  $row
     * @return \Illuminate\Support\Collection
     */
    protected function loadedVariations($row)
    {
        if ($row->parent_item_id || !$row->relationLoaded('variations')) {
            return collect();
        }

        return collect($row->variations);
    }

    /**
     * Variables con sus valores (Color -> Rojo, Azul...) para pintar los chips del
     * modal. El orden de los valores es el de creacion de las variaciones.
     *
     * @param  \App\Models\Tenant\Item  $row
     * @return array
     */
    protected function buildVariationVariables($row)
    {
        $variables = [];

        foreach ($this->loadedVariations($row) as $variation) {
            if (!$variation->relationLoaded('variationValues')) {
                continue;
            }

            foreach ($variation->variationValues as $variation_value) {
                $value = $variation_value->value;
                $variable = $value ? $value->variable : null;

                if (!$value || !$variable) {
                    continue;
                }

                if (!isset($variables[$variable->id])) {
                    $variables[$variable->id] = [
                        'id' => (int) $variable->id,
                        'name' => $variable->name,
                        'value_type' => $variable->value_type,
                        'values' => [],
                    ];
                }

                $variables[$variable->id]['values'][$value->id] = [
                    'id' => (int) $value->id,
                    'value' => $value->value,
                    'color' => $value->color,
                ];
            }
        }

        return array_map(function ($variable) {
            $variable['values'] = array_values($variable['values']);
            return $variable;
        }, array_values($variables));
    }
}
