<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;

class OperationType extends ModelCatalog
{
    use UsesTenantConnection;

    public const INACTIVE_INCOTERM_IDS = ['0200'];

    public function scopeWhereActive($query, bool $webExportation = false)
    {
        $query->where('active', true)->where('id', '<>', '0200');
        return $webExportation ? $query : $query->whereNotIn('id', array_keys(\App\Services\ExportInvoicePolicy::INCOTERMS));
    }

    protected $table = "cat_operation_types";
    public $incrementing = false;
    public $timestamps = false;
}
