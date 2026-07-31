<?php

namespace Modules\Item\Http\Requests;

use App\Traits\SunatItemCodeTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemUpdateRequest extends FormRequest
{
    use SunatItemCodeTrait;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->prepareSunatItemCode();
    }

    public function rules()
    {
        $id = $this->id;
        return [
            'internal_id' => [
                'required',
                'max:30',
                Rule::unique('tenant.items')->ignore($id),
            ],
            'barcode' => [
                'max:150',
            ],
            'model' => [
                'max:100',
            ],
            'has_igv' => 'boolean',
            'item_code' => $this->getSunatItemCodeRules(),
            'description' => [
                'required',
                'max:500',
            ],
            'sale_unit_price' => [
                'required',
                'numeric',
                'gt:0'
            ],
            'stock_min' => [
                'required',
            ],
        ];
    }

    public function messages()
    {
        return array_merge([
            'description.required' => 'El campo nombre es obligatorio.',
            'sale_unit_price.gt' => 'El precio unitario de venta debe ser mayor que 0.',
        ], $this->getSunatItemCodeMessages());
    }
}
