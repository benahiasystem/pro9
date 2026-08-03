<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Poner o quitar un pulgar sobre un producto.
 *
 * `recomendar` decide la dirección: un mismo endpoint idempotente para ambas,
 * así dos clics rápidos convergen al estado que mandó el último en vez de
 * quedar invertidos, como pasaría con un toggle sin estado explícito.
 */
class RecommendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer'],
            'recomendar' => ['required', 'boolean'],
        ];
    }
}
