<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationEcommerceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'information_contact_email' => [
                'required',
            ],
            'information_contact_name' => [
                'required',
            ],
            'information_contact_phone' => [
                'required',
            ],
            'quotation_enabled' => [
                'sometimes',
                'boolean',
            ],
            'quotation_mode' => [
                'sometimes',
                'string',
                Rule::in(['quote_and_sell', 'quote_only']),
            ],
            'quotation_show_prices' => [
                'sometimes',
                'boolean',
            ],
            'quotation_success_message' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'quotation_validity_days' => [
                'sometimes',
                'integer',
                'min:1',
                'max:90',
            ],
            'quotation_terms' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages()
    {
        return [
            'information_contact_email.required' => 'El campo Email de motivo de traslado es obligatorio.',
            'information_contact_name.required' => 'El campo Nombre es obligatorio.',
            'information_contact_phone.required' => 'El campo Telefono es obligatorio.',
            'quotation_mode.in' => 'El modo de cotización seleccionado no es válido.',
            'quotation_success_message.max' => 'El mensaje de respuesta no puede superar los 2000 caracteres.',
            'quotation_validity_days.min' => 'La vigencia mínima es de 1 día.',
            'quotation_validity_days.max' => 'La vigencia máxima es de 90 días.',
            'quotation_terms.max' => 'Las condiciones comerciales no pueden superar los 5000 caracteres.',
        ];
    }

    /**
     * Normaliza switches enviados como 0/1 desde el panel admin.
     */
    protected function prepareForValidation()
    {
        $booleanFields = ['quotation_enabled', 'quotation_show_prices'];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                        ?? (bool) $this->input($field),
                ]);
            }
        }
    }
}
