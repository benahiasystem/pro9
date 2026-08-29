<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use App\Models\Tenant\Item;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Inventory;
use Modules\Inventory\Models\InventoryConfiguration;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Inventory\Models\Warehouse;
use Modules\MobileApp\Http\Requests\Api\InventoryAdjustRequest;
use Modules\MobileApp\Http\Requests\Api\InventoryTransferRequest;

/**
 * Inventario para la app movil: catalogos, traslado entre almacenes y ajuste a stock real.
 * La lectura de stock usa GET items/records-scroll (warehouse_id, stock_filter).
 * El +/- de stock lo aplica InventoryChangeServiceProvider al crear Inventory
 * (type 1 suma, type 2 traslado origen->destino, type null segun inventory_transactions.type).
 */
class InventoryController extends Controller
{
    // Transaccion "Ajuste por diferencia de inventario" (salida), la misma que usa la web en InventoryController@stock
    const ADJUST_OUTPUT_TRANSACTION_ID = '28';

    // Transacciones automaticas (venta, compra, traslado, inicial, molino, importacion): no son motivos de ajuste manual
    const AUTOMATIC_TRANSACTION_IDS = ['01', '02', '11', '16', '21', '100', '101', '102'];

    /**
     * Almacenes (todos; un establecimiento = un almacen), motivos de ajuste y control de stock.
     *
     * @return array
     */
    public function tables()
    {
        $establishment_id = auth()->user()->establishment_id;

        $warehouses = Warehouse::with('establishment')
            ->get()
            ->map(fn($row) => [
                'id' => $row->id,
                'description' => $row->description,
                'establishment_id' => $row->establishment_id,
                'establishment_description' => optional($row->establishment)->description,
                'is_current' => $row->establishment_id === $establishment_id,
            ])
            ->values();

        $adjust_reasons = InventoryTransaction::query()
            ->whereNotIn('id', self::AUTOMATIC_TRANSACTION_IDS)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn($row) => [
                'id' => (string) $row->id,
                'name' => $row->name,
                'type' => $row->type,
            ])
            ->values();

        $configuration = InventoryConfiguration::first();

