<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\StateType;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VoidedCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {

            return [
                'type' => $row->type,
                'id' => $row->id,
                'identifier' => $row->identifier,
                'date_of_issue' => $row->date_of_issue,
                'date_of_reference' => $row->date_of_reference,
                'state_type_id' => $row->state_type_id,
                'state_type_description' => StateType::find($row->state_type_id)->description,
//                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
//                'updated_at' => $row->updated_at->format('Y-m-d H:i:s'),
            ];
        });
    }
}
