<?php

namespace Modules\Inventory\Providers;

use Modules\Order\Models\OrderNote;
use App\Models\Tenant\Document;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Traits\InventoryTrait;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Note;
use App\Models\Tenant\VoidedDocument;
use Modules\Inventory\Models\InventoryTransfer;

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
            if(in_array($document['document_type_id'], ['01', '03', '08'], true))
            {
                if (!$document->wasChanged('state_type_id')) return;

                if(in_array($document['state_type_id'], [ '09', '11' ], true)){
                    // $warehouse = $this->findWarehouse($document['establishment_id']);

                    foreach ($document['items'] as $detail) {
                        // dd($detail['item']->presentation);

                        if(!$detail->item->is_set){

                            $warehouse = ($detail->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($detail->warehouse_id)->establishment_id) : $this->findWarehouse($document['establishment_id']);

                            $presentationQuantity = (!empty($detail['item']->presentation)) ? $detail['item']->presentation->quantity_unit : 1;
                            $restoreQty = $detail['quantity'] * $presentationQuantity;

                            // Guía relacionada ya anulada: el stock ya se reingresó al anular la guía
                            if ($this->documentRelatedDispatchAlreadyRestoredStock($detail->document)) {
                                continue;
                            }

                            $this->createInventoryKardex($document, $detail['item_id'], $restoreQty, $warehouse->id);

                            if ($this->shouldRestoreStockOnDocumentVoid($detail->document)) {
                                $this->updateStock($detail['item_id'], $restoreQty, $warehouse->id);
                                $this->updateDataLots($detail);
                            }

                        }
                        else{
                            // Guía ya anulada: no reingresar de nuevo vía sets
                            if ($this->documentRelatedDispatchAlreadyRestoredStock($detail->document)) {
                                continue;
                            }
                            $this->voidedDocumentItemSet($detail);

                        }

                    }

                    $this->voidedWasDeductedPrepayment($document);

                }
            }
        });
    }

    /**
     * Indica si al anular el CPE debe reingresar stock físico.
     * - Sin guía / NV / pedido: sí
     * - Con guía que NO descontó: sí
     * - Con guía que SÍ descontó y aún no está anulada: sí (el descuento lo hizo la guía;
     *   al anular el CPE se devuelve una sola vez; la guía ya no debe volver a devolver)
     * - Con guía que SÍ descontó y ya está anulada: no (ya reingresó la guía)
     */
    private function shouldRestoreStockOnDocumentVoid(Document $document): bool
    {
        if ($document->sale_note_id || $document->order_note_id || $document->sale_notes_relateds) {
            return false;
        }

        if (!$document->dispatch_id) {
            return true;
        }

        $dispatch = $document->dispatch;
        if (!$dispatch) {
            return true;
        }

        $transferReason = $dispatch->transfer_reason_type;
        if (!$transferReason || !$transferReason->discount_stock) {
            return true;
        }

        // Guía descontó: solo reingresar si la guía todavía no fue anulada
        return !in_array($dispatch->state_type_id, ['09', '11'], true);
    }

    /**
     * El CPE no debe tocar inventario si su guía relacionada ya reingresó stock.
     */
    private function documentRelatedDispatchAlreadyRestoredStock(Document $document): bool
    {
        if (!$document->dispatch_id) {
            return false;
        }

        $dispatch = $document->dispatch;
        if (!$dispatch || !$dispatch->transfer_reason_type || !$dispatch->transfer_reason_type->discount_stock) {
            return false;
        }

        return in_array($dispatch->state_type_id, ['09', '11'], true);
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
                // si es nota credito tipo 13, no se asocia a inventario
                if($document->isCreditNoteAndType13()) return;

                foreach ($document->items as $document_item)
                {
                    if(!$document_item->item->is_set)
                    {
                        $warehouse = ($document_item->warehouse_id) ? $this->findWarehouse($this->findWarehouseById($document_item->warehouse_id)->establishment_id) : $this->findWarehouse($document->establishment_id);
                        $presentation_quantity = (!empty($document_item->item->presentation)) ? $document_item->item->presentation->quantity_unit : 1;

                        $factor = -1;
                        $calculate_quantity = $factor * ($document_item->quantity * $presentation_quantity);

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

        if($document->prepayments){

            foreach ($document->prepayments as $row) {
                $fullnumber = explode('-', $row->number);
                $series = $fullnumber[0];
                $number = $fullnumber[1];

                $doc = Document::where([['series',$series],['number',$number]])->first();
                if($doc){
                    $doc->was_deducted_prepayment = false;
                    $doc->pending_amount_prepayment += $row->total;
                    $doc->save();
                }
            }
        }

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
                    if($affected_document->prepayments && $note->document->total == $affected_document->total)
                    {
                        foreach($affected_document->prepayments as $row)
                        {
                            $number_full = explode('-', $row->number);
                            $find_document = Document::whereFilterWithOutRelations()->where([['series', $number_full[0]],['number', $number_full[1]]])->first();

                            if($find_document)
                            {
                                $find_document->pending_amount_prepayment += $row->total;

                                if($find_document->pending_amount_prepayment <= $find_document->total)
                                {
                                    $find_document->was_deducted_prepayment = false;
                                    $find_document->save();
                                }
                            }
                        }
                    }

                }
            }

        });

    }





    private function voided_order_note(){

        OrderNote::updated(function ($order_note) {

            if(in_array($order_note->state_type_id, [ '09', '11' ], true)){

                $warehouse = $this->findWarehouse($order_note->establishment_id);

                foreach ($order_note->items as $order_note_item) {

                    $presentationQuantity = (!empty($order_note_item->item->presentation)) ? $order_note_item->item->presentation->quantity_unit : 1;

                    $this->createInventoryKardex($order_note, $order_note_item->item_id, $order_note_item->quantity * $presentationQuantity, $warehouse->id);
                    $this->updateStock($order_note_item->item_id, $order_note_item->quantity * $presentationQuantity, $warehouse->id);

                }

            }

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

            // dd($dispatch, $dispatch['state_type_id'],$dispatch->state_type_id);
            if($dispatch->transfer_reason_type == null) {
                $dispatch = Dispatch::where('id', $dispatch->id)->first();
            }
            if(isset($dispatch->transfer_reason_type->discount_stock) && $dispatch->transfer_reason_type->discount_stock){

                    // CPE generado desde esta guía ya anulado: el stock ya se reingresó al anular el CPE
                    if ($this->dispatchRelatedDocumentAlreadyRestoredStock($dispatch)) {
                        return;
                    }

                    // Motivo 04: revertir el traslado inventario (destino → origen)
                    if ($dispatch->transfer_reason_type_id === '04') {
                        $this->reverseInventoryTransferFromDispatch($dispatch);
                        return;
                    }

                    $warehouse = $this->findWarehouse($dispatch->establishment_id);

                    foreach ($dispatch->items as $detail) {

                        $this->createInventoryKardex($dispatch, $detail->item_id, $detail->quantity, $warehouse->id);

                        if(!$detail->dispatch->reference_sale_note_id && !$detail->dispatch->reference_order_note_id && !$detail->dispatch->reference_document_id){
                            $this->updateStock($detail->item_id, $detail->quantity, $warehouse->id);
                        }

                        $this->updateDataLots($detail);
                    }
            }
        });
    }

    /**
     * Revierte el InventoryTransfer creado por la guía (motivo 04).
     */
    private function reverseInventoryTransferFromDispatch(Dispatch $dispatch): void
    {
        $transfer = InventoryTransfer::query()->where('dispatch_id', $dispatch->id)->first();
        if (!$transfer) {
            // Fallback: descuento simple sin traslado registrado
            $warehouse = $this->findWarehouse($dispatch->establishment_id);
            foreach ($dispatch->items as $detail) {
                $this->createInventoryKardex($dispatch, $detail->item_id, $detail->quantity, $warehouse->id);
                if (!$detail->dispatch->reference_sale_note_id && !$detail->dispatch->reference_order_note_id && !$detail->dispatch->reference_document_id) {
                    $this->updateStock($detail->item_id, $detail->quantity, $warehouse->id);
                }
                $this->updateDataLots($detail);
            }
            return;
        }

        foreach ($transfer->inventories as $inventory) {
            // Quitar del destino
            $this->createInventoryKardex($dispatch, $inventory->item_id, -1 * $inventory->quantity, $inventory->warehouse_destination_id);
            $this->updateStock($inventory->item_id, -1 * $inventory->quantity, $inventory->warehouse_destination_id);
            // Devolver al origen
            $this->createInventoryKardex($dispatch, $inventory->item_id, $inventory->quantity, $inventory->warehouse_id);
            $this->updateStock($inventory->item_id, $inventory->quantity, $inventory->warehouse_id);
        }

        foreach ($dispatch->items as $detail) {
            $this->updateDataLots($detail);
        }
    }

    /**
     * Evita doble reingreso: si el CPE ligado a la guía (documents.dispatch_id)
     * ya está anulado/rechazado, el stock ya se devolvió ahí.
     */
    private function dispatchRelatedDocumentAlreadyRestoredStock(Dispatch $dispatch): bool
    {
        return Document::where('dispatch_id', $dispatch->id)
            ->whereIn('state_type_id', ['09', '11'])
            ->exists();
    }


}
