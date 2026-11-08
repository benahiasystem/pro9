<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Dispatch, Item, Kardex, SaleNote};
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\Warehouse;

/** Allocate only verified stock movements from the notes attached to this invoice. */
final class SaleNoteInvoiceInventory
{
    public static function prepare(array $input, array $noteIds): array
    {
        foreach ($input['items'] as &$row) unset($row['_sale_note_components']); unset($row);
        if (!$noteIds || !empty($input['order_note_id']) || !empty($input['dispatch_id']) || !empty($input['dispatches_relateds'])) return $input;
        $available=[]; $global=[]; $tracked=[]; $sourceLines=[]; $packs=[];
        foreach (SaleNote::whereIn('id',$noteIds)->orderBy('id')->lockForUpdate()->get() as $note) {
            $noteAvailable=[]; $noteGlobal=[];
            $lines=$note->items()->lockForUpdate()->get();
            $expected=[]; $expectedPhysical=[];
            foreach ($lines as $line) {
                $sourceLines[$line->item_id][]=$line;
                if (($line->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;
                $item=Item::findOrFail($line->item_id);
                $pack=$item->is_set ? ($line->calculation_snapshot['inputs']['sale_note_pack_inventory'] ?? null) : null;
                if ($item->is_set && (!$pack || (int)$pack['item_id']!==(int)$item->id || (int)$pack['sale_note_id']!==(int)$note->id)) FiscalAmounts::error('inventory','El paquete no conserva la composición original de la nota.');
                $quantity=$line->exactValue('quantity')->multipliedBy($item->is_set ? D::of(1) : D::of($line->item->presentation->quantity_unit ?? 1));
                $expected[$line->item_id]=D::of($expected[$line->item_id] ?? 0)->plus($quantity);
                if ($pack) {
                    $packs[$item->id][]=$line;
                    foreach ($pack['components'] as $component) {
                        $key=$component['item_id'].':'.$pack['warehouse_id'];
                        $expectedPhysical[$key]=D::of($expectedPhysical[$key] ?? 0)->plus($quantity->multipliedBy(D::of($component['quantity'])));
                    }
                } else {
                    $warehouseId=$line->warehouse_id ?: Warehouse::where('establishment_id',$note->establishment_id)->orderBy('id')->value('id');
                    $key=$line->item_id.':'.$warehouseId;
                    $expectedPhysical[$key]=D::of($expectedPhysical[$key] ?? 0)->plus($quantity);
                    $noteAvailable[$key]=D::of($noteAvailable[$key] ?? 0)->plus($quantity);
                }
                if (!empty($line->item->IdLoteSelected) || !empty($line->item->lots)) {
                    $tracked[$line->item_id][]=$line;
                }
            }
            $physical=[];
            foreach ($note->inventory_kardex()->lockForUpdate()->get() as $movement) {
                $key=$movement->item_id.':'.$movement->warehouse_id;
                $physical[$key]=D::of($physical[$key] ?? 0)->minus($movement->exactValue('quantity'));
            }
            foreach (array_unique(array_merge(array_keys($expectedPhysical),array_keys($physical))) as $key) {
                if (!D::of($physical[$key] ?? 0)->isEqualTo(D::of($expectedPhysical[$key] ?? 0))) FiscalAmounts::error('inventory','No se pueden verificar los movimientos de almacén de la nota de venta.');
            }
            $globalRows=Kardex::where('sale_note_id',$note->id)->lockForUpdate()->get()->groupBy('item_id');
            foreach ($expected as $itemId=>$quantity) {
                $globalTotal=D::sum(($globalRows[$itemId] ?? collect())->map(fn($movement)=>$movement->exactValue('quantity')));
                if (!$globalTotal->isEqualTo($quantity)) {
                    FiscalAmounts::error('inventory','No se pueden verificar los movimientos de inventario de la nota de venta.');
                }
                $noteGlobal[$itemId]=$globalTotal;
            }
            // An earlier invoiced delivery order already consumed part of this source.
            foreach (Dispatch::where('reference_sale_note_id',$note->id)->whereNotNull('reference_document_id')->with('items','reference_document')->lockForUpdate()->get() as $order) {
                if (!$order->reference_document || in_array((string)$order->reference_document->state_type_id,['09','11'],true)) continue;
                foreach ($order->items as $line) {
                    if (isset($packs[$line->item_id])) FiscalAmounts::error('items','El paquete ya fue asociado a una orden facturada.');
                    $remaining=$line->exactValue('quantity');
                    foreach ($noteAvailable as $key=>$balance) {
                        if (!str_starts_with($key,$line->item_id.':') || !$remaining->isPositive()) continue;
                        $used=D::min($balance,$remaining); $noteAvailable[$key]=$balance->minus($used); $remaining=$remaining->minus($used);
                        $noteGlobal[$line->item_id]=D::max(0,D::of($noteGlobal[$line->item_id] ?? 0)->minus($used));
                    }
                }
            }
            foreach ($noteAvailable as $key=>$quantity) $available[$key]=D::of($available[$key] ?? 0)->plus($quantity);
            foreach ($noteGlobal as $itemId=>$quantity) $global[$itemId]=D::of($global[$itemId] ?? 0)->plus($quantity);
        }
        $usedSources=[];
        $rowCounts=collect($input['items'])->countBy('item_id');
        foreach ($input['items'] as &$row) {
            $item=Item::findOrFail($row['item_id']);
            if ($item->unit_type_id==='SERV' && !$item->is_set) continue;
            $factor=D::of(data_get($row,'item.presentation.quantity_unit',1));
            $quantity=D::of(D::input($row['quantity'],'items.quantity'));
            if (empty($row['quantity_input'])) {
                $group=$sourceLines[$item->id] ?? [];
                $sameFactor=$group && collect($group)->every(fn($source)=>$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1)));
                if ($sameFactor && ($rowCounts[$item->id] ?? 0)===1) {
                    $total=D::sum(array_map(fn($source)=>$source->exactValue('quantity'),$group));
                    if (D::represent($quantity)===D::represent($total) && !$quantity->isEqualTo($total)) {
                        $fraction=D::fraction($total);
                        $row['quantity_input']=['amount'=>D::input($fraction['n'],'items.quantity_input.amount'),
                            'unit_price'=>D::input($fraction['d'],'items.quantity_input.unit_price')];
                    }
                }
                $matches=[];
                foreach ($sourceLines[$item->id] ?? [] as $source) {
                    if (isset($usedSources[$source->id]) || !$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1))) continue;
                    if (D::represent($quantity)===D::represent($source->exactValue('quantity'))) $matches[]=$source;
                }
                if ($matches && empty($row['quantity_input'])) {
                    $original=$matches[0]->exactValue('quantity');
                    foreach ($matches as $match) if (!$original->isEqualTo($match->exactValue('quantity'))) {
                        FiscalAmounts::error('items.quantity','La cantidad exacta de la nota de venta es ambigua.');
                    }
                    if (!$quantity->isEqualTo($original)) {
                        $originalInput=$matches[0]->calculation_snapshot['inputs']['quantity_input'] ?? null;
                        if (!$originalInput) FiscalAmounts::error('items.quantity','La cantidad exacta de la nota de venta no se puede recuperar.');
                        $row['quantity_input']=$originalInput;
                    }
                    $usedSources[$matches[0]->id]=true;
                }
            }
            if (!empty($row['quantity_input'])) {
                $unit=D::inputRational($row['quantity_input']['unit_price'] ?? null,'items.quantity_input.unit_price');
                if (!$unit->isPositive()) FiscalAmounts::error('items.quantity','La cantidad no es válida.');
                $quantity=D::inputRational($row['quantity_input']['amount'] ?? null,'items.quantity_input.amount')->dividedBy($unit);
            }
            $warehouseId=($row['warehouse_id'] ?? null) ?: Warehouse::where('establishment_id',$input['establishment_id'])->orderBy('id')->value('id');
            $warehouse=Warehouse::findOrFail($warehouseId);
            if ((int)$warehouse->establishment_id!==(int)$input['establishment_id']) FiscalAmounts::error('warehouse','El almacén debe pertenecer a la sucursal de la factura.');
            if ($item->is_set) {
                $matches=$packs[$item->id] ?? [];
                $match=null; $used=[];
                foreach ($matches as $index=>$source) {
                    $snapshot=$source->calculation_snapshot['inputs']['sale_note_pack_inventory'];
                    if ((int)$snapshot['warehouse_id']===(int)$warehouse->id && $quantity->isEqualTo($source->exactValue('quantity'))) { $match=$source; $used=[$index]; break; }
                }
                if (!$match && $matches) {
                    $components=null; $total=D::of(0); $candidates=[];
                    foreach ($matches as $index=>$source) {
                        $snapshot=$source->calculation_snapshot['inputs']['sale_note_pack_inventory'];
                        if ((int)$snapshot['warehouse_id']!==(int)$warehouse->id) continue;
                        if ($components !== null && $components !== $snapshot['components']) FiscalAmounts::error('items','Los paquetes con composiciones distintas deben conservar partidas separadas.');
                        $components=$snapshot['components']; $total=$total->plus($source->exactValue('quantity')); $candidates[$index]=$source;
                    }
                    if ($candidates && $quantity->isEqualTo($total)) { $match=reset($candidates); $used=array_keys($candidates); }
                }
                if (!$match) FiscalAmounts::error('items','Conserve la cantidad y almacén originales del paquete.');
                foreach ($used as $index) unset($packs[$item->id][$index]);
                if (!$packs[$item->id]) unset($packs[$item->id]);
                $row['warehouse_id']=$warehouse->id;
                $row['item']['dispatch_inventory_quantity']=D::fraction(0);
                $row['item']['dispatch_global_inventory_quantity']=D::fraction(0);
                $row['_sale_note_components']=$match->calculation_snapshot['inputs']['sale_note_pack_inventory']['components'];
                continue;
            }
            $quantity=$quantity->multipliedBy($factor);
            $key=$item->id.':'.$warehouse->id;
            $taken=D::min($quantity,$available[$key] ?? 0); $available[$key]=D::max(0,D::of($available[$key] ?? 0)->minus($taken));
            $globalTaken=D::min($taken,$global[$item->id] ?? 0); $global[$item->id]=D::max(0,D::of($global[$item->id] ?? 0)->minus($globalTaken));
            if (isset($tracked[$item->id]) && $taken->isPositive()) {
                $sources=$tracked[$item->id];
                if (count($sources)!==1 || !$taken->isEqualTo($quantity) || !$quantity->isEqualTo($sources[0]->exactValue('quantity')->multipliedBy($sources[0]->item->presentation->quantity_unit ?? 1))
                    || !$factor->isEqualTo(D::of($sources[0]->item->presentation->quantity_unit ?? 1))) {
                    FiscalAmounts::error('items','Conserve en una línea la cantidad y presentación de cada producto con lotes o seriales.');
                }
                $row['item']['IdLoteSelected']=$sources[0]->item->IdLoteSelected ?? null;
                $row['item']['lots']=$sources[0]->item->lots ?? [];
                unset($tracked[$item->id]);
            }
            if ($taken->isPositive() && $taken->isLessThan($quantity) && (!empty($row['item']['lots']) || !empty($row['item']['IdLoteSelected']))) {
                FiscalAmounts::error('items','Separe la cantidad original de la nueva para conservar lotes y seriales.');
            }
            $row['warehouse_id']=$warehouse->id;
            $row['item']['dispatch_inventory_quantity']=D::fraction($quantity->minus($taken));
            $row['item']['dispatch_global_inventory_quantity']=D::fraction($quantity->minus($globalTaken));
        }
        unset($row);
        if ($tracked) FiscalAmounts::error('items','La factura debe conservar los productos con lotes o seriales de las notas.');
        if ($packs) FiscalAmounts::error('items','La factura debe conservar los paquetes de las notas de venta.');
        return $input;
    }
}
