<?php

namespace Modules\MobileApp\Models\System;

use App\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;


class AppModule extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17


    use UsesSystemConnection;

    protected $fillable = [
        'value',
        'description',
        'order_menu',
    ];

    protected $casts = [
        'order_menu' => 'int',
    ];

}
