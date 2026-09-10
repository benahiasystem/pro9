<?php

namespace App\Models\Tenant;

class FiscalEnvironment extends ModelTenant
{
    // ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########
    protected $keyType = 'string';
    protected $table = 'fiscal_environments';
    // ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
    public $incrementing = false;
    public $timestamps = false;
}
