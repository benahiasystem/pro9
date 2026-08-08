<?php

namespace Modules\Item\Services;

use App\Helpers\CacheHelper;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Item\Models\ItemVariationValue;
use Modules\Item\Models\ProductVariableValue;

class ItemVariationService
{
    /**
     * Campos que cada variación hereda del producto principal.
     */
    public const INHERITED_FIELDS = [
        'item_type_id',
        'unit_type_id',
        'currency_type_id',
        'sale_affectation_igv_type_id',
        'purchase_affectation_igv_type_id',
        'has_igv',
        'purchase_has_igv',
        'purchase_unit_price',
        'category_id',
        'brand_id',
        'image',
        'image_medium',
        'image_small',
        'line',
        'model',
        'warehouse_id',
        'amount_plastic_bag_taxes',
        'has_plastic_bag_taxes',
        'calculate_quantity',
        'has_isc',
        'system_isc_type_id',
        'percentage_isc',
        'subject_to_detraction',
    ];

    /**
     * Crea en lote las variaciones de un producto principal.
     * Cada variación es un item real: su save() dispara los listeners de
     * inventario inicial, kardex y costo promedio, igual que la creación individual.
     *
     * @param Item $parent
     * @param array $rows filas ya validadas por ItemVariationBulkRequest
     * @return array ids de los items creados
     */
    public function bulkCreate(Item $parent, array $rows)
    {
        $created_ids = [];

        DB::connection('tenant')->transaction(function () use ($parent, $rows, &$created_ids) {

            foreach ($rows as $row) {

                $values = ProductVariableValue::whereIn('id', $row['variable_value_ids'])
                    ->get()
                    ->keyBy('id');

                // el orden enviado define el orden de la etiqueta (M / Rojo)
                $labels = collect($row['variable_value_ids'])
                    ->map(function ($value_id) use ($values) {
                        $value = $values->get($value_id);
                        return $value ? $value->value : null;
                    })
                    ->filter();

                $item = new Item();

                foreach (self::INHERITED_FIELDS as $field) {
                    $item->{$field} = $parent->{$field};
                }

                $item->description = Str::limit($parent->description . ' · ' . $labels->implode(' / '), 600, '');
                $item->internal_id = trim($row['internal_id']);
                $item->barcode = !empty($row['barcode']) ? trim($row['barcode']) : null;
                $item->sale_unit_price = $row['sale_unit_price'];
                $item->stock = isset($row['stock']) ? $row['stock'] : 0;
                $item->stock_min = $parent->stock_min;
                $item->parent_item_id = $parent->id;
                $item->active = true;

                $item->save();

                if (!$item->barcode) {
                    $item->barcode = str_pad($item->id, 12, '0', STR_PAD_LEFT);
                }

                foreach ($row['variable_value_ids'] as $value_id) {
                    $value = $values->get($value_id);
                    ItemVariationValue::create([
                        'item_id' => $item->id,
                        'product_variable_id' => $value->product_variable_id,
                        'product_variable_value_id' => $value_id,
                    ]);
                }

                // segundo save: persiste el barcode por defecto y regenera
                // text_filter en el observer ya con los valores de variación
                $item->save();

                $created_ids[] = $item->id;
            }
        });

        CacheHelper::forget(['item_detail'], "item_detail_{$parent->id}");
        CacheHelper::flush(['items_list']);

        return $created_ids;
    }
}
