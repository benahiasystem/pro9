<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
        $exact=function($attribute,$value,$fail) {
            try { $amount=\App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::input($value,$attribute)); if ($amount->isNegative()) $fail('El saldo no puede ser negativo.'); }
            catch (\Throwable $error) { $fail('Indique un importe decimal válido.'); }
        };
        return [
            'opening_balances'=>['sometimes','array:VES,USD'],
            'opening_balances.VES'=>['sometimes',$exact],
            'opening_balances.USD'=>['sometimes',$exact],
        // ######## FIN CLASIFICACION DE COBROS Y CAJA ########
            'beginning_balance' => [
                'required',
                'numeric',
                'min:0',
            ],
        ];
    }
}
