<?php

namespace Modules\MultiUser\Http\Requests\Tenant\Api;

use Modules\MultiUser\Http\Requests\Tenant\ChangeClientRequest as BaseChangeClientRequest;


class ChangeClientRequest extends BaseChangeClientRequest
{
     
    public function rules()
    { 
        return array_merge(parent::rules(), [
            'fqdn' => ['required', 'string', 'max:253'],
        ]);
    }

}
