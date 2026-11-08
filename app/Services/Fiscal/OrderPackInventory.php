<?php

namespace App\Services\Fiscal;

use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\{InventoryConfiguration, ItemWarehouse};
use Modules\Order\Models\OrderNoteItem;

/** Inventory for new order packs, using the composition recorded at creation. */
final class OrderPackInventory
{
    public static function apply(OrderNoteItem $line, $quantity): bool
    {
        $snapshot=$line->calculation_snapshot['inputs']['order_pack_inventory'] ?? null;
        if ($snapshot === null) return false;
        $order=$line->order_note;
        $moves=!$order->sale_note_id && !$order->order_note_id && !$order->dispatch_id;
        if (!$moves && $order->dispatch) $moves=!$order->dispatch->discountsPhysicalStock();
        if (!$moves) return true;
        $quantity=D::of($quantity);
        if ($quantity->isZero()) return true;
        foreach ($snapshot['components'] as $component) {
            $delta=$quantity->multipliedBy(D::of($component['quantity']));
            $stock=ItemWarehouse::query()->lockForUpdate()->firstOrNew(['item_id'=>$component['item_id'],'warehouse_id'=>$snapshot['warehouse_id']]);
            $stock->stock=$stock->exactValue('stock')->plus($delta);
            if ($delta->isNegative() && $stock->exactValue('stock')->isNegative() && InventoryConfiguration::firstOrFail()->stock_control) {
                FiscalAmounts::error('items','No hay inventario suficiente para los componentes del paquete.');
            }
            $stock->save();
            $order->inventory_kardex()->create(['date_of_issue'=>date('Y-m-d'),'item_id'=>$component['item_id'],
                'warehouse_id'=>$snapshot['warehouse_id'],'quantity'=>$delta]);
        }
        return true;
    }
}
