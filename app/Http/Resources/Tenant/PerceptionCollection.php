<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PerceptionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {
            return [
                'id' => $row->id,
                'group_id' => $row->group_id,
                'fiscal_environment' => $row->fiscal_environment,
                'date_of_issue' => $row->date_of_issue->format('d-m-Y'),
                'document_type_short' => $row->document_type->short,
                'number' => $row->number_full,
                'customer_name' => $row->customer->name,
                'customer_number' => format_person_identity_document($row->customer),
                'total_retention' => $row->total_retention,
                'perception_type_description' => $row->perception_type->description,
                'total' => $row->total,
                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_type->description,
                'has_pdf' => $row->has_pdf,
                'download_external_pdf' => $row->download_external_pdf,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
