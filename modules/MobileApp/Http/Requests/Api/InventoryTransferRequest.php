<?php

namespace Modules\MobileApp\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class InventoryTransferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:tenant.warehouses,id'],
            'warehouse_destination_id' => ['required', 'integer', 'exists:tenant.warehouses,id', 'different:warehouse_id'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:tenant.items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages()
    {
        return [
            'warehouse_destination_id.different' => 'El almacén de destino debe ser distinto al de origen.',
            'items.required' => 'Selecciona al menos un producto.',
            'items.*.quantity.gt' => 'La cantidad a trasladar debe ser mayor a 0.',
        ];
    }
}
