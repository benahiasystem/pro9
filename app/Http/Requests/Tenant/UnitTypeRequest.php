<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Catalogs\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitTypeRequest extends FormRequest
{
    // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => strtoupper(trim((string) $this->input('id'))),
            'symbol' => strtoupper(trim((string) $this->input('symbol'))),
        ]);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id');
        return [
            'id' => [
                'required',
                'string',
                Rule::notIn(UnitType::LEGACY_UNIT_TYPES),
                Rule::unique('tenant.cat_unit_types')->ignore($id),
            ],
            'description' => [
                'required',
            ],
            'active' => [
                'required',
                'boolean',
            ],
            'symbol' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $id = (string) $this->input('id');
            if (!UnitType::isReserved($id)) {
                return;
            }

            $expected = $id === UnitType::DEFAULT_UNIT_TYPE
                ? ['symbol' => UnitType::DEFAULT_UNIT_TYPE, 'description' => 'Unidad']
                : ['symbol' => UnitType::SERVICE_UNIT_TYPE, 'description' => 'Servicio'];

            if (!$this->boolean('active')) {
                $validator->errors()->add('active', 'UND y SERV deben permanecer activos.');
            }
            foreach ($expected as $field => $value) {
                if ((string) $this->input($field) !== $value) {
                    $validator->errors()->add($field, "El valor de {$field} para {$id} no se puede modificar.");
                }
            }
        });
    }
    // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
}
