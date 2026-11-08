<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\{Document, DocumentItem, SaleNote};
use App\Support\Exact\Decimal as D;
use App\Traits\KardexTrait;

/** Reconcile the legacy global-stock movement of sale notes sourced from orders. */
final class OrderSaleNoteLegacyInventory
{
    use KardexTrait;

    public static function invoiceInheritsMovement(Document $document): bool
    {
        return $document->order_note_id && SaleNote::withoutEagerLoads()->where('document_id',$document->id)
            ->where('order_note_id',$document->order_note_id)->exists();
    }

    public function record(SaleNoteItem $line): void
    {
        $quantity=$line->exactValue('quantity')->multipliedBy(!empty($line->item->is_set) ? 1 : D::of($line->item->presentation->quantity_unit ?? 1));
        $kardex=$this->saveKardex('sale',$line->item_id,$line->sale_note_id,$quantity,'sale_note');
        $this->updateStock($line->item_id,$quantity,true);
        $snapshot=$line->calculation_snapshot ?? D::snapshot([]);
        $snapshot['inputs']['order_sale_note_legacy_inventory']=['item_id'=>$line->item_id,
            'quantity'=>D::fraction($quantity),'kardex_id'=>$kardex->id];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    public function reverse(SaleNoteItem $line): void
    {
        $movement=$line->calculation_snapshot['inputs']['order_sale_note_legacy_inventory'] ?? null;
        if (!$movement) return;
        $quantity=D::of($movement['quantity']);
        $this->updateStock($movement['item_id'],$quantity,false);
        $this->saveKardex('sale',$movement['item_id'],$line->sale_note_id,$quantity->negated(),'sale_note');
    }

    public function credit(DocumentItem $line, int $direction, bool $checkOnly): void
    {
        $movement=(array)($line->item->legacy_order_note_inventory ?? []);
        if (!$movement) return;
        $source=DocumentItem::findOrFail($line->affected_document_item_id);
        $saleLine=SaleNoteItem::whereKey($movement['sale_note_item_id'] ?? null)->lockForUpdate()->first();
        $recorded=$saleLine->calculation_snapshot['inputs']['order_sale_note_legacy_inventory'] ?? null;
        if (!$saleLine || !$recorded || (int)$saleLine->sale_note->document_id !== (int)$source->document_id
            || (int)$saleLine->item_id !== (int)$source->item_id
            || (int)$saleLine->sale_note->order_note_id !== (int)$source->document->order_note_id
            || (int)$line->document->note->affected_document_id !== (int)$source->document_id
            || (int)$recorded['item_id'] !== (int)($movement['item_id'] ?? 0)
            || !D::of($recorded['quantity'])->isEqualTo(D::of($movement['quantity'] ?? 0))
            || !$source->exactValue('quantity')->isEqualTo(D::of($movement['source_quantity'] ?? 0))) {
            FiscalAmounts::error('inventory','No se pudo verificar el movimiento global de origen.');
        }
        $sourceQuantity=D::of($movement['source_quantity']);
        if (!$sourceQuantity->isPositive()) FiscalAmounts::error('inventory','El movimiento de origen no tiene cantidad válida.');
        $quantity=$line->exactValue('inventory_quantity')->multipliedBy(D::of($movement['quantity']))->dividedBy($sourceQuantity)->multipliedBy($direction);
        if ($checkOnly || $quantity->isZero()) return;
        $this->updateStock($movement['item_id'],$quantity,false);
        $this->saveKardex('sale',$movement['item_id'],$line->document_id,$quantity->negated(),'document');
    }
}
