<?php

namespace Modules\Sale\Models;

use App\Models\Tenant\ModelTenant;

class ContractStateType extends ModelTenant
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
}