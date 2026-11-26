<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace App\Imports;

use App\Models\Tenant\Item;
use App\Models\Tenant\Warehouse;
use App\Traits\SunatItemCodeTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Item\Models\Category;
use Modules\Item\Models\Brand;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\Inventory;
use Exception;
use Modules\Item\Models\ItemLotsGroup;


class ItemsImport implements ToCollection
{
    use Importable;
    use SunatItemCodeTrait;

    protected $data;

    public function collection(Collection $rows)
    {
            $total = count($rows);
            $warehouse_id_de = request('warehouse_id');
            $registered = 0;
            unset($rows[0]);
            foreach ($rows as $index => $row) {
                $isNullAll = $row->every(function($el){
                    return is_null($el);
                });
                if ($isNullAll) continue;
                $description = $row[0];
                $item_type_id = '01';
                $internal_id = $this->normalizeInternalId($row[1] ?? null);
                $model = ($row[2]) ? : null;
                $item_code = self::cleanSunatItemCode($row[3] ?? null);

                if ($item_code !== null && !self::isValidSunatItemCode($item_code)) {
                    throw new Exception('Fila '.($index + 1).': '.self::getSunatItemCodeMessage(), 500);
                }

                $unit_type_id = $row[4];
                $currency_type_id = $row[5];
                $sale_unit_price = $this->validateImportRow(
                    (int) $index + 1,
                    $description,
                    $unit_type_id,
                    $currency_type_id,
                    $row[6],
                    $row[7]
                );
                $sale_affectation_igv_type_id = $row[7];
                // $has_igv = (strtoupper($row[7]) === 'SI')?true:false;

                $affectation_igv_types_exonerated_unaffected = ['20','21','30','31','32','33','34','35','36','37'];

                if(in_array($sale_affectation_igv_type_id, $affectation_igv_types_exonerated_unaffected)) {

                    $has_igv = true;

                }else{

                    $has_igv = (strtoupper($row[8]) === 'SI')?true:false;

                }

                $purchase_unit_price = ($row[9])?:0;
                $purchase_affectation_igv_type_id = ($row[10])?:null;
                $stock = $row[11];
                $stock_min = $row[12];
                $category_name = $row[13];
                $brand_name = $row[14];

                $name = $row[15];
                $second_name = $row[16];

                $lot_code = $row[17];
                $date_of_due = $row[18];
                $barcode = $row[19] ?? null;
                $image_url = $row[20] ?? null;

                $warehouse_id = request('warehouse_id');


                if($internal_id) {
                    $item = $this->findItemByInternalId($internal_id);
                } else {
                    $item = null;
                }
                // $establishment_id = auth()->user()->establishment->id;

                if(!$item) {
                    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    $images = app(\App\Support\ItemImport\ImportedItemImage::class)->load($image_url, $description, $internal_id);
                    $file_name = $images['image'];
                    $file_name_medium = $images['image_medium'];
                    $file_name_small = $images['image_small'];
                    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    $category = $category_name ? Category::updateOrCreate(['name' => $category_name]):null;
                    $brand = $brand_name ? Brand::updateOrCreate(['name' => $brand_name]):null;
                    // dd($row, $lot_code ,$date_of_due, $category, $brand);

                    if($lot_code && $date_of_due){

                        $_date_of_due = Date::excelToDateTimeObject($date_of_due)->format('Y-m-d');

                        // dd($lot_code, $date_of_due, $x);

                        $new_item = Item::create([
                            'description' => $description,
                            'model' => $model,
                            'item_type_id' => $item_type_id,
                            'internal_id' => $internal_id,
                            'item_code' => $item_code,
                            'unit_type_id' => $unit_type_id,
                            'currency_type_id' => $currency_type_id,
                            'sale_unit_price' => $sale_unit_price,
                            'sale_affectation_igv_type_id' => $sale_affectation_igv_type_id,
                            'has_igv' => $has_igv,
                            'purchase_unit_price' => $purchase_unit_price,
                            'purchase_affectation_igv_type_id' => $purchase_affectation_igv_type_id,
                            'stock' => $stock,
                            'stock_min' => $stock_min,
                            'category_id' => optional($category)->id,
                            'brand_id' => optional($brand)->id,
                            'name' => $name,
                            'second_name' => $second_name,

                            'lots_enabled' => true,
                            'lot_code' => $lot_code,
                            'date_of_due' => $_date_of_due,
                            'barcode' => $barcode,
                            'warehouse_id' => $warehouse_id,
                            'image' => $file_name,
                            'image_medium' => $file_name_medium,
                            'image_small' => $file_name_small,
                        ]);

                        $new_item->lots_group()->create([
                            'code'  => $lot_code,
                            'quantity'  => $stock,
                            'date_of_due'  => $_date_of_due,
                        ]);

                    }else{

                        $_date_of_due = $date_of_due ? Date::excelToDateTimeObject($date_of_due)->format('Y-m-d') : null;

                        Item::create([
                            'description' => $description,
                            'model' => $model,
                            'item_type_id' => $item_type_id,
                            'internal_id' => $internal_id,
                            'item_code' => $item_code,
                            'unit_type_id' => $unit_type_id,
                            'currency_type_id' => $currency_type_id,
                            'sale_unit_price' => $sale_unit_price,
                            'sale_affectation_igv_type_id' => $sale_affectation_igv_type_id,
                            'has_igv' => $has_igv,
                            'purchase_unit_price' => $purchase_unit_price,
                            'purchase_affectation_igv_type_id' => $purchase_affectation_igv_type_id,
                            'stock' => $stock,
                            'date_of_due' => $_date_of_due,
                            'stock_min' => $stock_min,
                            'category_id' => optional($category)->id,
                            'brand_id' => optional($brand)->id,
                            'name' => $name,
                            'second_name' => $second_name,
                            'barcode' => $barcode,
                            'warehouse_id' => $warehouse_id,
                            'image' => $file_name,
                            'image_medium' => $file_name_medium,
                            'image_small' => $file_name_small,
                        ]);

                    }


                    $registered += 1;

                }else{

                    $inventory_transaction = InventoryTransaction::findOrFail('102');
                    $stock = $row[11];
                    if (!$stock) {
                        throw new Exception("Debe ingresar el stock", 500);
                    }

                    $item->update([
                        'description' => $description,
                        'model' => $model,
                        'item_type_id' => $item_type_id,
                        'internal_id' => $internal_id,
                        'item_code' => $item_code,
                        'unit_type_id' => $unit_type_id,
                        'currency_type_id' => $currency_type_id,
                        'sale_unit_price' => $sale_unit_price,
                        'sale_affectation_igv_type_id' => $sale_affectation_igv_type_id,
                        'has_igv' => $has_igv,
                        'purchase_unit_price' => $purchase_unit_price,
                        'purchase_affectation_igv_type_id' => $purchase_affectation_igv_type_id,
                        'stock_min' => $stock_min,
                        'name' => $name,
                        'second_name' => $second_name,
                        'barcode' => $barcode,
                    ]);


                    $inventory = new Inventory();
                    $inventory->type = null;
                    $inventory->description = $inventory_transaction->name;
                    $inventory->item_id = $item->id;
                    $inventory->warehouse_id = $warehouse_id;
                    $inventory->quantity = $stock;
                    $inventory->inventory_transaction_id = $inventory_transaction->id;
                    $inventory->lot_code = $lot_code;
                    $inventory->save();


                    $lot_code = $row[17];
                    if($lot_code){

                        $date_of_due = $row[18];
                        if(!$date_of_due) {
                            throw new Exception("Debe ingresar el Fecha de vencimiento para el Lote", 500);
                        }


                        $current_lot = ItemLotsGroup::where([
                            'code' => $lot_code,
                            'item_id'=>$item->id
                        ])->first();

                        if($current_lot) {
                            // ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
                            $current_lot->quantity = $current_lot->exactValue('quantity')->plus(\App\Support\Exact\Decimal::of($stock));
                            // ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
                            $current_lot->old_quantity = $current_lot->exactValue('quantity');
                            $current_lot->save();
                        }
                        else {
                            $_date_of_due = Date::excelToDateTimeObject($date_of_due)->format('Y-m-d');

                            ItemLotsGroup::create([
                                'code' => $lot_code,
                                'quantity' => $stock,
                                'old_quantity' => $stock,
                                'date_of_due' =>  $_date_of_due,
                                'item_id' => $item->id
                            ]);
                        }


                    }


                    $registered += 1;

                }
            }
            $this->data = compact('total', 'registered', 'warehouse_id_de');

    }

