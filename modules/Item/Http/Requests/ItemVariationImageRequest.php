<?php

namespace Modules\Item\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ItemVariationImageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'image' => ['required', 'string', 'max:255'],
            'temp_path' => ['required', 'string', 'max:255'],
        ];
    }
}
