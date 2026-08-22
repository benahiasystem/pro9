<?php

namespace Modules\Purchase\Http\Resources;

use App\Models\Tenant\Person;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\PurchaseQuotation; 

class PurchaseQuotationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $purchase_quotation = PurchaseQuotation::with(['items', 'state_type', 'user', 'purchase_orders'])->find($this->id);

        $suppliers = collect(optional($purchase_quotation)->suppliers ? (array) $purchase_quotation->suppliers : [])
            ->filter()
            ->map(function ($supplier) {
                $supplier = (array) $supplier;
                $person = !empty($supplier['supplier_id'])
                    ? Person::with('identity_document_type')->find($supplier['supplier_id'])
                    : null;

                if ($person) {
                    $supplier['name'] = $supplier['name'] ?? $person->name;
                    $supplier['number'] = $supplier['number'] ?? $person->number;
                    $supplier['email'] = $supplier['email'] ?? $person->email;
                    $supplier['telephone'] = $person->telephone;
                    $supplier['identity_document_type_description'] = optional($person->identity_document_type)->description;
                }

                return $supplier;
            })
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,  
            'identifier' => $this->identifier,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'has_purchase_orders' => $purchase_quotation ? $purchase_quotation->purchase_orders->count() > 0 : false,
            'suppliers' => $suppliers,
            'purchase_quotation' => $purchase_quotation
        ];
    }
}
