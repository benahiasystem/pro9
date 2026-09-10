<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DeleteTestDocumentsRequest;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\Kardex;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\Retention;
use App\Models\Tenant\Perception;
use App\Models\Tenant\Summary;
use App\Models\Tenant\Voided;
use Illuminate\Http\Request;
use App\Models\Tenant\Configuration;
use Modules\Expense\Models\Expense;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Models\Income;
use Modules\Purchase\Models\PurchaseQuotation;
use Modules\Order\Models\OrderNote;
use Modules\Order\Models\OrderForm;
use Modules\Inventory\Models\{
    ItemWarehouse,
    InventoryKardex,
    DevolutionItem
};
use Modules\Sale\Models\SaleOpportunity;
use Modules\Sale\Models\Contract;
use Modules\Purchase\Models\FixedAssetPurchase;
use App\Models\Tenant\{
    CashDocumentCredit,
    CashDocument,
    CashDocumentPayment,
    ItemMovement,
    Inventory,
    Item,
    ItemUnitType
};
use Modules\Payment\Models\PaymentLink;
use Modules\MercadoPago\Models\Transaction;
use Modules\Pos\Models\Tip;
use Modules\Production\Models\{
    Production,
    Mill,
    Packaging,
};
use Modules\Item\Models\{
    ItemLotsGroup,
    ItemLot
};
use Modules\Purchase\Models\WeightedAverageCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Hotel\Models\HotelRent;
use Modules\Hotel\Models\HotelRentItem;
use Modules\Hotel\Models\HotelRentItemPayment;
use Modules\Hotel\Models\HotelRentOrder;
use App\Models\Tenant\Dispatch;

class OptionController extends Controller
{

    protected $delete_quantity;

    public function create()
    {
        return view('tenant.options.form');
    }

    public function deleteDocuments(DeleteTestDocumentsRequest $request)
    {
        // ######## INICIO PC-17 ELIMINACIÓN SEGURA DE DOCUMENTOS DE PRUEBA ########
        return DB::connection('tenant')->transaction(function () {
            $this->delete_quantity = 0;

        Summary::where('fiscal_environment', 'demo')->delete();
        Voided::where('fiscal_environment', 'demo')->delete();

        $dispatches = Dispatch::where('fiscal_environment', 'demo')->get();
        $this->deleteInventoryKardex(Dispatch::class, $dispatches);

        $dispatches->each(function ($dispatch) {
            $dispatch->items()->delete();
            $dispatch->delete();
        });

        //Purchase
        $this->deleteInventoryKardex(Purchase::class);

        Purchase::where('fiscal_environment', 'demo')->delete();

        PurchaseOrder::where('fiscal_environment', 'demo')->delete();
        PurchaseQuotation::where('fiscal_environment', 'demo')->delete();

        $documents = Document::where('fiscal_environment', 'demo')->get();
        $quantity = $documents->count();

        // Los comprobantes de prueba se eliminan junto a sus relaciones de detalle.
        $this->delete_quantity += $quantity;
        $this->deleteRecordsCash(Document::class);
        $this->deleteDocumentRelations($documents);
        // Document::where('fiscal_environment', 'demo')->delete();

        $this->update_quantity_documents($quantity);

        Retention::where('fiscal_environment', 'demo')->delete();
        Perception::where('fiscal_environment', 'demo')->delete();

        //SaleNote
        $sale_notes = SaleNote::where('fiscal_environment', 'demo')->get();
        // SaleNote::where('fiscal_environment', 'demo')->delete();

        $this->deleteRecordsCash(SaleNote::class);

        $this->delete_quantity += $sale_notes->count();
        $this->deleteSaleNoteRelations($sale_notes);


        Contract::where('fiscal_environment', 'demo')->delete();
        // Quotation::where('fiscal_environment', 'demo')->delete();
        $this->deleteQuotation();

        SaleOpportunity::where('fiscal_environment', 'demo')->delete();

        Expense::where('fiscal_environment', 'demo')->delete();
        OrderNote::where('fiscal_environment', 'demo')->delete();
        OrderForm::where('fiscal_environment', 'demo')->delete();

        GlobalPayment::where('fiscal_environment', 'demo')->delete();
        Tip::where('fiscal_environment', 'demo')->delete();

        Income::where('fiscal_environment', 'demo')->delete();

        FixedAssetPurchase::where('fiscal_environment', 'demo')->delete();

        $this->updateStockAfterDelete();

        $this->deletePaymentLink();

        // produccion

        Production::where('fiscal_environment', 'demo')->delete();
        Packaging::where('fiscal_environment', 'demo')->delete();
        $this->deleteMill();

            return [
                'success' => true,
                'message' => 'Documentos de prueba eliminados',
                'delete_quantity' => $this->delete_quantity,
            ];
        });
        // ######## FIN PC-17 ELIMINACIÓN SEGURA DE DOCUMENTOS DE PRUEBA ########
    }

