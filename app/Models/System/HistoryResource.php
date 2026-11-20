<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use App\Tenancy\Traits\UsesSystemConnection;

class HistoryResource extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use UsesSystemConnection;

    protected $fillable = [
        'cpu_percent',
        'memory_total',
        'memory_free',
        'memory_used',
    ];
}
