<?php

namespace App\Http\Helpers;

use App\Models\Tenant\Establishment;
use App\Models\Tenant\Order;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\StatusOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\ItemWarehouse;

class HeaderNotifications
{
    public function getAll(): array
    {
        $notifications = [];

        // ########## INICIO CAMBIO SIN XML CDR SUNAT
        // No se anuncian documentos pendientes ni alertas de un servicio fiscal inexistente.
        $this->safeAppend($notifications, 'appendPaymentDueToday');
        $this->safeAppend($notifications, 'appendLowStock');
        $this->safeAppend($notifications, 'appendPendingOrders');
        $this->safeAppend($notifications, 'appendPendingEcommerceQuotations');
        // ######### FIN CAMBIO SIN XML CDR SUNAT

        usort($notifications, function ($a, $b) {
            return ($b['sort_at'] ?? 0) <=> ($a['sort_at'] ?? 0);
        });

        return [
            'total_count' => count($notifications),
            'notifications' => array_map(function ($notification) {
                unset($notification['sort_at']);

                return $notification;
            }, $notifications),
        ];
    }

    public static function getPendingOrdersCount(): int
    {
        return (int) self::pendingOrdersQuery()->count();
    }

    public static function getPendingEcommerceQuotationsCount(): int
    {
        return (int) self::pendingEcommerceQuotationsQuery()->count();
    }

    /**
     * Cotizaciones de tienda virtual pendientes de revisión (no anuladas).
     * Las cotizaciones del facturador (source = admin) quedan fuera a propósito.
     */
    public static function pendingEcommerceQuotationsQuery($query = null)
    {
        $query = $query ?: Quotation::query();

        return $query
            ->whereSourceEcommerce()
            ->where('state_type_id', '01');
    }

    public static function pendingOrdersQuery($query = null)
    {
        $query = $query ?: Order::query();

        $statuses = StatusOrder::orderBy('sort_order')->get();

        $voidStatusIds = $statuses
            ->where('action_void_order', true)
            ->pluck('id')
            ->all();

        $attentionPaymentIds = $statuses
            ->where('is_payment_status', true)
            ->filter(function ($status) {
                if ($status->is_initial) {
                    return true;
                }

                if ($status->action_void_order || $status->action_mark_payment) {
                    return false;
                }

                return !$status->is_final;
            })
            ->pluck('id')
            ->all();

        $initialPayment = self::resolveInitialPaymentStatus();

        if ($initialPayment && !in_array($initialPayment->id, $attentionPaymentIds, true)) {
            $attentionPaymentIds[] = $initialPayment->id;
        }

        if (empty($attentionPaymentIds)) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->where(function ($query) use ($attentionPaymentIds, $initialPayment) {
                $query->whereIn('payment_status_order_id', $attentionPaymentIds);

                if ($initialPayment) {
                    $query->orWhere(function ($legacyQuery) use ($initialPayment) {
                        $legacyQuery
                            ->whereNull('payment_status_order_id')
                            ->where('status_order_id', $initialPayment->id);
                    });
                }
            })
            ->where(function ($query) use ($voidStatusIds) {
                foreach (['status_order_id', 'payment_status_order_id', 'shipping_status_order_id'] as $column) {
                    $query->where(function ($columnQuery) use ($column, $voidStatusIds) {
                        $columnQuery
                            ->whereNull($column)
                            ->orWhereNotIn($column, $voidStatusIds);
                    });
                }
            });
    }

    private function safeAppend(array &$notifications, string $method): void
    {
        try {
            $this->{$method}($notifications);
        } catch (\Throwable $exception) {
            Log::warning('Header notification block failed: ' . $exception->getMessage(), [
                'method' => $method,
                'exception' => $exception,
            ]);
        }
    }

