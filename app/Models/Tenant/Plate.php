<?php

namespace App\Models\Tenant;

use App\Tenancy\Traits\UsesTenantConnection;

class Plate extends ModelTenant 
{
    use UsesTenantConnection;


    public $incrementing = false;
    public $timestamps = false;

    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}