    /** @param \Illuminate\Support\Collection<int, Document> $documents */
    private function deleteDocumentRelations($documents): void
    {
        $documentIds = $documents->pluck('id');

        foreach ($documents as $document) {
            $document->items()->delete();
            $document->inventory_kardex()->delete();
            $document->payments()->each(function ($payment) {
                $payment->cashDocumentPayments()->delete();
                $payment->global_payment()->delete();
                $payment->delete();
            });
            $document->fee()->delete();
            $document->hotel()->delete();
            $document->transport()->delete();
            $document->invoice()->delete();
            $document->note()->delete();
            $document->summary_document()->delete();
            $document->affected_documents()->delete();
            Kardex::where('document_id', $document->id)->delete();
        }

        CashDocument::whereIn('document_id', $documentIds)->delete();
        Document::whereIn('id', $documentIds)->delete();
    }

    /** @param \Illuminate\Support\Collection<int, SaleNote> $saleNotes */
    private function deleteSaleNoteRelations($saleNotes): void
    {
        $saleNoteIds = $saleNotes->pluck('id');

        foreach ($saleNotes as $saleNote) {
            // sale_note_items.inventory_kardex_id restringe el borrado del Kardex.
            // Primero se elimina el detalle que mantiene esa referencia.
            $saleNote->items()->delete();
            $saleNote->inventory_kardex()->delete();
            $saleNote->payments()->each(function ($payment) {
                $payment->cashDocumentPayments()->delete();
                $payment->global_payment()->delete();
                $payment->delete();
            });
            $saleNote->fee()->delete();
            Kardex::where('sale_note_id', $saleNote->id)->delete();
        }

        $hotelOrderIds = HotelRentOrder::whereIn('sale_note_id', $saleNoteIds)->pluck('id');
        $hotelItemIds = HotelRentItem::whereIn('hotel_rent_order_id', $hotelOrderIds)->pluck('id');
        HotelRentItemPayment::whereIn('hotel_rent_item_id', $hotelItemIds)->delete();
        HotelRentItem::whereIn('id', $hotelItemIds)->delete();
        HotelRentOrder::whereIn('id', $hotelOrderIds)->delete();
        if (Schema::connection('tenant')->hasTable('dispatch_sale_notes')) {
            DB::connection('tenant')->table('dispatch_sale_notes')->whereIn('sale_note_id', $saleNoteIds)->delete();
        }
        CashDocument::whereIn('sale_note_id', $saleNoteIds)->delete();
        SaleNote::whereIn('id', $saleNoteIds)->delete();
    }


    /**
     *
     * Eliminar links de pago y transacciones asociadas en demo
     *
     * @return void
     */
    private function deletePaymentLink()
    {
        $transactions = Transaction::where('fiscal_environment', 'demo')->get();

        foreach ($transactions as $transaction)
        {
            $transaction->transaction_queries()->delete();
            $transaction->delete();
        }

        PaymentLink::where('fiscal_environment', 'demo')->delete();
    }


    /**
     *
     * Eliminar registros de ingresos de insumos
     *
     * @return void
     */
    private function deleteMill()
    {
        $mills = Mill::where('fiscal_environment', 'demo')->get();

        foreach ($mills as $mill)
        {
            $mill->relation_mill_items()->delete();
            $mill->delete();
        }

    }

    /**
     *
     * Eliminar registros relacionados en caja y cotizaciones
     *
     * @return void
     */
    private function deleteQuotation()
    {
        $records_id = Quotation::where('fiscal_environment', 'demo')->whereFilterWithOutRelations()->select('id')->get()->pluck('id')->toArray();
        // dd($records_id);
        CashDocument::whereIn('quotation_id', $records_id)->delete();
        Quotation::where('fiscal_environment', 'demo')->delete();
    }


