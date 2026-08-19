<?php

namespace App\Http\Requests\System;

use App\Support\System\Rif;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientUpdateRequest extends FormRequest
{
    // ########## INICIO CAMBIO RIF SUPER ADMIN
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['number' => Rif::normalize($this->input('number'))]);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', Rule::exists('system.clients', 'id')],
            'number' => [
                'required',
                'regex:' . Rif::PATTERN,
                Rule::unique('system.clients', 'number')->ignore($this->input('id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'number.regex' => 'El RIF debe contener un prefijo V, E, J, P o G y nueve dígitos.',
            'number.unique' => 'El RIF ya está registrado en otro cliente.',
        ];
    }
    // ######### FIN CAMBIO RIF SUPER ADMIN
}
