<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class RetentionCollection extends ResourceCollection
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
                'date_of_issue' => $row->date_of_issue->format('d-m-Y'),
                'number' => $row->number_full,
                'supplier_name' => $row->supplier->name,
                'supplier_number' => format_person_identity_document($row->supplier),
                'state_type_id' => $row->state_type_id,
                'state_type_description' => $row->state_type->description,
                'total_retention' => $row->total_retention,
                'total' => $row->total,
                'has_pdf' => $row->has_pdf,
                'download_external_pdf' => $row->download_external_pdf,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }
}
