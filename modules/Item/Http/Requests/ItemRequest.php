<?php

namespace Modules\Item\Http\Requests;

use App\Traits\SunatItemCodeTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\InventoryConfiguration;


class ItemRequest extends FormRequest
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

        //validar configuracion de codigo interno automatico para aplicar restriccion
        $generate_internal_id = InventoryConfiguration::getRecordIndividualColumn('generate_internal_id');

        $id = $this->input('id');

        return [
            'internal_id' => [
                $generate_internal_id ? 'nullable' : 'required',
                Rule::unique('tenant.items')->ignore($id),
            ],
            'item_code' => $this->getSunatItemCodeRules(),
            'description' => [
                'required',
            ],
            'unit_type_id' => [
                'required',
            ],
            'currency_type_id' => [
                'required'
            ],
            'sale_unit_price' => [
                'required',
                'numeric',
                'gt:0'
            ],
            'purchase_unit_price' => [
                'required', 'numeric'
            ],
            'stock' => [
                'required',
            ],
            'stock_min' => [
                'required',
            ],
            'sale_affectation_igv_type_id' => [
                'required'
            ],
            'purchase_affectation_igv_type_id' => [
                'required'
            ]

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