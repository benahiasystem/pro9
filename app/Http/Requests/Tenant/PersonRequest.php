<?php

namespace App\Http\Requests\Tenant;

use App\Support\Venezuela\Localization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    // ########### INICIO CAMBIO CLIENTES VENEZUELA
    private const FOREIGN_DOCUMENT_TYPE_ID = '4';

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') !== 'customers') {
            return;
        }

        $countryId = Localization::countryId();
        $identityDocumentTypeId = (string) $this->input('identity_document_type_id');
        $addresses = collect($this->input('addresses', []))
            ->map(static function ($address) use ($countryId) {
                if (!is_array($address)) {
                    return $address;
                }

                $address['country_id'] = $countryId;

                return $address;
            })
            ->all();

        $normalized = [
            'country_id' => $countryId,
            'addresses' => $addresses,
        ];

        if ($identityDocumentTypeId !== self::FOREIGN_DOCUMENT_TYPE_ID) {
            $normalized['nationality_id'] = $countryId;
        }

        $this->merge($normalized);
    }

    public function rules()
    {
        $id = $this->input('id');
        $type = $this->input('type');
        $email = $this->input('email');
        $isForeignCustomer = $type === 'customers'
            && (string) $this->input('identity_document_type_id') === self::FOREIGN_DOCUMENT_TYPE_ID;
        $numberRules = [
            'required',
            Rule::unique('tenant.persons', 'number')->where(function ($query) use($id, $type) {
                $query->where('type', $type);
            })->ignore($id, 'id'),
        ];

        if ($type === 'customers') {
            if ((string) $this->input('identity_document_type_id') === '6') {
                $numberRules[] = 'regex:/^[VEJGP][0-9]{9}$/i';
            } elseif ((string) $this->input('identity_document_type_id') === '1') {
                $numberRules[] = 'regex:/^[0-9]{6,8}$/';
            } elseif ($isForeignCustomer) {
                $numberRules[] = 'regex:/^[A-Z0-9-]{1,20}$/i';
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
            ],
            'country_id' => [
                'required',
                Rule::exists('tenant.countries', 'id'),
            ],
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
            'number.regex' => 'El formato del documento de identidad no es válido.',
        ];
    }
    // ########### FIN CAMBIO CLIENTES VENEZUELA
}
