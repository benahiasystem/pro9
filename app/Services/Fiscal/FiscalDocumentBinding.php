<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalDocumentBinding
{
    public static function apply(\App\Models\Tenant\ModelTenant $document, object $company): void
    {
        $db = $document->getConnection();
        $reservation = $db->table('fiscal_number_reservations')->where('id', $document->fiscalReservationId())->lockForUpdate()->first();
        if (!$reservation || $reservation->document_id || $reservation->dispatch_id || $reservation->status !== 'reserved') {
            throw new \DomainException('La reserva fiscal no está disponible para crear el documento.');
        }
        $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $isDispatch = $document instanceof \App\Models\Tenant\Dispatch;
        if (!in_array($snapshot['document_type_id'], $isDispatch ? ['09'] : ['01', '07', '08'], true)
            || $snapshot['document_type_id'] !== $document->document_type_id
            || (int) $snapshot['establishment_id'] !== (int) $document->establishment_id
            || $snapshot['environment'] !== $company->fiscal_environment || empty($snapshot['mode'])) {
            throw new \DomainException('La reserva no corresponde al documento, sucursal o ambiente.');
        }
        $document->series = $snapshot['series'];
        $document->number = $reservation->document_number;
        $document->fiscal_environment = $snapshot['environment'];
        if ($isDispatch) {
            $document->filename = \App\CoreFacturalo\Requests\Inputs\Functions::filename($company, '09', $document->series, $document->number);
        } else {
            $document->fiscal_emission_mode = $snapshot['mode'];
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
