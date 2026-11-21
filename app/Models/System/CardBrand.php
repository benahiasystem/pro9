<?php

namespace App\Models\System;
use App\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class CardBrand extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    public $timestamps = false;

    protected $fillable = [
        'description',
    ];
}