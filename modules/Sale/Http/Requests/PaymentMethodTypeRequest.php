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
        ];
        return $classification + [
            'id' => [
                'required',
                'string',
                'max:2',
                $idRule,
            ],
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
