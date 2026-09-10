<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() instanceof \App\Models\Tenant\User && $this->user()->type === 'admin';
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'name' => [
                'required',
                Rule::unique('tenant.companies')->ignore($id),
            ],
            'trade_name' => [
                'required',
                Rule::unique('tenant.companies')->ignore($id),
            ],
            'number' => [
                'required',
                Rule::unique('tenant.companies')->ignore($id),
            ],
            ...array_fill_keys(\App\Services\FiscalEmissionSettings::FIELDS, ['missing']),
        ];
    }
}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
