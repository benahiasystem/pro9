<?php

namespace App\Services\Fiscal;

use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\{InventoryConfiguration, ItemWarehouse};
use Modules\Item\Models\ItemLot;
use Modules\Order\Models\OrderNoteItem;

final class OrderSerialInventory
{
    public static function capture(OrderNoteItem $line, int $warehouse): ?array
    {
        $item=$line->item;
        if (!empty($item->is_set)) return null;
        $selected=[];
        foreach ($item->lots ?? [] as $serial) {
            $serial=(array)$serial;
            if (!filter_var($serial['has_sale'] ?? false,FILTER_VALIDATE_BOOLEAN)) continue;
            $id=(int)($serial['id'] ?? 0);
            if ($id<=0 || isset($selected[$id])) FiscalAmounts::error('items','Los seriales deben ser válidos y no pueden repetirse.');
            $selected[$id]=true;
        }
        if (!$selected) return null;
        $quantity=$line->exactValue('quantity')->multipliedBy(D::of($item->presentation->quantity_unit ?? 1));
        if (!$quantity->isPositive() || !$quantity->isEqualTo(count($selected))) FiscalAmounts::error('items','La cantidad física debe coincidir con los seriales seleccionados.');
        $ids=array_keys($selected); sort($ids,SORT_NUMERIC); $frozen=[];
        foreach ($ids as $id) {
            $serial=ItemLot::where('item_id',$line->item_id)->where('warehouse_id',$warehouse)->lockForUpdate()->find($id);
            if (!$serial) FiscalAmounts::error('items','El serial no pertenece al producto y almacén.');
            $frozen[]=['id'=>$id,'series'=>$serial->series,'has_sale'=>true];
        }
        $data=(array)$item; $data['lots']=$frozen; $line->item=$data;
        return ['warehouse_id'=>$warehouse,'serial_ids'=>$ids,'quantity'=>D::fraction($quantity)];
    }

    public static function apply(OrderNoteItem $line, int $direction, bool $moveStock=true): bool
    {
        $snapshot=$line->calculation_snapshot['inputs']['order_serial_inventory'] ?? null;
        if ($snapshot === null) return false;
        $order=$line->order_note;
        $moves=!$order->sale_note_id && !$order->order_note_id && !$order->dispatch_id;
        if (!$moves && $order->dispatch) $moves=!$order->dispatch->discountsPhysicalStock();
        if (!$moves) return true;
        $warehouse=$snapshot['warehouse_id']; $delta=D::of($snapshot['quantity'])->multipliedBy($direction);
        if ($moveStock) {
            $stock=ItemWarehouse::query()->lockForUpdate()->firstOrNew(['item_id'=>$line->item_id,'warehouse_id'=>$warehouse]);
            $stock->stock=$stock->exactValue('stock')->plus($delta);
            if ($direction<0 && $stock->exactValue('stock')->isNegative() && InventoryConfiguration::firstOrFail()->stock_control) FiscalAmounts::error('items','No hay inventario suficiente.');
        }
        foreach ($snapshot['serial_ids'] as $id) {
            $serial=ItemLot::where('item_id',$line->item_id)->where('warehouse_id',$warehouse)->lockForUpdate()->find($id);
            if (!$serial || (bool)$serial->has_sale !== ($direction>0)) FiscalAmounts::error('items','El serial no está disponible para este movimiento.');
            $serial->has_sale=$direction<0; $serial->save();
        }
        if ($moveStock) {
            $stock->save();
            $order->inventory_kardex()->create(['date_of_issue'=>date('Y-m-d'),'item_id'=>$line->item_id,'warehouse_id'=>$warehouse,'quantity'=>$delta]);
        }
        return true;
    }
}
