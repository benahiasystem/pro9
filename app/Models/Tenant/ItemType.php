<?php

namespace App\Models\Tenant;

class ItemType extends ModelTenant
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
}