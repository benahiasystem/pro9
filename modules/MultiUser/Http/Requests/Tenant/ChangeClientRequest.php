<?php

namespace Modules\MultiUser\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ChangeClientRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation()
    {
        // El formulario HTML envía "true"/"false"; la API también admite booleanos y 0/1.
        $direction = $this->input('is_destination');
        if (in_array($direction, ['true', 'false'], true)) {
            $this->merge(['is_destination' => $direction === 'true']);
        }
    }

    public function rules()
    {
        return [
            'multi_user_id' => ['required', 'integer', 'min:1'],
            'is_destination' => ['required', 'boolean'],
        ];
    }
}
