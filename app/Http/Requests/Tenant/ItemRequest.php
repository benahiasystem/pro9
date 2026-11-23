<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Catalogs\UnitType;
use App\Traits\SunatItemCodeTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class ItemRequest
 *
 * @package App\Http\Requests\Tenant
 * @mixin FormRequest
 */
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
        $id = $this->input('id');
        return [
            'internal_id' => [
                'nullable',
                Rule::unique('tenant.items')->ignore($id),
            ],
            'item_code' => $this->getSunatItemCodeRules(),
            'description' => [
                'required', 'max:600'
            ],
            'name' => [
                'max:1000'
            ],
            'second_name' => [
                'max:600'
            ],
            // 'name' => [
            //     'required',
            // ],
            // 'second_name' => [
            //     'required',
            // ],
            'unit_type_id' => [
                'required',
                UnitType::activeValidationRule(),
            ],
            'currency_type_id' => [
                'required'
            ],
            'sale_unit_price' => [
                'required',
                'numeric',
                'gt:0',
                ...(in_array('sale_unit_price', (array) $this->input('exact_edited_fields', []), true) ? [new \App\Rules\ExactDecimal()] : []),
            ],
            'exact_edited_fields' => ['sometimes', 'array'],
            'exact_edited_fields.*' => ['string', Rule::in(['sale_unit_price', 'purchase_unit_price', 'percentage_of_profit'])],
            'exact_profit_base' => ['required_with:exact_profit_sequence', 'array'],
            'exact_profit_sequence' => ['sometimes', 'array', 'max:1024'],
            'exact_profit_sequence.*.field' => ['required', Rule::in(['sale_unit_price', 'purchase_unit_price', 'percentage_of_profit'])],
            'exact_profit_sequence.*.value' => ['required', new \App\Rules\ExactDecimal()],
            'exact_profit_sequence.*.recalculate' => ['sometimes', 'boolean'],
            'purchase_unit_price' => [
                'required', 'numeric',
                ...(in_array('purchase_unit_price', (array) $this->input('exact_edited_fields', []), true) ? [new \App\Rules\ExactDecimal()] : []),
            ],
            'percentage_of_profit' => [
                Rule::requiredIf(in_array('percentage_of_profit', (array) $this->input('exact_edited_fields', []), true)),
                'nullable', 'numeric',
                ...(in_array('percentage_of_profit', (array) $this->input('exact_edited_fields', []), true) ? [new \App\Rules\ExactDecimal()] : []),
            ],
            'quantity_of_points' => ['sometimes',new \App\Rules\ExactDecimal('0')],
            'stock' => [
                'required',
                // 'gt:0'
            ],
            'stock_min' => [
                'required',
                // 'gt:0'
            ],
            'sale_affectation_igv_type_id' => [
                'required',
                Rule::in(\App\Support\Venezuela\Localization::selectableAffectationIds()),
            ],
            'purchase_affectation_igv_type_id' => [
                'required',
                Rule::in(\App\Support\Venezuela\Localization::selectableAffectationIds()),
            ],
            // 'category_id' => [
            //     'required_if:is_set,false',
            // ],
            // 'brand_id' => [
            //     'required_if:is_set,false',
            // ],
            'model' => 'max:100',

            'item_unit_types' => [
                'nullable',
                'array',
            ],
            'item_unit_types.*.description' => [
                'required',
                'string',
                'max:255',
            ],
            'item_unit_types.*.unit_type_id' => [
                'required',
                UnitType::activeValidationRule(),
            ],
            'item_unit_types.*.quantity_unit' => [
                'required',
                'numeric',
                'min:0.0001',
            ],
            // Validación para precios dinámicos en item_unit_types
            'item_unit_types.*.prices' => [
                'nullable',
                'array',
            ],
            'item_unit_types.*.prices.*.label' => [
                'required_with:item_unit_types.*.prices',
                'string',
                'max:50',
            ],
            'item_unit_types.*.prices.*.price' => [
                'required_with:item_unit_types.*.prices',
                'numeric',
                'min:0',
            ],
            'item_unit_types.*.prices.*.position' => [
                'required_with:item_unit_types.*.prices',
                'integer',
                'min:1',
            ],
            'item_unit_types.*.prices.*.is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages()
    {
        return array_merge([
            'description.required' => 'El campo nombre es obligatorio.',
            'name.max' => 'La descripción debe ser inferior a 1000 caracteres.',
            'sale_unit_price.gt' => 'El precio unitario de venta debe ser mayor que 0.',
            'item_unit_types.*.description.required' => 'La descripción de la presentación es obligatoria.',
            'item_unit_types.*.unit_type_id.required' => 'Seleccione la unidad de la presentación.',
            'item_unit_types.*.quantity_unit.required' => 'El factor de la presentación es obligatorio.',
            'item_unit_types.*.quantity_unit.min' => 'El factor de la presentación debe ser al menos 0.0001.',
        ], $this->getSunatItemCodeMessages());
    }
}
