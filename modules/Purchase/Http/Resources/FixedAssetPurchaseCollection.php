<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class FixedAssetPurchaseCollection extends ResourceCollection
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

            $D = \App\Support\Exact\Decimal::class;
            $format = fn($field) => $D::format($D::read($row, $field), 2, '.', '');
            $total = $D::read($row, 'total');

            return [
                'id' => $row->id,
                'document_type_description' => $row->document_type->description,
                'group_id' => $row->group_id,
                'fiscal_environment' => $row->fiscal_environment,
                'date_of_issue' => $row->date_of_issue->format('d-m-Y'),
                'date_of_due' => ($row->date_of_due) ? $row->date_of_due->format('d-m-Y') : '-',
                'number' => $row->number_full,
                'supplier_name' => $row->supplier->name,
                'supplier_number' => format_person_identity_document($row->supplier),
                'currency_type_id' => $row->currency_type_id,
                'total_exportation' => $row->total_exportation,
                'total_free' => $format('total_free'),
                'total_unaffected' => $format('total_unaffected'),
                'total_exonerated' => $format('total_exonerated'),
                'total_taxed' => $format('total_taxed'),
                'total_igv' => $format('total_igv'),
                'total' => $D::format($total, 2, '.', ''),
                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_type->description,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
                'items' => $row->items->map(function ($row, $key) use ($D) {
                    $quantity = $D::readRequired($row, 'quantity');
                    return [
                        'key' => $key + 1,
                        'id' => $row->id,
                        'description' => $row->item->description ?? $row->item->name ?? null,
                        'quantity' => $D::represent($quantity),
                        'quantity_fraction' => $D::fraction($quantity),
                    ];
                }),
            ];
        });
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
