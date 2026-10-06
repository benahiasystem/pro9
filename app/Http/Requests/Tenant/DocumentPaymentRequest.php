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
            'document_id' => ['required', 'integer', Rule::exists('tenant.documents', 'id')],
            'currency_type_id' => ['sometimes', Rule::in(['VES','USD'])],
            'original_amount' => ['sometimes','numeric','gt:0'],
            'exchange_rate' => ['sometimes','numeric','gt:0'],
            'exchange_rate_date' => ['nullable','date'],
            'exchange_rate_source' => ['nullable','string','max:255'],
            'operation_key' => ['required_with:currency_type_id','uuid'],
            'igtf_status' => ['sometimes', Rule::in(['subject','exempt','not_applicable'])],
            'exemption_reason' => ['nullable','string','max:255'],
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
                'required_without:original_amount',
                'numeric',
                'gt:0',
            ],
        ];
    }
}