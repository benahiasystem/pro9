<?php

namespace App\Models\Tenant;

class Group extends ModelTenant
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
}