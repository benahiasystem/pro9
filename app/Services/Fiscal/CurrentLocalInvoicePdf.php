<?php

namespace App\Services\Fiscal;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;

// ######## INICIO PRESENTACION FACTURA A4/A5 ########
/** Current local A4/A5 bytes; never replaces a stored PDF or an original HKA file. */
final class CurrentLocalInvoicePdf
{
    public static function applies($document, string $format = 'a4'): bool
    {
        return $document instanceof Document && $document->exists && in_array($format, ['a4', 'a5'], true)
            && $document->document_type_id === '01'
            && in_array($document->fiscal_emission_mode, ['free_form', 'fiscal_machine'], true);
    }

    public static function bytesIfApplicable($document, string $format = 'a4'): ?string
    {
        if (!self::applies($document, $format)) return null;
        $read = function () use ($document, $format) {
            $fresh = $document->fresh() ?? $document;
            if (!self::applies($fresh, $format)) return null;
            $registered = CurrentFreeFormPdf::bytesIfRegistered($fresh, $format);
            if ($registered !== null) return $registered;
            return (new Facturalo())->createPdf($fresh, 'invoice', $format, 'string');
        };
        $connection = $document->getConnection();
        // Rendering inside a caller's transaction retains its isolation/ownership.
        return $connection->transactionLevel() > 0 ? $read() : \App\Support\Exact\ReadSnapshot::run($connection, $read);
    }
}
// ######## FIN PRESENTACION FACTURA A4/A5 ########
