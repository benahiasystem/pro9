<?php

namespace Modules\MobileApp\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:tenant.warehouses,id'],
            'inventory_transaction_id' => ['required', 'string', 'exists:tenant.inventory_transactions,id'],
            'comments' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:tenant.items,id'],
            'items.*.system_stock' => ['required', 'numeric'],
            'items.*.real_stock' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages()
    {
        return [
            'items.required' => 'Selecciona al menos un producto.',
            'items.*.real_stock.gte' => 'El stock real debe ser mayor o igual a 0.',
        ];
    }
}
