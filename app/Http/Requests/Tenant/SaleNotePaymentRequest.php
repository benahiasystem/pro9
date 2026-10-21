<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleNotePaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            'id' => ['nullable','integer','min:1'],
            'sale_note_id' => ['required','integer','min:1',Rule::exists('tenant.sale_notes','id')],
            // ######## FIN CALCULOS EXACTOS PRO9 ########
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
                // ######## INICIO CALCULOS EXACTOS PRO9 ########
                'bail','required',new \App\Rules\ExactDecimal('0',true),
                function ($attribute,$value,$fail) { if (is_float($value)) $fail('Transmita el importe decimal como texto.'); },
                // ######## FIN CALCULOS EXACTOS PRO9 ########
            ],
        ];
    }
}
