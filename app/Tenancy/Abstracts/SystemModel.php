<?php

namespace App\Tenancy\Abstracts;

use App\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

abstract class SystemModel extends Model
{
    use UsesSystemConnection;
}
