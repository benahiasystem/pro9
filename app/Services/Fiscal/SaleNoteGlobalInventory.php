<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\SaleNoteItem;
use App\Support\Exact\Decimal as D;
use App\Traits\KardexTrait;

/** Provenance for new physical movements; older notes are never reconstructed. */
final class SaleNoteGlobalInventory
{
    use KardexTrait;

    public function record(SaleNoteItem $line, bool $pack=false): void
    {
        $factor=$pack ? D::of(1) : D::of($line->item->presentation->quantity_unit ?? 1);
        if (!$factor->isPositive()) FiscalAmounts::error('items','La presentación debe tener una cantidad positiva.');
        $quantity=$line->exactValue('quantity')->multipliedBy($factor);
        $movement=$this->saveKardex('sale',$line->item_id,$line->sale_note_id,$quantity,'sale_note');
        $this->updateStock($line->item_id,$quantity,true);
        $snapshot=$line->calculation_snapshot ?? D::snapshot([]);
        $snapshot['inputs']['sale_note_global_inventory']=['item_id'=>$line->item_id,'sale_note_id'=>$line->sale_note_id,
            'quantity'=>D::fraction($quantity),'kardex_id'=>$movement->id,'reversed'=>false];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    public function reverse(SaleNoteItem $line): void
    {
        $record=(array)($line->calculation_snapshot['inputs']['sale_note_global_inventory'] ?? []);
        if (!$record || !empty($record['reversed'])) return;
        if ((int)($record['item_id'] ?? 0)!==(int)$line->item_id || (int)($record['sale_note_id'] ?? 0)!==(int)$line->sale_note_id) {
            FiscalAmounts::error('inventory','El movimiento global no corresponde a la nota de venta.');
        }
        $quantity=D::of($record['quantity']);
        $this->updateStock($line->item_id,$quantity,false);
        $reverse=$this->saveKardex('sale',$line->item_id,$line->sale_note_id,$quantity->negated(),'sale_note');
        $snapshot=$line->calculation_snapshot;
        $snapshot['inputs']['sale_note_global_inventory']['reversed']=true;
        $snapshot['inputs']['sale_note_global_inventory']['reversal_kardex_id']=$reverse->id;
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }
}
