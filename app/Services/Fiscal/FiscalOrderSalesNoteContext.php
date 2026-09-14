<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOrderSalesNoteContext
{
    /** Trusted source argument from the order workflow; runs inside the receipt transaction. */
    public static function prepare(ConnectionInterface $db, array $source, array $inputs): array
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La conversión del pedido requiere transacción.');
        $db->table('companies')->orderBy('id')->lockForUpdate()->first();
        $order = $db->table('orders')->where('id', $source['id'] ?? null)->whereNull('deleted_at')->lockForUpdate()->first();
        if (!$order || (int) ($inputs['order_id'] ?? 0) !== (int) $order->id || !empty($inputs['id'])) throw new \DomainException('La nota de venta no corresponde al pedido.');
        FiscalOrderStockGuard::assertCanMutate((int) $order->id, $order->document_external_id, $db);
        $purchase = json_decode($order->purchase, true, 512, JSON_THROW_ON_ERROR);
        if (!hash_equals($source['purchase_fingerprint'], FiscalOrderConversion::fingerprint($purchase))) throw new \DomainException('El pedido cambió durante la generación. Recargue antes de continuar.');
        $existing = $db->table('sale_notes')->where('order_id', $order->id)->first();
        if ($existing) return ['inputs' => $inputs, 'existing_id' => (int) $existing->id];
        $emitter = $db->table('users')->where('id', $source['emitter_user_id'] ?? null)->where('establishment_id', $inputs['establishment_id'])->where('active', true)->whereIn('type', ['admin', 'seller', 'integrator'])->first();
        if (!$emitter) throw new \DomainException('La nota de venta requiere un emisor activo de su sucursal.');
        $inputs['user_id'] = (int) $emitter->id;
        $inputs['seller_id'] = (int) $emitter->id;
        $inputs = FiscalOrderStockReservation::applyWarehouses($inputs, $order);
        FiscalOrderStockReservation::releaseLocked($db, $order, (int) $inputs['establishment_id']);
        return ['inputs' => $inputs, 'existing_id' => null];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
