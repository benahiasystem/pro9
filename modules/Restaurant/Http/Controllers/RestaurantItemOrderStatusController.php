<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Exception;
use App\Models\Tenant\Item;
use Modules\Restaurant\Models\RestaurantItemOrderStatus;
use Modules\Restaurant\Models\RestaurantTable;
use Modules\Restaurant\Services\RestaurantStockService;
use App\Services\CentrifugoService;
use Hyn\Tenancy\Contracts\CurrentHostname;


class RestaurantItemOrderStatusController extends Controller
{
    const STATUS_RECEIVED = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_TO_DELIVER = 3;
    const STATUS_DELIVERED = 4;

    public function saveItemOrder(Request $request) {

        $itemData = $request->item;
        $stockService = app(RestaurantStockService::class);

        // Descontar supplies inmediatamente al crear la orden
        try {
            // Si el item es un set, descontar supplies de cada componente
            if (isset($itemData['has_sets']) && $itemData['has_sets']) {
                foreach ($itemData['items_sets'] as $itemSet) {
                    $item_model = Item::find($itemSet['id']);
                    if (!$item_model) continue;

                    $item_supplies = $item_model->restaurantItemSupplies;
                    foreach ($item_supplies as $item_supply) {
                        $supply_quantity = $item_supply->quantity;
                        $order_quantity = $request->quantity * $itemSet['pivot']['quantity'];
                        $total_to_discount = $supply_quantity * $order_quantity;
                        $supply = $item_supply->supply;
                        $supply->stock -= $total_to_discount;
                        $supply->save();
                    }

                    // Recalcular stock del componente después de descontar supplies
                    $stockService->calculateAndUpdateStock($itemSet['id']);
                }
            } else if (isset($itemData['has_supplies']) && $itemData['has_supplies']) {
                // Item con supplies: descontar insumos
                $item_model = Item::find($request->item_id);
                if ($item_model) {
                    $item_supplies = $item_model->restaurantItemSupplies;
                    foreach ($item_supplies as $item_supply) {
                        $supply_quantity = $item_supply->quantity;
                        $order_quantity = $request->quantity;
                        $total_to_discount = $supply_quantity * $order_quantity;
                        $supply = $item_supply->supply;
                        $supply->stock -= $total_to_discount;
                        $supply->save();
                    }
                }
            }

        } catch (Exception $e) {
            \Log::error("Error descontando supplies: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al descontar insumos.'
            ];
        }

        // Recalcular stock después de descontar supplies
        $stockService->calculateAndUpdateStock($request->item_id);

        // Reservar cantidades después de descontar supplies y recalcular stock
        try {
            // Si el item es un set, reservar cada componente
            if (isset($itemData['has_sets']) && $itemData['has_sets']) {
                foreach ($itemData['items_sets'] as $itemSet) {
                    $componentQuantity = $itemSet['pivot']['quantity'] * $request->quantity;
                    $stockService->reserveQuantity($itemSet['id'], $componentQuantity);
                }
            } else {
                // Item simple: reservar cantidad directamente
                $stockService->reserveQuantity($request->item_id, $request->quantity);
            }

            // Reservar stock de modificadores aplicados (si tienen type: "item" y item_id)
            if (isset($itemData['modifiersApplied']) && is_array($itemData['modifiersApplied'])) {
                foreach ($itemData['modifiersApplied'] as $group) {
                    if (isset($group['items']) && is_array($group['items'])) {
                        foreach ($group['items'] as $modifierItem) {
                            // Solo reservar si es de tipo "item" y tiene item_id
                            if (isset($modifierItem['type']) && $modifierItem['type'] === 'item'
                                && isset($modifierItem['item_id']) && $modifierItem['item_id']) {
                                $stockService->reserveQuantity($modifierItem['item_id'], $request->quantity);
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            \Log::error("Error reservando stock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al reservar stock.'
            ];
        }

        // Crear la orden
        $orderStatus = new RestaurantItemOrderStatus();
        $orderStatus->table_id = $request->table_id;
        $orderStatus->item_id = $request->item_id;
        $orderStatus->item = json_encode($itemData);
        $orderStatus->quantity = $request->quantity;
        $orderStatus->note = $request->note;
        $orderStatus->status = $request->status;
        $orderStatus->status_description = $request->status_description;
        $orderStatus->save();

        // Si se agrega un ítem nuevo a una mesa ya "served", volver a pending
        // (cocina tiene trabajo pendiente). No degradar shipped/delivered.
        $sync = $this->syncTableOrderStatusFromKitchen((int) $request->table_id);

        $this->publishCommandUpdate();
        $this->publishStockUpdate();

        return [
            'success' => true,
            'message' => 'Producto agregado con éxito.',
            'kitchen_complete' => $sync['kitchen_complete'],
            'order_status' => $sync['order_status'],
        ];

    }

    public function getStatusItems($id)
    {
        $data = [
            'productsStatusReceived' => $this->getItemsByStatus(self::STATUS_RECEIVED,$id),
            'productsStatusProcessing' => $this->getItemsByStatus(self::STATUS_PROCESSING,$id),
            'productsStatusToDeliver' => $this->getItemsByStatus(self::STATUS_TO_DELIVER,$id),
            'productsStatusDelivered' => $this->getItemsByStatus(self::STATUS_DELIVERED,$id, 20,'desc'),
        ];

        return [
            'success' => true,
            'data' => $data,
            'message' => 'Listado de productos por estados.',
            'id' =>$id
        ];
    }

    private function getItemsByStatus($status, $table_id = 0, $limit = null, $desc = null)
    {
        $query = RestaurantItemOrderStatus::where('status', $status)
            ->with(['table', 'itemModel.preparationArea']);

        if ($table_id>0) {
            $query->where('table_id',$table_id);
        }

        if ($limit) {
            $query->take($limit);
        }

        if ($desc) {
            $query->orderBy('updated_at',$desc);
        }

        return $query->get()->transform(function ($order) {
            return $this->transformOrderData($order);
        });
    }

    public function isProductsCommandStatusServer($tableId)
    {
        $total = RestaurantItemOrderStatus::where('table_id', $tableId)->count();

        // Sin ítems de comanda no se considera "servido" (evita true vacío).
        if ($total === 0) {
            return false;
        }

        $notCompleted = RestaurantItemOrderStatus::where('table_id', $tableId)
            ->where('status', '!=', self::STATUS_DELIVERED)
            ->count();

        return $notCompleted === 0;
    }


    private function transformOrderData($order)
    {
        $itemData = json_decode($order->item);

        return [
            'id' => $order->id,
            'name' => $itemData->name ?? null,
            'quantity' => $order->quantity,
            'note' => $order->note ?? null,
            'modifiers_applied' => $itemData->modifiersApplied ?? [],
            'status' => $order->status,
            'status_description' => $order->status_description,
            'mesa_id' => $order->table_id,
            'mesa' => $order->table->label ?? null,
            'environment_id' => $order->table->environment_id ?? null,
            'environment' => $order->table->environment ?? null,
            'preparation_area_id' => $order->itemModel->preparation_area_id ?? null,
            'preparation_area_name' => $order->itemModel->preparationArea->name ?? null,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    public function setStatusItem($id)
    {
        $order = RestaurantItemOrderStatus::where('id', $id)->first();

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Orden no encontrada'
            ];
        }

        // Solo incrementar el estado (supplies ya fueron descontados en saveItemOrder)
        if ($order->status < self::STATUS_DELIVERED) {
            $order->status += 1;
        }
        $order->save();

        // Si cocina terminó todos los ítems, promover la mesa a "served"
        // para desbloquear En camino / Despachado en Delivery (aunque ya esté pagado).
        $sync = $this->syncTableOrderStatusFromKitchen((int) $order->table_id);

        $this->publishCommandUpdate();

        return [
            'success' => true,
            'message' => 'Estado cambiado con éxito',
            'kitchen_complete' => $sync['kitchen_complete'],
            'order_status' => $sync['order_status'],
            'table_id' => $order->table_id,
            'is_paid' => $sync['is_paid'],
        ];
    }

    /**
     * Sincroniza restaurant_tables.order_status con el avance de comanda.
     *
     * Reglas:
     * - Si todos los ítems están en status 4 y order_status es pending/precuenta → served
     * - Si hay ítems incompletos y order_status es served → pending (vuelve a cocina)
     * - Nunca degrada shipped ni delivered (flujo de repartidor)
     * - No toca is_paid
     */
    private function syncTableOrderStatusFromKitchen(int $tableId): array
    {
        $table = RestaurantTable::find($tableId);

        if (!$table) {
            return [
                'kitchen_complete' => false,
                'order_status' => null,
                'is_paid' => false,
                'changed' => false,
            ];
        }

        $kitchenComplete = $this->isProductsCommandStatusServer($tableId);
        $current = $table->order_status ?: 'pending';
        $next = $current;
        $changed = false;

        // Estados avanzados de delivery/reparto: no degradar
        $lockedStatuses = ['shipped', 'delivered', 'deleted'];

        if ($kitchenComplete) {
            if (in_array($current, ['pending', 'precuenta', null, ''], true)) {
                $next = 'served';
            }
        } else {
            // Hay trabajo pendiente en cocina: solo bajar desde served → pending
            if ($current === 'served') {
                $next = 'pending';
            } elseif (in_array($current, $lockedStatuses, true)) {
                $next = $current;
            }
        }

        if ($next !== $current) {
            $table->order_status = $next;
            $table->save();
            $changed = true;
            $this->publishTablesUpdate();
        }

        return [
            'kitchen_complete' => $kitchenComplete,
            'order_status' => $table->order_status,
            'is_paid' => (bool) $table->is_paid,
            'changed' => $changed,
        ];
    }

    private function publishTablesUpdate(): void
    {
        $fqdn = app(CurrentHostname::class)?->fqdn ?? 'local';
        $payload = app(RestaurantConfigurationController::class)->tablesAndEnv();

        app(CentrifugoService::class)->publish("restaurant:{$fqdn}", [
            'event'   => 'tables-env-updated',
            'payload' => $payload,
        ]);
    }

    /**
     * Publica el snapshot completo de la comanda (las 4 colas, todas las mesas)
     * vía WebSocket. El front lo aplica directo y filtra por mesa client-side.
     * getStatusItems(0) no filtra por mesa, así que trae todo (el bucket
     * "Entregado" ya viene limitado a 20 en getItemsByStatus).
     */
    private function publishCommandUpdate(): void
    {
        $fqdn = app(CurrentHostname::class)?->fqdn ?? 'local';
        app(CentrifugoService::class)->publish("restaurant:{$fqdn}", [
            'event'   => 'command-items-updated',
            'payload' => $this->getStatusItems(0)['data'],
        ]);
    }

    /**
     * Publica el snapshot de stock (cantidades disponibles por ítem) vía WebSocket.
     * getStockStatus vive en RestaurantController, se llama cross-controller.
     */
    private function publishStockUpdate(): void
    {
        $fqdn = app(CurrentHostname::class)?->fqdn ?? 'local';
        $data = app(\Modules\Restaurant\Http\Controllers\RestaurantController::class)
            ->getStockStatus()['data'] ?? [];
        app(CentrifugoService::class)->publish("restaurant:{$fqdn}", [
            'event'   => 'stock-updated',
            'payload' => $data,
        ]);
    }

}
