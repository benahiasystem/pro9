<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpgradeLegacyStatusOrdersToAdvanced extends Migration
{
    /**
     * Actualiza tenants legacy (4 estados antiguos) al set avanzado de estados
     * usado en tiendas nuevas (pago, envío y pedido por separado).
     */
    public function up()
    {
        if (! Schema::hasColumn('configurations', 'has_advanced_statuses')) {
            return;
        }

        if (DB::table('configurations')->value('has_advanced_statuses')) {
            return;
        }

        $legacyDescriptions = ['Pago sin verificar', 'Pago verificado', 'Despachado', 'Confirmado por el cliente'];
        $current = DB::table('status_orders')->pluck('description', 'id');

        if ($current->count() !== 4 || $current->diff($legacyDescriptions)->isNotEmpty()) {
            return;
        }

        $orders = DB::table('orders')->get([
            'id',
            'status_order_id',
            'payment_status_order_id',
            'shipping_status_order_id',
        ]);

        Schema::disableForeignKeyConstraints();
        DB::table('status_orders')->truncate();

        $advancedStatuses = [
            ['description' => 'Pago pendiente', 'color' => '#ffc107', 'is_initial' => true, 'is_final' => false, 'is_payment_status' => true, 'is_shipping_status' => false, 'is_order_status' => false, 'action_mark_payment' => false, 'sort_order' => 1],
            ['description' => 'Pago completado', 'color' => '#28a745', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => true, 'is_shipping_status' => false, 'is_order_status' => false, 'action_mark_payment' => true, 'action_generate_document' => true, 'sort_order' => 2],
            ['description' => 'Pago rechazado', 'color' => '#dc3545', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => true, 'is_shipping_status' => false, 'is_order_status' => false, 'action_send_email' => true, 'sort_order' => 3],
            ['description' => 'Reembolso', 'color' => '#6c757d', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => true, 'is_shipping_status' => false, 'is_order_status' => false, 'action_send_email' => true, 'sort_order' => 4],
            ['description' => 'Preparando pedido', 'color' => '#17a2b8', 'is_initial' => true, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => true, 'is_order_status' => false, 'action_discount_stock' => true, 'sort_order' => 5],
            ['description' => 'Listo para recojo', 'color' => '#fd7e14', 'is_initial' => false, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => true, 'is_order_status' => false, 'action_send_email' => true, 'sort_order' => 6],
            ['description' => 'En camino', 'color' => '#007bff', 'is_initial' => false, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => true, 'is_order_status' => false, 'action_notify_dispatch' => true, 'sort_order' => 7],
            ['description' => 'Entregado', 'color' => '#28a745', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => false, 'is_shipping_status' => true, 'is_order_status' => false, 'action_mark_payment' => false, 'sort_order' => 8],
            ['description' => 'Entrega pendiente', 'color' => '#ffc107', 'is_initial' => false, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => true, 'is_order_status' => false, 'action_send_email' => true, 'sort_order' => 9],
            ['description' => 'Nuevo pedido', 'color' => '#17a2b8', 'is_initial' => true, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => false, 'is_order_status' => true, 'sort_order' => 10],
            ['description' => 'En proceso', 'color' => '#007bff', 'is_initial' => false, 'is_final' => false, 'is_payment_status' => false, 'is_shipping_status' => false, 'is_order_status' => true, 'sort_order' => 11],
            ['description' => 'Cancelado', 'color' => '#dc3545', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => false, 'is_shipping_status' => false, 'is_order_status' => true, 'action_send_email' => true, 'action_void_order' => true, 'sort_order' => 12],
            ['description' => 'Completado', 'color' => '#28a745', 'is_initial' => false, 'is_final' => true, 'is_payment_status' => false, 'is_shipping_status' => false, 'is_order_status' => true, 'sort_order' => 13],
        ];

        foreach ($advancedStatuses as $status) {
            $status['created_at'] = now();
            $status['updated_at'] = now();
            DB::table('status_orders')->insert($status);
        }

        $paymentMap = [1 => 1, 2 => 2];
        $orderMap = [1 => 10, 2 => 13, 3 => 11, 4 => 13];
        $shippingMap = [3 => 5, 4 => 8];

        foreach ($orders as $order) {
            $paymentId = $order->payment_status_order_id
                ? ($paymentMap[$order->payment_status_order_id] ?? 1)
                : null;

            $orderStatusId = $order->status_order_id
                ? ($orderMap[$order->status_order_id] ?? 10)
                : 10;

            $shippingId = $order->shipping_status_order_id
                ? ($shippingMap[$order->shipping_status_order_id] ?? null)
                : (isset($shippingMap[$order->status_order_id ?? 0]) ? $shippingMap[$order->status_order_id] : null);

            DB::table('orders')->where('id', $order->id)->update([
                'payment_status_order_id' => $paymentId,
                'status_order_id' => $orderStatusId,
                'shipping_status_order_id' => $shippingId,
            ]);
        }

        DB::table('configurations')->update(['has_advanced_statuses' => true]);

        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        // No reversible: los IDs y descripciones quedan reemplazados.
    }
}
