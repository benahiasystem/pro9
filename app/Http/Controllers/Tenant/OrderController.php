<?php
namespace App\Http\Controllers\Tenant;

use Exception;

use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use App\Models\Tenant\Series;
use App\Services\SeriesResolver;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\StatusOrder;
use App\Services\Tenant\OrderDocumentFromStatusService;
use Illuminate\Support\Facades\Cache;
use Hyn\Tenancy\Contracts\CurrentHostname;
use App\Http\Resources\Tenant\OrderCollection;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Http\Resources\Tenant\ItemWarehouseCollection;
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use App\Models\Tenant\Item;
use App\Models\Tenant\Catalogs\DocumentType;
use Modules\Ecommerce\Jobs\SendOrderStatusEmail;

class OrderController extends Controller
{

  use StorageDocument;

  protected $company;

    public function index()
    {
        return view('tenant.orders.index');
    }

    public function columns()
    {
        return [
            'id' => 'Codigo de Pedido',
            'number_document' => 'Comprobante Electronico',
        ];
    }

    public function tables()
    {
      $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
      $series = collect(app(SeriesResolver::class)->applyContext(Series::query())->get())->transform(function($row) {
          return [
              'id' => $row->id,
              'contingency' => (bool) $row->contingency,
              'document_type_id' => $row->document_type_id,
              'establishment_id' => $row->establishment_id,
              'number' => $row->number
          ];
      });

      $document_types = DocumentType::all();

      return compact('series', 'establishments', 'document_types');

    }

