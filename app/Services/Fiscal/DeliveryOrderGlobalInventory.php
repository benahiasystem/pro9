<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{DispatchItem, DocumentItem, Item, Kardex};
use App\Support\Exact\{Decimal as D, Persistence};

/** Global movement recorded only for an order line that actually moved stock. */
final class DeliveryOrderGlobalInventory
{
    public static function credit(DocumentItem $line, int $direction, bool $checkOnly): void
    {
        if (!$line->affected_document_item_id || $line->exactValue('inventory_quantity')->isLessThanOrEqualTo(0)) return;
        $source=DocumentItem::whereKey($line->affected_document_item_id)->lockForUpdate()->firstOrFail();
        if (!isset($source->item->dispatch_global_inventory_quantity) || isset($source->item->legacy_order_note_inventory)) return;
        if ((int) $source->item_id!==(int) $line->item_id
            || (int) $line->document->note->affected_document_id!==(int) $source->document_id) {
            FiscalAmounts::error('inventory', 'La devolución no corresponde al movimiento global de la factura.');
        }
        $factor=!empty($source->item->is_set) ? D::of(1) : D::of($source->item->presentation->quantity_unit ?? 1);
        $quantity=$line->exactValue('inventory_quantity')->multipliedBy($factor);
        $item=Item::whereKey($line->item_id)->lockForUpdate()->firstOrFail();
        $balance=$item->exactValue('stock')->plus($quantity->multipliedBy($direction));
        if ($direction < 0 && $balance->isNegative()) FiscalAmounts::error('inventory', 'No hay stock global suficiente para revertir la devolución.');
        if ($checkOnly) return;
        $item->stock=$balance; $item->save();
        $movement=new Kardex(['type'=>'sale','date_of_issue'=>now()->toDateString(),'item_id'=>$item->id,'document_id'=>$line->document_id]);
        $movement->quantity=$quantity->multipliedBy(-$direction); $movement->save();
    }

    public static function apply(DispatchItem $line, int $direction): void
    {
        if (!Persistence::enabled($line)) return;
        $line=DispatchItem::whereKey($line->id)->lockForUpdate()->firstOrFail();
        $snapshot=$line->calculation_snapshot ?? D::snapshot([]);
        $record=(array) ($snapshot['inputs']['dispatch_global_inventory'] ?? []);
        if ($direction > 0 && (!$record || !empty($record['reversed']))) return;
        if ($direction < 0 && $record && empty($record['reversed'])) {
            FiscalAmounts::error('inventory', 'El movimiento global de la orden ya fue registrado.');
        }
        $order=$line->dispatch;
        if ($record && ((int) ($record['item_id'] ?? 0)!==(int) $line->item_id
            || (int) ($record['dispatch_id'] ?? 0)!==(int) $order->id)) {
            FiscalAmounts::error('inventory', 'El movimiento global no corresponde a la partida de la orden.');
        }
        $quantity=$direction < 0 ? $line->exactValue('quantity') : D::of($record['quantity']);
        if (!$quantity->isPositive()) FiscalAmounts::error('inventory', 'La cantidad del movimiento debe ser positiva.');
        $item=Item::whereKey($line->item_id)->lockForUpdate()->firstOrFail();
        $item->stock=$item->exactValue('stock')->plus($quantity->multipliedBy($direction));
        $item->save();
        $movement=new Kardex(['type'=>'sale','date_of_issue'=>now()->toDateString(),'item_id'=>$item->id]);
        $movement->quantity=$quantity->multipliedBy(-$direction);
        $movementSnapshot=$movement->calculation_snapshot ?? D::snapshot([]);
        $movementSnapshot['inputs']['dispatch_global_inventory']=['dispatch_id'=>$order->id,'line_id'=>$line->id,
            'number_full'=>$order->number_full,'reversal'=>$direction > 0];
        $movement->calculation_snapshot=$movementSnapshot;
        $movement->save();
        if ($direction < 0) {
            $snapshot['inputs']['dispatch_global_inventory']=['dispatch_id'=>$order->id,'item_id'=>$item->id,
                'quantity'=>D::fraction($quantity),'kardex_id'=>$movement->id,'reversed'=>false];
        } else {
            $snapshot['inputs']['dispatch_global_inventory']['reversed']=true;
            $snapshot['inputs']['dispatch_global_inventory']['reversal_kardex_id']=$movement->id;
        }
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }
}
