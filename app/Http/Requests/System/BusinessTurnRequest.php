<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class BusinessTurnRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'modules' => 'present|array',
            'modules.*' => 'integer',
            'levels' => 'present|array',
            'levels.*' => 'string|max:30',
            'apps' => 'present|array',
            'apps.*' => 'integer',
            'app_levels' => 'present|array',
            'app_levels.*' => 'string|max:30',
            'active' => 'boolean',
            'sort' => 'nullable|integer|min:0',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'modules' => 'módulos',
            'apps' => 'apps',
        ];
    }
}
