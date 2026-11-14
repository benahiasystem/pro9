<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodTypeRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()?->type === 'admin';
    }

    public function rules()
    {
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $idRule = $this->isMethod('PUT')
            ? Rule::in([(string) $this->route('id')])
            : Rule::unique('tenant.payment_method_types', 'id');
        $classification=[
            'collection_kind'=>['required',Rule::in($this->isMethod('POST')
                ? ['cash', 'account', 'credit']
                : \App\Services\Payments\Classification::KINDS)],
            'currency_type_id'=>['required','string',Rule::exists('tenant.cat_currency_types','id')->where('active',1)],
            'iva_rate_type_id'=>['nullable',Rule::in(['IGTF']),Rule::exists('tenant.cat_iva_rate_types','id')->where('tax_kind','IGTF')],
            'is_active'=>['sometimes','boolean'],
            'available_for_change'=>['sometimes','boolean', function ($attribute, $value, $fail) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && !in_array($this->input('collection_kind'), ['cash', 'account'], true)) {
                    $fail('El vuelto requiere un medio de efectivo o cuenta.');
                }
            }],
            'bank_id'=>['required_if:collection_kind,account','nullable','integer'],
            'bank_account_id'=>['required_if:collection_kind,account','nullable','integer', function ($attribute, $value, $fail) {
                if ($this->input('collection_kind') !== 'account') return;
                $account = \App\Models\Tenant\BankAccount::query()->find($value);
                if (!$account || !$account->status || (string) $account->bank_id !== (string) $this->input('bank_id') || $account->currency_type_id !== $this->input('currency_type_id')) {
                    $fail('Seleccione una cuenta activa del banco y la moneda del método.');
                }
            }],
            'commission_percentage'=>['nullable', function ($attribute, $value, $fail) {
                try {
                    $amount = \App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::input($value, $attribute));
                    if ($amount->isNegative() || $amount->isGreaterThan('100')) $fail('La comisión debe estar entre 0 y 100 %.');
                } catch (\Throwable $error) { $fail('Indique un porcentaje válido.'); }
            }],
            'minimum_commission'=>['nullable', function ($attribute, $value, $fail) {
                try {
                    if (\App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::input($value, $attribute))->isNegative()) $fail('La comisión mínima no puede ser negativa.');
                } catch (\Throwable $error) { $fail('Indique una comisión mínima válida.'); }
            }],
        ];
        return $classification + [
            'id' => [
                'required',
                'string',
                'max:2',
                $idRule,
            ],
            'hka_code' => \App\Services\Payments\HkaPaymentMethods::emissionMode() !== 'digital' ? ['exclude'] : ['required', 'string', function ($attribute, $value, $fail) {
                if (!\App\Services\Payments\HkaPaymentMethods::compatible($value, $this->input('collection_kind'), $this->input('currency_type_id'))) {
                    $fail('Seleccione un medio fiscal compatible con la naturaleza y moneda.');
                }
            }],
            'description' => [
                'required',
            ], 
        ];
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    public function messages()
    {
        return ['id.unique' => 'El código del método de pago ya existe.'];
    }
}
