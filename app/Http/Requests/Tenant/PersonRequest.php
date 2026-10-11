<?php

namespace App\Http\Requests\Tenant;

use App\Support\Venezuela\Localization;
use App\Support\Venezuela\IdentityDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    // ########### INICIO CAMBIO CLIENTES VENEZUELA
    private const FOREIGN_DOCUMENT_TYPE_ID = 'E';
    private const LOCATION_EXEMPT_DOCUMENT_TYPE_IDS = ['E', '7', 'ND'];

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== 'customers') {
            return;
        }

        $identityDocumentTypeId = (string) $this->input('identity_document_type_id');
        $isNonDomiciled = $identityDocumentTypeId === 'ND';
        $countryId = $isNonDomiciled ? $this->input('country_id') : Localization::countryId();
        // ######## INICIO CORRECCION GUARDADO CLIENTES VENEZUELA
        $addresses = $this->input('addresses');
        if ($this->isLocationExemptDocument($identityDocumentTypeId)) {
            $addresses = $this->clearAddressLocations($addresses);
        }
        $addresses = $this->normalizeCustomerAddresses($addresses);

        foreach ($addresses as &$address) {
            $address['country_id'] = $isNonDomiciled ? ($address['country_id'] ?? $countryId) : $countryId;
        }
        unset($address);
        // ######## FIN CORRECCION GUARDADO CLIENTES VENEZUELA

        $normalized = [
            'country_id' => $countryId,
            'addresses' => $addresses,
            // El formulario de clientes recibe únicamente dígitos; el prefijo
            // corresponde a la presentación y no debe normalizar entradas inválidas.
            'number' => (string) $this->input('number'),
            'external_identity_type' => $isNonDomiciled ? $this->input('external_identity_type') : null,
        ];

        if ($this->isLocationExemptDocument($identityDocumentTypeId)) {
            $normalized = array_merge($normalized, [
                'location_id' => [],
                'department_id' => null,
                'province_id' => null,
                'district_id' => null,
            ]);
        }

        if ($isNonDomiciled) {
            $normalized['nationality_id'] = null;
        } elseif ($identityDocumentTypeId !== self::FOREIGN_DOCUMENT_TYPE_ID) {
            $normalized['nationality_id'] = $countryId;
        }

        $this->merge($normalized);
    }

    // ######## INICIO CORRECCION GUARDADO CLIENTES VENEZUELA
    /**
     * El formulario conserva filas vacías para permitir añadir establecimientos.
     * Esas filas no representan una dirección y no deben bloquear ni ensuciar el
     * guardado; además, payloads heredados pueden enviar addresses como null.
     */
    private function normalizeCustomerAddresses($addresses): array
    {
        if (!is_array($addresses)) {
            return [];
        }

        return array_values(array_filter($addresses, static function ($address): bool {
            if (!is_array($address)) {
                return false;
            }

            foreach (['address', 'phone', 'email', 'location_id', 'department_id', 'province_id', 'district_id'] as $field) {
                $value = $address[$field] ?? null;

                if (is_array($value) ? !empty(array_filter($value, static fn ($item) => $item !== null && $item !== '')) : $value !== null && $value !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Extranjero y Pasaporte no conservan la jerarquia territorial venezolana,
     * ni siquiera cuando una edicion envia valores antiguos desde el navegador.
     */
    private function clearAddressLocations($addresses): array
    {
        if (!is_array($addresses)) {
            return [];
        }

        return array_map(static function ($address) {
            if (!is_array($address)) {
                return $address;
            }

            $address['location_id'] = [];
            $address['department_id'] = null;
            $address['province_id'] = null;
            $address['district_id'] = null;

            return $address;
        }, $addresses);
    }

    private function isLocationExemptDocument(string $identityDocumentTypeId): bool
    {
        return in_array($identityDocumentTypeId, self::LOCATION_EXEMPT_DOCUMENT_TYPE_IDS, true);
    }
    // ######## FIN CORRECCION GUARDADO CLIENTES VENEZUELA

    public function rules()
    {
        $id = $this->input('id');
        $type = $this->input('type');
        $email = $this->input('email');
        $isForeignCustomer = $type === 'customers'
            && (string) $this->input('identity_document_type_id') === self::FOREIGN_DOCUMENT_TYPE_ID;
        $isNonDomiciled = $type === 'customers' && $this->input('identity_document_type_id') === 'ND';
        $numberRules = [
            'required',
            Rule::unique('tenant.persons', 'number')->where(function ($query) use($id, $type, $isNonDomiciled) {
                $query->where('type', $type);
                if ($isNonDomiciled) {
                    $query->where('identity_document_type_id', 'ND')
                        ->where('country_id', $this->input('country_id'))
                        ->where('external_identity_type', $this->input('external_identity_type'));
                } else {
                    $query->where('identity_document_type_id', '<>', 'ND');
                }
            })->ignore($id, 'id'),
        ];

        if ($type === 'customers') {
            if ($isNonDomiciled) {
                $numberRules[] = 'regex:/^[A-Za-z0-9][A-Za-z0-9.-]{0,44}$/D';
            } elseif ((string) $this->input('identity_document_type_id') === '1') {
                $numberRules[] = 'regex:/^[0-9]{6,8}$/';
            } else {
                $numberRules[] = 'regex:/^[0-9]{1,20}$/';
            }
        }

        return [
            'number' => $numberRules,
            'name' => [
                'required',
                Rule::unique('tenant.persons', 'name')->where(function ($query) use($id, $type) {
                    $query->where('type', $type);
                })->ignore($id, 'id')
            ],
            'identity_document_type_id' => [
                'required',
                Rule::in($type === 'customers' ? IdentityDocument::ids() : array_diff(IdentityDocument::ids(), ['ND'])),
            ],
            'external_identity_type' => $isNonDomiciled ? ['required', 'string', 'regex:/^[A-Z]{2,4}$/D', 'not_in:ND'] : ['nullable'],
            'country_id' => [
                'required',
                Rule::exists('tenant.countries', 'id'),
                Rule::notIn($isNonDomiciled ? ['VE'] : []),
            ],
            'addresses.*.country_id' => $isNonDomiciled ? ['required', Rule::exists('tenant.countries', 'id'), 'not_in:VE'] : [],
            'nationality_id' => [
                Rule::requiredIf($isForeignCustomer),
                'nullable',
                Rule::exists('tenant.countries', 'id'),
                Rule::notIn($isForeignCustomer ? [Localization::countryId()] : []),
            ],
            // 'person_type_id' => [
            //     'required_if:type,"customers"',
            // ],
            'department_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'province_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'district_id' => [
                'required_if:identity_document_type_id,"066"',
            ],
            'address' => [
                Rule::requiredIf($isNonDomiciled),
                'max:255',
                'required_if:identity_document_type_id,"066"',
            ],
            'email' => [
                isset($email) ? 'required' : 'nullable',
                'email',
                Rule::unique('tenant.persons', 'email')->ignore($id, 'id')
            ]
            ,
            'internal_code' => 'max:100'
        ];
    }

    public function messages(): array
    {
        return [
            'nationality_id.required' => 'La nacionalidad es obligatoria para un cliente extranjero.',
            'nationality_id.not_in' => 'Seleccione una nacionalidad extranjera.',
            'number.regex' => $this->input('identity_document_type_id') === 'ND' ? 'El identificador extranjero admite letras, números, punto y guion, hasta 45 caracteres.' : 'El campo número debe contener solo números.',
        ];
    }
    // ########### FIN CAMBIO CLIENTES VENEZUELA
}
