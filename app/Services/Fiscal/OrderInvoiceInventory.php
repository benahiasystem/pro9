<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Models\Tenant\{Document, SaleNoteItem};
use App\Support\Exact\Decimal as D;
use Modules\Item\Models\ItemLotsGroup;
use Modules\Order\Models\OrderNote;

/** Reuse only the physical inventory actually reserved by the source order. */
final class OrderInvoiceInventory
{
    public static function prepare(array $input, bool $saleNote=false, ?int $saleNoteId=null, ?int $sourceSaleNoteId=null): array
    {
        if (isset($input['items'])) foreach ($input['items'] as &$row) unset($row['_order_components'],$row['_order_legacy_inventory'],$row['item']['order_inventory_inherited'],$row['item']['legacy_order_note_inventory'],$row['item']['lot_serial_assignments_verified']);
        unset($row);
        if (empty($input['order_note_id'])) return $input;
        $order=OrderNote::query()->lockForUpdate()->findOrFail($input['order_note_id']);
        $user=auth()->user();
        if (!$user || ($user->type !== 'admin' && (int)$user->establishment_id !== (int)$order->establishment_id)) {
            FiscalAmounts::error('order_note_id','El pedido no pertenece a la sucursal autorizada.');
        }
        if (in_array((string)$order->state_type_id,Document::VOIDED_REJECTED_IDS,true)
            || (int)$order->establishment_id !== (int)$input['establishment_id']
            || (int)$order->customer_id !== (int)$input['customer_id']) {
            FiscalAmounts::error('order_note_id','El pedido debe estar vigente y corresponder al cliente y sucursal.');
        }
        if ($order->documents()->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS)->exists()) FiscalAmounts::error('order_note_id','El pedido ya tiene una factura vigente.');
        if ($saleNote) {
            $notes=$order->sale_notes()->whereNotIn('state_type_id',Document::VOIDED_REJECTED_IDS);
            if ($saleNoteId) $notes->where('id','<>',$saleNoteId);
            if ($notes->exists()) FiscalAmounts::error('order_note_id','El pedido ya tiene una nota de venta vigente.');
        }
        $sources=$order->items()->lockForUpdate()->get()->values(); $used=[];
        $saleSources=$sourceSaleNoteId ? SaleNoteItem::where('sale_note_id',$sourceSaleNoteId)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
        $usedSaleSources=[];
        foreach ($input['items'] as &$row) {
            $quantity=D::of(D::input($row['quantity'] ?? '', 'items.quantity'));
            if (!empty($row['quantity_input'])) {
                $q=$row['quantity_input'];
                $price=\App\Support\Exact\LineCalculation::priceInput($q['unit_price'] ?? '', 'items.quantity_input.unit_price');
                if (!$price->isPositive()) FiscalAmounts::error('items','La cantidad de la partida no es válida.');
                $quantity=D::of(D::input($q['amount'] ?? '', 'items.quantity_input.amount'))->dividedBy($price);
            }
            $factor=D::of(data_get($row,'item.presentation.quantity_unit',1)); $match=null;
            $hintedId = null;
            $hintedQuantity = null;
            $hints = $row['source_sale_note_items'] ?? [];
            if ($sourceSaleNoteId && $hints !== []) {
                $hintedId = is_array($hints) && count($hints) === 1 && is_array($hints[0] ?? null)
                    ? IntegerTransport::positiveKey($hints[0]['sale_note_item_id'] ?? null) : null;
                $hinted = $hintedId === null ? null : $saleSources->get($hintedId);
                if (!$hinted || isset($usedSaleSources[$hintedId]) || (int) $hinted->item_id !== (int) $row['item_id']
                    || !$factor->isEqualTo(D::of(data_get($hinted->item, 'presentation.quantity_unit', 1)))) {
                    FiscalAmounts::error('items','Identifique una partida original de la nota de venta para cada partida del pedido.');
                }
                $hintedQuantity = $hinted->exactValue('quantity');
            }
            foreach ($sources as $index=>$source) {
                if (isset($used[$index]) || (int)$source->item_id !== (int)($row['item_id'] ?? 0)) continue;
                if (!$factor->isEqualTo(D::of(data_get($source->item,'presentation.quantity_unit',1)))) continue;
                $sourceQuantity = $source->exactValue('quantity');
                if (!empty($row['quantity_input']) ? !$quantity->isEqualTo($sourceQuantity)
                    : D::represent($quantity)!==D::represent($sourceQuantity)) continue;
                if ($hintedQuantity && !$hintedQuantity->isEqualTo($sourceQuantity)) continue;
                if ($match) {
                    if (!$match->exactValue('quantity')->isEqualTo($sourceQuantity)) {
                        FiscalAmounts::error('items.quantity','Identifique la partida de origen cuando las cantidades del pedido sean ambiguas.');
                    }
                    continue;
                }
                $match=$source; $matchedIndex=$index;
                if (!$sourceSaleNoteId || $hintedQuantity) break;
            }
            if (!$match) FiscalAmounts::error('items','La factura debe conservar las partidas y cantidades del pedido.');
            $used[$matchedIndex]=true;
            if (!$saleNote) $row['item']['order_inventory_inherited']=true;
            $packSnapshot=$match->calculation_snapshot['inputs']['order_pack_inventory'] ?? null;
            if ($packSnapshot !== null) {
                if (!\App\Models\Tenant\Item::findOrFail($match->item_id)->is_set) FiscalAmounts::error('items','El producto original ya no conserva su tipo de paquete.');
                $row['warehouse_id']=$packSnapshot['warehouse_id'];
                if (!$saleNote) {
                    $row['_order_components']=$packSnapshot['components'];
                    // Component provenance does not authorize presentation metadata from the request.
                    $row['item']['presentation']=json_decode(json_encode($match->item->presentation ?? [],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
                    $row['item']['unit_type_id']=$match->item->unit_type_id;
                }
            } elseif (\App\Models\Tenant\Item::findOrFail($match->item_id)->is_set) {
                FiscalAmounts::error('items','El pedido no conserva la composición original del paquete.');
            }
            $matchedSale=null;
            foreach ($saleSources as $index=>$saleSource) {
                if (isset($usedSaleSources[$index]) || (int)$saleSource->item_id !== (int)$match->item_id
                    || ($hintedId !== null && $index !== $hintedId)
                    || !$factor->isEqualTo(D::of(data_get($saleSource->item,'presentation.quantity_unit',1)))
                    || !$saleSource->exactValue('quantity')->isEqualTo($match->exactValue('quantity'))) continue;
                $usedSaleSources[$index]=true;
                $matchedSale=$saleSource;
                $movement=$saleSource->calculation_snapshot['inputs']['order_sale_note_legacy_inventory'] ?? null;
                if ($movement) $row['_order_legacy_inventory']=['sale_note_item_id'=>$saleSource->id,'item_id'=>$movement['item_id'],'quantity'=>$movement['quantity'],
                    'source_quantity'=>D::fraction($match->exactValue('quantity'))];
                break;
            }
            if ($sourceSaleNoteId && !$matchedSale) FiscalAmounts::error('items','La nota de venta no conserva la partida original del pedido.');
            if ($matchedSale && !isset($row['_order_legacy_inventory'])
                && ($matchedSale->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) !== true) {
                FiscalAmounts::error('inventory','La nota de venta no conserva su movimiento global de origen.');
            }
            if ($matchedSale) SaleNoteInvoiceInventory::restoreSourcePrice($row, $matchedSale);
            if (empty($row['quantity_input']) && !$quantity->isEqualTo($match->exactValue('quantity'))) {
                $exactInput=$match->calculation_snapshot['inputs']['quantity_input'] ?? null;
                if (!$exactInput) FiscalAmounts::error('items.quantity','La cantidad exacta del pedido no se puede recuperar.');
                $row['quantity_input']=$exactInput;
            }
            $lotSnapshot=$match->calculation_snapshot['inputs']['order_lot_inventory'] ?? null;
            if ($lotSnapshot !== null) {
                $row['warehouse_id']=$lotSnapshot['warehouse_id']; $lots=[];
                $frozen=[];
                foreach ((array)($match->item->IdLoteSelected ?? []) as $selected) {
                    $selected=(array)$selected;
                    if (isset($selected['id'])) $frozen[(string)$selected['id']]=$selected;
                }
                foreach ($lotSnapshot['lots'] as $entry) {
                    $lot=ItemLotsGroup::where('item_id',$match->item_id)->lockForUpdate()->findOrFail($entry['id']);
                    $quantity=D::of($entry['quantity'])->dividedBy($factor);
                    $original=$frozen[(string)$lot->id] ?? [];
                    $lots[]=['id'=>$lot->id,'code'=>$original['code'] ?? $lot->code,'date_of_due'=>$original['date_of_due'] ?? $lot->date_of_due,
                        'compromise_quantity'=>D::represent($quantity),'compromise_quantity_exact'=>D::fraction($quantity)];
                }
                $row['item']['IdLoteSelected']=$lots;
                $row['item']['lots_group']=[];
                if ($saleNote) $row['IdLoteSelected']=$lots;
            }
            $serialSnapshot=$match->calculation_snapshot['inputs']['order_serial_inventory'] ?? null;
            if ($serialSnapshot !== null) {
                $row['warehouse_id']=$serialSnapshot['warehouse_id']; $serials=[];
                $frozen=[];
                foreach ((array)($match->item->lots ?? []) as $serial) {
                    $serial=(array)$serial;
                    if (isset($serial['id'])) $frozen[(string)$serial['id']]=$serial;
                }
                foreach ($serialSnapshot['serial_ids'] as $id) {
                    $record=\Modules\Item\Models\ItemLot::where('item_id',$match->item_id)
                        ->where('warehouse_id',$serialSnapshot['warehouse_id'])->lockForUpdate()->findOrFail($id);
                    $data=['id'=>$record->id,'series'=>$frozen[(string)$id]['series'] ?? $record->series,'has_sale'=>true];
                    if (isset($serialSnapshot['lot_groups'][(string)$id])) $data['lot_group_id']=$serialSnapshot['lot_groups'][(string)$id];
                    $serials[]=$data;
                }
                $row['item']['lots']=$serials;
                if (isset($serialSnapshot['lot_groups'])) $row['item']['lot_serial_assignments_verified']=true;
                if ($saleNote) $row['lots']=$serials;
            }
            if ($saleNote) unset($row['record_id']);
        }
        unset($row);
        if (count($used)!==$sources->count()) FiscalAmounts::error('items','La factura debe conservar todas las partidas del pedido.');
        return $input;
    }
}
