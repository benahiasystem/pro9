<?php

namespace Modules\MercadoPago\Models;

use App\Models\Tenant\{
    ModelTenant,
};

class TransactionState extends ModelTenant
{

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'success',
        'status',
        'status_detail',
        'original_message',
        'user_message',
    ];

}
