<?php

namespace Modules\Inventory\Providers;

use App\Database\IntegerTransport;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Document;
use App\Models\Tenant\Item;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\PurchaseSettlementItem;
use App\Models\Tenant\SaleNoteItem;
use App\Services\Fiscal\FiscalNoteInventory;
use Exception;
use App\Support\Exact\Decimal as ExactDecimal;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Traits\InventoryTrait;
use Modules\Order\Models\OrderNote;
use Modules\Order\Models\OrderNoteItem;
use Modules\Item\Models\ItemLotsGroup;
use Modules\Item\Models\ItemLot;
use Modules\Inventory\Models\DevolutionItem;
use Modules\Inventory\Models\ItemWarehouse;
use App\Models\Tenant\DispatchItem;


/**
 * Se debe tener en cuenta este provider para llevar el control de Kardex
 */
class InventoryKardexServiceProvider extends ServiceProvider
{
    use InventoryTrait;

    /** Freeze the catalog unit before insertion; never trust a marker from the client. */
    private function markNewServiceInventory($line): void
    {
        if (!\App\Support\Exact\Persistence::enabled($line)) return;
        $item = Item::withoutGlobalScopes()->whereKey($line->item_id)->lockForUpdate()->first(['id', 'unit_type_id', 'is_set', 'series_enabled', 'lots_enabled']);
        $snapshot = $line->calculation_snapshot ?? \App\Support\Exact\Decimal::snapshot([]);
        unset($snapshot['inputs']['inventory_service_without_movement']);
        if ($item && $item->unit_type_id === 'SERV' && !$item->is_set) $snapshot['inputs']['inventory_service_without_movement'] = true;
        if ($line instanceof SaleNoteItem) {
            unset($snapshot['inputs']['sale_note_pack_inventory']);
            if ($item && $item->is_set && !$line->sale_note->order_note_id) {
                if (!empty($line->item->IdLoteSelected) || !empty($line->item->lots)) \App\Services\Fiscal\FiscalAmounts::error('items','Los paquetes con lotes o seriales requieren trazabilidad por componente.');
                if (!empty($line->item->esFusionado) || !empty($line->item->modifiersApplied)) \App\Services\Fiscal\FiscalAmounts::error('items','Los paquetes fusionados o con modificadores requieren trazabilidad por componente.');
                $warehouse=$line->warehouse_id ? $this->findWarehouseById($line->warehouse_id) : $this->findWarehouse($line->sale_note->establishment_id);
                if ((int)$warehouse->establishment_id!==(int)$line->sale_note->establishment_id) \App\Services\Fiscal\FiscalAmounts::error('items.warehouse_id','El almacén debe pertenecer a la sucursal de la nota.');
                $original=$line->relationLoaded('verifiedSaleNotePackInventory') ? (array)$line->getRelation('verifiedSaleNotePackInventory') : null;
                if ($original && ((int)$original['item_id']!==(int)$item->id || (int)$original['sale_note_id']!==(int)$line->sale_note_id)) \App\Services\Fiscal\FiscalAmounts::error('items','La composición no pertenece a la partida original.');
                $components=$original['components'] ?? [];
                if (!$original) foreach ($item->sets()->orderBy('individual_item_id')->get() as $component) {
                    if ($component->individual_item->unit_type_id === 'SERV') continue;
                    $quantity=$component->exactValue('quantity');
                    if (!$quantity->isPositive()) \App\Services\Fiscal\FiscalAmounts::error('items','La cantidad de un componente del paquete debe ser positiva.');
                    $components[]=['item_id'=>$component->individual_item_id,'quantity'=>ExactDecimal::fraction($quantity)];
                }
                $snapshot['inputs']['sale_note_pack_inventory']=['item_id'=>$item->id,'sale_note_id'=>$line->sale_note_id,
                    'warehouse_id'=>$warehouse->id,'components'=>$components];
                $line->warehouse_id=$warehouse->id;
            }
        }
        if ($line instanceof OrderNoteItem) {
            unset($snapshot['inputs']['order_inventory_warehouse_id']);
            unset($snapshot['inputs']['order_pack_inventory']);
            unset($snapshot['inputs']['order_lot_inventory']);
            unset($snapshot['inputs']['order_serial_inventory']);
            if ($item) {
                $itemData=(array)$line->item;
                unset($itemData['lot_serial_assignments_verified']);
                $itemData['is_set']=(bool)$item->is_set;
                $line->item=$itemData;
            }
            if ($item && !$item->is_set && $item->unit_type_id !== 'SERV') {
                $warehouse = $line->warehouse_id
                    ? $this->findWarehouse($this->findWarehouseById($line->warehouse_id)->establishment_id)
                    : $this->findWarehouse();
                $snapshot['inputs']['order_inventory_warehouse_id'] = $warehouse->id;
                $lots=\App\Services\Fiscal\OrderLotInventory::capture($line,$warehouse->id);
                if ($lots !== null) $snapshot['inputs']['order_lot_inventory']=$lots;
                if ($item->lots_enabled && $lots === null) \App\Services\Fiscal\FiscalAmounts::error('items','Seleccione los lotes del producto.');
                $serials=\App\Services\Fiscal\OrderSerialInventory::capture($line,$warehouse->id);
                $serials=\App\Services\Fiscal\OrderTrackedInventory::verifyAssociation($line,$lots,$serials);
                if ($serials !== null) $snapshot['inputs']['order_serial_inventory']=$serials;
                if ($item->series_enabled && $serials === null) \App\Services\Fiscal\FiscalAmounts::error('items','Seleccione los seriales del producto.');
            }
            if ($item && $item->is_set) {
                if (!empty($line->item->IdLoteSelected)
                    || collect($line->item->lots_group ?? [])->contains(fn ($lot) => !ExactDecimal::of(
                        ExactDecimal::input(data_get($lot, 'compromise_quantity') ?? '0', 'items')
                    )->isZero())
                    || collect($line->item->lots ?? [])->contains(fn ($serial) => filter_var(data_get($serial, 'has_sale'), FILTER_VALIDATE_BOOLEAN))) {
                    \App\Services\Fiscal\FiscalAmounts::error('items', 'Los paquetes con lotes o seriales requieren trazabilidad por componente.');
                }
                $components=[];
                foreach ($item->sets()->orderBy('individual_item_id')->get() as $component) {
                    if ($component->individual_item->unit_type_id === 'SERV') continue;
                    $quantity=$component->exactValue('quantity');
                    if (!$quantity->isPositive()) \App\Services\Fiscal\FiscalAmounts::error('items','La cantidad de un componente del paquete debe ser positiva.');
                    $components[]=['item_id'=>$component->individual_item_id,'quantity'=>\App\Support\Exact\Decimal::fraction($quantity)];
                }
                $snapshot['inputs']['order_pack_inventory']=['warehouse_id'=>$this->findWarehouse()->id,'components'=>$components];
            }
        }
        $line->calculation_snapshot = $snapshot;
    }

