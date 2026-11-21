<?php

namespace App\Models\System;

// ######## INICIO CALCULOS EXACTOS SAB ########
abstract class ModelSystem extends \Illuminate\Database\Eloquent\Model
{
    use \App\Tenancy\Traits\UsesSystemConnection;
    use \App\Support\Exact\HasExactValues;
        use \App\Database\HasPostgresIdentity;
    use \App\Support\Exact\PersistsExactValues;
}
// ######## FIN CALCULOS EXACTOS SAB ########
