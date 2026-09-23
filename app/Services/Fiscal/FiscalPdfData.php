<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Models\Tenant\Dispatch;
use App\Services\FiscalControlNumber;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalPdfData
{
    public static function forDocument($document): ?array
    {
        if ((!$document instanceof Document && !$document instanceof Dispatch) || !$document->exists) {
            return null;
        }
        $column = $document instanceof Dispatch ? 'dispatch_id' : 'document_id';
        $reservation = $document->getConnection()->table('fiscal_number_reservations')->where($column, $document->id)->first();
        return $reservation ? self::fromReservation(FiscalReservation::effective($document->getConnection(), $reservation)) : null;
    }

    public static function fromReservation(object $reservation): array
    {
        $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $result = json_decode($reservation->provider_result ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $lot = $snapshot['lot'] ?? null;
        return [
            'environment' => $snapshot['environment'], 'mode' => $snapshot['mode'],
            'issuer' => $snapshot['issuer'] ?? [], 'series' => $snapshot['series'],
            'document_number' => (string) $reservation->document_number,
            'control_number' => $reservation->control_number, 'status' => $reservation->status,
            'status_label' => [
                'reserved' => 'Emisión pendiente', 'processing' => 'Emisión en curso', 'uncertain' => 'Emisión sin confirmar',
                'awaiting_print' => 'Pendiente de confirmar impresión', 'issued' => 'Emisión confirmada',
                'rejected' => 'Emisión rechazada', 'inutilized' => 'Control inutilizado',
            ][$reservation->status] ?? 'Estado no confirmado',
            'simulated' => (bool) ($result['simulated'] ?? false),
            'device_serial' => $result['device_serial'] ?? null,
            'provider_reference' => $result['provider_reference'] ?? null,
            'affected_document' => $snapshot['affected_document'] ?? null,
            'contingency' => $snapshot['contingency'] ?? null,
            'print_replacement' => $snapshot['print_replacement'] ?? null,
            'printer' => $lot ? [
                'name' => $lot['printer_name'], 'rif' => $lot['printer_rif'], 'authorization' => $lot['authorization'],
                'authorization_date' => $lot['authorization_date'], 'prepared_at' => $lot['prepared_at'],
                'start' => self::control((int) $lot['start_ordinal']), 'end' => self::control((int) $lot['end_ordinal']),
            ] : null,
        ];
    }

    private static function control(int $ordinal): string
    {
        return (string) new FiscalControlNumber(sprintf('%02d-%d', intdiv($ordinal, 100000000), $ordinal % 100000000));
    }

    public static function issuerForPrint($company, ?array $fiscal)
    {
        if (!$fiscal) {
            return $company;
        }
        $copy = clone $company;
        foreach ($fiscal['issuer'] as $key => $value) {
            if (in_array($key, ['name', 'trade_name', 'number'], true)) {
                $copy->{$key} = $value;
            }
        }
        return $copy;
    }

    public static function assertPageCount(?array $fiscal, int $pages): void
    {
        if ($fiscal && $fiscal['mode'] === 'free_form' && $pages !== 1) {
            throw new \DomainException('El documento excede la capacidad de una hoja de forma libre. No se puede imprimir con un único control.');
        }
    }

    public static function assertItemCapacity(array $snapshot, int $rows): void
    {
        if (($snapshot['mode'] ?? null) !== 'free_form') return;
        $capacity = $snapshot['profile']['configuration']['page_capacity'] ?? null;
        if (filter_var($capacity, FILTER_VALIDATE_INT) === false || (int) $capacity < 1 || (int) $capacity > 100) {
            throw new \DomainException('La forma libre requiere una capacidad de líneas válida.');
        }
        if ($rows < 1 || $rows > (int) $capacity) {
            throw new \DomainException('El documento excede la capacidad de líneas configurada. Divida su contenido antes de emitir.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
