<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class DocumentEmailRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $value = $this->input('customer_email');
        if (is_string($value)) {
            $this->merge(['recipients' => array_values(array_unique(array_map(
                fn ($email) => strtolower(trim($email)), preg_split('/[,;]/', $value)
            )))]);
        }
    }

    public function rules()
    {
        return [
            'id' => ['required', 'integer'],
            'customer_email' => ['required', 'string', 'max:5080'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['required', 'string', 'email:rfc', 'max:254'],
            'request_id' => ['nullable', 'uuid'],
            'resend' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return ['recipients.*.email' => 'Ingrese una dirección de correo válida.'];
    }
}