    public function getData()
    {
        return $this->data;
    }

    private function normalizeInternalId($internal_id): ?string
    {
        if (is_null($internal_id)) {
            return null;
        }

        $normalized = str_replace("\xC2\xA0", ' ', (string) $internal_id);
        $withoutControlChars = preg_replace('/[\x00-\x1F\x7F]/u', '', $normalized);
        $normalized = is_null($withoutControlChars) ? $normalized : $withoutControlChars;
        $normalized = trim($normalized);

        return $normalized === '' ? null : $normalized;
    }

    private function findItemByInternalId(string $internal_id): ?Item
    {
        return Item::query()
            ->whereRaw(
                "TRIM(REPLACE(REPLACE(REPLACE(internal_id COLLATE \"C\", CHR(9), ''), CHR(10), ''), CHR(13), '')) = ?",
                [$internal_id]
            )
            ->first();
    }

    private function validateImportRow(
        int $rowNumber,
        $description,
        $unit_type_id,
        $currency_type_id,
        $sale_unit_price,
        $sale_affectation_igv_type_id
    ): string {
        if ($this->isBlank($description)) {
            throw new Exception("Fila {$rowNumber}: la descripción del producto es obligatoria (columna A).");
        }

        if ($this->isBlank($unit_type_id)) {
            throw new Exception("Fila {$rowNumber}: la unidad de medida es obligatoria (columna E). Ejemplo: UND, KG, UND.");
        }

        if ($this->isBlank($currency_type_id)) {
            throw new Exception("Fila {$rowNumber}: la moneda es obligatoria (columna F). Use VES o USD.");
        }

        if ($this->isBlank($sale_unit_price)) {
            throw new Exception("Fila {$rowNumber}: el precio de venta unitario es obligatorio (columna G). Revise que la celda no esté vacía.");
        }

        if (!is_numeric($sale_unit_price)) {
            throw new Exception("Fila {$rowNumber}: el precio de venta unitario debe ser un número válido (columna G). Valor recibido: \"{$sale_unit_price}\".");
        }

        // ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
        $sale_unit_price = \App\Support\Exact\Decimal::input($sale_unit_price,'sale_unit_price');

        if (\App\Support\Exact\Decimal::of($sale_unit_price)->isNegative()) {
            throw new Exception("Fila {$rowNumber}: el precio de venta unitario no puede ser negativo (columna G).");
        }

        if ($this->isBlank($sale_affectation_igv_type_id)) {
            // ########## INICIO CAMBIO IGV A IVA
            throw new Exception("Fila {$rowNumber}: el tipo de Afectación IVA es obligatorio (columna H). Ejemplo: 10.");
            // ######### FIN CAMBIO IGV A IVA
        }

        return $sale_unit_price;
        // ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
    }

    private function isBlank($value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
