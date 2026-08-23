<?php

namespace Modules\MobileApp\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class FinanceExpenseRequest extends FormRequest
{
    // Tipo de gasto con numero opcional ("Otros" en la web)
    const NUMBER_OPTIONAL_TYPE_ID = 4;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'expense_type_id' => ['required', 'integer', 'exists:tenant.expense_types,id'],
            'expense_reason_id' => ['required', 'integer', 'exists:tenant.expense_reasons,id'],
            // Regla que la web valida solo en el cliente: numero obligatorio salvo tipo "Otros"
            'number' => [
                'nullable',
                'string',
                'max:100',
                'required_unless:expense_type_id,' . self::NUMBER_OPTIONAL_TYPE_ID,
            ],
            'supplier_id' => ['required', 'integer', 'exists:tenant.persons,id'],
            'date_of_issue' => ['required', 'date_format:Y-m-d'],
            'time_of_issue' => ['required'],
            'currency_type_id' => ['required', 'string'],
            'establishment_id' => ['required', 'integer'],
            'total' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.total' => ['required', 'numeric', 'gt:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.expense_method_type_id' => ['required', 'integer'],
            'payments.*.payment' => ['required', 'numeric', 'gt:0'],
            // Destino requerido salvo metodo 1 (caja)
            'payments.*.payment_destination_id' => ['required_unless:payments.*.expense_method_type_id,1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $total = (float) $this->input('total', 0);
            $payments = collect($this->input('payments', []))->sum('payment');
            if ($payments - $total > 0.01) {
                $v->errors()->add('payments', 'Los montos ingresados superan el monto total.');
            }
        });
    }

    public function messages()
    {
        return [
            'number.required_unless' => 'El número del comprobante es obligatorio para este tipo de gasto.',
            'items.required' => 'Agrega al menos un detalle.',
            'payments.required' => 'Agrega al menos un método de gasto.',
        ];
    }
}
