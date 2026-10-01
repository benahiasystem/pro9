<?php

namespace Modules\Document\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeriesConfigurationsRequest extends FormRequest
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
        return $this->user() && $this->user()->type === 'admin';
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'series_id' => [
                'required',
                // 'unique:tenant.series_configurations'
            ],
            'document_type_id' => [
                'required',
                // 'unique:tenant.series_configurations'
            ],
            'series' => [
                'nullable', 'string', 'max:20', 'regex:/\A[A-Za-z0-9-]*\z/',
            ],
            'number' => [
                'required',
                'numeric',
                'integer',
                'min:1', 'max:2147483647'
            ],
        ];
    }
}
