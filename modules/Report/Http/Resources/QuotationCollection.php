<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class QuotationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {
        
            $amounts = \Modules\Report\Services\QuotationReportAmounts::row($row);
            $btn_generate = (count($row->documents) > 0 || count($row->sale_notes) > 0)?false:true;

            return [
                'id' => $row->id, 
                'fiscal_environment' => $row->fiscal_environment,
                'external_id' => $row->external_id,
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'identifier' => $row->identifier,
                'customer_name' => $row->customer->name,
                'customer_number' => format_person_identity_document($row->customer),
                'currency_type_id' => $row->currency_type_id,
                'total_exportation' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total_exportation'),2,'.',''),
                'total_unaffected' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total_unaffected'),2,'.',''),
                'total_exonerated' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total_exonerated'),2,'.',''),
                'total_taxed' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total_taxed'),2,'.',''),
                'total_igv' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total_igv'),2,'.',''),
                'total' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts, 'total'),2,'.',''),
                'calculation_snapshot' => $amounts['calculation_snapshot'],
                'state_type_id' => $row->state_type_id, 
                'state_type_description' => $row->state_type->description, 
                'documents' => $row->documents->transform(function($row) {
                    return [
                        'number_full' => $row->number_full, 
                    ];
                }),
                'sale_notes' => $row->sale_notes->transform(function($row) {
                    return [
                        'identifier' => $row->identifier,
                    ];
                }),
                'btn_generate' => $btn_generate,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
                'user_name' => ($row->user) ? $row->user->name : '',
                'sale_opportunity_number_full' => ($row->sale_opportunity) ? $row->sale_opportunity->number_full : '',
            ];
        });
    }
    
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
