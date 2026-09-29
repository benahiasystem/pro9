<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class TransferReasonTypeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => [
                'required',
                'string',
                'size:2',
                'regex:/^\d{2}$/',
            ],
            'discount_stock' => [
                'required',
                'boolean',
            ],
        ];
    }
}
