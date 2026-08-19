<?php

namespace Modules\Dispatch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatcherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');

        return [
            'identity_document_type_id' => ['required', 'in:6'],
            'number' => [
                'required',
                'regex:/^(10|15|16|17|20)\d{9}$/',
                Rule::unique('tenant.dispatchers')->ignore($id),
            ],
            'name' => ['required', 'string', 'min:2', 'regex:/[A-Za-zÁÉÍÓÚáéíóúÑñ]/'],
            'address' => ['nullable', 'string', 'min:3', 'regex:/[A-Za-zÁÉÍÓÚáéíóúÑñ]/'],
            'number_mtc' => ['nullable', 'regex:/^[a-zA-Z0-9]+$/', 'max:12'],
        ];
    }

    public function messages()
    {
        return [
            'identity_document_type_id.required' => 'Seleccione el tipo de documento.',
            'identity_document_type_id.in' => 'El transportista solo puede registrarse con RUC.',
            'number.required' => 'El número es obligatorio.',
            'number.unique' => 'Ya existe un transportista con este número.',
            'number.regex' => 'El RUC debe tener 11 dígitos y un prefijo válido (10, 15, 16, 17 o 20).',
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.regex' => 'El nombre debe contener al menos una letra.',
            'address.min' => 'La dirección debe tener al menos 3 caracteres.',
            'address.regex' => 'La dirección debe contener al menos una letra.',
            'number_mtc.regex' => 'El MTC solo admite letras y números.',
            'number_mtc.max' => 'El MTC no debe superar los 12 caracteres.',
        ];
    }
}
