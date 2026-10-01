<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeriesRequest extends FormRequest
{

    public $advanced_message;


    public function authorize()
    {
        return $this->user() && $this->user()->type === 'admin';
    }

    public function rules()
    {

        $id = $this->input('id');
        $validate_number = $this->advancedValidationNumber();

        return [
            'establishment_id' => 'required|integer|exists:tenant.establishments,id',
            'correlative' => 'sometimes|integer|min:1|max:2147483647',
            'dedicated' => 'sometimes|boolean',
            'contingency' => 'sometimes|boolean',
            'document_type_id' => ['required', \Illuminate\Validation\Rule::in(array_unique(array_column(\App\Services\SeriesCodeGenerator::availableTypes(false), 'document_type_id')))],
            'number' => $validate_number,
        ];
    }


    /**
     *
     * Validaciones para el formato de la serie, aplica a facturas y boletas
     *
     * @return array
     */
    public function advancedValidationNumber()
    {

        $general_validations = ['required', 'string', 'size:4', 'regex:/^[A-Z0-9]{4}$/'];
        $advanced_validations = [];
        $document_type_id = $this->input('document_type_id');
        $contingency = $this->input('contingency');

        // facturas y boletas
        if(in_array($document_type_id, ['01']))
        {

            switch ($document_type_id)
            {
                // validaciones para facturas
                case '01':
                    if($contingency)
                    {
                        $regex = 'regex:"^([0-9]{4})?$"';
                        $this->advanced_message = ' - Formato del campo: [0-9]{4}, Ejemplo: 0001';
                    }
                    else
                    {
                        $regex = 'regex:"^([F][A-Z0-9]{3})?$"';
                        $this->advanced_message = ' - Formato del campo: [F][A-Z0-9]{3}, Ejemplo: FF01';
                    }

                    $advanced_validations[] = $regex;

                    break;
            }

            return array_merge($general_validations, $advanced_validations);
        }
        // facturas y boletas

        return $general_validations;
    }


    public function messages()
    {
        return [
            'number.regex' => 'El formato de la serie es inválido'.$this->advanced_message ?? '',
        ];
    }

}