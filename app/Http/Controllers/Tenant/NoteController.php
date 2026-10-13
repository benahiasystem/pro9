<?php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Configuration;

class NoteController extends Controller
{
    public function create($document_id)
    {
        $document_affected = Document::findOrFail($document_id);
        if (\App\Services\ExportInvoicePolicy::isExport($document_affected->invoice->operation_type_id ?? '')) \App\Services\Fiscal\FiscalAmounts::error('note', 'Las notas de exportación todavía no están habilitadas.');
        DocumentFiscalController::authorizeDocument($document_affected);
        if ($reason=\App\Services\Fiscal\FiscalNote::reason($document_affected)) \App\Services\Fiscal\FiscalAmounts::error('note',$reason);
        $document_affected=$this->snapshot($document_affected);
        $configuration = Configuration::first();

        return view('tenant.documents.note', compact('document_affected', 'configuration'));
    }

    public function record($document_id)
    {
        $record = Document::findOrFail($document_id);

        DocumentFiscalController::authorizeDocument($record);
        return $this->snapshot($record);
    }

    private function snapshot(Document $document): array
    {
        return $document->only(['id','document_type_id','series','number','date_of_issue','establishment_id','customer_id','currency_type_id','exchange_rate_sale','exchange_rate_source','exchange_rate_date','customer'])+['note_context'=>\App\Services\Fiscal\FiscalNote::context($document)];
    }

    public function hasDocuments($document_id)
    {

        DocumentFiscalController::authorizeDocument(Document::findOrFail($document_id));
        $record = Document::wherehas('affected_documents', function ($q) {
            $q->whereHas('document', function ($q) {
                $q->where('state_type_id', '05');
            });
        })->find($document_id);

        if($record){

            return [
                'success' => true,
                'data' => $record->affected_documents->transform(function($row, $key) {
                            return [
                                'id' => $row->id,
                                'document_id' => $row->document_id,
                                'document_type_description' => $row->document->document_type->description,
                                'description' => $row->document->number_full,
                            ];
                        })
            ];

        }

        return [
            'success' => false,
            'data' => []
        ];

    }

}