    /**
     *
     * Eliminar registros relacionados en caja - notas de venta/cpe
     *
     * @return void
     */
    private function deleteRecordsCash($model)
    {
        $records_id = $model::where('fiscal_environment', 'demo')->whereFilterWithOutRelations()->select('id')->get()->pluck('id')->toArray();

        $column = ($model === Document::class) ? 'document_id' : 'sale_note_id';

        $idCashDocumentCredit = CashDocumentCredit::whereIn($column, $records_id)->select('id')->get()->pluck('id')->toArray();
        CashDocumentPayment::whereIn('cash_document_credit_id', $idCashDocumentCredit)->delete();
        CashDocumentCredit::whereIn($column, $records_id)->delete();

        $document_records = $model::where('fiscal_environment', 'demo')->get();

        $document_records->each(function ($record) {
            $record->payments()->each(function ($payment) {
                if (method_exists($payment, 'cashDocumentPayments')) {
                    $payment->cashDocumentPayments()->delete();
                }
            });
        });

        // if ($model === SaleNote::class) {
        //     HotelRentItemPayment::query()->delete();
        //     HotelRentItem::query()->delete();
        //     HotelRentOrder::query()->delete();
        //     HotelRent::query()->delete();

        //     $model::with('items')->each(function ($record) {
        //         $record->items->each(function ($item) {
        //             $item->delete();
        //         });
        //     });
        // } 

        // La eliminación del registro principal se hace después de sus relaciones.
    }


    private function deleteInventoryKardex($model, $records = null){

        if(!$records){
            $records = $model::where('fiscal_environment', 'demo')->get();
        }

        $this->delete_quantity += $records->count();

        foreach ($records as $record) {
            $record->inventory_kardex()->delete();

        }
    }

    private function updateStockAfterDelete(){

        // if($this->delete_quantity > 0){

        //     ItemWarehouse::latest()->update([
        //         'stock' => 0
        //     ]);

        // }

    }

    private function update_quantity_documents($quantity)
    {
        $configuration = Configuration::first();
        $configuration->quantity_documents = max(0, (int) $configuration->quantity_documents - $quantity);
        $configuration->save();
    }

    public function deleteItems(Request $request)
    {   
        $id_items_movement = ItemMovement::distinct()->pluck('item_id');
        $id_items_inventory = Inventory::distinct()->where('description','<>','Stock inicial')->pluck('item_id');
        $id_items_devolution_items = DevolutionItem::distinct()->pluck('item_id');
        $ids_item_merge = $id_items_movement->merge($id_items_inventory)
            ->merge($id_items_devolution_items)
            ->unique();
        $ids_item_merge_array = $ids_item_merge->toArray();
        $deletedItem = 0;
    
        try{
            DB::transaction(function () use ($ids_item_merge_array,&$deletedItem) {
                $ids_item_delete = Item::whereNotIn('id', $ids_item_merge_array)->pluck('id');
                $ids_item_delete_array = $ids_item_delete->toArray();
                
                InventoryKardex::whereIn('item_id', $ids_item_delete_array)->delete();
                Kardex::whereIn('item_id', $ids_item_delete_array)->delete();
                WeightedAverageCost::whereIn('item_id', $ids_item_delete_array)->delete();
                Inventory::whereIn('item_id', $ids_item_delete_array)->delete();
                ItemUnitType::whereIn('item_id', $ids_item_delete_array)->delete();
                ItemLot::whereIn('item_id', $ids_item_delete_array)->delete();
                ItemLotsGroup::whereIn('item_id', $ids_item_delete_array)->delete();
                
                $deletedItem = Item::whereIn('id', $ids_item_delete_array)->delete();
            });

            return [
                'success' => true,
                'message' => ($deletedItem==1)?$deletedItem.' Producto eliminado':$deletedItem.' Productos eliminados',
                'delete_quantity' => $deletedItem,
            ];

        }catch(\Exception $ex){
            return [
                'success' => false,
                'message' => 'Inconvenientes al eliminar',
                'delete_quantity' => $deletedItem,
            ];
        }
        
    }

}
