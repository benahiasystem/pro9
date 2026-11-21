<?php

namespace Modules\MultiUser\Models\System;

use App\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class ModelSystem extends Model
{
    // ########## INICIO CAMBIO POSTGRESQL 17
    use \App\Database\HasPostgresIdentity;
    // ######### FIN CAMBIO POSTGRESQL 17

    use UsesSystemConnection;
}