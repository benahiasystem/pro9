<?php

namespace Modules\Sale\Http\Resources;

use App\Models\Tenant\Person;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sale\Models\Contract;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var Contract $contract */
        $contract = $this->resource;
        $contract->payments = self::getTransformPayments($contract->payments);

        $customer = Person::find($this->customer_id);
        $customerData = $customer ? $customer->getCollectionData() : null;
        $storedCustomer = $contract->customer;

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'identifier' => $this->identifier,
            'number_full' => $this->number_full,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'customer_id' => $this->customer_id,
            'customer_email' => optional($customer)->email ?? optional($storedCustomer)->email,
            'customer_telephone' => optional($customer)->telephone ?? optional($contract->person)->telephone,
            'customer' => $customerData,
            'contract' => $contract,
        ];
    }

    
    public static function getTransformPayments($payments){
        
        return $payments->transform(function($row, $key){ 
            return [
                'id' => $row->id, 
                'contract_id' => $row->contract_id, 
                'date_of_payment' => $row->date_of_payment->format('Y-m-d'), 
                'payment_method_type_id' => $row->payment_method_type_id, 
                'has_card' => $row->has_card, 
                'card_brand_id' => $row->card_brand_id, 
                'reference' => $row->reference, 
                'payment' => $row->payment, 
                'payment_method_type' => $row->payment_method_type, 
                'payment_destination_id' => ($row->global_payment) ? ($row->global_payment->type_record == 'cash' ? 'cash':$row->global_payment->destination_id):null, 
            ];
        }); 

    }

}
