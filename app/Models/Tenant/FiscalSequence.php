<?php

namespace App\Models\Tenant;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
/** Read model for identifier filtering; configuration is owned by FiscalNumberingRepository. */
class FiscalSequence extends ModelTenant
{
    protected $table = 'fiscal_sequences';
    protected $guarded = ['*'];
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
