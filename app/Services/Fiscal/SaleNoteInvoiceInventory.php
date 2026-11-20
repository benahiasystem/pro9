<?php

namespace App\Services\Fiscal;

use App\Database\IntegerTransport;
use App\Models\Tenant\{Dispatch, Item, Kardex, SaleNote, SaleNoteItem};
use App\Support\Exact\Decimal as D;
use Brick\Math\BigRational;
use Modules\Inventory\Models\Warehouse;

/** Allocate only verified stock movements from the notes attached to this invoice. */
final class SaleNoteInvoiceInventory
{
    public static function prepare(array $input, array $noteIds): array
    {
        foreach ($input['items'] as &$row) unset($row['_sale_note_components'], $row['_sale_note_is_set']); unset($row);
        if (!$noteIds || !empty($input['order_note_id'])) return $input;
        if (!empty($input['dispatch_id']) || !empty($input['dispatches_relateds'])) {
            // The delivery order owns the inventory allocation. Prices still come from the sale note.
            $lines = SaleNoteItem::whereIn('sale_note_id', $noteIds)->orderBy('id')->lockForUpdate()->get()
                ->groupBy('item_id')->map(fn ($items) => $items->keyBy('id')->all());
            $usedHints = [];
            foreach ($input['items'] as &$row) {
                $group = $lines[$row['item_id']] ?? [];
                $selected = self::selectSources($row['source_sale_note_items'] ?? [], $group, $usedHints);
                $price = self::originalPrice($row, $selected ?: $group, (bool) $selected);
                if ($price !== null) self::restoreOriginalValues($row, ['unit_price' => $price]);
            }
            unset($row);
            return $input;
        }
        $available=[]; $global=[]; $tracked=[]; $sourceLines=[]; $packs=[]; $individualMovements=[];
        $additionalComponents = [];
        $sourceWarehouses = [];
        foreach (SaleNote::whereIn('id',$noteIds)->orderBy('id')->lockForUpdate()->get() as $note) {
            $noteAvailable=[]; $noteGlobal=[];
            $individualSources = [];
            $billedWarehouses = [];
            $lines=$note->items()->lockForUpdate()->get();
            $movements = $note->inventory_kardex()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $anchors = $lines->pluck('inventory_kardex_id')->filter()->countBy();
            $expected=[]; $expectedPhysical=[]; $expectedIndividual=[];
            foreach ($lines as $line) {
                $sourceLines[$line->item_id][$line->id]=$line;
                if (($line->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;
                $item=Item::findOrFail($line->item_id);
                $pack = $line->calculation_snapshot['inputs']['sale_note_pack_inventory'] ?? null;
                if (($pack !== null && (!$pack || (int) $pack['item_id'] !== (int) $item->id || (int) $pack['sale_note_id'] !== (int) $note->id))
                    || ($pack === null && $item->is_set && ($line->item->is_set ?? null) !== false)) {
                    FiscalAmounts::error('inventory','El paquete no conserva la composición original de la nota.');
                }
                $lineQuantity = $line->exactValue('quantity');
                $quantity = $lineQuantity->multipliedBy($pack ? 1 : ($line->item->presentation->quantity_unit ?? 1));
                $expected[$line->item_id]=D::of($expected[$line->item_id] ?? 0)->plus($quantity);
                if ($pack) {
                    $packs[$item->id][]=$line;
                    foreach ($pack['components'] as $component) {
                        $key=$component['item_id'].':'.$pack['warehouse_id'];
                        $expectedPhysical[$key]=D::of($expectedPhysical[$key] ?? 0)->plus($quantity->multipliedBy(D::of($component['quantity'])));
                    }
                } else {
                    $components = InvoiceModifiers::saleNoteFactors($line->item, $line->item_id, $line->quantity, $lineQuantity);
                    $requestedWarehouse = $line->warehouse_id ?: Warehouse::where('establishment_id',$note->establishment_id)->orderBy('id')->value('id');
                    $anchor = $movements->get($line->inventory_kardex_id);
                    $warehouseId = $anchor->warehouse_id ?? $requestedWarehouse;
                    if ((string) $warehouseId !== (string) $requestedWarehouse) {
                        $anchorItem = (string) $anchor->item_id;
                        if ($anchors->get($anchor->id) !== 1
                            || ($anchorItem !== (string) $line->item_id && !isset($components[$anchorItem]))
                            || !$anchor->exactValue('quantity')->isNegative()
                            || !Warehouse::whereKey($warehouseId)->where('establishment_id', $note->establishment_id)->exists()) {
                            FiscalAmounts::error('inventory', 'No se puede acreditar el almacén físico de la partida de origen.');
                        }
                    }
                    $sourceWarehouses[$line->id] = ['requested' => (string) $requestedWarehouse, 'physical' => (string) $warehouseId];
                    $key=$line->item_id.':'.$warehouseId;
                    $expectedPhysical[$key]=D::of($expectedPhysical[$key] ?? 0)->plus($quantity);
                    $noteAvailable[$key]=D::of($noteAvailable[$key] ?? 0)->plus($quantity);
                    $individualSources[$key][$line->id] = $quantity;
                    $expectedIndividual[$line->item_id] = D::of($expectedIndividual[$line->item_id] ?? 0)->plus($quantity);
                    if ($components) $additionalComponents[$line->id] = $components;
                    foreach ($components as $componentId => $componentFactor) {
                        $componentKey = $componentId.':'.$warehouseId;
                        $amount = $lineQuantity->multipliedBy(D::of($componentFactor));
                        $expectedPhysical[$componentKey] = D::of($expectedPhysical[$componentKey] ?? 0)->plus($amount);
                    }
                }
                if (!empty($line->item->IdLoteSelected) || FiscalNoteInventory::selectedSerials((array) ($line->item->lots ?? []))) {
                    $tracked[$line->item_id][]=$line;
                }
            }
            $physical=[];
            foreach ($movements as $movement) {
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
                // The global movement also includes packs. Only the original
                // individual units can cover new individual invoice lines.
                $noteGlobal[$itemId] = $expectedIndividual[$itemId] ?? D::of(0);
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
                        if ($used->isPositive()) $billedWarehouses[$key] = true;
                        $noteGlobal[$line->item_id]=D::max(0,D::of($noteGlobal[$line->item_id] ?? 0)->minus($used));
                    }
                }
            }
            $individualMovements[$note->id] = ['sources' => $individualSources, 'available' => $noteAvailable, 'billed' => $billedWarehouses];
            foreach ($noteAvailable as $key=>$quantity) $available[$key]=D::of($available[$key] ?? 0)->plus($quantity);
            foreach ($noteGlobal as $itemId=>$quantity) $global[$itemId]=D::of($global[$itemId] ?? 0)->plus($quantity);
        }
        $usedSources=[]; $usedHints=[]; $usedComponentSources=[];
        $rowCounts=collect($input['items'])->countBy('item_id');
        foreach ($input['items'] as &$row) {
            $item=Item::findOrFail($row['item_id']);
            $group=$sourceLines[$item->id] ?? [];
            $selected=self::selectSources($row['source_sale_note_items'] ?? [],$group,$usedHints);
            $price=self::originalPrice($row,$selected ?: $group,(bool)$selected);
            $originalValues=$price !== null ? ['unit_price'=>$price] : [];
            $factor=D::of(data_get($row,'item.presentation.quantity_unit',1));
            $quantity=D::of(D::input($row['quantity'],'items.quantity'));
            $packSources = $packs[$item->id] ?? [];
            $sources = $selected ?: $group;
            $historicalPacks = array_filter($sources, static fn ($source) =>
                isset($source->calculation_snapshot['inputs']['sale_note_pack_inventory']));
            if ($historicalPacks && count($historicalPacks) !== count($sources)) {
                FiscalAmounts::error('items', 'Separe los paquetes y los productos individuales e identifique las partidas de origen.');
            }
            $originalIndividual = $item->is_set && $sources && !$historicalPacks
                && collect($sources)->every(static fn ($source) => ($source->item->is_set ?? null) === false
                    && ($source->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) !== true);
            $isPack = $historicalPacks !== [] || ($item->is_set && !$originalIndividual);
            $packSourceIds = $isPack ? $selected : [];
            if ($selected && empty($row['quantity_input'])) {
                $sameFactor=$isPack || collect($selected)->every(
                    fn($source)=>$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1))
                );
                if ($sameFactor) {
                    $original=D::sum(array_map(static fn ($source)=>$source->exactValue('quantity'),$selected));
                    if (D::represent($quantity)===D::represent($original)) {
                        // Source fractions may exceed the input limits of a decimal operand.
                        $quantity=$original;
                        $originalValues['quantity']=$original;
                    }
                }
            } elseif (empty($row['quantity_input'])) {
                $sameFactor=$group && collect($group)->every(fn($source)=>$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1)));
                $total=$sameFactor && ($rowCounts[$item->id] ?? 0)===1
                    ? D::sum(array_map(fn($source)=>$source->exactValue('quantity'),$group))
                    : null;
                if ($total !== null && D::represent($quantity)===D::represent($total) && !$quantity->isEqualTo($total)) {
                    $quantity=$total;
                    $originalValues['quantity']=$total;
                } else {
                    $matches=[];
                    foreach ($group as $source) {
                        if (isset($usedSources[$source->id]) || !$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1))) continue;
                        if (D::represent($quantity)===D::represent($source->exactValue('quantity'))) $matches[]=$source;
                    }
                    if ($matches) {
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
            }
            if (!empty($row['quantity_input'])) {
                $unit=D::inputRational($row['quantity_input']['unit_price'] ?? null,'items.quantity_input.unit_price');
                if (!$unit->isPositive()) FiscalAmounts::error('items.quantity','La cantidad no es válida.');
                $quantity=D::inputRational($row['quantity_input']['amount'] ?? null,'items.quantity_input.amount')->dividedBy($unit);
            }
            self::restoreOriginalValues($row,$originalValues);
            if ($item->unit_type_id==='SERV' && !$isPack && !$originalIndividual) continue;
            $warehouseId=($row['warehouse_id'] ?? null) ?: Warehouse::where('establishment_id',$input['establishment_id'])->orderBy('id')->value('id');
            if (!$isPack) $warehouseId = self::physicalWarehouse($warehouseId, $sources, $sourceWarehouses);
            $warehouse=Warehouse::findOrFail($warehouseId);
            if ((int)$warehouse->establishment_id!==(int)$input['establishment_id']) FiscalAmounts::error('warehouse','El almacén debe pertenecer a la sucursal de la factura.');
            if ($isPack) {
                $matches=$packSources;
                $match=null; $used=[]; $singleIdentity=null;
                foreach ($matches as $index=>$source) {
                    if ($packSourceIds && (count($packSourceIds)!==1 || !isset($packSourceIds[(string)$source->id]))) continue;
                    $snapshot=$source->calculation_snapshot['inputs']['sale_note_pack_inventory'];
                    if (!$factor->isEqualTo(D::of($source->item->presentation->quantity_unit ?? 1))) continue;
                    if (isset($row['item']['presentation']['id'],$source->item->presentation->id)
                        && (string)$row['item']['presentation']['id']!==(string)$source->item->presentation->id) continue;
                    if ((int)$snapshot['warehouse_id']===(int)$warehouse->id && $quantity->isEqualTo($source->exactValue('quantity'))) {
                        $identity=[self::packPresentationIdentity($source->item), $snapshot['components']];
                        if ($singleIdentity !== null && $singleIdentity !== $identity)
                            FiscalAmounts::error('items','La composición o presentación original es ambigua; seleccione las partidas de origen de cada nota.');
                        $singleIdentity=$identity;
                        if ($match === null) {$match=$source;$used=[$index];}
                    }
                }
                if (!$match && $matches) {
                    $components=null; $originalPresentation=null; $total=D::of(0); $candidates=[];
                    foreach ($matches as $index=>$source) {
                        if ($packSourceIds && !isset($packSourceIds[(string)$source->id])) continue;
                        $snapshot=$source->calculation_snapshot['inputs']['sale_note_pack_inventory'];
                        if ((int)$snapshot['warehouse_id']!==(int)$warehouse->id) continue;
                        if ($components !== null && $components !== $snapshot['components']) FiscalAmounts::error('items','Los paquetes con composiciones distintas deben conservar partidas separadas.');
                        $identity=self::packPresentationIdentity($source->item);
                        if ($originalPresentation !== null && $originalPresentation !== $identity)
                            FiscalAmounts::error('items','Los paquetes con presentaciones distintas deben conservar partidas separadas.');
                        $originalPresentation=$identity;
                        $components=$snapshot['components']; $total=$total->plus($source->exactValue('quantity')); $candidates[$index]=$source;
                    }
                    if ($candidates && $quantity->isEqualTo($total)) { $match=reset($candidates); $used=array_keys($candidates); }
                }
                if (!$match) FiscalAmounts::error('items','Conserve la cantidad y almacén originales del paquete.');
                if ($packSourceIds && count($used)!==count($packSourceIds)) FiscalAmounts::error('items','La cantidad debe conservar todas las partidas de origen seleccionadas.');
                foreach ($used as $index) unset($packs[$item->id][$index]);
                if (!$packs[$item->id]) unset($packs[$item->id]);
                $row['warehouse_id']=$warehouse->id;
                $row['item']['dispatch_inventory_quantity']=D::fraction(0);
                $row['item']['dispatch_global_inventory_quantity']=D::fraction(0);
                $row['_sale_note_components']=$match->calculation_snapshot['inputs']['sale_note_pack_inventory']['components'];
                $row['item']['presentation']=json_decode(json_encode($match->item->presentation ?? [],JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
                $row['item']['unit_type_id']=$match->item->unit_type_id;
                continue;
            }
            $quantity=$quantity->multipliedBy($factor);
            $key=$item->id.':'.$warehouse->id;
            $taken=D::min($quantity,$available[$key] ?? 0); $available[$key]=D::max(0,D::of($available[$key] ?? 0)->minus($taken));
            $globalTaken=D::min($taken,$global[$item->id] ?? 0); $global[$item->id]=D::max(0,D::of($global[$item->id] ?? 0)->minus($globalTaken));
            if ($originalIndividual) {
                if (!$selected && $rowCounts[$item->id] > 1) {
                    FiscalAmounts::error('items', 'Identifique la partida individual de origen de cada línea.');
                }
                if (!$taken->isEqualTo($quantity) || !$globalTaken->isEqualTo($quantity)) {
                    FiscalAmounts::error('inventory', 'La partida individual debe estar respaldada por el inventario original de la nota.');
                }
                self::claimOriginalIndividualMovement($sources, $key, $quantity, $individualMovements);
                self::preserveIndividualPresentation($row, $sources, $factor);
            }
            if (isset($tracked[$item->id]) && $taken->isPositive()) {
                $sources=$tracked[$item->id];
                if (count($sources)!==1 || !$taken->isEqualTo($quantity) || !$quantity->isEqualTo($sources[0]->exactValue('quantity')->multipliedBy($sources[0]->item->presentation->quantity_unit ?? 1))
                    || !$factor->isEqualTo(D::of($sources[0]->item->presentation->quantity_unit ?? 1))) {
                    FiscalAmounts::error('items','Conserve en una línea la cantidad y presentación de cada producto con lotes o seriales.');
                }
                $row['item']['IdLoteSelected']=$sources[0]->item->IdLoteSelected ?? null;
                $row['item']['lots']=FiscalNoteInventory::selectedSerials((array) ($sources[0]->item->lots ?? []));
                unset($tracked[$item->id]);
            }
            if ($taken->isPositive() && $taken->isLessThan($quantity)
                && (FiscalNoteInventory::selectedSerials((array) ($row['item']['lots'] ?? [])) || !empty($row['item']['IdLoteSelected']))) {
                FiscalAmounts::error('items','Separe la cantidad original de la nueva para conservar lotes y seriales.');
            }
            $row['warehouse_id']=$warehouse->id;
            $row['item']['dispatch_inventory_quantity']=D::fraction($quantity->minus($taken));
            $row['item']['dispatch_global_inventory_quantity']=D::fraction($quantity->minus($globalTaken));
            $componentLines = array_filter($selected ?: $group, static fn ($source) => isset($additionalComponents[$source->id]));
            if ($componentLines) {
                $componentSources = $selected ?: $group;
                self::preserveAdditionalComponents($row, $componentSources, $additionalComponents, $usedComponentSources, $factor, $quantity, $taken, $globalTaken);
                if (!$originalIndividual) self::claimOriginalIndividualMovement($componentSources, $key, $quantity, $individualMovements);
            }
        }
        unset($row);
        if ($tracked) FiscalAmounts::error('items','La factura debe conservar los productos con lotes o seriales de las notas.');
        if ($packs) FiscalAmounts::error('items','La factura debe conservar los paquetes de las notas de venta.');
        return $input;
    }

    private static function physicalWarehouse($requested, array $sources, array $warehouses)
    {
        $physical = [];
        $redirected = false;
        foreach ($sources as $source) {
            $origin = $warehouses[$source->id] ?? null;
            if (!$origin) continue;
            $matches = (string) $requested === $origin['requested'] || (string) $requested === $origin['physical'];
            $warehouse = $matches ? $origin['physical'] : (string) $requested;
            $physical[$warehouse] = true;
            if ($warehouse !== (string) $requested) $redirected = true;
        }
        if (!$redirected) return $requested;
        if (count($physical) !== 1) {
            FiscalAmounts::error('inventory', 'Separe las partidas que provienen de almacenes físicos distintos.');
        }

        return array_key_first($physical);
    }

    private static function preserveAdditionalComponents(
        array &$row,
        array $sources,
        array $components,
        array &$used,
        BigRational $factor,
        BigRational $quantity,
        BigRational $taken,
        BigRational $globalTaken,
    ): void {
        $original = $components[reset($sources)->id] ?? [];
        $originalQuantity = D::sum(array_map(static fn ($line) => $line->exactValue('quantity'), $sources))->multipliedBy($factor);
        if (!$quantity->isEqualTo($originalQuantity) || !$taken->isEqualTo($quantity) || !$globalTaken->isEqualTo($quantity)) {
            FiscalAmounts::error('inventory', 'Conserve la cantidad y el almacén originales de las partidas con componentes.');
        }
        foreach ($sources as $source) {
            if (isset($used[$source->id]) || ($components[$source->id] ?? []) !== $original) {
                FiscalAmounts::error('items', 'Separe las partidas con componentes distintos e identifique su origen.');
            }
            $used[$source->id] = true;
        }
        self::preserveIndividualPresentation($row, $sources, $factor);
        foreach ($original as $id => $amount) {
            $row['_sale_note_components'][] = ['item_id' => $id, 'quantity_exact' => $amount, 'invoice_modifier' => true];
        }
    }

    private static function claimOriginalIndividualMovement(array $sources, string $key, BigRational $quantity, array &$movements): void
    {
        $claims = [];
        foreach ($sources as $source) {
            $stock = $movements[$source->sale_note_id]['sources'][$key][$source->id] ?? null;
            if ($stock === null) {
                FiscalAmounts::error('inventory', 'El almacén debe coincidir con el movimiento de la partida de origen.');
            }
            $claims[$source->sale_note_id][$source->id] = $stock;
        }
        foreach ($claims as $noteId => $parts) {
            $movement = &$movements[$noteId];
            if (isset($movement['billed'][$key]) && count($parts) !== count($movement['sources'][$key])) {
                FiscalAmounts::error('inventory', 'Identifique todas las partidas del almacén si una orden ya usó parte del movimiento.');
            }
            $used = D::min($quantity, D::min($movement['available'][$key], D::sum($parts)));
            $movement['available'][$key] = $movement['available'][$key]->minus($used);
            $quantity = $quantity->minus($used);
            unset($movement);
        }
        if (!$quantity->isZero()) {
            FiscalAmounts::error('inventory', 'La cantidad supera el inventario respaldado por las partidas de origen.');
        }
    }

    private static function preserveIndividualPresentation(array &$row, array $sources, BigRational $factor): void
    {
        $original = reset($sources)->item;
        foreach ($sources as $source) {
            $item = $source->item;
            if (!$factor->isEqualTo(D::of($item->presentation->quantity_unit ?? 1))
                || $item->unit_type_id !== $original->unit_type_id
                || (string) ($item->presentation->id ?? '') !== (string) ($original->presentation->id ?? '')) {
                FiscalAmounts::error('items', 'Conserve la presentación individual original en una partida separada.');
            }
        }
        $row['item']['presentation'] = (array) ($original->presentation ?? []);
        $row['item']['unit_type_id'] = $original->unit_type_id;
        $row['_sale_note_components'] = [['item_id' => $row['item_id'], 'quantity_exact' => D::fraction($factor)]];
        $row['_sale_note_is_set'] = false;
    }

    private static function packPresentationIdentity($item): array
    {
        return [(string)($item->presentation->id ?? ''),(string)$item->unit_type_id,
            D::fraction(FiscalNoteInventory::presentationQuantity($item))];
    }

    private static function selectSources($hints, array $sources, array &$used): array
    {
        if (!is_array($hints) || !array_is_list($hints)) {
            FiscalAmounts::error('items','El origen de las partidas no es válido.');
        }
        $selected=[];
        foreach ($hints as $hint) {
            $id=IntegerTransport::positiveKey(is_array($hint) ? ($hint['sale_note_item_id'] ?? null) : null);
            if ($id === null || isset($used[$id])) {
                FiscalAmounts::error('items','Identifique cada partida de origen una sola vez.');
            }
            if (!isset($sources[$id])) {
                FiscalAmounts::error('items','La partida de origen no pertenece a las notas seleccionadas o ya fue utilizada.');
            }
            $used[$id]=true;
            $selected[$id]=$sources[$id];
        }
        return $selected;
    }

    public static function restoreSourcePrice(array &$row, SaleNoteItem $source): void
    {
        $price = self::originalPrice($row, [$source], true);
        if ($price !== null) self::restoreOriginalValues($row, ['unit_price' => $price]);
    }

    private static function originalPrice(array $row, array $sources, bool $identified): ?BigRational
    {
        if (!$sources || !empty($row['price_input'])) return null;

        $displayed=D::represent(D::input($row['unit_price'] ?? null,'items.unit_price'));
        $price=null;
        $ambiguous=false;
        foreach ($sources as $source) {
            $candidate=$source->exactValue('unit_price');
            if (D::represent($candidate)!==$displayed) {
                if ($identified) return null;
                continue;
            }
            if ($price !== null && !$price->isEqualTo($candidate)) $ambiguous=true;
            $price=$candidate;
        }
        if ($ambiguous) FiscalAmounts::error('items.unit_price','El precio exacto de la nota de venta es ambiguo. Identifique cada partida o indique explícitamente su precio.');
        return $price;
    }

    private static function restoreOriginalValues(array &$row, array $values): void
    {
        if (!$values) return;
        $snapshot=$row['calculation_snapshot'] ?? [];
        if (is_string($snapshot)) $snapshot=json_decode($snapshot,true,512,JSON_THROW_ON_ERROR);
        $row['calculation_snapshot']=array_replace_recursive($snapshot,D::snapshot($values));
    }
}