        return [
            'success' => true,
            'data' => [
                'warehouses' => $warehouses,
                'adjust_reasons' => $adjust_reasons,
                'stock_control' => (bool) optional($configuration)->stock_control,
            ],
        ];
    }

    /**
     * Traslado de 1..N productos entre almacenes. Replica InventoryController@moveMultiples
     * (modulo Inventory): solo crea Inventory type 2, sin documento U4 ni serie.
     *
     * @param  InventoryTransferRequest $request
     * @return array
     */
    public function transfer(InventoryTransferRequest $request)
    {
        $warehouse_id = (int) $request->warehouse_id;
        $warehouse_destination_id = (int) $request->warehouse_destination_id;
        $description = $request->description ?: null;

        try {
            $moved = DB::connection('tenant')->transaction(function () use ($request, $warehouse_id, $warehouse_destination_id, $description) {
                $moved = [];

                foreach ($request->items as $row) {
                    $item = Item::findOrFail($row['item_id']);
                    $quantity = (float) $row['quantity'];

                    $this->assertWithoutLotsOrSeries($item);

                    $stock = $this->currentStock($item->id, $warehouse_id);
                    if ($quantity > $stock) {
                        throw new \Exception("Stock insuficiente para {$item->description} (disponible: {$stock}).");
                    }

                    Inventory::create([
                        'type' => 2,
                        'description' => 'Traslado',
                        'detail' => $description,
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse_id,
                        'warehouse_destination_id' => $warehouse_destination_id,
                        'quantity' => $quantity,
                    ]);

                    $moved[] = [
                        'item_id' => $item->id,
                        'quantity' => $quantity,
                        'origin_stock' => $this->currentStock($item->id, $warehouse_id),
                        'destination_stock' => $this->currentStock($item->id, $warehouse_destination_id),
                    ];
                }

                return $moved;
            });
        } catch (\Exception $e) {
            return $this->error($e);
        }

        return [
            'success' => true,
            'message' => count($moved) === 1
                ? 'Producto trasladado con éxito'
                : count($moved) . ' productos trasladados con éxito',
            'data' => ['items' => $moved],
        ];
    }

    /**
     * Ajuste a stock real de 1..N productos con motivo por catalogo. Replica InventoryController@stock:
     * registra la diferencia (type 1 si sube; type null + transaccion de salida si baja) y fija
     * item_warehouse.stock = real_stock. Si el stock cambio desde que la app lo leyo, responde
     * code=stock_changed con el stock actual para que el usuario confirme de nuevo.
     *
     * @param  InventoryAdjustRequest $request
     * @return array
     */
    public function adjust(InventoryAdjustRequest $request)
    {
        $warehouse_id = (int) $request->warehouse_id;
        $transaction = InventoryTransaction::find($request->inventory_transaction_id);
        $comments = $request->comments ?: null;

        // Comprobacion de concurrencia antes de mutar nada
        foreach ($request->items as $row) {
            $current = $this->currentStock((int) $row['item_id'], $warehouse_id);
            if (abs($current - (float) $row['system_stock']) > 0.0001) {
                return [
                    'success' => false,
                    'code' => 'stock_changed',
                    'item_id' => (int) $row['item_id'],
                    'current_stock' => $current,
                    'message' => 'El stock cambió desde que abriste el producto. Revisa el valor actual y vuelve a confirmar.',
                ];
            }
        }

        try {
            $adjusted = DB::connection('tenant')->transaction(function () use ($request, $warehouse_id, $transaction, $comments) {
                $adjusted = [];

                foreach ($request->items as $row) {
                    $item = Item::findOrFail($row['item_id']);
                    $this->assertWithoutLotsOrSeries($item);

                    $system_stock = (float) $row['system_stock'];
                    $real_stock = (float) $row['real_stock'];
                    $difference = $real_stock - $system_stock;

                    if (abs($difference) > 0.0001) {
                        $is_input = $difference > 0;
                        // Solo se usa la transaccion elegida si su sentido coincide con la diferencia;
                        // si no, cae a la transaccion de ajuste por diferencia (como la web).
                        $transaction_id = ($transaction && $transaction->type === ($is_input ? 'input' : 'output'))
                            ? $transaction->id
                            : ($is_input ? null : self::ADJUST_OUTPUT_TRANSACTION_ID);

                        Inventory::create([
                            // type 1 => suma; type null => el provider resuelve por inventory_transactions.type (salida)
                            'type' => $is_input ? 1 : null,
                            'description' => 'Stock Real',
                            'item_id' => $item->id,
                            'warehouse_id' => $warehouse_id,
                            'quantity' => abs($difference),
                            'inventory_transaction_id' => $transaction_id,
                            'comments' => $comments,
                            'real_stock' => $real_stock,
                            'system_stock' => $system_stock,
                        ]);
                    }

                    // Igual que la web: el stock queda exactamente en el valor contado
                    $item_warehouse = ItemWarehouse::firstOrNew([
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse_id,
                    ]);
                    $item_warehouse->stock = $real_stock;
                    $item_warehouse->save();

                    $adjusted[] = [
                        'item_id' => $item->id,
                        'previous_stock' => $system_stock,
                        'new_stock' => $real_stock,
                        'difference' => $difference,
                    ];
                }

                return $adjusted;
            });
        } catch (\Exception $e) {
            return $this->error($e);
        }

        return [
            'success' => true,
            'message' => count($adjusted) === 1
                ? 'Stock actualizado con éxito'
                : count($adjusted) . ' productos ajustados con éxito',
            'data' => ['items' => $adjusted],
        ];
    }

    private function currentStock(int $item_id, int $warehouse_id): float
    {
        $row = ItemWarehouse::getItemStockData($item_id, $warehouse_id)->first();
        return (float) optional($row)->stock;
    }

    // Lotes y series se gestionan solo desde la web en esta version
    private function assertWithoutLotsOrSeries(Item $item): void
    {
        if ($item->lots_enabled || $item->series_enabled) {
            throw new \Exception("{$item->description} maneja lotes o series: gestiónalo desde la web.");
        }
    }

    private function error(\Exception $e): array
    {
        return [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }
}