    private static function resolveInitialPaymentStatus(): ?StatusOrder
    {
        return StatusOrder::where('is_payment_status', true)
            ->where('is_initial', true)
            ->orderBy('sort_order')
            ->first()
            ?: StatusOrder::where('is_payment_status', true)
                ->orderBy('sort_order')
                ->first();
    }

    private function safeRoute(string $name, string $fallback = '#'): string
    {
        try {
            return route($name);
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    private function appendPaymentDueToday(array &$notifications): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $documentPayments = DB::connection('tenant')->table('document_payments')
            ->select('document_id', DB::raw('SUM(payment) as total_payment'))
            ->groupBy('document_id');

        $record = DB::connection('tenant')
            ->table('documents')
            ->join('invoices', 'invoices.document_id', '=', 'documents.id')
            ->join('persons', 'persons.id', '=', 'documents.customer_id')
            ->leftJoinSub($documentPayments, 'payments', function ($join) {
                $join->on('documents.id', '=', 'payments.document_id');
            })
            ->whereIn('documents.state_type_id', ['01', '03', '05', '07', '13'])
            ->whereIn('documents.document_type_id', ['01', '03', '08'])
            ->whereDate('invoices.date_of_due', $today)
            ->whereRaw('documents.total > IFNULL(payments.total_payment, 0)')
            ->select(
                'documents.id',
                'documents.total',
                'documents.updated_at',
                'persons.name as customer_name',
                DB::raw("CONCAT(documents.series, '-', documents.number) AS number_full"),
                DB::raw('IFNULL(payments.total_payment, 0) as total_payment')
            )
            ->orderBy('documents.updated_at', 'desc')
            ->first();

        if (!$record) {
            return;
        }

        $amount = max((float) $record->total - (float) $record->total_payment, 0);
        $timestamp = $record->updated_at ? Carbon::parse($record->updated_at) : now();

        $notifications[] = [
            'id' => 'payment_due_today',
            'type' => 'pagos',
            'icon' => 'invoice',
            'icon_bg' => 'blue',
            'title' => 'Pago pendiente por cobrar',
            'description_parts' => [
                ['text' => 'La factura ', 'bold' => false],
                ['text' => $record->number_full, 'bold' => true],
                ['text' => ' de ' . $record->customer_name . ' vence hoy · Bs. ' . number_format($amount, 2, '.', ','), 'bold' => false],
            ],
            'time_ago' => $this->timeAgo($timestamp),
            'unread' => true,
            'url' => $this->safeRoute('tenant.finances.unpaid.index', '/finances/unpaid'),
            'sort_at' => $timestamp->timestamp,
        ];
    }

    private function appendLowStock(array &$notifications): void
    {
        $establishmentId = optional(Establishment::select('id')->first())->id;

        $baseQuery = ItemWarehouse::query()
            ->whereHas('item', function ($query) {
                $query->whereNotIsSet()
                    ->where('status', true)
                    ->where('unit_type_id', '!=', 'ZZ')
                    ->where('stock_min', '>', 0);
            })
            ->whereRaw('stock <= (SELECT stock_min FROM items WHERE items.id = item_warehouse.item_id)');

        if ($establishmentId) {
            $baseQuery->whereHas('warehouse', function ($query) use ($establishmentId) {
                $query->where('establishment_id', $establishmentId);
            });
        }

        $count = (int) (clone $baseQuery)->count();

        $row = (clone $baseQuery)
            ->with('item:id,description,stock_min')
            ->orderBy('stock', 'asc')
            ->first();

        if (!$row || !$row->item) {
            return;
        }

        $stock = (int) max(round((float) $row->stock), 0);
        $timestamp = $row->updated_at;

        $notifications[] = [
            'id' => 'low_stock',
            'type' => 'inventario',
            'icon' => 'box',
            'icon_bg' => 'red',
            'title' => 'Producto por agotarse',
            'description_parts' => [
                ['text' => $row->item->description, 'bold' => true],
                ['text' => " — quedan {$stock} unidad" . ($stock === 1 ? '' : 'es') . ' bajo el mínimo.', 'bold' => false],
            ],
            'time_ago' => $this->timeAgo($timestamp),
            'unread' => false,
            'url' => $this->safeRoute('inventory.index', '/inventory'),
            'count' => $count,
            'sort_at' => $timestamp ? $timestamp->timestamp : now()->timestamp,
        ];
    }

    private function appendPendingOrders(array &$notifications): void
    {
        $query = self::pendingOrdersQuery();
        $count = (int) (clone $query)->count();

        if ($count <= 0) {
            return;
        }

        $latest = (clone $query)->latest('created_at')->first();

        $timestamp = optional($latest)->created_at;

        $notifications[] = [
            'id' => 'pending_orders',
            'type' => 'pedidos',
            'icon' => 'bag',
            'icon_bg' => 'green',
            'title' => 'Pedidos pendientes',
            'description_parts' => [
                ['text' => 'Hay ', 'bold' => false],
                ['text' => (string) $count, 'bold' => true],
                ['text' => ' pedido' . ($count === 1 ? '' : 's') . ' pendiente' . ($count === 1 ? '' : 's') . ' por procesar.', 'bold' => false],
            ],
            'time_ago' => $this->timeAgo($timestamp),
            'unread' => true,
            'url' => $this->safeRoute('tenant_orders_index', '/orders'),
            'count' => $count,
            'sort_at' => $timestamp ? $timestamp->timestamp : now()->timestamp,
        ];
    }

    private function appendPendingEcommerceQuotations(array &$notifications): void
    {
        $query = self::pendingEcommerceQuotationsQuery();
        $count = (int) (clone $query)->count();

        if ($count <= 0) {
            return;
        }

        $latest = (clone $query)->with('person')->latest('created_at')->first();
        $timestamp = optional($latest)->created_at;

        $customerName = optional(optional($latest)->person)->name
            ?: (optional($latest)->customer->name ?? 'Cliente');

        $code = $latest
            ? ($latest->identifier ?? $latest->number_full ?? ('COT-' . $latest->id))
            : '';

        $notifications[] = [
            'id' => 'pending_ecommerce_quotations',
            'type' => 'cotizaciones',
            'icon' => 'invoice',
            'icon_bg' => 'yellow',
            'title' => 'Cotización' . ($count === 1 ? '' : 'es') . ' de tienda virtual',
            'description_parts' => $count === 1
                ? [
                    ['text' => 'Nueva cotización ', 'bold' => false],
                    ['text' => $code, 'bold' => true],
                    ['text' => ' de ' . $customerName . ' — pendiente de revisión.', 'bold' => false],
                ]
                : [
                    ['text' => 'Hay ', 'bold' => false],
                    ['text' => (string) $count, 'bold' => true],
                    ['text' => ' cotizaciones de tienda virtual pendientes de revisión.', 'bold' => false],
                ],
            'time_ago' => $this->timeAgo($timestamp),
            'unread' => true,
            'url' => $this->safeRoute('tenant.quotations.index', '/quotations'),
            'count' => $count,
            'sort_at' => $timestamp ? $timestamp->timestamp : now()->timestamp,
        ];
    }

    private function timeAgo($datetime): string
    {
        if (!$datetime) {
            return 'Recién';
        }

        $carbon = Carbon::parse($datetime);

        if ($carbon->isFuture()) {
            return 'Recién';
        }

        $diffMinutes = $carbon->diffInMinutes(now());

        if ($diffMinutes < 1) {
            return 'Recién';
        }

        if ($diffMinutes < 60) {
            return "Hace {$diffMinutes} min";
        }

        $diffHours = $carbon->diffInHours(now());

        if ($diffHours < 24) {
            return "Hace {$diffHours} h";
        }

        $diffDays = $carbon->diffInDays(now());

        return "Hace {$diffDays} d";
    }
}
