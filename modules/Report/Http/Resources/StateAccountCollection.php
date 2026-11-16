<?php

namespace Modules\Report\Http\Resources;

use App\Models\Tenant\Document;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\Receivables;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** Account rows keep their real model, even when document and sale-note IDs overlap. */
class StateAccountCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $documents = $this->collection->filter(fn ($row) => $row instanceof Document);
        $balances = [];
        foreach ($documents->chunk(500) as $chunk) $balances += Receivables::balancesForDocuments($chunk);
        $saleType = DocumentType::find('80');
        $linked = \Modules\Report\Services\StateAccountAmounts::activeLinkedInvoices($this->collection);

        return $this->collection->map(function ($row) use ($balances, $saleType, $linked) {
            $document = $row instanceof Document;
            $type = $document ? $row->document_type_id : '80';
            $void = in_array($row->state_type_id, ['09', '11'], true);
            $seller = $row->getSellerData();
            $affected = null;
            if ($document && in_array($type, ['07', '08'], true) && $row->note) {
                $target = $row->note->affected_document ?: $row->note->data_affected_document;
                if ($target) $affected = \App\Services\Fiscal\FiscalIdentity::numberFull($target->series, $target->number);
            }
            $converted = !$document && $row->document_id && $linked->contains($row->document_id);
            $balance = ($void || $converted) ? D::of(0) : ($document ? ($balances[$row->id] ?? D::of(0)) :
                \Modules\Report\Services\StateAccountAmounts::saleNoteBalance($row));
            $result = [
                'id'=>$row->id, 'group_id'=>$document ? $row->group_id : null,
                'fiscal_environment'=>$row->fiscal_environment,
                'fiscal_environment_description'=>optional($row->fiscal_environment_type)->description,
                'date_of_issue'=>optional($row->date_of_issue)->format('Y-m-d'),
                'date_of_due'=>$document ? optional(optional($row->invoice)->date_of_due)->format('Y-m-d') : optional($row->due_date)->format('Y-m-d'),
                'number'=>$row->number_full, 'customer_name'=>optional($row->person)->name,
                'customer_number'=>$row->person ? format_person_identity_document($row->person) : null,
                'currency_type_id'=>$row->currency_type_id, 'series'=>$row->series,
                'establishment_id'=>$row->establishment_id, 'alone_number'=>$row->number,
                'purchase_order'=>$row->purchase_order, 'guides'=>!empty($row->guides) ? (array) $row->guides : null,
                'state_type_id'=>$row->state_type_id, 'state_type_description'=>optional($row->state_type)->description,
                'document_type_description'=>$document ? optional($row->document_type)->description : optional($saleType)->description,
                'document_type_id'=>$type, 'converted'=>$converted, 'affected_document'=>$affected,
                'user_name'=>$seller->name ?? '', 'user_email'=>$seller->email ?? '',
                'notes'=>$document && $type === '01' ? $row->affected_documents->map(fn ($note) => [
                    'id'=>$note->id, 'document_id'=>$note->document_id,
                    'note_type_description'=>$note->note_type === 'credit' ? 'NC' : 'ND',
                    'description'=>optional($note->document)->number_full,
                ]) : null,
                'quotation_number_full'=>optional($row->quotation)->number_full ?? '',
                'sale_opportunity_number_full'=>optional(optional($row->quotation)->sale_opportunity)->number_full ?? '',
                'plate_number'=>$row->plate_number, 'payment_state'=>D::format($balance, 2, '.', ''),
                'items'=>$row->items->values()->map(fn ($item, $key) => [
                    'key'=>$key+1, 'id'=>$item->id, 'description'=>$item->item->description,
                    'quantity'=>D::represent(D::read($item, 'quantity')),
                    'quantity_fraction'=>D::fraction(D::read($item, 'quantity')),
                ]),
            ];
            foreach (['total_exportation','total_exonerated','total_unaffected','total_free','total_taxed','total_igv','total','total_charge'] as $field) {
                // ######## INICIO CALCULOS EXACTOS SAB ########
                if (!array_key_exists($field, $row->getAttributes())) {
                    $result[$field] = null;
                    continue;
                }
                // ######## FIN CALCULOS EXACTOS SAB ########
                $result[$field] = $void ? '0.00' : D::format(D::read($row, $field), 2, '.', '');
            }
            return $result;
        });
    }
}
