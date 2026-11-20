<?php

namespace Modules\Inventory\Providers;

use Modules\Order\Models\OrderNote;
use App\Models\Tenant\Document;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Traits\InventoryTrait;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Note;
use App\Models\Tenant\VoidedDocument;

class InventoryVoidedServiceProvider extends ServiceProvider
{
    use InventoryTrait;

    public function register()
    {
    }

    public function boot()
    {
        $this->voided();
        $this->voidedCreditNote();
        $this->voided_order_note();
        $this->voided_dispatch();
        $this->verifyRelatedPrepaymentDocument();
    }

    private function voided()
    {
        //Revisar los tipos de documentos, ello varia el control de stock en las anulaciones.
        Document::updated(function ($document) {
            // if($document['document_type_id'] == '01' || $document['document_type_id'] == '03'){
            if(in_array($document['document_type_id'], ['01', '08'], true))
            {
                if (!$document->wasChanged('state_type_id')) return;
                if (\App\Services\Fiscal\FiscalNote::ordinary($document)) return;

                if(in_array($document['state_type_id'], [ '09', '11' ], true)){
                    // $warehouse = $this->findWarehouse($document['establishment_id']);

                    foreach ($document['items'] as $detail) {
                        if (in_array($document->getOriginal('state_type_id'),['09','11'],true)) continue;
                        if (($detail->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;
                        if (\App\Services\Fiscal\DeliveryOrderInvoiceInventory::apply($detail,1)) continue;
                        // dd($detail['item']->presentation);

                        if(!$detail->item->is_set){

                            $warehouse = ($detail->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($detail->warehouse_id)->establishment_id) : $this->findWarehouse($document['establishment_id']);

                            $presentationQuantity = \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($detail->item);
                            $quantity=$detail->exactValue('quantity')->multipliedBy(\App\Support\Exact\Decimal::of($presentationQuantity));

                            $this->createInventoryKardex($document, $detail['item_id'], $quantity, $warehouse->id);

                            if(!$detail->document->sale_note_id && !$detail->document->order_note_id && !$detail->document->dispatch_id && !$detail->document->sale_notes_relateds){

                                $this->updateStock($detail['item_id'], $quantity, $warehouse->id);

                            }else{

                                if($detail->document->dispatch){

                                    if(!$detail->document->dispatch->discountsPhysicalStock()){
                                        // $warehouse = $this->findWarehouse($document['establishment_id']);
                                        $this->updateStock($detail['item_id'], $quantity, $warehouse->id);
                                    }
                                }
                            }

                            if (!$detail->document->order_note_id) $this->updateDataLots($detail);
                            $this->moveInvoiceModifiers($detail, 1);

                        }
                        else{

                            $this->voidedDocumentItemSet($detail);

                        }

                    }

                    if (!in_array($document->getOriginal('state_type_id'), ['09', '11'], true)) {
                        $this->voidedWasDeductedPrepayment($document);
                    }

                }
            }
        });
    }


    /**
     *
     * Flujo para nota credito cuando se anula o rechaza
     *
     * @return void
     */
    public function voidedCreditNote()
    {
        Document::updated(function ($document) {

            if($document->isCreditNote() && $document->isVoidedOrRejected())
            {
                if (!$document->wasChanged('state_type_id')
                    || in_array($document->getOriginal('state_type_id'), Document::VOIDED_REJECTED_IDS, true)) return;
                // si es nota credito tipo 13, no se asocia a inventario
                if($document->isCreditNoteAndType13()) return;
                if (\App\Services\Fiscal\FiscalNote::ordinary($document)) return;

                foreach ($document->items as $document_item)
                {
                    if (($document_item->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;
                    if(!$document_item->item->is_set)
                    {
                        $warehouse = ($document_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($document_item->warehouse_id)->establishment_id) : $this->findWarehouse($document->establishment_id);
                        $presentation_quantity = \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($document_item->item);

                        $calculate_quantity = $document_item->exactValue('quantity')
                            ->multipliedBy($presentation_quantity)->negated();

                        $this->createInventoryKardex($document, $document_item->item_id, $calculate_quantity, $warehouse->id);

                        if(!$document_item->document->sale_note_id && !$document_item->document->order_note_id && !$document_item->document->dispatch_id && !$document_item->document->sale_notes_relateds)
                        {
                            $this->updateStock($document_item->item_id, $calculate_quantity, $warehouse->id);
                        }
                    }
                }
            }
        });
    }



    private function voidedWasDeductedPrepayment($document)
    {
        \App\Services\Fiscal\PrepaymentBalance::restore($document);
    }

    /**
     *
     * Verificar documento relacionado a la nota de credito para liberar el monto del anticipo informado
     *
     * @return void
     */
    private function verifyRelatedPrepaymentDocument()
    {

        Note::created(function ($note) {

            //si es nc y tiene tipo de nc igual a "Anulación de la operación"
            if($note->document->document_type_id === '07' && ($note->note_credit_type_id === '01' || $note->note_credit_type_id === '06' ))
            {
                $affected_document = $note->affected_document;

                if($affected_document)
                {
                    //si el cpe relacionado tiene anticipos y el total de la nota es igual al del cpe afectado
                    if($affected_document->prepayments && $note->document->exactValue('total')->isEqualTo($affected_document->exactValue('total')))
                    {
                        \App\Services\Fiscal\PrepaymentBalance::restore($affected_document, true);
                    }

                }
            }

        });

    }





    private function voided_order_note(){

        OrderNote::updated(function ($order_note) {
            // ######## INICIO ANULACIÓN EXACTA DE PEDIDO ########
            if (!$order_note->wasChanged('state_type_id') || in_array((string)$order_note->getOriginal('state_type_id'),['09','11'],true)) return;
            if(in_array($order_note->state_type_id, [ '09', '11' ], true)){
                $moves=!$order_note->sale_note_id && !$order_note->order_note_id && !$order_note->dispatch_id;
                if (!$moves && $order_note->dispatch) $moves=!$order_note->dispatch->discountsPhysicalStock();
                if (!$moves) return;

                foreach ($order_note->items as $order_note_item) {
                    if (($order_note_item->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;
                    if (\App\Services\Fiscal\OrderPackInventory::apply($order_note_item,$order_note_item->exactValue('quantity'))) continue;
                    if (\App\Services\Fiscal\OrderTrackedInventory::apply($order_note_item,1)) continue;
                    $recordedWarehouse = $order_note_item->calculation_snapshot['inputs']['order_inventory_warehouse_id'] ?? null;
                    $warehouse = $recordedWarehouse ? $this->findWarehouseById($recordedWarehouse) : $this->findWarehouse($order_note->establishment_id);

                    $presentationQuantity = (!empty($order_note_item->item->presentation)) ? $order_note_item->item->presentation->quantity_unit : 1;

                    $quantity=$order_note_item->exactValue('quantity')->multipliedBy(\App\Support\Exact\Decimal::of($presentationQuantity));
                    $this->createInventoryKardex($order_note, $order_note_item->item_id, $quantity, $warehouse->id);
                    $this->updateStock($order_note_item->item_id, $quantity, $warehouse->id);

                }

            }
            // ######## FIN ANULACIÓN EXACTA DE PEDIDO ########

        });

    }



    private function voided_dispatch()
    {
        Dispatch::updated(function ($dispatch) {

            if (!$dispatch->wasChanged('state_type_id')) {
                return;
            }

            if (!in_array($dispatch->state_type_id, ['09', '11'], true)) {
                return;
            }

            if (in_array($dispatch->getOriginal('state_type_id'),['09','11'],true)) return;
            if ($dispatch->creation_key) { foreach ($dispatch->items as $line) \App\Services\Fiscal\DeliveryOrderInventory::apply($line,1); return; }
            // dd($dispatch, $dispatch['state_type_id'],$dispatch->state_type_id);
            if($dispatch->discountsPhysicalStock()){

                    $warehouse = $this->findWarehouse($dispatch->establishment_id);

                    foreach ($dispatch->items as $detail) {

                        $this->createInventoryKardex($dispatch, $detail->item_id, $detail->quantity, $warehouse->id);

                        $this->updateStock($detail->item_id, $detail->quantity, $warehouse->id);

                        $this->updateDataLots($detail);
                        \App\Services\Fiscal\DeliveryOrderGlobalInventory::apply($detail,1);
                    }
            }
        });
    }


}
