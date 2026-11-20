<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class NoteDebitType extends ModelCatalog
{
    use UsesTenantConnection;
    
    protected $table = "cat_note_debit_types";
    public $incrementing = false;
    protected $keyType = 'string';
}