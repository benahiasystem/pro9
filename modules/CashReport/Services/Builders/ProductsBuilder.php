<?php

namespace Modules\CashReport\Services\Builders;

use App\CoreFacturalo\Helpers\Template\ReportHelper;
use App\Models\Tenant\Cash;
use App\Models\Tenant\CashDocument;
use App\Models\Tenant\Company;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Document;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\SaleNote;
use App\Support\Exact\ConsolidatedLines;
use App\Support\Exact\Decimal;
use Modules\CashReport\Services\Contracts\CashReportBuilderInterface;
use Modules\CashReport\Services\HeaderDataBuilder;
use Modules\CashReport\Services\ProductsAmounts;

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
        \App\Services\Payments\CashLedger::authorize($cash);
        $company = Company::first();
        $cash_documents = Document::withoutEagerLoads()->whereIn('id', CashDocument::getDocumentIdsReport($cash))
            ->whereNotIn('state_type_id', ['09','11'])->pluck('id')->all();
        ReportHelper::setBoolIsGarage($is_garage);

        $source = DocumentItem::with('document')->whereIn('document_id', $cash_documents)->get();
        $allocated = $this->allocatedTotals($source, 'document');

        $documents = collect($source)->transform(function(DocumentItem $row) use ($allocated){

            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data = array_replace($data, ProductsAmounts::line($row, (string) $row->document->currency_type_id,
                (string)$row->document->document_type_id === '07'));
            $data = $this->withAllocatedTotal($data, $allocated[$row->id]);
            $data['number_full'] = $row->document->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'document_item';

            $data['item_id'] = $row->item_id;

            /*
            $data['total'] = $row->document->total;
            $data['item_id'] =$row->relation_item->id;
            */

            return $data;
        });

        $documents = $documents->merge($this->getSaleNotesReportProducts($cash, $cash_documents));

        $documents = $documents->merge($this->getPurchasesReportProducts($cash));

        return compact("cash", "company", "documents", 'is_garage');

    }



    public function getSaleNotesReportProducts($cash, ?array $cashDocuments = null)
    {

        $cd_sale_notes =  CashDocument::getSaleNoteIdsReport($cash);
        $cashDocuments ??= Document::withoutEagerLoads()->whereIn('id', CashDocument::getDocumentIdsReport($cash))
            ->whereNotIn('state_type_id', ['09','11'])->pluck('id')->all();
        if ($cashDocuments && $cd_sale_notes) {
            // A converted note and its invoice in the same shift represent one sale.
            // Keep the note when the invoice belongs to a different shift.
            $converted = SaleNote::withoutEagerLoads()->whereIn('id', $cd_sale_notes)
                ->whereIn('document_id', $cashDocuments)->pluck('id')->all();
            $cd_sale_notes = array_values(array_diff($cd_sale_notes, $converted));
        }

        $sale_note_items = SaleNoteItem::with('sale_note')->whereIn('sale_note_id', $cd_sale_notes)->get();
        $allocated = $this->allocatedTotals($sale_note_items, 'sale_note');

        return collect($sale_note_items)->transform(function(SaleNoteItem $row) use ($allocated){
            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data = array_replace($data, ProductsAmounts::line($row, (string) $row->sale_note->currency_type_id));
            $data = $this->withAllocatedTotal($data, $allocated[$row->id]);
            $data['number_full'] = $row->sale_note->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'sale_note_item';
            
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

        $purchase_items = PurchaseItem::with('purchase')->whereIn('purchase_id', $cd_purchases)
            ->whereHas('purchase', fn($q)=>$q->whereNotIn('state_type_id', ['09','11']))->get();

        return collect($purchase_items)->transform(function(PurchaseItem $row){

            $item = $row->item;
            $data = $row->toArray();
            $data['item'] =$item;
            $data = array_replace($data, ProductsAmounts::line($row, (string) $row->purchase->currency_type_id));
            $data['number_full'] = $row->purchase->number_full;
            $data['description'] = $row->item->description;
            $data['unit_type_id'] = $this->getUnitTypeId($row);
            $data['record_type'] = 'purchase_item';

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

    /** Allocate document-level adjustments using the same frozen fractions as other reports. */
    private function allocatedTotals($rows, string $source): array
    {
        $totals = [];
        $reader = new ConsolidatedLines((new Document())->getConnection());
        foreach ($rows->chunk(500) as $chunk) {
            foreach ($chunk as $row) {
                $parent = $source === 'document' ? $row->document : $row->sale_note;
                $row->source_type = $source;
                $row->source_id = $parent->id;
                $row->currency_type_id = $parent->currency_type_id;
                $row->exchange_rate_sale = $parent->exchange_rate_sale;
                $row->document_type_id = $source === 'document' ? $parent->document_type_id : '80';
            }
            foreach ($reader->rows($chunk) as $line) {
                $totals[$line['id']] = Decimal::readRequired($line, 'total_original');
            }
        }
        return $totals;
    }

    private function withAllocatedTotal(array $row, $total): array
    {
        unset($row['source_type'], $row['source_id'], $row['exchange_rate_sale'], $row['document_type_id']);
        $values = [];
        foreach (['quantity', 'unit_value', 'sub_total'] as $field) $values[$field] = Decimal::readRequired($row, $field);
        $values['line_total'] = Decimal::readRequired($row, 'total');
        $values['total'] = $total;
        $row['total'] = Decimal::represent($total);
        $row['calculation_snapshot'] = Decimal::snapshot($values);
        return $row;
    }
}
