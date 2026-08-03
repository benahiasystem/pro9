<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Marketplace\Services\Settings;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Uno de los dos: se denuncia un producto o la tienda entera.
            'item_id' => ['nullable', 'integer'],
            'store' => ['required_without:item_id', 'nullable', 'string', 'max:180'],

            // Solo motivos de la lista configurada: el campo es un select, no
            // texto libre, así que cualquier otra cosa es manipulación.
            'reason' => ['required', 'string', Rule::in(Settings::get('report_reasons', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in' => 'Elige un motivo de la lista.',
        ];
    }
}
