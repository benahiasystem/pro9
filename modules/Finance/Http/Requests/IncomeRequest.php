<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeRequest extends FormRequest
{

    public function authorize()
    {
        return true;
    }

    public function rules()
    {

        return [
            'customer' => [
                'required',
            ],
            'income_reason_id' => [
                'required',
            ],
            'date_of_issue' => [
                'required',
            ],
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            'payments.*.payment_method_type_id' => [
                'required', Rule::exists('tenant.payment_method_types', 'id')->where('is_active', 1),
            ],
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        ];
    }
}
