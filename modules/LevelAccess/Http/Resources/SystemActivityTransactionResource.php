<?php

namespace Modules\LevelAccess\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SystemActivityTransactionResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $row = is_array($this->resource) ? (object) $this->resource : $this->resource;

        return [
            'id' => $row->id ?? null,
            'user_id' => $row->user_id ?? null,
            'user_name' => $row->user_name ?? null,
            'date_of_issue' => $row->date_of_issue ?? null,
            'time_of_issue' => $row->time_of_issue ?? null,
            'document_type_id' => $row->document_type_id ?? null,
            'document_type_description' => $row->document_type_description ?? null,
            'series' => $row->series ?? null,
            'number' => $row->number ?? null,
            'number_full' => $row->number_full ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
