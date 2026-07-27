<?php

namespace Modules\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La puerta de contacto: lo que el comprador declara antes de que el servidor
 * genere el enlace de WhatsApp (plan de seguridad, A2.2 / B1).
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:store,product,cart'],
            'store' => ['required', 'string', 'max:180'],
            // Solo para type=product. `nullable` es imprescindible: el front
            // envía item_id: null en los otros flujos, y sin él la regla
            // `integer` rechaza el null («debe ser un número entero»).
            'item_id' => ['nullable', 'required_if:type,product', 'integer'],
            // Solo para type=cart: ítems con cantidades. El nombre no se
            // recibe del cliente: se relee de la base para que el mensaje no
            // sea falsificable. Mismo `nullable` que item_id.
            'items' => ['nullable', 'required_if:type,cart', 'array', 'max:60'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],

            'name' => ['required', 'string', 'min:2', 'max:80'],
            // Mismo criterio E.164 sin '+' que el WhatsApp de la tienda.
            'whatsapp' => ['required', 'string', 'regex:/^\d{9,15}$/'],

            // La solución del proof-of-work. Se valida en el controlador con
            // PowChallenge::verify, aquí solo la forma.
            'pow' => ['required', 'array'],
            'pow.salt' => ['required', 'string', 'max:60'],
            'pow.challenge' => ['required', 'string', 'size:64'],
            'pow.number' => ['required', 'integer', 'min:0'],
            'pow.signature' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Dinos tu nombre para generar el enlace.',
            'whatsapp.regex' => 'Tu WhatsApp debe ir solo con dígitos, con código de país (ej. 51987654321).',
            'pow.required' => 'Completa la verificación antes de continuar.',
        ];
    }
}
