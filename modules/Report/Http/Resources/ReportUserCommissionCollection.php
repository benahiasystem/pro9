<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportUserCommissionCollection extends ResourceCollection
{
    private ?array $amounts = null;

    public function withAmounts(array $amounts): self
    {
        $this->amounts = $amounts;
        return $this;
    }
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $amounts = $this->amounts ?? \Modules\Report\Services\EstimatedUserCommission::users($this->collection);
        return $this->collection->transform(function($row, $key) use ($amounts) {
            $values = $amounts[$row->id];
 
            return [
                'id' => $row->id,  
                'user_name' => $row->name,
                'type' => ($row->user_commission->type == 'amount') ? 'Monto':'Porcentaje',
                'commission' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($values,'commission'),2,'.',''),
                'amount' => $row->user_commission->amount,
                'total_utility' => \App\Support\Exact\Decimal::format(\App\Support\Exact\Decimal::readRequired($values,'total_utility'),2,'.',''),
                'currency_type_id' => 'VES',
                'cost_is_estimated' => true,
                'calculation_snapshot' => $values['calculation_snapshot'],
            ];
        });
    }
    
}
