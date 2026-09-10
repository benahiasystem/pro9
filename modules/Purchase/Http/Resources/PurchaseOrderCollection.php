<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseOrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {

            return [

                'id' => $row->id,
                // 'purchases' => $row->purchases,
                'has_purchases' => ($row->purchases->count()) ? true : false,
                'fiscal_environment' => $row->fiscal_environment,
                'external_id' => $row->external_id,
                'date_of_issue' => $row->date_of_issue->format('d-m-Y'),
                'date_of_due' => ($row->date_of_due) ? $row->date_of_due->format('d-m-Y') : '-',
                'number' => $row->number_full,
                'supplier_name' => $row->supplier->name,
                'supplier_number' => format_person_identity_document($row->supplier),
                'currency_type_id' => $row->currency_type_id,
                'total_exportation' => $row->total_exportation,
                'total_free' => $row->total_free,
                'total_unaffected' => $row->total_unaffected,
                'total_exonerated' => $row->total_exonerated,
                'total_taxed' => $row->total_taxed,
                'total_igv' => $row->total_igv,
                'total' => $row->total,
                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_type->description,
                // 'payment_method_type_description' => isset($row->purchase_payments['payment_method_type']['description'])?$row->purchase_payments['payment_method_type']['description']:'-',
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
                'sale_opportunity_number_full' => ($row->sale_opportunity) ? $row->sale_opportunity->number_full : '',
                'show_actions_row' => $row->getShowActionsRow(),

            ];
        });
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
