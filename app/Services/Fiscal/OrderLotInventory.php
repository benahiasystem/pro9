<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\{InventoryConfiguration, ItemWarehouse};
use Modules\Item\Models\ItemLotsGroup;
use Modules\Order\Models\OrderNoteItem;

final class OrderLotInventory
{
    public static function capture(OrderNoteItem $line, int $warehouse): ?array
    {
        $item=$line->item;
        if (!empty($item->is_set)) return null;
        $selected=$item->IdLoteSelected ?? $item->lots_group ?? null;
        if (($item->IdLoteSelected ?? null) === [] && !empty($item->lots_group)) {
            // Una selección explícitamente vacía no reserva lotes del catálogo.
            $itemData = (array) $item;
            $itemData['lots_group'] = [];
            $line->item = $itemData;
        }
        if (empty($selected)) return null;
        $target=$line->exactValue('quantity');
        $factor=D::of($item->presentation->quantity_unit ?? 1);
        if (!$target->isPositive() || !$factor->isPositive()) FiscalAmounts::error('items','La cantidad del lote debe ser positiva.');
        if (!is_array($selected)) $selected=[['id'=>$selected,'compromise_quantity'=>D::represent($target)]];
        $entries=[]; $sum=D::of(0); $seen=[];
        foreach ($selected as $entry) {
            $entry=(array)$entry; $id=IntegerTransport::positiveKey($entry['id'] ?? null);
            if ($id===null || isset($seen[$id])) FiscalAmounts::error('items','Los lotes deben ser válidos y no pueden repetirse.');
            $quantity=D::of(D::input($entry['compromise_quantity'] ?? '', 'items.lot_quantity'));
            if (!$quantity->isPositive()) FiscalAmounts::error('items','La cantidad del lote debe ser positiva.');
            $seen[$id]=true; $entries[]=['id'=>$id,'quantity'=>$quantity]; $sum=$sum->plus($quantity);
        }
        if (!$sum->isEqualTo($target)) {
            if (!$entries || D::represent($sum)!==D::represent($target)) FiscalAmounts::error('items','Los lotes deben sumar la cantidad del pedido.');
            $last=count($entries)-1; $entries[$last]['quantity']=$entries[$last]['quantity']->plus($target->minus($sum));
            if (!$entries[$last]['quantity']->isPositive()) FiscalAmounts::error('items','La cantidad del lote debe ser positiva.');
        }
        $frozen=[];
        foreach ($entries as $entry) {
            $record=ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->find($entry['id']);
            if (!$record) FiscalAmounts::error('items','El lote no pertenece al producto.');
            $frozen[]=['id'=>$record->id,'code'=>$record->code,'date_of_due'=>$record->date_of_due,
                'compromise_quantity'=>D::represent($entry['quantity']),'compromise_quantity_exact'=>D::fraction($entry['quantity'])];
        }
        $itemData=(array)$item; $itemData['IdLoteSelected']=$frozen; $line->item=$itemData;
        return ['warehouse_id'=>$warehouse,'lots'=>array_map(fn($entry)=>['id'=>$entry['id'],
            'quantity'=>D::fraction($entry['quantity']->multipliedBy($factor))],$entries),
            'quantity'=>D::fraction($target->multipliedBy($factor))];
    }

    public static function apply(OrderNoteItem $line, int $direction): bool
    {
        $snapshot=$line->calculation_snapshot['inputs']['order_lot_inventory'] ?? null;
        if ($snapshot === null) return false;
        $order=$line->order_note;
        $moves=!$order->sale_note_id && !$order->order_note_id && !$order->dispatch_id;
        if (!$moves && $order->dispatch) $moves=!$order->dispatch->discountsPhysicalStock();
        if (!$moves) return true;
        $warehouse=$snapshot['warehouse_id'];
        $delta=D::of($snapshot['quantity'])->multipliedBy($direction);
        $stock=ItemWarehouse::query()->lockForUpdate()->firstOrNew(['item_id'=>$line->item_id,'warehouse_id'=>$warehouse]);
        $stock->stock=$stock->exactValue('stock')->plus($delta);
        if ($direction < 0 && $stock->exactValue('stock')->isNegative() && InventoryConfiguration::firstOrFail()->stock_control) FiscalAmounts::error('items','No hay inventario suficiente.');
        $lots=$snapshot['lots']; usort($lots,fn($a,$b)=>$a['id']<=>$b['id']);
        foreach ($lots as $entry) {
            $lot=ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->find($entry['id']);
            if (!$lot) FiscalAmounts::error('items','El lote no pertenece al producto.');
            $lot->quantity=$lot->exactValue('quantity')->plus(D::of($entry['quantity'])->multipliedBy($direction));
            if ($lot->exactValue('quantity')->isNegative()) FiscalAmounts::error('items','No hay existencias suficientes en el lote.');
            $lot->save();
        }
        $stock->save();
        $order->inventory_kardex()->create(['date_of_issue'=>date('Y-m-d'),'item_id'=>$line->item_id,'warehouse_id'=>$warehouse,'quantity'=>$delta]);
        return true;
    }
}
