<?php

namespace App\Models\Tenant\Catalogs;

use App\Tenancy\Traits\UsesTenantConnection;

class OtherTaxConceptType extends ModelCatalog
{
    use UsesTenantConnection;

    protected $table = "cat_other_tax_concept_types";
    public $incrementing = false;
}