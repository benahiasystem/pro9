<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{DocumentItem, Item, Document, ItemUnitType};
use App\Database\IntegerTransport;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Item\Models\{ItemLot, ItemLotsGroup};
use App\Support\Exact\Decimal as D;

final class FiscalNoteInventory
{
    public static function selectedSerials(array $serials): array
    {
        return array_values(array_filter($serials, static fn ($serial) =>
            filter_var(data_get($serial, 'has_sale'), FILTER_VALIDATE_BOOLEAN)));
    }

    /** Preserve physical composition when an invoice is first persisted. */
    public static function capture(array $line, ?array $frozenComponents=null, ?bool $frozenIsSet=null): array
    {
        $item=Item::findOrFail($line['item_id']);
        $line['item']['is_set']=$frozenIsSet ?? (bool)$item->is_set;
        $components=[];
        if ($line['item']['is_set']) {
            $presentation=(array)($line['item']['presentation'] ?? []);
            // The commercial pack composition keeps its existing rule. Only freeze
            // its presentation from the catalog or a finite legacy input, never
            // from client-supplied fraction/snapshot metadata.
            if ($presentation && $frozenComponents === null) {
                if (isset($presentation['id'])) {
                    $id=IntegerTransport::positiveKey($presentation['id']);
                    $unit=$id === null ? null : ItemUnitType::where('item_id',$item->id)->whereKey($id)->lockForUpdate()->first();
                    if (!$unit) FiscalAmounts::error('items.presentation','La presentación no pertenece al producto seleccionado.');
                    $factor=D::readRequired($unit,'quantity_unit');
                    $presentation=array_replace($presentation,$unit->only(['id','item_id','description','unit_type_id']));
                    $line['item']['unit_type_id']=$unit->unit_type_id;
                } else $factor=D::of(D::input($presentation['quantity_unit'] ?? 1,'items.presentation.quantity_unit'));
                if (!$factor->isPositive()) FiscalAmounts::error('items.presentation','La presentación debe tener una cantidad positiva.');
                $presentation['quantity_unit']=D::represent($factor);
                $presentation['quantity_unit_exact']=D::fraction($factor);
                $presentation['calculation_snapshot']=D::snapshot(['quantity_unit'=>$factor]);
                $line['item']['presentation']=$presentation;
            }
            if ($frozenComponents !== null) $components=$frozenComponents;
            else foreach ($item->sets as $component) if ($component->individual_item->unit_type_id !== 'SERV') $components[]=['item_id'=>$component->individual_item_id,'quantity'=>$component->exactValue('quantity')];
        } else {
            $presentation=(array)($line['item']['presentation'] ?? []);
            $factor=D::of(1);
            if ($frozenComponents !== null) {
                $components=$frozenComponents;
                foreach ($components as $component) {
                    $component=(array)$component;
                    if (empty($component['invoice_modifier']) && (string)$component['item_id']===(string)$item->id) $factor=D::of($component['quantity_exact'] ?? $component['quantity']);
                }
            } else {
                if (isset($presentation['id'])) {
                    $id=IntegerTransport::positiveKey($presentation['id']);
                    $unit=$id === null ? null : ItemUnitType::where('item_id',$item->id)->whereKey($id)->lockForUpdate()->first();
                    if (!$unit) FiscalAmounts::error('items.presentation','La presentación no pertenece al producto seleccionado.');
                    $factor=D::readRequired($unit,'quantity_unit');
                    $presentation=array_replace($presentation,$unit->only(['id','item_id','description','unit_type_id']));
                    $line['item']['unit_type_id']=$unit->unit_type_id;
                } elseif ($presentation) {
                    // Legacy scalar presentations remain finite decimals, never client-supplied fractions.
                    $factor=D::of(D::input($presentation['quantity_unit'] ?? 1,'items.presentation.quantity_unit'));
                }
                if ($item->unit_type_id !== 'SERV') $components[]=['item_id'=>$item->id,'quantity'=>$factor];
            }
            if (!$factor->isPositive()) FiscalAmounts::error('items.presentation','La presentación debe tener una cantidad positiva.');
            if ($presentation) {
                $presentation['quantity_unit']=D::represent($factor);
                $presentation['quantity_unit_exact']=D::fraction($factor);
                $presentation['calculation_snapshot']=D::snapshot(['quantity_unit'=>$factor]);
                $line['item']['presentation']=$presentation;
            }
        }
        if ($item->unit_type_id !== 'SERV' || $line['item']['is_set']) {
            $components = array_merge($components, $line['_invoice_modifier_components'] ?? []);
        }
        foreach ($components as &$component) {
            if (isset($component['merged_total'])) {
                $lineQuantity = D::readRequired($line, 'quantity');
                if (!$lineQuantity->isPositive()) FiscalAmounts::error('items', 'La partida fusionada debe tener una cantidad positiva.');
                $quantity = D::of($component['merged_total'])->dividedBy($lineQuantity);
                unset($component['merged_total']);
            } else {
                $quantity = D::of($component['quantity_exact'] ?? $component['quantity'] ?? 0);
            }
            if (empty($component['item_id']) || !$quantity->isPositive()) FiscalAmounts::error('items','La composición del producto debe tener cantidades positivas.');
            $component['quantity']=D::represent($quantity); $component['quantity_exact']=D::fraction($quantity);
        }
        unset($component);
        $line['item']['inventory_components']=$components;
        unset($line['_invoice_components'],$line['_invoice_is_set'],$line['_invoice_modifier_components']);
        return $line;
    }

