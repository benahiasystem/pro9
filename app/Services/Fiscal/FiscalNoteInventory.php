<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{DocumentItem, Item, Document};
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Item\Models\{ItemLot, ItemLotsGroup};
use App\Services\ExchangeRates\ExchangeRateMath as Math;

final class FiscalNoteInventory
{
    /** Preserve physical composition when an invoice is first persisted. */
    public static function capture(array $line): array
    {
        $item=Item::findOrFail($line['item_id']);
        $line['item']['is_set']=(bool)$item->is_set;
        $components=[];
        if ($item->unit_type_id !== 'SERV') {
            if ($item->is_set) {
                foreach ($item->sets as $component) if ($component->individual_item->unit_type_id !== 'SERV') $components[]=['item_id'=>$component->individual_item_id,'quantity'=>(string)($component->quantity ?: 1)];
            }
            else $components[]=['item_id'=>$item->id,'quantity'=>(string)($line['item']['presentation']['quantity_unit'] ?? 1)];
        }
        $line['item']['inventory_components']=$components;
        foreach ($components as $component) validator($component,['quantity'=>'required|numeric|gt:0'])->validate();
        return $line;
    }

    public static function snapshot(DocumentItem $source, $quantity, array $posted): array
    {
        validator($posted,['lot_returns'=>'nullable|array','lot_returns.*.id'=>'required|integer|min:1',
            'lot_returns.*.compromise_quantity'=>'required|numeric|gt:0|regex:/^[0-9]+(?:\\.[0-9]{1,4})?$/D',
            'serial_ids'=>'nullable|array','serial_ids.*'=>'required|integer|min:1'])->validate();
        $item=json_decode(json_encode($source->item),true);
        if ($quantity <= 0) { $item['lots']=[]; $item['IdLoteSelected']=null; return $item; }
        if (!isset($item['inventory_components'])) {
            if (!empty($item['is_set'])) FiscalAmounts::error('items','El pack original no conserva su composición de inventario.');
            $item['inventory_components']=($item['unit_type_id'] ?? '') === 'SERV' ? [] : [['item_id'=>$source->item_id,'quantity'=>(string)($item['presentation']['quantity_unit'] ?? 1)]];
        }
        $used=DocumentItem::where('affected_document_item_id',$source->id)->whereHas('document',fn ($q)=>$q->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS))->get();
        $lots=$item['IdLoteSelected'] ?? [];
        if ($lots && !is_array($lots)) $lots=[['id'=>$lots,'compromise_quantity'=>$source->quantity]];
        $available=[];
        foreach ($lots ?: [] as $lot) $available[$lot['id']]=(float)$lot['compromise_quantity'];
        foreach ($used as $prior) foreach ((array)($prior->item->IdLoteSelected ?? []) as $lot) {
            $lot=(array)$lot; $available[$lot['id']]=($available[$lot['id']] ?? 0)-$lot['compromise_quantity'];
        }
        $selected=$posted['lot_returns'] ?? null;
        if ($available) {
            if ($selected === null) {
                $selected=[]; $left=(float)$quantity;
                foreach ($available as $id=>$amount) { $take=min(max(0,$amount),$left); if ($take > 0) $selected[]=['id'=>$id,'compromise_quantity'=>$take]; $left-=$take; }
            }
            $sum=0; $seen=[];
            foreach ($selected as $lot) {
                $id=$lot['id'] ?? 0; $take=$lot['compromise_quantity'] ?? 0;
                if (isset($seen[$id]) || $take <= 0 || $take > ($available[$id] ?? 0)+0.00005) FiscalAmounts::error('items.lot_returns','El lote o su cantidad no pertenece a la devolución pendiente.');
                $seen[$id]=true; $sum+=$take;
            }
            if (abs($sum-$quantity)>0.00005) FiscalAmounts::error('items.lot_returns','Los lotes deben sumar la cantidad devuelta.');
            $item['IdLoteSelected']=$selected;
        }
        $serials=array_filter($item['lots'] ?? [],fn ($l)=>!empty($l['has_sale']));
        $usedSerials=[];
        foreach ($used as $prior) foreach ((array)($prior->item->lots ?? []) as $serial) $usedSerials[]=((array)$serial)['id'];
        $serials=array_values(array_filter($serials,fn ($l)=>!in_array($l['id'],$usedSerials,true)));
        if ($serials) {
            if (floor((float)$quantity)!=(float)$quantity) FiscalAmounts::error('items.quantity','La devolución de unidades con serial requiere cantidades enteras.');
            $ids=array_map('intval',$posted['serial_ids'] ?? array_column(array_slice($serials,0,(int)$quantity),'id'));
            if (count(array_unique($ids)) !== (int)$quantity || array_diff($ids,array_column($serials,'id'))) FiscalAmounts::error('items.serial_ids','Seleccione los seriales originales pendientes de devolución.');
            $item['lots']=array_values(array_filter($serials,fn ($l)=>in_array($l['id'],$ids)));
        } else $item['lots']=[];
        return $item;
    }

    public static function apply(DocumentItem $line, int $direction=1): void
    {
        if ($line->inventory_quantity <= 0 || empty($line->item->inventory_components)) return;
        $document=$line->document;
        $warehouse=$line->warehouse_id;
        if (!$warehouse || !\Modules\Inventory\Models\Warehouse::where('id',$warehouse)->where('establishment_id',$document->establishment_id)->exists()) FiscalAmounts::error('items.warehouse_id','La devolución requiere el almacén original de la sucursal.');
        foreach ((array)($line->item->inventory_components ?? []) as $component) {
            $component=(array)$component;
            $quantity=Math::multiply($line->inventory_quantity,$component['quantity'],4)*$direction;
            $stock=ItemWarehouse::where('item_id',$component['item_id'])->where('warehouse_id',$warehouse)->lockForUpdate()->firstOrFail();
            if ($direction < 0 && $stock->stock+$quantity < 0) FiscalAmounts::error('inventory','No hay stock suficiente para revertir la devolución.');
            $stock->stock+=$quantity; $stock->save();
            $document->inventory_kardex()->create(['date_of_issue'=>$document->date_of_issue,'item_id'=>$component['item_id'],'warehouse_id'=>$warehouse,'quantity'=>$quantity]);
        }
        foreach ((array)($line->item->IdLoteSelected ?? []) as $lot) {
            $lot=(array)$lot;
            $record=ItemLotsGroup::query()->lockForUpdate()->findOrFail($lot['id']);
            if ($record->item_id != $line->item_id) FiscalAmounts::error('items.lot_returns','El lote no corresponde al producto original.');
            $record->quantity+=Math::multiply($lot['compromise_quantity'],$line->item->presentation->quantity_unit ?? 1,4)*$direction;
            if ($record->quantity < 0) FiscalAmounts::error('inventory','El lote no respalda la reversión.');
            $record->save();
        }
        foreach ((array)($line->item->lots ?? []) as $serial) {
            $serial=(array)$serial;
            $record=ItemLot::query()->lockForUpdate()->findOrFail($serial['id']);
            if ($record->item_id != $line->item_id || ($record->warehouse_id && $record->warehouse_id != $warehouse) || !$record->has_sale && $direction > 0) FiscalAmounts::error('items.serial_ids','El serial no corresponde al producto y almacén originales o ya fue devuelto.');
            $record->has_sale=$direction < 0; $record->save();
        }
    }
}
