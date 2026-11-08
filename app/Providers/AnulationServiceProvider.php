<?php

namespace App\Providers;

use App\Models\Tenant\Item;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Purchase;

use App\Models\Tenant\Kardex;
use Illuminate\Support\ServiceProvider;
use App\Traits\KardexTrait;

class AnulationServiceProvider extends ServiceProvider
{
    use KardexTrait;

    public function register()
    {
    }

    public function boot()
    {
        $this->anulation();
        //$this->anulation_purchase();

    }


    private function anulation(){

        Document::updated(function ($document) {

            if($document['document_type_id'] == '01'){

                if($document['state_type_id'] == 11 && $document->wasChanged('state_type_id') && !in_array((string)$document->getOriginal('state_type_id'), ['09','11'], true)){
                    if (\App\Services\Fiscal\OrderSaleNoteLegacyInventory::invoiceInheritsMovement($document)) return;

                    foreach ($document['items'] as $detail) {

                        if (($detail->calculation_snapshot['inputs']['inventory_service_without_movement'] ?? false) === true) continue;

                        // $item = Item::find($detail['item_id']);
                        // $item->stock = $item->stock + $detail['quantity'];
                        // $item->save();

                        $quantity=isset($detail->item->dispatch_global_inventory_quantity)
                            ? \App\Support\Exact\Decimal::of($detail->item->dispatch_global_inventory_quantity)
                            : (isset($detail->calculation_snapshot['inputs']['invoice_global_inventory']['quantity'])
                                ? \App\Support\Exact\Decimal::of($detail->calculation_snapshot['inputs']['invoice_global_inventory']['quantity'])
                                : $detail->exactValue('quantity'));
                        if ($quantity->isZero()) continue;
                        $this->updateStock($detail['item_id'], $quantity, false);
                        $this->saveKardex('sale', $detail['item_id'], $document['id'], $quantity->negated(),'document');

                    }

                }
            }


        });

    }

    private function anulation_purchase(){

        Purchase::updated(function ($document) {

                if($document['state_type_id'] == 11){

                    foreach ($document['items'] as $detail) {

                        $this->updateStock($detail['item_id'], $detail['quantity'], true); //pongo true porque la compra se anula, entonces el stock disminuye

                       // $this->saveKardex('sale', $detail['item_id'], $document['id'], -$detail['quantity'],'document');

                    }

                }



        });

    }
}
