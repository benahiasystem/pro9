<?php

namespace Modules\Dispatch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Venezuela\IdentityDocument;

class DispatcherRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            'number' => IdentityDocument::normalizeNumber('6', $this->input('number')),
        ]);
    }

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
                'regex:/^(?:\d{9}|\d{11})$/',
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
            'identity_document_type_id.in' => 'El transportista solo puede registrarse con RIF.',
            'number.required' => 'El número es obligatorio.',
            'number.unique' => 'Ya existe un transportista con este número.',
            'number.regex' => 'El RIF debe contener 9 dígitos (J-#########) o 11 dígitos numéricos.',
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