    /** A fiscal line uses the factor frozen at persistence, independent of the current catalog. */
    public static function presentationQuantity($item): \Brick\Math\BigRational
    {
        $item=(array)$item;
        $presentation=(array)($item['presentation'] ?? []);
        if (isset($presentation['quantity_unit_exact'])) return D::of($presentation['quantity_unit_exact']);
        // Earlier persisted fiscal lines retain their original factor only in
        // calculation_snapshot. capture() normalizes all new input first.
        $presentation['quantity_unit']=$presentation['quantity_unit'] ?? 1;
        return D::readRequired($presentation,'quantity_unit');
    }

    public static function snapshot(DocumentItem $source, $quantity, array $posted): array
    {
        validator($posted,['lot_returns'=>'nullable|array','lot_returns.*.id'=>'required|integer|min:1',
            'lot_returns.*.compromise_quantity'=>'required|numeric|gt:0|regex:/^[0-9]+(?:\\.[0-9]{1,12})?$/D',
            'serial_ids'=>'nullable|array','serial_ids.*'=>'required|integer|min:1'])->validate();
        $item=json_decode(json_encode($source->item),true);
        if (D::of($quantity)->isLessThanOrEqualTo(0)) { $item['lots']=[]; $item['IdLoteSelected']=null; return $item; }
        if (!isset($item['inventory_components'])) {
            if (!empty($item['is_set'])) FiscalAmounts::error('items','El pack original no conserva su composición de inventario.');
            $factor=self::presentationQuantity($item);
            $item['inventory_components']=($item['unit_type_id'] ?? '') === 'SERV' ? [] : [['item_id'=>$source->item_id,
                'quantity'=>D::represent($factor),'quantity_exact'=>D::fraction($factor)]];
        }
        $used=DocumentItem::where('affected_document_item_id',$source->id)->whereHas('document',fn ($q)=>$q->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS))->get();
        // ######## INICIO DEVOLUCIÓN EXACTA POR LOTE ########
        $lots=$item['IdLoteSelected'] ?? [];
        if ($lots && !is_array($lots)) $lots=[['id'=>$lots,'compromise_quantity'=>D::represent($source->exactValue('quantity')),
            'compromise_quantity_exact'=>D::fraction($source->exactValue('quantity'))]];
        $available=[]; $lotMetadata=[];
        foreach ($lots ?: [] as $lot) {
            $available[$lot['id']]=D::of($lot['compromise_quantity_exact'] ?? $lot['compromise_quantity']);
            $record=(!isset($lot['code']) || !isset($lot['date_of_due']))
                ? ItemLotsGroup::where('item_id',$source->item_id)->find($lot['id']) : null;
            $lotMetadata[$lot['id']]=['code'=>$lot['code'] ?? $record->code ?? '',
                'date_of_due'=>$lot['date_of_due'] ?? $record->date_of_due ?? ''];
        }
        foreach ($used as $prior) foreach ((array)($prior->item->IdLoteSelected ?? []) as $lot) {
            $lot=(array)$lot; $available[$lot['id']]=D::of($available[$lot['id']] ?? 0)->minus(D::of($lot['compromise_quantity_exact'] ?? $lot['compromise_quantity']));
        }
        $selected=$posted['lot_returns'] ?? null;
        if ($available) {
            $automatic=$selected === null;
            if ($automatic) {
                $selected=[]; $left=D::of($quantity);
                foreach ($available as $id=>$amount) { $take=D::min(D::max(0,$amount),$left); if ($take->isGreaterThan(0)) $selected[]=['id'=>$id,'compromise_quantity'=>D::represent($take),'compromise_quantity_exact'=>D::fraction($take)]; $left=$left->minus($take); }
            }
            $sum=D::of(0); $seen=[]; $normalized=[];
            foreach ($selected as $lot) {
                $id=$lot['id'] ?? 0;
                $take=$automatic ? D::of($lot['compromise_quantity_exact']) : D::of(D::input($lot['compromise_quantity'] ?? 0, 'items.lot_returns.compromise_quantity'));
                if (isset($seen[$id]) || $take->isLessThanOrEqualTo(0) || $take->isGreaterThan($available[$id] ?? 0)) FiscalAmounts::error('items.lot_returns','El lote o su cantidad no pertenece a la devolución pendiente.');
                $seen[$id]=true; $sum=$sum->plus($take);
                $normalized[]=['id'=>$id,'quantity'=>$take];
            }
            $target=D::of($quantity);
            if (!$sum->isEqualTo($target)) {
                if (!$normalized || D::represent($sum)!==D::represent($target)) FiscalAmounts::error('items.lot_returns','Los lotes deben sumar la cantidad devuelta.');
                $last=count($normalized)-1; $corrected=$normalized[$last]['quantity']->plus($target->minus($sum));
                if (!$corrected->isPositive() || $corrected->isGreaterThan($available[$normalized[$last]['id']] ?? 0)) FiscalAmounts::error('items.lot_returns','El lote o su cantidad no pertenece a la devolución pendiente.');
                $normalized[$last]['quantity']=$corrected;
            }
            $item['IdLoteSelected']=array_map(fn($lot)=>['id'=>$lot['id'],'code'=>$lotMetadata[$lot['id']]['code'],
                'date_of_due'=>$lotMetadata[$lot['id']]['date_of_due'],'compromise_quantity'=>D::represent($lot['quantity']),
                'compromise_quantity_exact'=>D::fraction($lot['quantity'])],$normalized);
        }
        // ######## FIN DEVOLUCIÓN EXACTA POR LOTE ########
        $serials=self::selectedSerials((array) ($item['lots'] ?? []));
        $usedSerials=[];
        foreach ($used as $prior) foreach ((array)($prior->item->lots ?? []) as $serial) $usedSerials[]=((array)$serial)['id'];
        $serials=array_values(array_filter($serials,fn ($l)=>!in_array($l['id'],$usedSerials,true)));
        if ($serials) {
            $physical=D::of($quantity)->multipliedBy(!empty($item['lot_serial_assignments_verified']) ? self::presentationQuantity($item) : 1);
            if (!$physical->simplified()->getDenominator()->isEqualTo(1) || $physical->isGreaterThan(count($serials))) FiscalAmounts::error('items.quantity','La devolución de unidades con serial requiere cantidades físicas enteras disponibles.');
            $expected=[];
            if (!empty($item['lot_serial_assignments_verified'])) {
                foreach ($item['IdLoteSelected'] ?? [] as $lot) {
                    $lot=(array)$lot; $amount=D::of($lot['compromise_quantity_exact'] ?? $lot['compromise_quantity'])->multipliedBy(self::presentationQuantity($item));
                    if (!$amount->simplified()->getDenominator()->isEqualTo(1)) FiscalAmounts::error('items.lot_returns','Cada lote con seriales requiere cantidades físicas enteras.');
                    $expected[(string)$lot['id']]=$amount;
                }
            }
            if (isset($posted['serial_ids'])) $ids=array_map('intval',$posted['serial_ids']);
            elseif ($expected) {
                $ids=[]; $selectedCounts=[];
                foreach ($serials as $serial) {
                    $lotId=(string)($serial['lot_group_id'] ?? '');
                    if (isset($expected[$lotId]) && D::of($selectedCounts[$lotId] ?? 0)->isLessThan($expected[$lotId])) {
                        $ids[]=(int)$serial['id']; $selectedCounts[$lotId]=($selectedCounts[$lotId] ?? 0)+1;
                    }
                }
            } else $ids=array_column(array_slice($serials,0,$physical->toBigInteger()->toInt()),'id');
            if (count(array_unique($ids)) !== count($ids) || !$physical->isEqualTo(count($ids)) || array_diff($ids,array_column($serials,'id'))) FiscalAmounts::error('items.serial_ids','Seleccione los seriales originales pendientes de devolución.');
            $item['lots']=array_values(array_filter($serials,fn ($l)=>in_array($l['id'],$ids)));
            if ($expected) {
                $counts=[];
                foreach ($item['lots'] as $serial) { $lotId=(string)($serial['lot_group_id'] ?? ''); $counts[$lotId]=($counts[$lotId] ?? 0)+1; }
                foreach ($expected as $lotId=>$amount) if (!$amount->isEqualTo($counts[$lotId] ?? 0)) FiscalAmounts::error('items.serial_ids','Los seriales devueltos deben corresponder a los lotes seleccionados.');
                foreach ($counts as $lotId=>$count) if (!isset($expected[$lotId])) FiscalAmounts::error('items.serial_ids','Los seriales devueltos deben corresponder a los lotes seleccionados.');
            }
        } else $item['lots']=[];
        return $item;
    }

    public static function apply(DocumentItem $line, int $direction=1, bool $checkOnly=false): void
    {
        if ($line->exactValue('inventory_quantity')->isLessThanOrEqualTo(0) || empty($line->item->inventory_components)) return;
        $document=$line->document;
        $warehouse=$line->warehouse_id;
        if (!$warehouse || !\Modules\Inventory\Models\Warehouse::where('id',$warehouse)->where('establishment_id',$document->establishment_id)->exists()) FiscalAmounts::error('items.warehouse_id','La devolución requiere el almacén original de la sucursal.');
        foreach ((array)($line->item->inventory_components ?? []) as $component) {
            $component=(array)$component;
            $quantity=$line->exactValue('inventory_quantity')->multipliedBy(D::of($component['quantity_exact'] ?? $component['quantity']))->multipliedBy($direction);
            $stock=ItemWarehouse::where('item_id',$component['item_id'])->where('warehouse_id',$warehouse)->lockForUpdate()->firstOrFail();
            if ($direction < 0 && $stock->exactValue('stock')->plus($quantity)->isNegative()) FiscalAmounts::error('inventory','No hay stock suficiente para revertir la devolución.');
            $stock->stock=$stock->exactValue('stock')->plus($quantity);
            if (!$checkOnly) {
                $stock->save();
                $document->inventory_kardex()->create(['date_of_issue'=>$direction < 0 ? now()->toDateString() : $document->date_of_issue,'item_id'=>$component['item_id'],'warehouse_id'=>$warehouse,'quantity'=>$quantity]);
            }
        }
        foreach ((array)($line->item->IdLoteSelected ?? []) as $lot) {
            $lot=(array)$lot;
            $record=ItemLotsGroup::query()->lockForUpdate()->findOrFail($lot['id']);
            if ($record->item_id != $line->item_id) FiscalAmounts::error('items.lot_returns','El lote no corresponde al producto original.');
            $record->quantity=$record->exactValue('quantity')->plus(D::of($lot['compromise_quantity_exact'] ?? $lot['compromise_quantity'])->multipliedBy(self::presentationQuantity($line->item))->multipliedBy($direction));
            if (D::of($record->quantity)->isNegative()) FiscalAmounts::error('inventory','El lote no respalda la reversión.');
            if (!$checkOnly) $record->save();
        }
        foreach ((array)($line->item->lots ?? []) as $serial) {
            $serial=(array)$serial;
            $record=ItemLot::query()->lockForUpdate()->findOrFail($serial['id']);
            if ($record->item_id != $line->item_id || ($record->warehouse_id && $record->warehouse_id != $warehouse) || !$record->has_sale && $direction > 0) FiscalAmounts::error('items.serial_ids','El serial no corresponde al producto y almacén originales o ya fue devuelto.');
            if ($direction < 0 && $record->has_sale) FiscalAmounts::error('inventory','El serial devuelto ya fue vendido nuevamente.');
            $record->has_sale=$direction < 0; if (!$checkOnly) $record->save();
        }
        (new OrderSaleNoteLegacyInventory())->credit($line,$direction,$checkOnly);
        DeliveryOrderGlobalInventory::credit($line,$direction,$checkOnly);
        InvoiceGlobalInventory::credit($line,$direction,$checkOnly);
    }
}
