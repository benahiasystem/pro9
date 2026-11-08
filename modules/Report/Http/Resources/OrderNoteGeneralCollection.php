<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderNoteGeneralCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        
        return $this->collection->map(function($row, $key){
            $amounts=\Modules\Report\Services\OrderGeneralAmounts::row($row);
             
            return [
                'id' => $row->id, 
                'delivery_date' => optional($row->delivery_date)->format('Y-m-d'),  
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),  
                'number_full' => $row->number_full,  
                'user_name' => $row->user->name,  
                'customer_name' => $row->customer->name,  
                'customer_number' => format_person_identity_document($row->customer),
                'total' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($amounts,'total'),2,'.',''),
                'currency_type_id' => $amounts['currency_type_id'],
                'calculation_snapshot' => $amounts['calculation_snapshot'],
                'state_description' => strtoupper(optional($row->state_type)->description ?? 'PENDIENTE'),  
            ];
        });
    }
    
}
