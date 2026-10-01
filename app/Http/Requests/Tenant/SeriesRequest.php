<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeriesRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $value = $this->input('number');
        if ($value === null || is_string($value)) {
            $this->merge(['number' => \App\Services\SeriesNumbering::normalizeCode($value)]);
        }
    }


    public function authorize()
    {
        return $this->user() && $this->user()->type === 'admin';
    }

    public function rules()
    {

        $id = $this->input('id');
        $validate_number = $this->advancedValidationNumber();

        return [
            'establishment_id' => 'required|integer|exists:tenant.establishments,id',
            'correlative' => 'sometimes|integer|min:1|max:2147483647',
            'dedicated' => 'sometimes|boolean',
            'contingency' => 'sometimes|boolean',
            'document_type_id' => ['required', \Illuminate\Validation\Rule::in(array_unique(array_column(\App\Services\SeriesCodeGenerator::availableTypes(false), 'document_type_id')))],
            'number' => $validate_number,
        ];
    }


    /** Optional local series, shared by every document type and emission mode. */
    public function advancedValidationNumber()
    {
        if ($this->input('number') === null || (is_string($this->input('number')) && trim($this->input('number')) === '')) {
            return ['nullable', 'string'];
        }
        return ['nullable', 'string', 'max:20', 'regex:/\A[A-Za-z0-9-]*\z/'];
    }

    public function messages()
    {
        return [
            'number.max' => 'La serie debe tener hasta 20 caracteres.',
            'number.string' => 'La serie debe ser un texto.',
            'number.regex' => 'La serie sólo admite letras, números y guiones (-), sin espacios internos ni otros símbolos.',
        ];
    }

}
