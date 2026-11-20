<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{DocumentItem, Item, Kardex};
use App\Support\Exact\{Decimal as D, Persistence};

/** Provenance for the global stock actually moved by an invoice line. */
final class InvoiceGlobalInventory
{
    public static function remember(DocumentItem $line, Kardex $movement, $quantity): void
    {
        if (!Persistence::enabled($line)) return;
        $snapshot=$line->calculation_snapshot ?? D::snapshot([]);
        $snapshot['inputs']['invoice_global_inventory']=['item_id'=>$line->item_id,'document_id'=>$line->document_id,
            'kardex_id'=>$movement->id,'quantity'=>D::fraction($quantity),'source_quantity'=>D::fraction($line->exactValue('quantity'))];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    public static function credit(DocumentItem $line, int $direction, bool $checkOnly): void
    {
        if (!$line->affected_document_item_id || !$line->exactValue('inventory_quantity')->isPositive()) return;
        $source=DocumentItem::whereKey($line->affected_document_item_id)->lockForUpdate()->firstOrFail();
        if (isset($source->item->dispatch_global_inventory_quantity) || isset($source->item->legacy_order_note_inventory)) return;
        $record=$source->calculation_snapshot['inputs']['invoice_global_inventory'] ?? null;
        if (!$record) return;
        $movement=Kardex::whereKey($record['kardex_id'] ?? null)->lockForUpdate()->first();
        $sourceQuantity=D::of($record['source_quantity'] ?? 0); $original=D::of($record['quantity'] ?? 0);
        if (!$movement || !$sourceQuantity->isPositive() || !$original->isPositive()
            || (int)$source->item_id!==(int)$line->item_id || (int)$source->item_id!==(int)($record['item_id'] ?? 0)
            || (int)$source->document_id!==(int)($record['document_id'] ?? 0)
            || (int)$line->document->note->affected_document_id!==(int)$source->document_id
            || (int)$movement->document_id!==(int)$source->document_id || (int)$movement->item_id!==(int)$source->item_id
            || !$movement->exactValue('quantity')->isEqualTo($original) || !$source->exactValue('quantity')->isEqualTo($sourceQuantity)) {
            FiscalAmounts::error('inventory','No se pudo verificar el movimiento global de la factura original.');
        }
        $quantity=$line->exactValue('inventory_quantity')->multipliedBy($original)->dividedBy($sourceQuantity)->multipliedBy($direction);
        $item=Item::whereKey($source->item_id)->lockForUpdate()->firstOrFail();
        $balance=$item->exactValue('stock')->plus($quantity);
        if ($balance->isNegative() && $direction < 0) FiscalAmounts::error('inventory','No hay stock global suficiente para revertir la devolución.');
        if ($checkOnly) return;
        $item->stock=$balance; $item->save();
        $reverse=new Kardex(['type'=>'sale','date_of_issue'=>now()->toDateString(),'item_id'=>$item->id,'document_id'=>$line->document_id]);
        $reverse->quantity=$quantity->negated(); $reverse->save();
    }

    /** The editor holds the invoice lock and reverses only its recorded movement. */
    public static function reverseForEdit(DocumentItem $line): void
    {
        $record=$line->calculation_snapshot['inputs']['invoice_global_inventory'] ?? null;
        if ($record === null) return;
        $movement=Kardex::whereKey($record['kardex_id'] ?? null)->lockForUpdate()->first();
        $quantity=D::of($record['quantity'] ?? 0);
        if (!$movement || $quantity->isNegative()
            || (string)$line->item_id!==(string)($record['item_id'] ?? '')
            || (string)$line->document_id!==(string)($record['document_id'] ?? '')
            || (string)$movement->item_id!==(string)$line->item_id || (string)$movement->document_id!==(string)$line->document_id
            || !$movement->exactValue('quantity')->isEqualTo($quantity)
            || !$line->exactValue('quantity')->isEqualTo(D::of($record['source_quantity'] ?? -1))) {
            FiscalAmounts::error('inventory','No se pudo verificar el movimiento global original antes de editar la factura.');
        }
        $item=Item::whereKey($line->item_id)->lockForUpdate()->firstOrFail();
        $item->stock=$item->exactValue('stock')->plus($quantity);$item->save();
        $reverse=new Kardex(['type'=>'sale','date_of_issue'=>now()->toDateString(),'item_id'=>$line->item_id,'document_id'=>$line->document_id]);
        $reverse->quantity=$quantity->negated();$reverse->save();
    }
}
