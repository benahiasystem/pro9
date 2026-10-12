<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class DocumentUpdateRequest
 *
 * @package App\Http\Requests\Tenant
 * @mixin FormRequest
 */
class DocumentUpdateRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'control_number' => 'prohibited',
            'issuer' => 'prohibited',
            'third_party_id' => 'nullable|integer|min:1',
            'third_party' => 'prohibited',
            'fiscal_data' => 'nullable|array',
            'fiscal_data.special_tax_regime_id' => 'nullable|integer|between:1,7',
            'fiscal_data.catalog_snapshot' => 'prohibited',
            'fiscal_data.special_tax_regime_description' => 'prohibited',
            'fiscal_data.regimenEspTributacion' => 'prohibited',
            'fiscal_data.third_party' => 'prohibited',
            'fiscal_data.third_party_person_id' => 'prohibited',
            'id' => 'required|numeric',
            'customer_id' => [
                'required',
            ],
            'establishment_id' => [
                'required',
            ],
            // A configured empty series is a valid fiscal identity.
            'series' => ['nullable', 'string', 'max:20'],
            'date_of_issue' => [
                'required',
            ],
            'exchange_rate_sale' => [
                'required',
                'numeric',
                'min:0.01'
            ],
        ];
    }
}
