<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTestDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) optional($this->user())->isAdmin();
    }

    public function rules(): array
    {
        return [
            // ######## INICIO PC-17 CONFIRMACIÓN DE ELIMINACIÓN ########
            'confirmation' => ['required', 'string', 'in:ELIMINAR'],
            // ######## FIN PC-17 CONFIRMACIÓN DE ELIMINACIÓN ########
        ];
    }
}
