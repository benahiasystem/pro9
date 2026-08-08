<?php

namespace Modules\Item\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Item\Models\ProductVariable;

class ProductVariableRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tenant.product_variables')->ignore($id),
            ],
            'value_type' => [
                'required',
                Rule::in([ProductVariable::VALUE_TYPE_LIST, ProductVariable::VALUE_TYPE_COLOR]),
            ],
            'active' => ['nullable', 'boolean'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.id' => ['nullable', 'integer'],
            'values.*.value' => ['required', 'string', 'max:100'],
            'values.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'values.*.active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $values = $this->input('values', []);

            $seen = [];
            foreach ($values as $index => $row) {
                $value = mb_strtolower(trim($row['value'] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (isset($seen[$value])) {
                    $validator->errors()->add("values.{$index}.value", "El valor \"{$row['value']}\" está repetido");
                    continue;
                }
                $seen[$value] = true;
            }

            if ($this->input('value_type') === ProductVariable::VALUE_TYPE_COLOR) {
                foreach ($values as $index => $row) {
                    if (empty($row['color'])) {
                        $validator->errors()->add("values.{$index}.color", 'Selecciona un color para este valor');
                    }
                }
            }
        });
    }

    public function attributes()
    {
        return [
            'name' => 'nombre',
            'value_type' => 'tipo de valor',
            'values' => 'valores',
        ];
    }
}
