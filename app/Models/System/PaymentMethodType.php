<?php

namespace App\Models\System;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodType extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    protected $keyType = 'string';
    public $incrementing = false;
    use UsesSystemConnection;

    public $timestamps = false;

    protected $fillable = [
        'description',
    ];
}