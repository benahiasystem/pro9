<?php

namespace Modules\CashReport\Services\Builders;

use App\CoreFacturalo\Helpers\Template\ReportHelper;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;

/**
 * Productos vendidos en la caja (punto de venta y venta rápida).
 * Lógica trasladada desde App\Http\Controllers\Tenant\CashController.
 */
class ProductsBuilder implements CashReportBuilderInterface
{
    public function build(Cash $cash, array $options): array
    {
        $report = $this->getDataReport($cash->id, (bool) ($options['is_garage'] ?? false));

        return array_merge($report, [
            'header' => HeaderDataBuilder::build($cash),
            'data' => $report,
        ]);
    }

    public function getDataReport($id, $is_garage = false)
    {

        $cash = Cash::findOrFail($id);
        $company = Company::first();
        $cash_documents =  CashDocument::getDocumentIdsReport($cash);
        ReportHelper::setBoolIsGarage($is_garage);

        $source = DocumentItem::with('document')->whereIn('document_id', $cash_documents)->get();

        $documents = collect($source)->transform(function(DocumentItem $row){

            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->document->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'document_item';

            $data['total'] = $row->total;
            $data['item_id'] = $row->item_id;

            /*
            $data['total'] = $row->document->total;
            $data['item_id'] =$row->relation_item->id;
            */

            return $data;
        });

        $documents = $documents->merge($this->getSaleNotesReportProducts($cash));

        $documents = $documents->merge($this->getPurchasesReportProducts($cash));

        return compact("cash", "company", "documents", 'is_garage');

    }



    public function getSaleNotesReportProducts($cash)
    {

        $cd_sale_notes =  CashDocument::getSaleNoteIdsReport($cash);

        $sale_note_items = SaleNoteItem::with('sale_note')->whereIn('sale_note_id', $cd_sale_notes)->get();

        return collect($sale_note_items)->transform(function(SaleNoteItem $row){
            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->sale_note->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'sale_note_item';
            
            $data['total'] = $row->total;
            $data['item_id'] = $row->item_id;

            /*
            $data['total'] = $row->sale_note->total;
            $data['item_id'] =$row->relation_item->id;
            */

            return $data;
        });

    }


    public function getPurchasesReportProducts($cash)
    {

        $cd_purchases =  CashDocument::getPurchaseIdsReport($cash);

        $purchase_items = PurchaseItem::with('purchase')->whereIn('purchase_id', $cd_purchases)->get();

        return collect($purchase_items)->transform(function(PurchaseItem $row){

            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data['unit_value']=$data['unit_value']??0;
            $data['sub_total'] =$data['unit_value'] * $data['quantity'];
            $data['number_full'] = $row->purchase->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'purchase_item';

            $data['total'] = $row->total;
            $data['item_id'] = $row->item_id;

            /*
            $data['total'] = $row->purchase->total;
            $data['item_id'] =$row->purchase->id;
            */

            return $data;
        });

    }
    
    
    /**
     * @param  array $row
     * @return string
     */
    private function getUnitTypeId($row)
    {
        return $row->item->unit_type_id ?? null;
    }
}
