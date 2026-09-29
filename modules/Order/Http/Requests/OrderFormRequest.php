<?php

namespace Modules\Order\Http\Requests;

use App\Models\Tenant\Catalogs\UnitType;
use App\Models\Tenant\Catalogs\TransferReasonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrderFormRequest extends FormRequest
{
    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    protected function prepareForValidation()
    {
        if ($this->input('transfer_reason_type_id') !== TransferReasonType::OTHER) {
            $this->merge(['transfer_reason_description' => null]);
        }
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    // ########## INICIO RETIRO SEMIRREMOLQUE ##########
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $licensePlates = $this->input('license_plates');
            if (!is_array($licensePlates)) {
                return;
            }

            foreach (['license_plate_2', 'register_number_2'] as $retiredField) {
                if (array_key_exists($retiredField, $licensePlates)) {
                    $validator->errors()->add(
                        "license_plates.{$retiredField}",
                        'Los datos de semirremolque fueron retirados de los formularios de pedido.'
                    );
                }
            }
        });
    }
    // ######### FIN RETIRO SEMIRREMOLQUE #########

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        //$id = $this->input('id');

        return [
            'establishment_id' => [
                'required',
            ],
            'unit_type_id' => [
                'required',
                UnitType::activeValidationRule(),
            ],
            'transfer_reason_description' => [
                // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
                'required_if:transfer_reason_type_id,24',
                'nullable',
                'string',
                'max:255',
                // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            ],
            'observations' => [
                'required',
            ],
            'delivery.address'=> [
                'required',
               
            ],

            'customer_id'=> [
                'required',
            ],
            'transport_mode_type_id'=> [
                'required',
            ],
            'transfer_reason_type_id'=> [
                'required',
                TransferReasonType::activeValidationRule(),
            ],
            'origin.address'=> [
                'required',
            ],

            'dispatcher_id'=> [
                'required',
            ],
            'driver_id'=> [
                'required',
            ],
            
            'license_plates.license_plate_1'=> [
                'required',
            ],
            'license_plates.register_number_1'=> [
                'required',
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
        ];
    }
}
