<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Catalogs\UnitType;
use App\Models\Tenant\Catalogs\TransferReasonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *
 * @mixin FormRequest
 */
class DispatchRequest extends FormRequest
{
    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    protected function prepareForValidation()
    {
        if ($this->input('transfer_reason_type_id') !== TransferReasonType::OTHER) {
            $this->merge(['transfer_reason_description' => null]);
        }
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        //$id = $this->input('id');
        // ########## INICIO RETIRO TRASLADO M1/L1 ##########
        $condition_driver_id = 'required_if:transport_mode_type_id,02';

        return [
            'unit_type_id' => [
                'required',
                UnitType::activeValidationRule(),
            ],
            'delivery_address_id'=> [
                'required_if:document_type_id,09',
            ],
            'origin_address_id'=> [
                'required_if:document_type_id,09',
            ],
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            'transfer_reason_description' => [
                'required_if:transfer_reason_type_id,24',
                'nullable',
                'string',
                'max:255',
            ],
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            // 'observations' => [
            //     'required',
            // ],

//            'dispatcher.identity_document_type_id'=> [
//                'required',
//            ],
//            'dispatcher.number'=> [
//                'required',
//            ],
//            'dispatcher.name'=> [
//                'required',
//            ],
            // 'driver.identity_document_type_id'=> [
            //     'required',
            // ],
            // 'driver.number'=> [
            //     'required',
            // ],
            // 'license_plate'=> [
            //     'required',
            // ],
//            'license_plate'=> [
//                'required_if:transport_mode_type_id, "02"',
//            ],
            'driver_id'=> [
                $condition_driver_id
            ],
            'transport_id' => [
                'required_if:transport_mode_type_id,02',
            ],
            'dispatcher_id' => [
                'required_if:transport_mode_type_id,01',
            ],
            'is_transport_m1l' => ['prohibited'],
            'license_plate_m1l' => ['prohibited'],
            // ######### FIN RETIRO TRASLADO M1/L1 #########
//            'driver.number'=> [
//                'required_if:transport_mode_type_id, "02"',
//            ],
            // 'customer_id'=> [
            //     'required_if:document_type_id, "09"',
            // ],
            'transport_mode_type_id'=> [
                'required_if:document_type_id,09',
            ],
            'transfer_reason_type_id'=> [
                'required_if:document_type_id,09',
                'nullable',
                TransferReasonType::activeValidationRule(),
            ],
            'origin.address'=> [
                'required_if:document_type_id,09',
                'max:100',
            ],
            'delivery.address'=> [
                'required_if:document_type_id,09',
                'max:100',
            ],
        ];
    }

    public function messages()
    {
        return [

            'transfer_reason_description.required' => 'El campo Descripción de motivo de traslado es obligatorio.',
            'observations.required' => 'El campo Observaciones es obligatorio.',
            'dispatcher.identity_document_type_id.required' => 'El campo Tipo Doc. Identidad es obligatorio.',
            'dispatcher.number.required' => 'El campo Número es obligatorio.',
            'dispatcher.name.required' => 'El campo Nombre y/o razón social es obligatorio.',
            'driver.identity_document_type_id.required' => 'El campo Tipo Doc. Identidad es obligatorio.',
            'driver.number.required' => 'El campo Número es obligatorio.',
            'license_plate.required' => 'El campo Número de placa del vehiculo es obligatorio.',

            'driver.number.required_if' => 'El campo Número es obligatorio cuando modo de traslado es '.$this->transport_mode_type_id.'.',
            'driver.identity_document_type_id.required_if' => 'El campo Tipo Doc. Identidad es obligatorio cuando modo de traslado es '.$this->transport_mode_type_id.'.',

            'related.number.regex' => 'El formato de Número de documento es inválido - Formato del campo: XXXX-XX-XXX-XXXXXX, Ejemplo: 0001-01-002-001234',

        ];
    }
}
