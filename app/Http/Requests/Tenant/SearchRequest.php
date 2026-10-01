<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $value = $this->input('series');
        if ($value === null || is_string($value)) {
            $this->merge(['series' => \App\Services\SeriesNumbering::normalizeCode($value)]);
        }
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'establishment_id' => ['nullable', 'integer', 'exists:tenant.establishments,id'],
            'document_type_id' => [
                'required',
            ],
            'series' => [
                'nullable', 'string', 'max:20', 'regex:/\A[A-Za-z0-9-]*\z/',
            ],
            'number' => [
                'required',
            ],
            'date_of_issue' => [
                'required',
            ],
            'customer_number' => [
                'required',
            ],
            'total' => [
                'required',
            ],
        ];
    }
}
