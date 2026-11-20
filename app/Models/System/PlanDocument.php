<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class PlanDocument extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17


    protected $table = "plan_documents";

    protected $fillable = [
        'description',
    ];

    public $timestamps = false;
}
