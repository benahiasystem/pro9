<?php

namespace App\Http\Requests\System;

use App\Rules\SubdomainNotLatin;
// ########## INICIO CAMBIO RIF SUPER ADMIN
use App\Support\System\Rif;
// ######### FIN CAMBIO RIF SUPER ADMIN
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    // ########## INICIO CAMBIO RIF SUPER ADMIN
    protected function prepareForValidation(): void
    {
        $this->merge(['number' => Rif::normalize($this->input('number'))]);
    }
    // ######### FIN CAMBIO RIF SUPER ADMIN

    public function rules()
    {
        $id = $this->input('id');
        $password_rules = $this->getPasswordRules($this->input('regex_password_client') ?? false);

        return [
            'email' => [
                'required',
                'email',
            ],
            'number' => [
                'required',
                // ########## INICIO CAMBIO RIF SUPER ADMIN
                'regex:' . Rif::PATTERN,
                 Rule::unique('system.clients')->ignore($id),
                // ######### FIN CAMBIO RIF SUPER ADMIN
            ],
            'name' => [
                'required',
                Rule::unique('system.clients')->ignore($id)
            ],
            'password' => $password_rules,
            'subdomain' => [
                'required',
                new SubdomainNotLatin
            ],
            'plan_id' => [
                'required',
            ],
            'type' => [
                'required',
            ],
            'soap_send_id' => [
                'required',
            ],
            'soap_type_id' => [
                'required',
            ],
            'soap_username' => [
                'required_if:soap_type_id,"02"'
            ],
            'soap_password' => [
                'required_if:soap_type_id,"02"'
            ],
            'soap_url' => [
                'required_if:soap_send_id,"02"'
            ],
            'contact_email' => [
                'nullable',
                'email',
            ],
            'phone_ws' => [
                'nullable',
                'numeric',
            ],
        ];
    }

    
    /**
     *
     * @param  bool $regex_password_client
     * @return array
     */
    private function getPasswordRules($regex_password_client)
    {
        $password_rules = [
            'required',
            'min:6',
        ];

        if($regex_password_client)
        {
            $password_rules = array_merge($password_rules, [
                'regex:/[a-z]/',      
                'regex:/[A-Z]/',   
                'regex:/[0-9]/',
                'regex:/[@.$!%*#?&-]/',
            ]);
        }

        return $password_rules;
    }
    public function messages()
    {
        return [
            // ########## INICIO CAMBIO RIF SUPER ADMIN
            'number.regex' => 'El RIF debe contener un prefijo V, E, J, P o G y nueve dígitos.',
            'number.unique' => 'El RIF ya está registrado en otro cliente.',
            // ######### FIN CAMBIO RIF SUPER ADMIN
        ];
    }

}
