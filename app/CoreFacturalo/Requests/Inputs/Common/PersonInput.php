<?php

namespace App\CoreFacturalo\Requests\Inputs\Common;

use App\Models\Tenant\Person as PersonModel;
use App\Models\Tenant\PersonAddress;
use App\Support\Venezuela\PersonLocation;

class PersonInput
{
    public static function set($person_id, $address_id = null, $itinerant = false)
    {
        $person = PersonModel::find($person_id);

        if(!$person) {
            return null;
        }

        $customer_address = null;
        $telephone = $person->telephone;
        if($address_id)
        {
            $customer_address = PersonAddress::where('person_id', $person->id)->find($address_id);
            if (!$customer_address) {
                \App\Services\Fiscal\FiscalAmounts::error('customer_address_id', 'La dirección seleccionada no pertenece al cliente.');
            }
            $telephone = $customer_address->telephone ?: $telephone;
        } else if($itinerant) {
            $itinerantAddress = validator((array) $itinerant, [
                'address' => 'required|string|max:255',
                'country_id' => 'required|in:VE',
                'district_id' => 'nullable|string',
                'telephone' => 'nullable|string',
            ])->validate();
            $territory = PersonLocation::resolve($person->getConnection(), $itinerantAddress['country_id'], $itinerantAddress['district_id'] ?? null);
            $customer_address = new PersonAddress($territory + [
                'address' => $itinerantAddress['address'],
                'country_id' => $itinerantAddress['country_id'],
            ]);
            $telephone = ($itinerantAddress['telephone'] ?? null) ?: $telephone;
        }
        $address = $customer_address ?? $person;
        $location = $itinerant ? $address : $person;

        if ($person->identity_document_type_id === 'ND') {
            if (!$address->country_id || $address->country_id === 'VE' || !trim((string) $address->address)) {
                \App\Services\Fiscal\FiscalAmounts::error('customer_address_id', 'Seleccione una dirección extranjera completa.');
            }
        }

        return [
            'identity_document_type_id' => $person->identity_document_type_id,
            'external_identity_type' => $person->external_identity_type,
            'identity_document_type' => [
                'id' => $person->identity_document_type_id,
                'description' => $person->identity_document_type->description,
            ],
            'number' => $person->number,
            'name' => $person->name,
            'trade_name' => $person->trade_name,
            'country_id' => $address->country_id,
            'country' => [
                'id' => $address->country_id,
                'description' => optional($location->country)->description,
            ],
            'department_id' => $address->department_id,
            'department' => [
                'id' => $address->department_id,
                'description' => optional($location->department)->description,
            ],
            'province_id' => $address->province_id,
            'province' => [
                'id' => $address->province_id,
                'description' => optional($location->province)->description,
            ],
            'district_id' => $address->district_id,
            'district' => [
                'id' => $address->district_id,
                'description' => optional($location->district)->description,
            ],
            'address' => $address->address,
            'email' => $person->email,
            'telephone' => $telephone,
            'address_id' => $address_id,
            'internal_code' => $person->internal_code,
            'address_type_id' => $person->address_type_id,
            'address_type' => [
                'id' => $person->address_type_id,
                'description' => optional($person->address_type)->description,
            ],
        ];
    }
}
