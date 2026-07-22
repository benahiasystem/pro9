<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Marketplace\Services\Settings;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización la resuelve el guard system_api en la ruta.
        return true;
    }

    public function rules(): array
    {
        return [
            'store' => ['required', 'array'],
            'store.external_uuid' => ['required', 'string', 'size:36'],
            'store.name' => ['required', 'string', 'max:160'],
            'store.tax_id' => ['nullable', 'string', 'max:20'],
            'store.email' => ['nullable', 'email', 'max:160'],
            // E.164 sin '+': solo dígitos.
            'store.whatsapp' => ['required', 'string', 'regex:/^\d{8,20}$/'],
            'store.description' => ['nullable', 'string', 'max:500'],
            'store.address' => ['nullable', 'string', 'max:255'],
            // Opt-in de la dirección exacta. Ausente = false: solo se publica
            // la zona referencial (o nada).
            'store.show_address' => ['nullable', 'boolean'],
            // La «zona referencial» que se muestra con show_address apagado
            // («Bloque 7», «Portería principal»). La escribe la tienda.
            'store.address_zone' => ['nullable', 'string', 'max:120'],
            // Opt-in de precios. Ausente = false (la tienda no los muestra).
            'store.show_prices' => ['nullable', 'boolean'],
            'store.logo_hash' => ['nullable', 'string', 'max:64'],
            'store.logo_base64' => ['nullable', 'string'],

            'items' => ['present', 'array'],
            'items.*.external_id' => ['required', 'string', 'max:64'],
            'items.*.name' => ['required', 'string', 'max:200'],
            'items.*.internal_code' => ['nullable', 'string', 'max:64'],
            'items.*.barcode' => ['nullable', 'string', 'max:64'],
            'items.*.category' => ['nullable', 'string', 'max:120'],
            // Precio del producto. Se guarda siempre; solo se muestra si la
            // tienda tiene show_prices. Sin tope de decimales aquí: la columna
            // es decimal(10,2).
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.image_hash' => ['nullable', 'string', 'max:64'],
            'items.*.image_base64' => ['nullable', 'string'],

            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $max = (int) Settings::get('max_items_per_store', 500);
            $count = count($this->input('items', []));

            if ($count > $max) {
                $validator->errors()->add(
                    'items',
                    "El catálogo supera el máximo de {$max} productos por tienda (recibidos: {$count})."
                );
            }

            // Dos ítems con el mismo external_id colisionarían en el unique
            // (store_id, external_id) a mitad de transacción.
            $ids = array_column($this->input('items', []), 'external_id');

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('items', 'Hay external_id duplicados en el catálogo.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'store.whatsapp.regex' => 'El WhatsApp debe ir en formato E.164 sin "+", solo dígitos (ej. 51987654321).',
            'store.external_uuid.size' => 'El external_uuid debe ser un UUID de 36 caracteres.',
        ];
    }
}
