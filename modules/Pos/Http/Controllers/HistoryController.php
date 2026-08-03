<?php

namespace Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Resources\HistorySalesCollection;
use Modules\Pos\Http\Resources\HistoryPurchasesCollection;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\ItemSet;

class HistoryController extends Controller
{
    
    public function recordsSales(Request $request)
    {

        $form = json_decode($request->form);
        $saleItemIds = $this->getSaleHistoryItemIds((int) $form->item_id);
        
        $sale_notes = SaleNoteItem::whereIn('item_id', $saleItemIds);
        if(!$form->all_user){
            $sale_notes = $sale_notes->whereHas('sale_note', function($query) use($form){
                $query->where('customer_id', $form->customer_id);
            });
        }
                                    
        $sale_notes = $sale_notes->join('sale_notes', 'sale_note_items.sale_note_id', '=', 'sale_notes.id')
                                    ->join('persons', 'persons.id', '=', 'sale_notes.customer_id')->select(DB::raw('sale_note_items.id as id, sale_notes.prefix as series, sale_notes.id as number,
                                    sale_note_items.unit_price as price, sale_notes.date_of_issue as date_of_issue,persons.name as name'));


        $documents = DocumentItem::whereIn('item_id', $saleItemIds);
        if(!$form->all_user){
            $documents = $documents->whereHas('document', function($query) use($form){
                $query->where('customer_id', $form->customer_id);
            });
        }
                                    
        $documents=$documents->join('documents', 'document_items.document_id', '=', 'documents.id')
                                    ->join('persons', 'persons.id', '=', 'documents.customer_id')->select(DB::raw('document_items.id as id, documents.series as series, documents.number as number,
                                    document_items.unit_price as price, documents.date_of_issue as date_of_issue,persons.name as name'));

        $records = $documents->union($sale_notes)->orderBy('date_of_issue', 'desc');

        return new HistorySalesCollection($records->paginate(config('tenant.items_per_page_simple_d_table_params')));

    }

    private function getSaleHistoryItemIds(int $itemId): array
    {
        $packIds = ItemSet::where('individual_item_id', $itemId)->pluck('item_id')->toArray();

        return array_values(array_unique(array_merge([$itemId], $packIds)));
    }

    
    public function recordsPurchases(Request $request)
    {

        $form = json_decode($request->form);
        
        $purchases = PurchaseItem::where('item_id', $form->item_id) 
                                    ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                                    ->select(DB::raw("purchase_items.id as id, purchases.series as series, purchases.number as number,
                                    purchases.supplier as supplier,purchase_items.unit_price as price, purchases.date_of_issue as date_of_issue"))
                                    ->orderBy('created_at', 'desc');
 

        $records = $purchases->orderBy('date_of_issue','desc')->orderBy('number','desc');

        return new HistoryPurchasesCollection($records->paginate(config('tenant.items_per_page_simple_d_table_params')));

    }
}
