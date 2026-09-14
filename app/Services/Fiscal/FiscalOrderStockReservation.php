<?php

namespace App\Services\Fiscal;

use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOrderStockReservation
{
    public static function change(ConnectionInterface $db, int $orderId, int $establishmentId, ?array $selection, string $field, int $status): void
    {
        if (!in_array($field, ['status_order_id', 'payment_status_order_id', 'shipping_status_order_id'], true)) throw new \DomainException('Campo de estado inválido.');
        $db->transaction(function () use ($db, $orderId, $establishmentId, $selection, $field, $status) {
            // Same lock order as fiscal reservation/conversion.
            $db->table('companies')->orderBy('id')->lockForUpdate()->first();
            $order = $db->table('orders')->where('id', $orderId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (!$order) throw new \DomainException('Pedido no encontrado.');
            $purchase = json_decode($order->purchase ?? 'null', true, 512, JSON_THROW_ON_ERROR);
            if (!empty($purchase['establishment_id']) && (int) $purchase['establishment_id'] !== $establishmentId) throw new \DomainException('El pedido pertenece a otra sucursal.');
            FiscalOrderStockGuard::assertCanMutate($orderId, $order->document_external_id, $db);
            if ($selection === null) {
                self::releaseLocked($db, $order, $establishmentId);
            } elseif (!$order->stock_discounted) {
                $resolved = self::movements($db, $order, $selection, $establishmentId);
                $movements = $resolved['movements'];
                foreach ($movements as $movement) {
                    $row = $db->table('item_warehouse')->where('id', $movement['id'])->lockForUpdate()->first();
                    if (!$row || bccomp((string) $row->stock, $movement['quantity'], 4) < 0) throw new \DomainException('Existencias insuficientes para reservar el pedido.');
                    $db->table('item_warehouse')->where('id', $row->id)->update(['stock' => bcsub((string) $row->stock, $movement['quantity'], 4)]);
                }
                $db->table('orders')->where('id', $orderId)->update(['stock_discounted' => true, 'stock_reservation' => json_encode(['establishment_id' => $establishmentId, 'movements' => $movements, 'warehouses' => $resolved['warehouses']], JSON_THROW_ON_ERROR)]);
            } else {
                $snapshot = json_decode($order->stock_reservation ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                if (!$snapshot || (int) $snapshot['establishment_id'] !== $establishmentId) throw new \DomainException('La reserva del pedido no corresponde a la sucursal.');
            }
            $db->table('orders')->where('id', $orderId)->update([$field => $status, 'updated_at' => now()]);
        });
    }

    /** Called with the order locked, inside the same transaction that will write the invoice. */
    public static function releaseLocked(ConnectionInterface $db, object $order, int $establishmentId): void
    {
        if ($db->transactionLevel() < 1) throw new \LogicException('La liberación requiere transacción.');
        // Re-read under lock: callers may retain the object from before a previous release.
        $order = $db->table('orders')->where('id', $order->id)->lockForUpdate()->first();
        if (!$order) throw new \DomainException('Pedido no encontrado.');
        if (!$order->stock_discounted) return;
        $snapshot = json_decode($order->stock_reservation ?? 'null', true, 512, JSON_THROW_ON_ERROR);
        if (!$snapshot || (int) $snapshot['establishment_id'] !== $establishmentId || !isset($snapshot['movements'])) throw new \DomainException('No se puede conciliar la reserva de existencias del pedido.');
        foreach ($snapshot['movements'] as $movement) {
            $row = $db->table('item_warehouse')->where('id', $movement['id'])->where('item_id', $movement['item_id'])->where('warehouse_id', $movement['warehouse_id'])->lockForUpdate()->first();
            if (!$row) throw new \DomainException('El almacén de la reserva ya no está disponible.');
            $db->table('item_warehouse')->where('id', $row->id)->update(['stock' => bcadd((string) $row->stock, $movement['quantity'], 4)]);
        }
        $db->table('orders')->where('id', $order->id)->update(['stock_discounted' => false, 'stock_reservation' => null]);
    }

    private static function movements(ConnectionInterface $db, object $order, array $selection, int $establishmentId): array
    {
        $expected = [];
        foreach (json_decode($order->items, true, 512, JSON_THROW_ON_ERROR) as $item) {
            $id = (int) ($item['id'] ?? 0);
            $quantity = self::quantity($item['cantidad'] ?? $item['quantity'] ?? null);
            $expected[$id] = bcadd($expected[$id] ?? '0', $quantity, 4);
        }
        $selected = [];
        foreach ($selection as $entry) {
            $row = $db->table('item_warehouse as iw')->join('warehouses as w', 'w.id', '=', 'iw.warehouse_id')->where('iw.id', $entry['id'] ?? null)->where('w.establishment_id', $establishmentId)->select('iw.*')->first();
            if (!$row || isset($selected[$row->item_id]) || !isset($expected[$row->item_id]) || bccomp(self::quantity($entry['cantidad'] ?? null), $expected[$row->item_id], 4) !== 0) throw new \DomainException('La selección de almacén o cantidad no corresponde al pedido.');
            $selected[$row->item_id] = $row;
        }
        $movements = [];
        foreach ($expected as $id => $quantity) {
            $item = $db->table('items')->find($id);
            if (!$item) throw new \DomainException('Producto del pedido no encontrado.');
            if ($item->unit_type_id === 'SERV') continue;
            if (!isset($selected[$id])) throw new \DomainException('Seleccione el almacén de cada producto del pedido.');
            $warehouseId = $selected[$id]->warehouse_id;
            $components = $item->is_set ? $db->table('item_sets')->where('item_id', $id)->get() : [(object) ['individual_item_id' => $id, 'quantity' => '1']];
            if (count($components) === 0) throw new \DomainException('El pack del pedido no tiene componentes.');
            foreach ($components as $component) {
                $componentItem = $db->table('items')->find($component->individual_item_id);
                if (!$componentItem) throw new \DomainException('Componente del pack no encontrado.');
                if ($componentItem->unit_type_id === 'SERV') continue;
                $row = $db->table('item_warehouse')->where('item_id', $component->individual_item_id)->where('warehouse_id', $warehouseId)->first();
                if (!$row) throw new \DomainException('Producto no disponible en el almacén seleccionado.');
                $amount = bcmul($quantity, self::quantity($component->quantity), 4);
                $movements[$row->id] = ['id' => (int) $row->id, 'item_id' => (int) $row->item_id, 'warehouse_id' => (int) $row->warehouse_id, 'quantity' => bcadd($movements[$row->id]['quantity'] ?? '0', $amount, 4)];
            }
        }
        ksort($movements, SORT_NUMERIC);
        return ['movements' => array_values($movements), 'warehouses' => array_map(fn ($row) => (int) $row->warehouse_id, $selected)];
    }

    public static function applyWarehouses(array $inputs, object $order): array
    {
        if (!$order->stock_discounted) return $inputs;
        $snapshot = json_decode($order->stock_reservation, true, 512, JSON_THROW_ON_ERROR);
        foreach ($inputs['items'] as &$item) {
            if (isset($snapshot['warehouses'][$item['item_id']])) {
                $item['warehouse_id'] = (int) $snapshot['warehouses'][$item['item_id']];
            }
        }
        return $inputs;
    }

    private static function quantity($value): string
    {
        if (is_bool($value) || !is_scalar($value) || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', (string) $value) || bccomp((string) $value, '0', 4) <= 0) throw new \DomainException('Cantidad del pedido inválida.');
        return (string) $value;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
