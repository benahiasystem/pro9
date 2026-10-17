<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationRequest extends FormRequest
{
    public function authorize() {
        return true;
    }

    public function rules() {
        $id = $this->input('id');

        return [
            'cron' => ['required', 'boolean'],
            'auto_send_hka' => ['sometimes', 'boolean'],
            'decimal_quantity' => ['required', 'integer'],
            // ######## INICIO CALCULOS EXACTOS PRO9 ########
            'change_decimal_quantity_unit_price_pdf' => ['sometimes', 'boolean'],
            'decimal_quantity_unit_price_pdf' => ['sometimes', 'required', 'integer', 'between:2,12'],
            'point_system_sale_amount' => ['required_if:enabled_point_system,true',new \App\Rules\ExactDecimal('0', (bool)$this->input('enabled_point_system'))],
            'quantity_of_points' => ['sometimes',new \App\Rules\ExactDecimal('0')],
            // ######## FIN CALCULOS EXACTOS PRO9 ########


            // 'subtotal_account' => ['required'],
            // 'stock' => ['required', 'boolean']
        ];
    }
}
