<?php

namespace App\Imports;

use App\Models\Tenant\Item;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\ItemWarehouse;

class StockImport implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
        $warehouse_id = (int) request('warehouse_id');
        $total = $rows->count();
        $registered = 0;
        $skipped = 0;
        $errors = [];
        $processed_internal_ids = [];

        unset($rows[0]);

        DB::connection('tenant')->beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $row_number = (int) $index + 1;
                $internal_id = $this->normalizeInternalId($row[0] ?? null);
                $quantity_raw = $row[1] ?? null;

                if ($this->isEmptyRow($internal_id, $quantity_raw)) {
                    $skipped++;
                    continue;
                }

                if ($internal_id === '') {
                    $errors[] = "Fila {$row_number}: el código interno es obligatorio.";
                    continue;
                }

                if (isset($processed_internal_ids[$internal_id])) {
                    $errors[] = "Fila {$row_number} ({$internal_id}): código interno duplicado en el archivo.";
                    continue;
                }

                if (!$this->isValidQuantity($quantity_raw)) {
                    $errors[] = "Fila {$row_number} ({$internal_id}): el stock real debe ser un número mayor o igual a 0.";
                    continue;
                }

                $quantity_real = (float) $quantity_raw;

                $item = Item::where('internal_id', $internal_id)->first();
                if (!$item) {
                    $errors[] = "Fila {$row_number} ({$internal_id}): producto no encontrado.";
                    continue;
                }

                $item_warehouse = ItemWarehouse::where('item_id', $item->id)
                    ->where('warehouse_id', $warehouse_id)
                    ->first();

                $quantity = $item_warehouse ? (float) $item_warehouse->stock : 0;

                if ($quantity_real == $quantity) {
                    $skipped++;
                    continue;
                }

                $type = 1;
                $quantity_new = $quantity_real - $quantity;
                if ($quantity_real < $quantity) {
                    $quantity_new = $quantity - $quantity_real;
                    $type = null;
                }

                $inventory = new Inventory();
                $inventory->type = $type;
                $inventory->description = 'Stock Real';
                $inventory->item_id = $item->id;
                $inventory->warehouse_id = $warehouse_id;
                $inventory->quantity = $quantity_new;
                if ($quantity_real != $quantity) {
                    $inventory->inventory_transaction_id = 28;
                }
                $inventory->real_stock = $quantity_real;
                $inventory->system_stock = $quantity;
                $inventory->save();

                $item_warehouse = ItemWarehouse::firstOrNew([
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse_id,
                ]);
                $item_warehouse->stock = $quantity_real;
                $item_warehouse->save();

                $processed_internal_ids[$internal_id] = true;
                $registered++;
            }

            if ($registered === 0 && !empty($errors)) {
                throw new Exception($this->buildErrorMessage($errors));
            }

            DB::connection('tenant')->commit();
        } catch (Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            throw new Exception('Error al importar el stock real: ' . $e->getMessage(), 0, $e);
        }

        $this->data = [
            'total' => $total,
            'registered' => $registered,
            'skipped' => $skipped,
            'errors' => $errors,
            'warehouse_id' => $warehouse_id,
            'warehouse_id_de' => $warehouse_id,
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    private function normalizeInternalId($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(str_replace("\t", '', (string) $value));
    }

    private function isEmptyRow(?string $internal_id, $quantity_raw): bool
    {
        $quantity_is_empty = $quantity_raw === null
            || (is_string($quantity_raw) && trim($quantity_raw) === '');

        return $internal_id === '' && $quantity_is_empty;
    }

    private function isValidQuantity($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $value = trim(str_replace(',', '.', $value));
            if ($value === '') {
                return false;
            }
        }

        if (!is_numeric($value)) {
            return false;
        }

        return (float) $value >= 0;
    }

    private function buildErrorMessage(array $errors): string
    {
        $messages = array_slice($errors, 0, 15);
        $message = "No se importó ningún producto. Errores encontrados:\n" . implode("\n", $messages);

        if (count($errors) > 15) {
            $message .= "\n... y " . (count($errors) - 15) . ' error(es) más.';
        }

        return $message;
    }
}
