<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
	public function authorize()
	{
		return true;
	}

	public function rules()
	{
		return [
			'supplier_id' => [
				'required',
			],
			'number' => [
				'required',
				'numeric'
			],
			'series' => [
				'required',
			],
			'date_of_issue' => [
				'required',
			],
            'items' => [
                'required',
                'array',
            ],
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            'payments.*.payment_method_type_id' => [
                'required', Rule::exists('tenant.payment_method_types', 'id')->where('is_active', 1),
            ],
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
		];
	}
}
