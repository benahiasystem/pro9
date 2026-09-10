<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Sale\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TechnicalServiceCollection extends ResourceCollection
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


            return $row->getCollectionData();
            return [
                'id' => $row->id,
                'fiscal_environment' => $row->fiscal_environment,
                'cellphone' => $row->cellphone,
                'serial_number' => $row->serial_number,
                'cost' => $row->cost,
                'prepayment' => $row->prepayment,
                // 'balance' => $row->cost - $row->prepayment,
                'balance' => $row->cost - collect($row->payments)->sum('payment'),
                'date_of_issue' => $row->date_of_issue->format('Y-m-d'),
                'customer_name' => $row->customer->name,
                'customer_number' => format_person_identity_document($row->customer),
            ];
        });
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
