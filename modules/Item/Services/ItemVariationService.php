<?php

namespace Modules\Item\Services;

use App\Helpers\CacheHelper;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Finance\Helpers\UploadFileHelper;
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
        'apply_store',
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

                $this->applyRowImage($item, $row);

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

    /**
     * Guarda la imagen subida para una combinación (original, medium y small),
     * igual que el alta individual de items. Sin temp_path la variación conserva
     * la imagen heredada del producto principal.
     *
     * @param Item $item variación aún sin guardar, ya con internal_id asignado
     * @param array $row fila enviada desde el formulario
     * @return void
     */
    private function applyRowImage(Item $item, array $row)
    {
        $temp_path = isset($row['temp_path']) ? $row['temp_path'] : null;

        if (!$temp_path) {
            return;
        }

        $real_path = realpath($temp_path);
        $temp_dir = realpath(sys_get_temp_dir());

        if (!$real_path || !$temp_dir || strpos($real_path, $temp_dir) !== 0) {
            return;
        }

        $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR;

        $slug_name = $item->internal_id ? Str::slug($item->internal_id) : Str::slug($item->description);
        $prefix_name = Str::limit($slug_name, 20, '');

        $original_name = isset($row['image']) ? $row['image'] : '';
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $extension = $extension ? $extension : 'jpg';

        $datenow = date('YmdHis');

        UploadFileHelper::checkIfValidFile($prefix_name.'.'.$extension, $real_path, true);

        $file_name = $prefix_name.'-'.$datenow.'.'.$extension;
        Storage::put($directory.$file_name, file_get_contents($real_path));
        $item->image = $file_name;

        //--- IMAGE SIZE MEDIUM
        $image = \Image::make($real_path);
        $image->resize(512, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $file_name = $prefix_name.'-'.$datenow.'_medium.'.$extension;
        Storage::put($directory.$file_name, (string) $image->encode('jpg', 30));
        $item->image_medium = $file_name;

        //--- IMAGE SIZE SMALL
        $image = \Image::make($real_path);
        $image->resize(256, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $file_name = $prefix_name.'-'.$datenow.'_small.'.$extension;
        Storage::put($directory.$file_name, (string) $image->encode('jpg', 20));
        $item->image_small = $file_name;
    }
}
