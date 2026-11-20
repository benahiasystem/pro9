<?php

namespace App\Providers;

use App\Models\Tenant\Item;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Document;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\PurchaseSettlementItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Kardex;
use Illuminate\Support\ServiceProvider;
use App\Traits\KardexTrait;


/**
 * Se debe tener en cuenta este provider para llevar el control de Kardex
 */
class KardexServiceProvider extends ServiceProvider
{
    use KardexTrait;

    public function boot()
    {
        $this->save_item();
        $this->sale();
        $this->purchase();
        $this->purchase_settlement();
        $this->reversePurchaseOnDelete();
        $this->sale_note();

    }

    public function register()
    {

    }

    /**
     * Cuando se realiza una venta
     */
    private function sale()
    {
        DocumentItem::created(function (DocumentItem $document_item) {
            if (($document_item->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) return;
            $document = Document::whereIn('document_type_id',['01','03'])->find($document_item->document_id);
            if($document){
                if (\App\Services\Fiscal\OrderSaleNoteLegacyInventory::invoiceInheritsMovement($document)) return;

                $quantity=isset($document_item->item->dispatch_global_inventory_quantity)
                    ? \App\Support\Exact\Decimal::of($document_item->item->dispatch_global_inventory_quantity)
                    : $document_item->exactValue('quantity')->multipliedBy(
                        !empty($document_item->item->is_set) ? 1 : \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($document_item->item));
                if ($quantity->isZero()) return;
                $kardex = $this->saveKardex('sale', $document_item->item_id, $document_item->document_id, $quantity, 'document');

                if($document->state_type_id != 11){

                    $this->updateStock($document_item->item_id, $quantity, true);
                    \App\Services\Fiscal\InvoiceGlobalInventory::remember($document_item,$kardex,$quantity);

                }

            }
        });
    }

    /**
     *Cuando se realiza una compra
     */
    private function purchase()
    {
        PurchaseItem::created(function (PurchaseItem $purchase_item) {
            if (($purchase_item->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) return;

            $quantity=$purchase_item->exactValue('quantity');
            $kardex = $this->saveKardex('purchase', $purchase_item->item_id, $purchase_item->purchase_id, $quantity, 'purchase');
            $this->updateStock($purchase_item->item_id, $quantity, false);
            $this->rememberPurchaseMovement($purchase_item, $kardex, $quantity);

        });
    }

    /**
     *Cuando se realiza una compra
     */
    private function purchase_settlement()
    {
        PurchaseSettlementItem::created(function (PurchaseSettlementItem $purchase_item_settlement) {
            if (($purchase_item_settlement->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) return;

            $quantity=$purchase_item_settlement->exactValue('quantity');
            $kardex = $this->saveKardex('purchase', $purchase_item_settlement->item_id, $purchase_item_settlement->purchase_settlement_id, $quantity, 'purchase_settlement');
            $this->updateStock($purchase_item_settlement->item_id, $quantity, false);
            $this->rememberPurchaseMovement($purchase_item_settlement, $kardex, $quantity);

        });
    }

    // ######## INICIO REVERSIÓN DE KARDEX DE COMPRAS ########
    private function rememberPurchaseMovement($line, Kardex $kardex, $quantity): void
    {
        if (!\App\Support\Exact\Persistence::enabled($line)) return;
        $snapshot=$line->calculation_snapshot;
        $snapshot['inputs']['legacy_purchase_inventory']=['kardex_id'=>$kardex->id,
            'quantity'=>\App\Support\Exact\Decimal::fraction($quantity)];
        $line->calculation_snapshot=$snapshot;
        $line->getConnection()->table($line->getTable())->where($line->getKeyName(),$line->getKey())
            ->update(['calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    }

    private function reversePurchaseOnDelete(): void
    {
        foreach ([PurchaseItem::class, PurchaseSettlementItem::class] as $class) {
            $class::deleted(function ($line) {
                $movement=$line->calculation_snapshot['inputs']['legacy_purchase_inventory'] ?? null;
                if (!$movement) return;
                $relation=$line instanceof PurchaseItem ? 'purchase' : 'purchase_settlement';
                $parentId=$relation === 'purchase' ? $line->purchase_id : $line->purchase_settlement_id;
                $parent=$line->{$relation};
                if ($parent && (string)$parent->state_type_id === '11') return;
                $quantity=\App\Support\Exact\Decimal::of($movement['quantity']);
                $this->saveKardex('purchase',$line->item_id,$parentId,$quantity->negated(),$relation);
                $this->updateStock($line->item_id,$quantity,true);
            });
        }
    }
    // ######## FIN REVERSIÓN DE KARDEX DE COMPRAS ########

    /**
     * Cuando se realiza una nota de compra
     */
    private function sale_note()
    {
        SaleNoteItem::created(function (SaleNoteItem $sale_note_item) {
            if (($sale_note_item->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) return;
            if ($sale_note_item->sale_note->order_note_id) {
                (new \App\Services\Fiscal\OrderSaleNoteLegacyInventory())->record($sale_note_item);
                return;
            }
            $pack=Item::findOrFail($sale_note_item->item_id)->is_set;
            if (\App\Support\Exact\Persistence::enabled($sale_note_item) && (!$pack || isset($sale_note_item->calculation_snapshot['inputs']['sale_note_pack_inventory']))) {
                (new \App\Services\Fiscal\SaleNoteGlobalInventory())->record($sale_note_item,$pack);
                return;
            }

            $quantity=$sale_note_item->exactValue('quantity');
            $this->saveKardex('sale', $sale_note_item->item_id, $sale_note_item->sale_note_id, $quantity, 'sale_note');
            $this->updateStock($sale_note_item->item_id, $quantity, true);

        });
        SaleNoteItem::deleted(function (SaleNoteItem $line) {
            if (in_array((string)$line->sale_note->state_type_id,['09','11'],true)) return;
            if ($line->sale_note->order_note_id) (new \App\Services\Fiscal\OrderSaleNoteLegacyInventory())->reverse($line);
            else (new \App\Services\Fiscal\SaleNoteGlobalInventory())->reverse($line);
        });
    }

    /**
     * Cuando se guarda un item
     */
    private function save_item(){

        Item::created(function (Item $item) {

            $stock = ($item->stock) ? $item->stock : 0;
            $kardex = $this->saveKardex(null, $item->id, null, $stock, null);

        });

    }



}
