<?php

namespace Modules\Payment\Models;

use App\Models\Tenant\ModelTenant;

class PaymentLinkType extends ModelTenant
{

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'description',
    ];

}
