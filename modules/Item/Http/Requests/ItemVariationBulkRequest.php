<?php

namespace Modules\Item\Http\Requests;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemUnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Item\Models\ProductVariableValue;

class ItemVariationBulkRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'variations' => ['required', 'array', 'min:1', 'max:200'],
            'variations.*.internal_id' => ['required', 'string', 'max:30'],
            'variations.*.barcode' => ['nullable', 'string', 'max:150'],
            'variations.*.sale_unit_price' => ['required', 'numeric', 'gt:0'],
            'variations.*.stock' => ['nullable', 'numeric', 'min:0'],
            'variations.*.image' => ['nullable', 'string', 'max:255'],
            'variations.*.temp_path' => ['nullable', 'string', 'max:255'],
            'variations.*.variable_value_ids' => ['required', 'array', 'min:1'],
            'variations.*.variable_value_ids.*' => ['integer', Rule::exists('tenant.product_variable_values', 'id')],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            if ($validator->errors()->any()) {
                return;
            }

            $variations = $this->input('variations', []);

            $parent = Item::find($this->route('item'));
            if (!$parent) {
                $validator->errors()->add('variations', 'El producto principal no existe');
                return;
            }
            if (!is_null($parent->parent_item_id)) {
                $validator->errors()->add('variations', 'Una variación no puede tener variaciones propias');
                return;
            }
            if ($parent->is_set) {
                $validator->errors()->add('variations', 'Un producto compuesto (combo) no puede tener variaciones');
                return;
            }

            $this->validateInternalIds($validator, $variations);
            $this->validateBarcodes($validator, $variations);
            $this->validateCombinations($validator, $variations, $parent);
        });
    }

    private function validateInternalIds($validator, array $variations)
    {
        $internal_ids = collect($variations)->pluck('internal_id')->map(function ($value) {
            return trim($value);
        });

        $duplicates = $internal_ids->duplicates();
        foreach ($internal_ids as $index => $internal_id) {
            if ($duplicates->contains($internal_id)) {
                $validator->errors()->add("variations.{$index}.internal_id", "El código interno \"{$internal_id}\" está repetido en el listado");
            }
        }

        $existing = Item::whereIn('internal_id', $internal_ids->all())->pluck('internal_id');
        foreach ($internal_ids as $index => $internal_id) {
            if ($existing->contains($internal_id)) {
                $validator->errors()->add("variations.{$index}.internal_id", "El código interno \"{$internal_id}\" ya existe en otro producto");
            }
        }
    }

    private function validateBarcodes($validator, array $variations)
    {
        $barcodes = collect($variations)->map(function ($row) {
            return isset($row['barcode']) ? trim((string) $row['barcode']) : '';
        });

        $filled = $barcodes->filter(function ($value) {
            return $value !== '';
        });

        $duplicates = $filled->duplicates();
        $existing_items = Item::whereIn('barcode', $filled->values()->all())->pluck('barcode');
        $existing_presentations = ItemUnitType::whereIn('barcode', $filled->values()->all())->pluck('barcode');

        foreach ($barcodes as $index => $barcode) {
            if ($barcode === '') {
                continue;
            }
            if ($duplicates->contains($barcode)) {
                $validator->errors()->add("variations.{$index}.barcode", "El código de barras \"{$barcode}\" está repetido en el listado");
            }
            if ($existing_items->contains($barcode)) {
                $validator->errors()->add("variations.{$index}.barcode", "El código de barras \"{$barcode}\" ya existe en otro producto");
            }
            if ($existing_presentations->contains($barcode)) {
                $validator->errors()->add("variations.{$index}.barcode", "El código de barras \"{$barcode}\" ya existe en una presentación");
            }
        }
    }

    private function validateCombinations($validator, array $variations, Item $parent)
    {
        $all_value_ids = collect($variations)->pluck('variable_value_ids')->flatten()->unique()->values();

        $values = ProductVariableValue::with('variable')
            ->whereIn('id', $all_value_ids->all())
            ->get()
            ->keyBy('id');

        // Combinaciones ya registradas en el padre
        $existing_combos = $parent->variations()
            ->with('variationValues')
            ->get()
            ->map(function ($variation) {
                return $variation->variationValues
                    ->pluck('product_variable_value_id')
                    ->sort()
                    ->implode('-');
            });

        $seen_combos = [];

        foreach ($variations as $index => $row) {
            $value_ids = collect($row['variable_value_ids']);

            $variable_ids = $value_ids->map(function ($value_id) use ($values) {
                $value = $values->get($value_id);
                return $value ? $value->product_variable_id : null;
            });

            if ($variable_ids->unique()->count() !== $value_ids->count()) {
                $validator->errors()->add("variations.{$index}.variable_value_ids", 'La combinación tiene más de un valor de la misma variable');
                continue;
            }

            foreach ($value_ids as $value_id) {
                $value = $values->get($value_id);
                if ($value && (!$value->active || !$value->variable || !$value->variable->active)) {
                    $validator->errors()->add("variations.{$index}.variable_value_ids", "El valor \"{$value->value}\" o su variable está inactivo");
                }
            }

            $combo_key = $value_ids->sort()->implode('-');

            if (isset($seen_combos[$combo_key])) {
                $validator->errors()->add("variations.{$index}.variable_value_ids", 'La combinación está repetida en el listado');
                continue;
            }
            $seen_combos[$combo_key] = true;

            if ($existing_combos->contains($combo_key)) {
                $validator->errors()->add("variations.{$index}.variable_value_ids", 'La combinación ya existe como variación de este producto');
            }
        }
    }
}
