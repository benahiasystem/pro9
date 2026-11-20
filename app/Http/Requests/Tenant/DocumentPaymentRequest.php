<?php

namespace App\Http\Requests\Tenant;

use App\Rules\PositiveBigint;
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
            'document_id' => ['bail', 'required', new PositiveBigint(), Rule::exists('tenant.documents', 'id')],
            'currency_type_id' => ['sometimes', Rule::in(['VES','USD'])],
            'original_amount' => ['sometimes','numeric','gt:0'],
            'exchange_rate' => ['sometimes','numeric','gt:0'],
            'exchange_rate_date' => ['nullable','date'],
            'exchange_rate_source' => ['nullable','string','max:255'],
            'operation_key' => ['required_with:currency_type_id','uuid'],
            // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            // Legacy syntax is accepted only for a genuine replay, resolved and
            // checked under lock by FiscalDocumentPersistence. New receipts reject it.
            'igtf_status' => ['sometimes', Rule::in(['subject','exempt','not_applicable'])],
            // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            'quoted_tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'exemption_reason' => ['nullable','string','max:255'],
            'exception_basis' => ['nullable','string','max:255'],
            'financial_intermediation' => ['nullable',Rule::in(['none','financial_institution'])],
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
