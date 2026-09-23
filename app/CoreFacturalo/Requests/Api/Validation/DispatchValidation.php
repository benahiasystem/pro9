<?php

namespace App\CoreFacturalo\Requests\Api\Validation;

class DispatchValidation
{
    public static function validation($inputs)
    {
        // ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
        if (($inputs['document_type_id'] ?? null) !== '09') throw new \DomainException('Tipo de orden de entrega no admitido.');
        if (!empty($inputs['establishment'])) $inputs['establishment_id'] = Functions::establishment($inputs['establishment']);
        unset($inputs['establishment']);
        $inputs = \App\Services\Fiscal\FiscalApiDocumentContext::prepareFor($inputs, auth()->user(),
            \App\Models\Tenant\Company::active()->getConnection(), app(\App\Services\SeriesResolver::class)->activeGroupId());
        \Illuminate\Support\Facades\Validator::make($inputs, [
            'transshipment_indicator' => ['required', 'boolean'],
            'time_of_issue' => ['required', 'date_format:H:i:s'],
            'total_weight' => ['required', 'numeric', 'gt:0'], 'packages_number' => ['nullable', 'integer', 'min:1'],
            'driver' => [\Illuminate\Validation\Rule::requiredIf(($inputs['transport_mode_type_id'] ?? null) === '02' && empty($inputs['is_transport_m1l'])), 'nullable', 'array'],
            'driver.identity_document_type_id' => ['required_with:driver'],
            'driver.number' => ['required_with:driver', 'string', 'max:20'],
            'driver.name' => ['required_with:driver', 'string', 'max:255'],
            'dispatcher' => ['required_if:transport_mode_type_id,01', 'nullable', 'array'],
            'dispatcher.identity_document_type_id' => ['required_with:dispatcher'],
            'dispatcher.number' => ['required_with:dispatcher', 'string', 'max:20'],
            'dispatcher.name' => ['required_with:dispatcher', 'string', 'max:255'],
            'date_of_issue' => ['required', 'date_format:Y-m-d'], 'date_of_shipping' => ['required', 'date_format:Y-m-d'],
            'customer' => ['required', 'array'], 'customer.identity_document_type_id' => ['required'],
            'customer.number' => ['required', 'string'], 'customer.name' => ['required', 'string'],
            'origin' => ['required', 'array'], 'origin.address' => ['required', 'string', 'max:100'],
            'origin.location_id' => ['required'], 'delivery' => ['required', 'array'],
            'delivery.address' => ['required', 'string', 'max:100'], 'delivery.location_id' => ['required'],
            'transport_mode_type_id' => ['required', 'exists:tenant.cat_transport_mode_types,id'],
            'transfer_reason_type_id' => ['required', 'exists:tenant.cat_transfer_reason_types,id'],
            'items' => ['required', 'array', 'min:1'], 'items.*.internal_id' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['nullable', 'numeric', 'min:0'],
        ])->validate();
        \App\Services\SalesCustomerIdentityPolicy::assertIdentityTypeAllowed($inputs['customer']['identity_document_type_id']);
        foreach (['origin', 'delivery'] as $field) {
            $location = $inputs[$field]['location_id'];
            $district = is_array($location) ? ($location[2] ?? null) : $location;
            if (!$district) throw \Illuminate\Validation\ValidationException::withMessages([$field . '.location_id' => 'Indique una parroquia válida.']);
            $resolved = \App\Support\Venezuela\PersonLocation::resolve(\App\Models\Tenant\Company::active()->getConnection(), 'VE', $district);
            if (is_array($location) && array_values($location) !== array_values($resolved)) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field . '.location_id' => 'La jerarquía territorial no corresponde a la parroquia.']);
            }
            $inputs[$field]['location_id'] = $resolved['district_id'];
        }
        \App\Models\Tenant\Catalogs\UnitType::requireActiveCode($inputs['unit_type_id'] ?? null);
        foreach ($inputs['items'] as $item) \App\Models\Tenant\Catalogs\UnitType::requireActiveCode($item['unit_type_id'] ?? null);
        return $inputs;
        // ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
    }

    public static function materialize(array $inputs): array
    {
        $inputs['customer_id'] = Functions::person($inputs['customer'], 'customers');
        unset($inputs['customer']);

        $inputs['items'] = self::items($inputs['items']);

        return $inputs;
    }

    private static function items($inputs)
    {
        $items = [];
        foreach ($inputs as $row)
        {

            $id = Functions::item2($row);

            $items[] = [
                'item_id' => $id,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'total' => $row['total'],
                'additional_data' => $row['additional_data']
            ];
        }

        return $items;
    }
}
