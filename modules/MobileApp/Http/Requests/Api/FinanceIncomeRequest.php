<?php

namespace Modules\MobileApp\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FinanceIncomeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'income_type_id' => ['required', 'integer', 'exists:tenant.income_types,id'],
            'income_reason_id' => ['required', 'integer', 'exists:tenant.income_reasons,id'],
            'date_of_issue' => ['required', 'date_format:Y-m-d'],
            'time_of_issue' => ['required'],
            'currency_type_id' => ['required', 'string'],
            'establishment_id' => ['required', 'integer'],
            'total' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.total' => ['required', 'numeric', 'gt:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_type_id' => ['required'],
            'payments.*.payment_destination_id' => ['required'],
            'payments.*.payment' => ['required', 'numeric', 'gt:0'],
        ];
    }

    // La web valida esto solo en el cliente: la suma de pagos debe igualar el total.
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $total = (float) $this->input('total', 0);
            $payments = collect($this->input('payments', []))->sum('payment');
            if (abs($payments - $total) > 0.01) {
                $v->errors()->add('payments', 'Los montos ingresados no coinciden con el monto total.');
            }
        });
    }

    public function messages()
    {
        return [
            'items.required' => 'Agrega al menos un detalle.',
            'payments.required' => 'Agrega al menos un método de ingreso.',
        ];
    }
}