    private function hasNoServiceInventory($line): bool
    {
        return ($line->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true;
    }

    public function register() {
        //
    }

    public function boot() {
        DocumentItem::creating(fn ($line) => $this->markNewServiceInventory($line));
        SaleNoteItem::creating(fn ($line) => $this->markNewServiceInventory($line));
        PurchaseItem::creating(fn ($line) => $this->markNewServiceInventory($line));
        PurchaseSettlementItem::creating(fn ($line) => $this->markNewServiceInventory($line));
        OrderNoteItem::creating(fn ($line) => $this->markNewServiceInventory($line));
        $this->purchase();
        $this->purchase_settlement();
        $this->sale();
        $this->sale_note();
        $this->sale_note_item_delete();
        $this->sale_document_type_03_delete();
        $this->order_note();
        $this->order_note_item_delete();
        $this->purchase_item_delete();
        $this->purchase_item_settlement_delete();
        $this->item_lot_delete();

        $this->devolution();

        $this->dispatch();
        $this->document_item_delete();

    }

    /**
     *Se dispara luego de crear la compra.
     */
    private function purchase() {
        PurchaseItem::created(function (PurchaseItem $purchase_item) {
            if ($this->hasNoServiceInventory($purchase_item)) return;

            $presentationQuantity = (!empty($purchase_item->item->presentation)) ? $purchase_item->item->presentation->quantity_unit : 1;

            $warehouse = ($purchase_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id) : $this->findWarehouse();
            // $warehouse = $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id);
            // $warehouse = $this->findWarehouse();
            //$this->createInventory($purchase_item->item_id, $purchase_item->quantity, $warehouse->id);
            $quantity=$purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity));
            $movement=$this->createInventoryKardex($purchase_item->purchase, $purchase_item->item_id, $quantity, $warehouse->id);
            $this->updateStock($purchase_item->item_id, $quantity, $warehouse->id);
            $this->rememberPurchaseWarehouseMovement($purchase_item,$movement,$quantity,$warehouse->id);

            // Sincronizar stock del restaurante
            $this->syncRestaurantStock($purchase_item->item_id);
        });
    }

    /**
     *Se dispara luego de crear la compra.
     */
    private function purchase_settlement() {
        PurchaseSettlementItem::created(function (PurchaseSettlementItem $purchase_item) {
            if ($this->hasNoServiceInventory($purchase_item)) return;
            /* dd($purchase_item); */
            $presentationQuantity = (!empty($purchase_item->item->presentation)) ? $purchase_item->item->presentation->quantity_unit : 1;

            $warehouse = ($purchase_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id) : $this->findWarehouse();
            // $warehouse = $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id);
            // $warehouse = $this->findWarehouse();
            //$this->createInventory($purchase_item->item_id, $purchase_item->quantity, $warehouse->id);
            $quantity=$purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity));
            $movement=$this->createInventoryKardex($purchase_item->purchase_settlement, $purchase_item->item_id, $quantity, $warehouse->id);
            $this->updateStock($purchase_item->item_id, $quantity, $warehouse->id);
            $this->rememberPurchaseWarehouseMovement($purchase_item,$movement,$quantity,$warehouse->id);

            // Sincronizar stock del restaurante
            $this->syncRestaurantStock($purchase_item->item_id);
        });
    }


    // ######## INICIO MOVIMIENTO ORIGINAL DE COMPRA ########
    private function rememberPurchaseWarehouseMovement($line, $movement, $quantity, $warehouseId): void
    {
        if (!\App\Support\Exact\Persistence::enabled($line)) return;
        $snapshot=$line->calculation_snapshot;
        $snapshot['inputs']['purchase_warehouse_inventory']=['kardex_id'=>$movement->id,'warehouse_id'=>$warehouseId,
            'quantity'=>ExactDecimal::fraction($quantity)];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where($line->getKeyName(),$line->getKey())
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }
    // ######## FIN MOVIMIENTO ORIGINAL DE COMPRA ########

    /**
     * Se dispara cuando se realiza una venta
     */
    private function sale() {

        DocumentItem::created(function (DocumentItem $document_item) {
            if (\App\Services\Fiscal\FiscalNote::ordinary($document_item->document)) {
                \App\Services\Fiscal\FiscalNoteInventory::apply($document_item);
                if ($document_item->exactValue('inventory_quantity')->isPositive() && !empty($document_item->item->inventory_components)) {
                    $ids=[$document_item->item_id];
                    foreach ((array)$document_item->item->inventory_components as $component) $ids[]=((array)$component)['item_id'];
                    foreach (array_unique($ids) as $id) $this->syncRestaurantStock($id);
                }
                return;
            }
            if ($this->hasNoServiceInventory($document_item)) return;
            if ($document_item->document->document_type_id==='01' && \App\Services\Fiscal\DeliveryOrderInvoiceInventory::apply($document_item,-1)) return;
            // si es nota credito tipo 13, no se asocia a inventario
            if($document_item->document->isCreditNoteAndType13()) return;

            if (!$document_item->item->is_set)
            {
                $presentationQuantity = \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($document_item->item);
                $document = $document_item->document;
                $factor = ($document->document_type_id === '07') ? 1 : -1;
                $warehouse = ($document_item->warehouse_id) ? $this->findWarehouseById($document_item->warehouse_id) : $this->findWarehouse();
                if ($warehouse->establishment_id != $document->establishment_id) \App\Services\Fiscal\FiscalAmounts::error('items.warehouse_id','El almacén debe corresponder a la sucursal del comprobante.');
                if (!$document_item->warehouse_id) $document_item->forceFill(['warehouse_id'=>$warehouse->id])->saveQuietly();
                //$this->createInventory($document_item->item_id, $factor * $document_item->quantity, $warehouse->id);
                $this->createInventoryKardex($document_item->document, $document_item->item_id, $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($factor), $warehouse->id);

                if (!$document_item->document->sale_note_id && !$document_item->document->order_note_id && !$document_item->document->dispatch_id && !$document_item->document->sale_notes_relateds)
                {
                    $this->updateStock($document_item->item_id, $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($factor), $warehouse->id);

                } else
                {
                    if ($document_item->document->dispatch)
                    {
                        if (!$document_item->document->dispatch->discountsPhysicalStock()) {
                            $this->updateStock($document_item->item_id, $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($factor), $warehouse->id);
                        }
                    }
                }

            } else {

                foreach ((array)($document_item->item->inventory_components ?? []) as $component) {
                    $component=(array)$component;
                    $item_set_quantity=ExactDecimal::of($component['quantity_exact'] ?? $component['quantity']);
                    $presentationQuantity = 1;
                    $document = $document_item->document;
                    $factor = ($document->document_type_id === '07') ? 1 : -1;
                    $warehouse = $document_item->warehouse_id ? $this->findWarehouseById($document_item->warehouse_id) : $this->findWarehouse();
                    if ($warehouse->establishment_id != $document->establishment_id) \App\Services\Fiscal\FiscalAmounts::error('items.warehouse_id','El almacén debe corresponder a la sucursal del comprobante.');
                    if (!$document_item->warehouse_id) $document_item->forceFill(['warehouse_id'=>$warehouse->id])->saveQuietly();
                    $this->createInventoryKardex($document_item->document, $component['item_id'], $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($item_set_quantity)->multipliedBy($factor), $warehouse->id);

                    if (!$document_item->document->sale_note_id && !$document_item->document->order_note_id && !$document_item->document->dispatch_id && !$document_item->document->sale_notes_relateds)
                    {
                        $this->updateStock($component['item_id'], $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($item_set_quantity)->multipliedBy($factor), $warehouse->id);
                    } else {
                        if ($document_item->document->dispatch) {
                            if (!$document_item->document->dispatch->discountsPhysicalStock()) {
                                $this->updateStock($component['item_id'], $document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($item_set_quantity)->multipliedBy($factor), $warehouse->id);
                            }
                        }
                    }

                }
            }

            if (!$document_item->item->is_set) $this->moveInvoiceModifiers($document_item, $factor);
            foreach (\App\Services\Fiscal\InvoiceModifiers::components($document_item->item) as $component) {
                $this->syncRestaurantStock($component['item_id']);
            }
            $invoiceLotMovements=[];$invoiceSerialMovements=[];

            /*
             * Calculando el stock por lote por factor según la unidad
             */

            if(!$document->isGeneratedFromExternalRecord())
            {

                if (isset($document_item->item->IdLoteSelected))
                {
                    if (!in_array($document_item->item->IdLoteSelected, ['', []], true))
                    {
                        $productName = $document_item->item->description
                            ?? ($document_item->item->name ?? 'sin nombre');

                        if(is_array($document_item->item->IdLoteSelected))
                        {
                            // presentacion - factor de lista de precios
                            $quantity_unit = \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($document_item->item);

                            // ######## INICIO ASIGNACIÓN EXACTA Y BLOQUEO DE LOTES ########
                            $lotesSelecteds=[]; $seen=[]; $selectedTotal=ExactDecimal::of(0);
                            foreach ($document_item->item->IdLoteSelected as $selected) {
                                $selected=(array)$selected;
                                $id = IntegerTransport::positiveKey($selected['id'] ?? null);
                                if ($id === null || isset($seen[$id])) {
                                    \App\Services\Fiscal\FiscalAmounts::error('items.IdLoteSelected', "Lote inválido o duplicado para el producto [{$productName}].");
                                }
                                $quantity=ExactDecimal::inputRational($selected['compromise_quantity'] ?? null,'items.lots');
                                if (!$quantity->isPositive()) throw new Exception("La cantidad del lote debe ser positiva para el producto [{$productName}]");
                                $seen[$id]=true; $selectedTotal=$selectedTotal->plus($quantity);
                                $lotesSelecteds[]=['id'=>$id,'quantity'=>$quantity];
                            }
                            $lineQuantity=$document_item->exactValue('quantity');
                            if (!$lotesSelecteds || ExactDecimal::represent($selectedTotal)!==ExactDecimal::represent($lineQuantity)) {
                                throw new Exception("Las cantidades de lotes no coinciden con el producto [{$productName}]");
                            }
                            $last=count($lotesSelecteds)-1;
                            $lotesSelecteds[$last]['quantity']=$lotesSelecteds[$last]['quantity']->plus($lineQuantity->minus($selectedTotal));
                            if (!$lotesSelecteds[$last]['quantity']->isPositive()) throw new Exception("La cantidad del lote debe ser positiva para el producto [{$productName}]");
                            usort($lotesSelecteds,fn($a,$b)=>$a['id']<=>$b['id']);
                            $document_factor = ($document->document_type_id === '07') ? 1 : -1;
                            $lotMetadata=[];

                            foreach ($lotesSelecteds as $item)
                            {
                                $lotId = $item['id'];
                                $compromiseQuantity = $item['quantity'];

                                $lot = ItemLotsGroup::query()->where('item_id',$document_item->item_id)->lockForUpdate()->find($lotId);
                                if (!$lot) {
                                    throw new Exception("Lote no encontrado (id: {$lotId}) para el producto [{$productName}]");
                                }
                                $lotMetadata[(string)$lotId]=['code'=>$lot->code,'date_of_due'=>$lot->date_of_due];

                                $lotDelta=ExactDecimal::of($quantity_unit)->multipliedBy(ExactDecimal::of($compromiseQuantity))->multipliedBy($document_factor);
                                $lot->quantity = $lot->exactValue('quantity')->plus($lotDelta);
                                $this->validateStockLotGroup($lot, $document_item);
                                $lot->save();
                                $invoiceLotMovements[]=['id'=>$lot->id,'quantity'=>ExactDecimal::fraction($lotDelta)];
                            }
                            $frozen=json_decode(json_encode($document_item->item,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
                            $exactById=[];
                            foreach ($lotesSelecteds as $selected) $exactById[(string)$selected['id']]=$selected['quantity'];
                            foreach ($frozen['IdLoteSelected'] as &$selected) {
                                $exact=$exactById[(string)$selected['id']];
                                $selected['code']=$lotMetadata[(string)$selected['id']]['code'];
                                $selected['date_of_due']=$lotMetadata[(string)$selected['id']]['date_of_due'];
                                $selected['compromise_quantity']=ExactDecimal::represent($exact);
                                $selected['compromise_quantity_exact']=ExactDecimal::fraction($exact);
                            }
                            unset($selected);
                            $document_item->item=$frozen;
                            $document_item->getConnection()->table($document_item->getTable())->where($document_item->getKeyName(),$document_item->getKey())
                                ->update(['item'=>$document_item->getAttributes()['item']]);
                            // ######## FIN ASIGNACIÓN EXACTA Y BLOQUEO DE LOTES ########

                        }
                        else{

                            $lotId = IntegerTransport::positiveKey($document_item->item->IdLoteSelected);
                            if ($lotId === null) {
                                \App\Services\Fiscal\FiscalAmounts::error('items.IdLoteSelected', "Lote inválido para el producto [{$productName}].");
                            }
                            $lot = ItemLotsGroup::query()->where('item_id',$document_item->item_id)->lockForUpdate()->find($lotId);
                            if (!$lot) {
                                throw new Exception("Lote no encontrado (id: {$lotId}) para el producto [{$productName}]");
                            }

                            $quantity_unit = \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($document_item->item);
                            if ($document->document_type_id === '07') {
                                $quantity = $lot->exactValue('quantity')->plus($document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($quantity_unit)));
                            } else {
                                $quantity = $lot->exactValue('quantity')->minus($document_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($quantity_unit)));
                            }

                            $lotDelta=$quantity->minus($lot->exactValue('quantity'));
                            $lot->quantity = $quantity;
                            $this->validateStockLotGroup($lot, $document_item);
                            $lot->save();
                            $invoiceLotMovements[]=['id'=>$lot->id,'quantity'=>ExactDecimal::fraction($lotDelta)];
                        }

                    }
                }
            }

            foreach (FiscalNoteInventory::selectedSerials((array) ($document_item->item->lots ?? [])) as $it) {
                $r = ItemLot::where('item_id', $document_item->item_id)->whereKey($it->id)->lockForUpdate()->firstOrFail();
                if ($r->warehouse_id !== null && (string) $r->warehouse_id !== (string) $document_item->warehouse_id) {
                    \App\Services\Fiscal\FiscalAmounts::error('items.serial_ids', 'El serial no corresponde al almacén de la factura.');
                }
                if ($document->document_type_id === '01' && $r->has_sale) {
                    \App\Services\Fiscal\FiscalAmounts::error('items.serial_ids', 'El serial ya fue vendido.');
                }
                $serialBefore = $r->has_sale;
                $r->has_sale = ($document->document_type_id === '07') ? false : true;
                $r->save();
                $invoiceSerialMovements[] = ['id' => $r->id, 'before' => $serialBefore, 'after' => $r->has_sale];
            }

            \App\Services\Fiscal\InvoiceTrackedInventory::remember($document_item,$invoiceLotMovements,$invoiceSerialMovements);
            // Sincronizar stock del restaurante
            $this->syncRestaurantStock($document_item->item_id);

        });
    }


    /**
     * Se dispara  al generar una nota de venta
     */
    private function sale_note()
    {
        SaleNoteItem::created(function (SaleNoteItem $sale_note_item) {

            if ($this->hasNoServiceInventory($sale_note_item)) return;
            if ($sale_note_item->sale_note->order_note_id) return;
            if (\App\Services\Fiscal\SaleNotePackInventory::apply($sale_note_item,-1)) return;

            if(!$sale_note_item->item->is_set){

                $presentationQuantity = (!empty($sale_note_item->item->presentation)) ? $sale_note_item->item->presentation->quantity_unit : 1;

                // $warehouse = $this->findWarehouse($sale_note_item->sale_note->establishment_id);
                $warehouse = ($sale_note_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($sale_note_item->warehouse_id)->establishment_id) : $this->findWarehouse($sale_note_item->sale_note->establishment_id);

                // $this->createInventoryKardex($sale_note_item->sale_note, $sale_note_item->item_id, $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);
                $this->createInventoryKardexSaleNote($sale_note_item->sale_note, $sale_note_item->item_id, $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id, $sale_note_item->id);
                if(!$sale_note_item->sale_note->order_note_id) $this->updateStock($sale_note_item->item_id, $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);

            }else{

                $item = Item::findOrFail($sale_note_item->item_id);

                foreach ($item->sets as $it) {

                    $ind_item  = $it->individual_item;
                    $item_set_quantity  = ($it->quantity) ? $it->quantity : 1;
                    $presentationQuantity = 1;
                    $warehouse = $this->findWarehouse($sale_note_item->sale_note->establishment_id);
                    // $this->createInventoryKardex($sale_note_item->sale_note, $ind_item->id , $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);
                    $this->createInventoryKardexSaleNote($sale_note_item->sale_note, $ind_item->id , (-1 * ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity)), $warehouse->id, $sale_note_item->id);
                    if(!$sale_note_item->sale_note->order_note_id) $this->updateStock($ind_item->id , (-1 * ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity)), $warehouse->id);

                }

            }

            $components = array_merge(
                $this->processMergedProductsSaleNote($sale_note_item),
                $this->processModifiersAppliedSaleNote($sale_note_item)
            );
            if ($components && \App\Support\Exact\Persistence::enabled($sale_note_item)) {
                $sale_note_item->refresh();
                $snapshot = $sale_note_item->calculation_snapshot;
                $snapshot['inputs']['sale_note_component_movements'] = ['ids' => $components, 'reversed' => false];
                $sale_note_item->forceFill(['calculation_snapshot' => $snapshot])->saveQuietly();
            }

            foreach (FiscalNoteInventory::selectedSerials((array) ($sale_note_item->item->lots ?? [])) as $serial) {
                $record = ItemLot::findOrFail($serial->id);
                $record->has_sale = true;
                $record->save();
            }

            // Sincronizar stock del restaurante
            $this->syncRestaurantStock($sale_note_item->item_id);

        });
    }


    /**
     * @param $item_id
     * @param $quantity
     * @param $warehouse_id
     */
    private function createInventory($item_id, $quantity, $warehouse_id) {
        if(!$this->checkInventory($item_id, $warehouse_id)) {
            $item = $this->findItem($item_id);
            $this->createInitialInventory($item_id, $item->exactValue('stock')->minus(ExactDecimal::of($quantity)), $warehouse_id);
        }
    }


    /**
     * Se dispara al borrar un item de nota de venta
     */
    private function sale_note_item_delete()
    {

        SaleNoteItem::deleted(function (SaleNoteItem $sale_note_item) {

            if ($this->hasNoServiceInventory($sale_note_item)) return;
            if ($sale_note_item->sale_note->order_note_id) return;
            if (($sale_note_item->calculation_snapshot['inputs']['sale_note_component_movements']['reversed'] ?? false) === true) return;
            if (isset($sale_note_item->calculation_snapshot['inputs']['sale_note_pack_inventory'])) {
                if (!in_array((string)$sale_note_item->sale_note->state_type_id,['09','11'],true)) \App\Services\Fiscal\SaleNotePackInventory::apply($sale_note_item,1);
                return;
            }

            // dd($sale_note_item);

            if(!$sale_note_item->item->is_set){

                $presentationQuantity = (!empty($sale_note_item->item->presentation)) ? $sale_note_item->item->presentation->quantity_unit : 1;

                // $warehouse = $this->findWarehouse();
                $warehouse = ($sale_note_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($sale_note_item->warehouse_id)->establishment_id) : $this->findWarehouse($sale_note_item->sale_note->establishment_id);

                $this->createInventoryKardex($sale_note_item->sale_note, $sale_note_item->item_id, $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity)), $warehouse->id);
                // $this->deleteInventoryKardex($sale_note_item->sale_note, $sale_note_item->inventory_kardex_id);
                $this->updateStock($sale_note_item->item_id, $sale_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity)), $warehouse->id);

            }else{

                $item = Item::findOrFail($sale_note_item->item_id);

                foreach ($item->sets as $it) {

                    $ind_item  = $it->individual_item;
                    $item_set_quantity  = ($it->quantity) ? $it->quantity : 1;
                    $presentationQuantity = 1;
                    $warehouse = $this->findWarehouse();

                    $this->createInventoryKardex($sale_note_item->sale_note, $ind_item->id, ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity), $warehouse->id);
                    // $this->deleteInventoryKardex($sale_note_item->sale_note, $sale_note_item->inventory_kardex_id);

                    $this->updateStock($ind_item->id , (1 * ($sale_note_item->quantity * $presentationQuantity * $item_set_quantity)), $warehouse->id);

                }

            }

            $this->reverseSaleNoteComponents($sale_note_item);
            $this->deleteItemLots($sale_note_item);

        });
    }


    /**
     * Se dispara al borrar un documento tipo 3
     */
    private function sale_document_type_03_delete() {

        Document::deleted(function (Document $document) {
        });
    }

    /**
     * Se dispara al generar un pedido
     */
    private function order_note() {

        OrderNoteItem::created(function (OrderNoteItem $order_note_item) {
            if ($this->hasNoServiceInventory($order_note_item)) return;
            if (\App\Services\Fiscal\OrderPackInventory::apply($order_note_item,$order_note_item->exactValue('quantity')->negated())) return;
            if (\App\Services\Fiscal\OrderTrackedInventory::apply($order_note_item,-1)) return;
            /** @todo bloque repetido, buscar colocar en funcion */
            $item = $order_note_item->item;
            $document = $order_note_item->order_note;
            $warehouse_id = $order_note_item->warehouse_id;

            $presentationQuantity = $item->presentation->quantity_unit ?? 1;
            // $warehouse = $this->findWarehouse($order_note_item->order_note->establishment_id);
            // $warehouse = ($warehouse_id) ? $this->findWarehouse($this->findWarehouseById($warehouse_id)->establishment_id) : $this->findWarehouse($order_note_item->order_note->establishment_id);
            $item_id =$order_note_item->item_id;
            // $factor = 1;
            // Factor proviende de Document.
             $factor = ($document->document_type_id  && $document->document_type_id === '07') ? 1 : -1;

            if (!$item->is_set) {
                $presentationQuantity = $item->presentation->quantity_unit ?? 1;
                $quanty = $order_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->multipliedBy($factor);

                $warehouse = ($warehouse_id) ?
                    $this->findWarehouse($this->findWarehouseById($warehouse_id)->establishment_id) :
                    $this->findWarehouse();
                //$this->createInventory($item_id, $factor * $order_note_item->quantity, $warehouse->id);
                $this->createInventoryKardex($document, $item_id, $quanty, $warehouse->id);
                if (!$document->sale_note_id && !$document->order_note_id && !$document->dispatch_id) {
                    $this->updateStock($item_id, ($quanty), $warehouse->id);
                } else {
                    if ($document->dispatch) {
                        if (!$document->dispatch->discountsPhysicalStock()) {
                            $this->updateStock($item_id, ($quanty), $warehouse->id);
                        }
                    }
                }

            } else {

                $item = Item::findOrFail($item_id);
                foreach ($item->sets as $it) {
                    /** @var Item $ind_item */

                    $ind_item = $it->individual_item;
                    $item_id = $ind_item->id;
                    $item_set_quantity = ($it->quantity) ?: 1;
                    $presentationQuantity = 1;

                    $warehouse = $this->findWarehouse();
                    $quanty = $factor * ($order_note_item->quantity * $presentationQuantity * $item_set_quantity);

                    $this->createInventoryKardex($document, $item_id, ($quanty), $warehouse->id);

                    if (!$document->sale_note_id && !$document->order_note_id && !$document->dispatch_id) {
                        $this->updateStock($item_id, ($quanty), $warehouse->id);
                    } else {
                        if ($document->dispatch) {
                            if (!$document->dispatch->discountsPhysicalStock()) {
                                $this->updateStock($item_id, ($quanty), $warehouse->id);
                            }
                        }
                    }

                }
            }


            // $this->createInventoryKardex($order_note_item->order_note, $order_note_item->item_id, $order_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);
            // $this->updateStock($order_note_item->item_id, $order_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);

            if (isset($order_note_item->calculation_snapshot['inputs']['order_inventory_warehouse_id'])) return;

            // control de lotes
            if (isset($order_note_item->item->IdLoteSelected))
            {
                $IdLoteSelected = $order_note_item->item->IdLoteSelected;

                if(is_array($IdLoteSelected))
                {
                    foreach ($IdLoteSelected as $lot_selected)
                    {
                        $lot = ItemLotsGroup::find($lot_selected->id);
                        $lot->quantity = $lot->quantity - ($lot_selected->compromise_quantity * $presentationQuantity ?? 1);
                        $lot->save();
                    }
                }
            }
            else
            {

            if (isset($order_note_item->item->lots_group)) {
                    if(is_array($order_note_item->item->lots_group) && count($order_note_item->item->lots_group) > 0) {
                            $lots_group = $order_note_item->item->lots_group;

                            foreach ($lots_group as $item) {
                                $lot = ItemLotsGroup::query()->find($item->id);
                                $lot->quantity = $lot->quantity - $item->compromise_quantity;
                                $lot->save();
                            }
                    }
            }

            }
            // control de lotes



            foreach (FiscalNoteInventory::selectedSerials((array) ($item->lots ?? [])) as $serial) {
                $record = ItemLot::findOrFail($serial->id);
                $record->has_sale = true;
                $record->save();
            }



        });
    }


    /**
     * Se dispara cuando se borra un item de pedido
     */
    private function order_note_item_delete() {

        OrderNoteItem::deleted(function (OrderNoteItem $order_note_item) {
            if ($this->hasNoServiceInventory($order_note_item)) return;
            if (in_array((string)$order_note_item->order_note->state_type_id,['09','11'],true)) return;
            if (\App\Services\Fiscal\OrderPackInventory::apply($order_note_item,$order_note_item->exactValue('quantity'))) return;
            if (\App\Services\Fiscal\OrderTrackedInventory::apply($order_note_item,1)) return;
            $order=$order_note_item->order_note;
            $moves=!$order->sale_note_id && !$order->order_note_id && !$order->dispatch_id;
            if (!$moves && $order->dispatch) $moves=!$order->dispatch->discountsPhysicalStock();
            if (!$moves) return;



            // dd($order_note_item);
            $presentationQuantity = (!empty($order_note_item->item->presentation)) ? $order_note_item->item->presentation->quantity_unit : 1;

            // $warehouse = $this->findWarehouse($order_note_item->order_note->establishment_id);
            $recordedWarehouse = $order_note_item->calculation_snapshot['inputs']['order_inventory_warehouse_id'] ?? null;
            $warehouse = $recordedWarehouse ? $this->findWarehouseById($recordedWarehouse)
                : (($order_note_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($order_note_item->warehouse_id)->establishment_id) : $this->findWarehouse($order_note_item->order_note->establishment_id));

            $this->createInventoryKardex($order_note_item->order_note, $order_note_item->item_id , $order_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity)), $warehouse->id);

            $this->updateStock($order_note_item->item_id, $order_note_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity)), $warehouse->id);

            // Los lotes registrados ya se compensaron en OrderTrackedInventory.
            if ($recordedWarehouse !== null) return;

            if (isset($order_note_item->item->lots_group)) {
                if(is_array($order_note_item->item->lots_group) && count($order_note_item->item->lots_group) > 0) {
                        $lots_group = $order_note_item->item->lots_group;

                        foreach ($lots_group as $item) {
                            $lot = ItemLotsGroup::query()->find($item->id);
                            $lot->quantity = $lot->quantity + $item->compromise_quantity;
                            $lot->save();
                        }
                }
            }


        });




    }

    /**
     * Se dispara cuando se borra un item de compra
     */
    private function purchase_item_delete()
    {
        PurchaseItem::deleted(function (PurchaseItem $purchase_item) {
            if ($this->hasNoServiceInventory($purchase_item)) return;
            if ((string)$purchase_item->purchase->state_type_id === '11') return;


            $presentationQuantity = (!empty($purchase_item->item->presentation)) ? $purchase_item->item->presentation->quantity_unit : 1;

            $warehouse = ($purchase_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id) : $this->findWarehouse();

            $this->verifyHasSaleLots($purchase_item);
            $this->verifyHasSaleLotsGroup($purchase_item);

            $this->deleteItemSeriesAndGroup($purchase_item);

            $original=$purchase_item->calculation_snapshot['inputs']['purchase_warehouse_inventory'] ?? null;
            $warehouseId=$original['warehouse_id'] ?? $warehouse->id;
            $quantity=$original ? ExactDecimal::of($original['quantity']) : $purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity));
            $this->createInventoryKardex($purchase_item->purchase, $purchase_item->item_id, $quantity->negated(), $warehouseId);
            // $this->updateStock($purchase_item->item_id, $purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);
            $this->updateStockPurchase($purchase_item->item_id, $quantity->negated(), $warehouseId);

        });
    }

    /**
     * Se dispara cuando se borra un item de compra
     */
    private function purchase_item_settlement_delete()
    {
        PurchaseSettlementItem::deleted(function (PurchaseSettlementItem $purchase_item) {
            if ($this->hasNoServiceInventory($purchase_item)) return;


            $presentationQuantity = (!empty($purchase_item->item->presentation)) ? $purchase_item->item->presentation->quantity_unit : 1;

            $warehouse = ($purchase_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($purchase_item->warehouse_id)->establishment_id) : $this->findWarehouse();

            $this->verifyHasSaleLots($purchase_item);
            $this->verifyHasSaleLotsGroup($purchase_item);

            $this->deleteItemSeriesAndGroup($purchase_item);

            $original=$purchase_item->calculation_snapshot['inputs']['purchase_warehouse_inventory'] ?? null;
            $warehouseId=$original['warehouse_id'] ?? $warehouse->id;
            $quantity=$original ? ExactDecimal::of($original['quantity']) : $purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity));
            $this->createInventoryKardex($purchase_item->purchase_settlement, $purchase_item->item_id, $quantity->negated(), $warehouseId);
            // $this->updateStock($purchase_item->item_id, $purchase_item->exactValue('quantity')->multipliedBy(ExactDecimal::of($presentationQuantity))->negated(), $warehouse->id);
            $this->updateStockPurchase($purchase_item->item_id, $quantity->negated(), $warehouseId);

        });
    }

    /**
     * Se dispara cuando se borra un item de lote de item
     */
    private function item_lot_delete()
    {
        /*ItemLot::deleted(function(ItemLot $item_lot) {

            if((bool)$item_lot->has_sale)
            {
                throw new Exception("La serie {$item_lot->series} ha sido vendida!");
            }
        });*/
    }


    /**
     * Se dispara cuando se genera una devolucion
     */
    private function devolution() {

        DevolutionItem::created(function(DevolutionItem $devolution_item) {

            $devolution = $devolution_item->devolution;

            $warehouse = $this->findWarehouse($devolution_item->devolution->establishment_id);

            //$this->createInventory($devolution_item->item_id, $factor * $devolution_item->quantity, $warehouse->id);
            $this->createInventoryKardex($devolution_item->devolution, $devolution_item->item_id, -$devolution_item->quantity, $warehouse->id);

            $this->updateStock($devolution_item->item_id, -$devolution_item->quantity, $warehouse->id);

            // Lotes de vencimiento (cabecera)
            $lots_group = $devolution_item->item->IdLoteSelected
                ?? $devolution_item->item->lots_group_selected
                ?? $devolution_item->item->lots_group
                ?? null;

            if (!is_null($lots_group) && $lots_group !== '') {
                if (is_string($lots_group)) {
                    $lots_group = json_decode($lots_group, true);
                }

                if (is_array($lots_group)) {
                    foreach ($lots_group as $item) {
                        $item = (array) $item;
                        if (empty($item['id'])) {
                            continue;
                        }
                        $lot = ItemLotsGroup::find($item['id']);
                        if (!$lot) {
                            continue;
                        }
                        $qty = isset($item['compromise_quantity'])
                            ? (float) $item['compromise_quantity']
                            : (float) $devolution_item->quantity;
                        $lot->quantity = $lot->quantity - $qty;
                        $lot->save();
                    }
                } elseif (is_numeric($lots_group)) {
                    $lot = ItemLotsGroup::find($lots_group);
                    if ($lot) {
                        $lot->quantity = $lot->quantity - $devolution_item->quantity;
                        $lot->save();
                    }
                }
            }

            // Series: marcar como inactivas las seleccionadas en la devolución
            $series = $devolution_item->item->lots
                ?? $devolution_item->item->lots_selected
                ?? [];

            foreach ((array) $series as $it) {
                $it = (object) $it;
                // En el selector de series, las elegidas llegan con has_sale = true
                if (empty($it->id) || empty($it->has_sale)) {
                    continue;
                }
                $r = ItemLot::find($it->id);
                if (!$r) {
                    continue;
                }
                $r->has_sale = true;
                $r->state = 'Inactivo';
                $r->save();
            }

        });
    }


    /**
     * Se dispara cuando se genera un despacho
     */
    private function dispatch() {

        DispatchItem::created(function(DispatchItem $dispatch_item) {
            $dispatch = $dispatch_item->dispatch;
            if ($dispatch->creation_key) { \App\Services\Fiscal\DeliveryOrderInventory::apply($dispatch_item,-1); return; }
            if($dispatch->document_type_id === '09') {
                if($dispatch->discountsPhysicalStock()){

                    $warehouse = $this->findWarehouse($dispatch->establishment_id);

                    if(!$dispatch->reference_sale_note_id && !$dispatch->reference_order_note_id && !$dispatch->reference_document_id){
                        $item=Item::whereKey($dispatch_item->item_id)->lockForUpdate()->firstOrFail();
                        $quantity=$dispatch_item->exactValue('quantity');
                        $stock=ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',$warehouse->id)->lockForUpdate()->first();
                        if ($quantity->isLessThanOrEqualTo(0) || !$stock || $stock->exactValue('stock')->isLessThan($quantity)) {
                            \App\Services\Fiscal\FiscalAmounts::error('items', 'Existencias insuficientes en el almacén de la sucursal.');
                        }
                        if ($item->series_enabled) \App\Services\Fiscal\FiscalAmounts::error('items', 'Seleccione seriales mediante la orden de entrega.');
                        $lotId=$dispatch_item->item->IdLoteSelected ?? null;
                        if ($item->lots_enabled && !$lotId) \App\Services\Fiscal\FiscalAmounts::error('items', 'Seleccione el lote del producto.');
                        $lot=null;
                        if ($lotId) {
                            $lot=ItemLotsGroup::where('item_id',$item->id)->lockForUpdate()->find($lotId);
                            if (!$lot || $lot->exactValue('quantity')->isLessThan($quantity)) {
                                \App\Services\Fiscal\FiscalAmounts::error('items', 'El lote no pertenece al producto o no tiene existencias suficientes.');
                            }
                        }
                        $this->createInventoryKardex($dispatch, $item->id, $quantity->negated(), $warehouse->id);
                        $this->updateStock($item->id, $quantity->negated(), $warehouse->id);
                        if ($lot) { $lot->quantity=$lot->exactValue('quantity')->minus($quantity); $lot->save(); }
                        \App\Services\Fiscal\DeliveryOrderGlobalInventory::apply($dispatch_item,-1);
                    }
                }
            }
        });
    }


    /**
     * Se dispara cuando se borra un document item
     */
    private function document_item_delete() {

        DocumentItem::deleted(function(DocumentItem $document_item) {
            if (\App\Services\Fiscal\FiscalNote::ordinary($document_item->document)) { \App\Services\Fiscal\FiscalNoteInventory::apply($document_item, -1); $this->syncRestaurantStock($document_item->item_id); return; }
            if ($document_item->relationLoaded('invoice_inventory_reversed_for_edit') && $document_item->getRelation('invoice_inventory_reversed_for_edit') === true) {
                $this->syncRestaurantStock($document_item->item_id);
                return;
            }

            if ($this->hasNoServiceInventory($document_item)) return;

            if(!$document_item->item->is_set){

                $this->processIndividualDocumentItem($document_item);
                $this->moveInvoiceModifiers($document_item, 1);

            }else{

                $this->voidedDocumentItemSet($document_item);

            }

        });

    }

    /**
     * Procesa los productos fusionados (merged_products) en nota de venta
     * Lee los datos desde sale_note_item->item (campo JSON)
     * Excluye el producto raíz para evitar doble descuento
     *
     * @param SaleNoteItem $sale_note_item
     */
    private function processMergedProductsSaleNote(SaleNoteItem $sale_note_item): array {

        // Obtener item del sale_note_item (campo JSON)
        $item_data = $sale_note_item->item;

        // Si no está fusionado, salir
        if (!isset($item_data->esFusionado) || !$item_data->esFusionado) {
            return [];
        }

        // Verificar que tenga productosFusionados
        if (!isset($item_data->productosFusionados) || empty($item_data->productosFusionados)) {
            return [];
        }

        $sale_note = $sale_note_item->sale_note;
        $warehouse = ($sale_note_item->warehouse_id)
            ? $this->findWarehouse($this->findWarehouseById($sale_note_item->warehouse_id)->establishment_id)
            : $this->findWarehouse($sale_note->establishment_id);

        // Procesar cada producto fusionado
        $movements = [];
        foreach ($item_data->productosFusionados as $merged_product) {

            $merged_item_id = $merged_product->id ?? null;
            $merged_quantity = $merged_product->quantity ?? 1;

            // Si no tiene id, no podemos procesar
            if (!$merged_item_id) {
                continue;
            }

            // Saltar el producto principal para evitar doble descuento
            if ($merged_item_id == $sale_note_item->item_id) {
                continue;
            }

            $quantity = ExactDecimal::of(ExactDecimal::input($merged_quantity, 'items.productosFusionados.quantity'));
            if (!$quantity->isPositive()) {
                \App\Services\Fiscal\FiscalAmounts::error('items', 'La cantidad del producto fusionado debe ser positiva.');
            }
            $movement = $quantity->negated();

            // Crear registro en kardex
            $movements[] = $this->createInventoryKardexSaleNote(
                $sale_note,
                $merged_item_id,
                $movement,
                $warehouse->id,
                $sale_note_item->id
            )->id;

            // Actualizar stock si no viene de order_note
            if (!$sale_note->order_note_id) {
                $this->updateStock($merged_item_id, $movement, $warehouse->id);
            }
        }
        return $movements;
    }

    /**
     * Procesa los modificadores aplicados (modifiersApplied) en nota de venta
     * Lee los datos desde sale_note_item->item (campo JSON)
     * Solo procesa modificadores con type: "item" y item_id
     *
     * @param SaleNoteItem $sale_note_item
     */
    private function processModifiersAppliedSaleNote(SaleNoteItem $sale_note_item): array {

        // Obtener item del sale_note_item (campo JSON)
        $item_data = $sale_note_item->item;

        // Verificar que tenga modifiersApplied
        if (!isset($item_data->modifiersApplied) || empty($item_data->modifiersApplied)) {
            return [];
        }

        $sale_note = $sale_note_item->sale_note;
        $warehouse = ($sale_note_item->warehouse_id)
            ? $this->findWarehouse($this->findWarehouseById($sale_note_item->warehouse_id)->establishment_id)
            : $this->findWarehouse($sale_note->establishment_id);

        // Procesar cada grupo de modificadores
        $movements = [];
        foreach ($item_data->modifiersApplied as $group) {
            if (!isset($group->items) || empty($group->items)) {
                continue;
            }

            // Procesar cada item del grupo
            foreach ($group->items as $modifierItem) {
                // Solo procesar si es de tipo "item" y tiene item_id
                if (!isset($modifierItem->type) || $modifierItem->type !== 'item') {
                    continue;
                }

                if (!isset($modifierItem->item_id) || !$modifierItem->item_id) {
                    continue;
                }

                $modifier_item_id = $modifierItem->item_id;
                // La conversión recupera esta misma cantidad decimal de la partida.
                $movement = ExactDecimal::of($sale_note_item->quantity)->negated();

                // Crear registro en kardex
                $movements[] = $this->createInventoryKardexSaleNote(
                    $sale_note,
                    $modifier_item_id,
                    $movement,
                    $warehouse->id,
                    $sale_note_item->id
                )->id;

                // Actualizar stock si no viene de order_note
                if (!$sale_note->order_note_id) {
                    $this->updateStock($modifier_item_id, $movement, $warehouse->id);
                }

                // Sincronizar stock del restaurante
                $this->syncRestaurantStock($modifier_item_id);
            }
        }
        return $movements;
    }

    /**
     *

    /**
     * Sincroniza el stock del restaurante cuando se actualiza el inventario
     *
     * @param int $item_id
     * @return void
     */
    public function syncRestaurantStock($item_id)
    {
        try {
            $stockService = app(\Modules\Restaurant\Services\RestaurantStockService::class);
            $stockService->calculateAndUpdateStock($item_id);

            // Notificar al POS del restaurante vía WebSocket que el stock cambió.
            // Este es el chokepoint de TODOS los movimientos de inventario (venta,
            // nota de venta, compra, devolución, modificadores), así que cubre todos
            // los caminos de venta. Se limita a ítems del restaurante para no publicar
            // en cada venta ajena al módulo.
            $item = Item::find($item_id);
            if ($item && $item->apply_restaurant) {
                $fqdn = app(\App\Tenancy\Contracts\CurrentHostname::class)?->fqdn ?? 'local';
                $data = app(\Modules\Restaurant\Http\Controllers\RestaurantController::class)
                    ->getStockStatus()['data'] ?? [];
                app(\App\Services\CentrifugoService::class)->publish("restaurant:{$fqdn}", [
                    'event'   => 'stock-updated',
                    'payload' => $data,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning("No se pudo sincronizar stock del restaurante para item {$item_id}: " . $e->getMessage());
        }
    }

}
