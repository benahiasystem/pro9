<?php

namespace App\Models\System;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
abstract class ModelSystem extends \Illuminate\Database\Eloquent\Model
{
    use \Hyn\Tenancy\Traits\UsesSystemConnection;
    use \App\Support\Exact\HasExactValues;
    use \App\Support\Exact\PersistsExactValues;
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
