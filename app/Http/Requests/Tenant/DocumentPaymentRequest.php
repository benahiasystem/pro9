<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentPaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'date_of_payment' => [
                'date',
                'required',
            ],
            'payment_method_type_id' => [
                'required',
                Rule::exists('tenant.payment_method_types', 'id')->where('is_active', 1),
            ],
            'payment_destination_id' => [
                'required',
            ],
            'payment' => [
                'required',
                'gt:0',
            ],
        ];
    }
}