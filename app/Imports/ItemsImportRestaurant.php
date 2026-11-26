<?php

namespace App\Imports;

use App\Models\Tenant\Item;
use App\Models\Tenant\Warehouse;
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


class ItemsImportRestaurant implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
            $total = count($rows);
            $warehouse_id_de = request('warehouse_id');
            $registered = 0;
            unset($rows[0]);
            foreach ($rows as $row) {
                $description = $row[0];
                $item_type_id = '01';
                $internal_id = $this->normalizeInternalId($row[1] ?? null);
                $unit_type_id = $row[2];
                $currency_type_id = $row[3];
                $sale_unit_price = $row[4];
                $sale_affectation_igv_type_id = $row[5];

                $affectation_igv_types_exonerated_unaffected = ['20','21','30','31','32','33','34','35','36','37'];

                if(in_array($sale_affectation_igv_type_id, $affectation_igv_types_exonerated_unaffected)) {

                    $has_igv = true;

                }else{

                    $has_igv = (strtoupper($row[6]) === 'SI')?true:false;

                }

                $purchase_unit_price = ($row[7])?:0;
                $purchase_affectation_igv_type_id = ($row[8])?:null;
                $stock = $row[9];
                $stock_min = $row[10];
                $category_name = $row[11];
                $barcode = $row[12] ?? null;
                $image_url = $row[13] ?? null;

                $warehouse_id = request('warehouse_id');


                if($internal_id) {
                    $item = $this->findItemByInternalId($internal_id);
                } else {
                    $item = null;
                }

                if(!$item) {
                    // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    $images = app(\App\Support\ItemImport\ImportedItemImage::class)->load($image_url, $description, $internal_id);
                    $file_name = $images['image'];
                    $file_name_medium = $images['image_medium'];
                    $file_name_small = $images['image_small'];
                    // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
                    $category = $category_name ? Category::updateOrCreate(['name' => $category_name]):null;

                    Item::create([
                        'description' => $description,
                        'item_type_id' => $item_type_id,
                        'internal_id' => $internal_id,
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
                        'barcode' => $barcode,
                        'warehouse_id' => $warehouse_id,
                        'image' => $file_name,
                        'image_medium' => $file_name_medium,
                        'image_small' => $file_name_small,
                        'apply_restaurant' => 1,
                    ]);

                    $registered += 1;

                }else{

                    $inventory_transaction = InventoryTransaction::findOrFail('102');
                    $stock = $row[9];
                    if (!$stock) {
                        throw new Exception("Debe ingresar el stock", 500);
                    }

                    $item->update([
                        'description' => $description,
                        'item_type_id' => $item_type_id,
                        'internal_id' => $internal_id,
                        'unit_type_id' => $unit_type_id,
                        'currency_type_id' => $currency_type_id,
                        'sale_unit_price' => $sale_unit_price,
                        'sale_affectation_igv_type_id' => $sale_affectation_igv_type_id,
                        'has_igv' => $has_igv,
                        'purchase_unit_price' => $purchase_unit_price,
                        'purchase_affectation_igv_type_id' => $purchase_affectation_igv_type_id,
                        'stock_min' => $stock_min,
                        'barcode' => $barcode,
                        'apply_restaurant' => 1,
                    ]);


                    $inventory = new Inventory();
                    $inventory->type = null;
                    $inventory->description = $inventory_transaction->name;
                    $inventory->item_id = $item->id;
                    $inventory->warehouse_id = $warehouse_id;
                    $inventory->quantity = $stock;
                    $inventory->inventory_transaction_id = $inventory_transaction->id;
                    $inventory->save();

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
}
