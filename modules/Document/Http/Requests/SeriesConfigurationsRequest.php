<?php

namespace Modules\Document\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeriesConfigurationsRequest extends FormRequest
{
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
                'required',
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