<?php

namespace App\Tenancy\Abstracts;

use App\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    use UsesTenantConnection;
}
