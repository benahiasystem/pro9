<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Models\Tenant\DocumentItem;
use App\Support\Exact\Decimal as D;
use Modules\Item\Models\{ItemLot, ItemLotsGroup};
use Modules\Inventory\Models\ItemWarehouse;

/** Record actual invoice mutations; editing never reconstructs a movement from request data. */
final class InvoiceTrackedInventory
{
    public static function remember(DocumentItem $line, array $lots, array $serials, $warehouseDelta=null): void
    {
        if ($line->document->document_type_id !== '01') return;
        $snapshot=$line->calculation_snapshot ?? D::snapshot([]);
        $snapshot['inputs']['invoice_tracked_inventory']=[
            'version'=>1,'item_id'=>$line->item_id,'document_id'=>$line->document_id,'warehouse_id'=>$line->warehouse_id,
            'source_quantity'=>D::fraction($line->exactValue('quantity')),
            'presentation_quantity'=>D::fraction(FiscalNoteInventory::presentationQuantity($line->item)),
            'lots'=>$lots,'serials'=>$serials,
            'warehouse_delta'=>$warehouseDelta === null ? null : D::fraction($warehouseDelta),
        ];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where('id',$line->id)
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    /** Caller owns the invoice transaction, before deleting and recreating its lines. */
    public static function reverseForEdit(DocumentItem $line): void
    {
        $record=$line->calculation_snapshot['inputs']['invoice_tracked_inventory'] ?? null;
        if ($record === null) {
            if (!empty($line->item->IdLoteSelected) || !empty($line->item->lots)) {
                FiscalAmounts::error('inventory','La partida no conserva el movimiento original de sus lotes o seriales.');
            }
            return;
        }
        if (($record['version'] ?? null)!==1 || (string)($record['item_id'] ?? '')!==(string)$line->item_id
            || (string)($record['document_id'] ?? '')!==(string)$line->document_id
            || (string)($record['warehouse_id'] ?? '')!==(string)$line->warehouse_id
            || !$line->exactValue('quantity')->isEqualTo(D::of($record['source_quantity'] ?? -1))
            || !FiscalNoteInventory::presentationQuantity($line->item)->isEqualTo(D::of($record['presentation_quantity'] ?? -1))
            || !is_array($record['lots'] ?? null) || !is_array($record['serials'] ?? null)) {
            FiscalAmounts::error('inventory','No se pudo verificar el movimiento original de lotes y seriales.');
        }
        $lots=$record['lots'];$serials=$record['serials'];
        usort($lots,fn($a,$b)=>strcmp(str_pad((string)$a['id'],19,'0',STR_PAD_LEFT),str_pad((string)$b['id'],19,'0',STR_PAD_LEFT)));
        usort($serials,fn($a,$b)=>strcmp(str_pad((string)$a['id'],19,'0',STR_PAD_LEFT),str_pad((string)$b['id'],19,'0',STR_PAD_LEFT)));
        $seen=[];
        foreach ($lots as $entry) {
            $id=IntegerTransport::positiveKey($entry['id'] ?? null);$delta=D::of($entry['quantity'] ?? 0);
            if ($id === null || isset($seen[$id]) || $delta->isPositive()) FiscalAmounts::error('inventory','Movimiento original de lote inválido.');
            $seen[$id]=true;
            $lot=ItemLotsGroup::where('item_id',$line->item_id)->whereKey($id)->lockForUpdate()->first();
            if (!$lot) FiscalAmounts::error('inventory','El lote original no pertenece al producto.');
            $lot->quantity=$lot->exactValue('quantity')->minus($delta);$lot->save();
        }
        $seen=[];
        foreach ($serials as $entry) {
            $id=IntegerTransport::positiveKey($entry['id'] ?? null);
            if ($id === null || isset($seen[$id]) || !is_bool($entry['before'] ?? null) || !is_bool($entry['after'] ?? null)) {
                FiscalAmounts::error('inventory','Movimiento original de serial inválido.');
            }
            $seen[$id]=true;
            $serial=ItemLot::where('item_id',$line->item_id)->whereKey($id)->lockForUpdate()->first();
            if (!$serial || $serial->warehouse_id !== null && (string)$serial->warehouse_id!==(string)$line->warehouse_id
                || $serial->has_sale!==$entry['after']) FiscalAmounts::error('inventory','El serial original cambió de estado o almacén.');
            $serial->has_sale=$entry['before'];$serial->save();
        }
        if (isset($record['warehouse_delta'])) {
            $delta=D::of($record['warehouse_delta']);
            if ($delta->isPositive()) FiscalAmounts::error('inventory','Movimiento original de almacén inválido.');
            if (!$delta->isZero()) {
                $stock=ItemWarehouse::where('item_id',$line->item_id)->where('warehouse_id',$line->warehouse_id)->lockForUpdate()->firstOrFail();
                $stock->stock=$stock->exactValue('stock')->minus($delta);$stock->save();
                $line->document->inventory_kardex()->create(['date_of_issue'=>now()->toDateString(),
                    'item_id'=>$line->item_id,'warehouse_id'=>$line->warehouse_id,'quantity'=>$delta->negated()]);
            }
            // Transient model relation, only for the deletion within this edit.
            $line->setRelation('invoice_inventory_reversed_for_edit',true);
        }
    }
}
