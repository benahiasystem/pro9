<?php

namespace Modules\Finance\Http\Resources;

use App\Models\Tenant\Person;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Finance\Models\Income;

class IncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $income = Income::with([
            'items',
            'income_reason',
            'income_type',
            'state_type',
            'user',
        ])->find($this->id);

        $customerName = $income ? $income->customer : $this->customer;
        $matchedPerson = $this->resolveCustomerPerson($customerName);

        $payments = $this->payments->transform(function ($row) {
            return [
                'id' => $row->id,
                'payment_method_type_description' => optional($row->payment_method_type)->description,
                'destination_description' => ($row->global_payment) ? $row->global_payment->destination_description : null,
                'reference' => $row->reference,
                'payment' => $row->payment,
            ];
        });

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'number' => $this->number,
            'state_type_id' => $this->state_type_id,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'payments' => $payments,
            'customer_name' => $customerName,
            'customer_number' => format_person_identity_document($matchedPerson),
            'customer_telephone' => optional($matchedPerson)->telephone,
            'customer_email' => optional($matchedPerson)->email,
            'customer_identity_document_type_description' => optional(optional($matchedPerson)->identity_document_type)->description,
            'income' => $income,
        ];
    }

    /**
     * Intenta relacionar el texto libre de cliente con un registro de Personas.
     *
     * @param  string|null  $customer
     * @return Person|null
     */
    private function resolveCustomerPerson($customer)
    {
        $customer = trim((string) $customer);

        if ($customer === '') {
            return null;
        }

        $personQuery = Person::with('identity_document_type');

        $person = (clone $personQuery)->where('name', $customer)->first();
        if ($person) {
            return $person;
        }

        $person = (clone $personQuery)->where('number', $customer)->first();
        if ($person) {
            return $person;
        }

        if (preg_match('/^(\d+)\s*[-–]\s*(.+)$/u', $customer, $matches)) {
            $number = trim($matches[1]);
            $name = trim($matches[2]);

            $person = (clone $personQuery)
                ->where('number', $number)
                ->when($name !== '', function ($query) use ($name) {
                    return $query->where('name', $name);
                })
                ->first();

            if ($person) {
                return $person;
            }
        }

        return null;
    }
}
