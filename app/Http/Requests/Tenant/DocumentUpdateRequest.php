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