    public function item($internal_id)
    {
        $establishment_id = auth()->user()->establishment_id;
        $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

        $row = Item::where('internal_id', $internal_id)->first();

        return [
            'id' => $row->id,
            'description' => $row->description,
            'sale_unit_price' => round($row->sale_unit_price, 2),
            'lots' => $row->item_lots->where('has_sale', false)->where('warehouse_id', $warehouse->id)->transform(function($row) {
                return [
                    'id' => $row->id,
                    'series' => $row->series,
                    'date' => $row->date,
                    'item_id' => $row->item_id,
                    'warehouse_id' => $row->warehouse_id,
                    'has_sale' => (bool)$row->has_sale,
                    'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                ];
            })->values(),
            'series_enabled' => (bool) $row->series_enabled,
        ];
    }

    public function records(Request $request)
    {
        $records = Order::with('sale_note')
            ->where($request->column, 'like', "%{$request->value}%")
            ->when($request->status_order_id, function ($q) use ($request) {
                $q->where('status_order_id', $request->status_order_id);
            })
            ->latest();

        return new OrderCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function updateStatusOrders(Request $request)
    {
        // Obtener el estado desde cache (misma clave que StatusOrdersController)
        $hostname = app(CurrentHostname::class);
        $fqdn     = $hostname ? $hostname->fqdn : 'default';
        $cacheKey = "status_orders_{$fqdn}";

        $statuses = Cache::rememberForever($cacheKey, fn () => StatusOrder::orderBy('sort_order')->get());

        $field = in_array($request->field, ['status_order_id', 'payment_status_order_id', 'shipping_status_order_id'], true)
            ? $request->field
            : 'status_order_id';

        $statusOrder = $statuses->firstWhere('id', $request->record[$field]);

        if ($statusOrder && $statusOrder->action_generate_document) {
            $order = Order::with('sale_note')->find($request->record['id']);
            if (!$order) {
                return ['message' => 'Pedido no encontrado', 'type' => 'error'];
            }

            // Actualizar siempre el estatus primero
            $order->update([$field => $request->record[$field]]);
            $order->refresh();

            $docResult = app(OrderDocumentFromStatusService::class)
                ->generateIfConfigured($order, $statusOrder);

            if (($docResult['type'] ?? null) === 'warning') {
                return [
                    'message' => 'Estado actualizado, pero hubo un problema al generar el comprobante'
                        .(! empty($docResult['message']) ? ': '.$docResult['message'] : ' (revisa los logs)'),
                    'type' => 'warning',
                ];
            }

            if (! empty($docResult['sale_note_id'])) {
                return [
                    'message' => 'Estatus actualizado y nota de venta generada exitosamente',
                    'type' => 'success',
                    'sale_note_id' => $docResult['sale_note_id'],
                ];
            }

            if (! empty($docResult['generated'])) {
                return ['message' => 'Estatus actualizado y comprobante generado exitosamente', 'type' => 'success'];
            }

            return ['message' => 'Estatus actualizado correctamente', 'type' => 'success'];
        }

        // Anulación del pedido: revierte stock (vía NV si existe, o directo) y marca el estado
        if ($statusOrder && $statusOrder->action_void_order) {
            return $this->voidOrder($request, $field);
        }

        // Descuento de stock: antes hardcodeado para id=3, ahora guiado por el flag del estado
        if ($statusOrder && $statusOrder->action_discount_stock) {
            // Obtener la orden para verificar si ya se descontó stock
            $order = Order::where('id', $request->record['id'])->first();

            if ($order && $order->stock_discounted) {
                return ['message' => 'El stock ya fue descontado para esta orden'];
            }
            for ($i = 0; $i <= count($request->discount) - 1; $i++) {
                if (isset($request->discount[$i]['id'])) {
                    $itemWarehouse = ItemWarehouse::where('id', $request->discount[$i]['id'])->first();

                    ItemWarehouse::where('id', $itemWarehouse->id)->update([
                        'stock' => ($itemWarehouse->stock - $request->discount[$i]['cantidad'])
                    ]);
                }
            }

            Order::where('id', $request->record['id'])->update([
                $field => $request->record[$field],
                'stock_discounted' => true
            ]);

            // Encolar notificación por correo si el estado lo requiere
            if ($statusOrder->action_send_email ?? false) {
                try {
                    dispatch(new SendOrderStatusEmail($request->record['id'], $statusOrder->id, $this->buildOrderListUrl()));
                } catch (\Throwable $e) {
                    \Log::error('Failed to dispatch SendOrderStatusEmail: '.$e->getMessage());
                }
            }

            return ['message' => 'Estatus y Stock actualizado'];
        }

        // Lógica de reversión de stock cuando se rechaza/cancela un pedido
        if ($statusOrder && $statusOrder->action_free_reserved_stock) {
            $order = Order::where('id', $request->record['id'])->first();
            if ($order && $order->stock_discounted) {
                foreach ($order->items as $item) {
                    $warehouse_id = null;
                    if (isset($item->warehouse_id)) {
                        $warehouse_id = $item->warehouse_id;
                    } elseif (isset($item->warehouses) && count($item->warehouses) > 0) {
                        $warehouse_id = $item->warehouses[0]->warehouse_id;
                    }

                    $query = ItemWarehouse::where('item_id', $item->id);
                    if ($warehouse_id) {
                        $query->where('warehouse_id', $warehouse_id);
                    }
                    
                    $itemWarehouse = $query->first();

                    if ($itemWarehouse) {
                        ItemWarehouse::where('id', $itemWarehouse->id)->update([
                            'stock' => ($itemWarehouse->stock + $item->cantidad)
                        ]);
                    }
                }

                Order::where('id', $request->record['id'])->update([
                    $field => $request->record[$field],
                    'stock_discounted' => false
                ]);

                // Notificar por correo si es necesario
                if ($statusOrder->action_send_email ?? false) {
                    try {
                        dispatch(new SendOrderStatusEmail($request->record['id'], $statusOrder->id, $this->buildOrderListUrl()));
                    } catch (\Throwable $e) {
                        \Log::error('Failed to dispatch SendOrderStatusEmail: '.$e->getMessage());
                    }
                }

                return ['message' => 'Pedido rechazado y stock devuelto al inventario', 'type' => 'error'];
            }
        }

        Order::where('id', $request->record['id'])->update([
            $field => $request->record[$field]
        ]);

        // Encolar notificación por correo si el estado lo requiere
        if ($statusOrder && ($statusOrder->action_send_email ?? false)) {
            try {
                dispatch(new SendOrderStatusEmail($request->record['id'], $statusOrder->id, $this->buildOrderListUrl()));
            } catch (\Throwable $e) {
                \Log::error('Failed to dispatch SendOrderStatusEmail: '.$e->getMessage());
            }
        }

        return ['message' => 'Estatus actualizado'];
    }

    /**
     * Anula un pedido en cualquier punto del flujo.
     * - Si el pedido tiene nota de venta vigente: la anula (revierte stock + kardex + lotes).
     * - Si no tiene NV pero ya descontó stock: revierte el stock al almacén del establecimiento.
     * En ambos casos persiste el estado de anulación seleccionado.
     */
    private function voidOrder(Request $request, string $field): array
    {
        $order = Order::where('id', $request->record['id'])->first();

        if (!$order) {
            return ['message' => 'Pedido no encontrado'];
        }

        // Persistir el estado de anulación
        Order::where('id', $order->id)->update([$field => $request->record[$field]]);

        // Revertir stock según el caso
        if ($order->sale_note && (string) $order->sale_note->state_type_id !== '11') {
            // Caso con NV: reutiliza la anulación de nota de venta (revierte stock y lotes)
            app(SaleNoteController::class)->anulate($order->sale_note->id);
        } elseif ($order->stock_discounted) {
            // Caso sin NV: revierte el stock descontado directamente
            $this->revertOrderStock($order);
        }

        return ['message' => 'Pedido anulado'];
    }

    /**
     * Revierte el stock descontado de un pedido sin nota de venta, devolviendo las
     * cantidades al almacén del establecimiento del usuario. Marca stock_discounted = false.
     */
    private function revertOrderStock(Order $order): void
    {
        $warehouse = ModuleWarehouse::where('establishment_id', auth()->user()->establishment_id)->first();

        if ($warehouse) {
            foreach ($order->items as $item) {
                $itemId  = $item->id ?? null;
                $quantity = $item->cantidad ?? 0;

                if (!$itemId || !$quantity) {
                    continue;
                }

                $itemWarehouse = ItemWarehouse::where('item_id', $itemId)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();

                if ($itemWarehouse) {
                    $itemWarehouse->update(['stock' => $itemWarehouse->stock + $quantity]);
                }
            }
        }

        $order->update(['stock_discounted' => false]);
    }

    /**
     * Construye la URL absoluta de la lista de pedidos usando el hostname del tenant activo en la request.
     */
    private function buildOrderListUrl(): string
    {
        $hostname = app(CurrentHostname::class);
        $fqdn     = $hostname ? $hostname->fqdn : config('app.url');
        $protocol = config('tenant.force_https') ? 'https' : 'http';
        return "{$protocol}://{$fqdn}/ecommerce/order_list";
    }

    public function searchWarehouse(Request $request)
    {
      $product = ItemWarehouse::whereIn('item_id', $request->item_id)->orderBy('item_id')->get();
      return new ItemWarehouseCollection($product);
    }
}