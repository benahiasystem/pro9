<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Models\Tenant\Dispatch;

final class FiscalPdfData
{
    public static function forDocument($document): ?array
    {
        if ((!$document instanceof Document && !$document instanceof Dispatch) || !$document->exists) return null;
        return array_replace(FiscalIdentity::forDocument($document), [
            'environment' => $document->fiscal_environment,
            'issuer' => [], 'status_label' => 'Documento registrado localmente',
            'simulated' => false, 'affected_document' => null, 'printer' => null,
        ]);
    }

    public static function issuerForPrint($company, ?array $fiscal)
    {
        return $company;
    }

    public static function assertPageCount(?array $fiscal, int $pages): void
    {
        // No reserved preprinted sheet exists in the series configuration model.
    }
}
