<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\User;
use App\Services\FiscalControlNumber;
use Illuminate\Database\ConnectionInterface;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalNoteReference
{
    public static function capture(ConnectionInterface $db, array $input, array $context, User $actor): array
    {
        if (!in_array($actor->type, ['admin', 'seller', 'integrator'], true) || (int) $actor->establishment_id !== (int) $context['establishment_id']) {
            throw new \DomainException('El usuario no puede emitir esta nota en la sucursal indicada.');
        }
        $external = $input['data_affected_document'] ?? null;
        if ($external) {
            if ($actor->type !== 'admin') {
                throw new \DomainException('Solo un administrador puede registrar referencias de facturas externas.');
            }
            if (($external['document_type_id'] ?? null) !== '01'
                || !preg_match('/\A[A-Z0-9-]{0,32}\z/', (string) ($external['series'] ?? ''))
                || !preg_match('/\A[1-9][0-9]{0,17}\z/', (string) ($external['number'] ?? ''))) {
                throw new \DomainException('La referencia externa debe identificar una factura válida.');
            }
            self::date($external['date_of_issue'] ?? '', $context['date_of_issue']);
            if (!in_array($external['mode'] ?? 'free_form', ['free_form', 'digital', 'fiscal_machine'], true)) {
                throw new \DomainException('La modalidad de la factura externa no es válida.');
            }
            $machine = ($external['mode'] ?? 'free_form') === 'fiscal_machine';
            $device = trim((string) ($external['device_serial'] ?? ''));
            if ($machine && ($device === '' || mb_strlen($device) > 120)) {
                throw new \DomainException('Indique el registro del equipo de la factura externa.');
            }
            return [
                'external' => true, 'document_id' => null, 'document_type_id' => '01',
                'series' => $external['series'] ?? '', 'number' => (string) $external['number'],
                'date_of_issue' => $external['date_of_issue'],
                'control_number' => $machine ? null : (string) new FiscalControlNumber($external['control_number'] ?? ''),
                'device_serial' => $machine ? $device : null,
            ];
        }
        $invoice = $db->table('documents')->where('id', $input['affected_document_id'] ?? null)->first();
        if (!$invoice || $invoice->document_type_id !== '01' || $invoice->state_type_id === '11'
            || (int) $invoice->establishment_id !== (int) $context['establishment_id']
            || (int) $invoice->customer_id !== (int) $context['customer_id']
            || $invoice->currency_type_id !== $context['currency_type_id']
            || $invoice->fiscal_environment !== $context['fiscal_environment']
            || (int) $actor->establishment_id !== (int) $invoice->establishment_id
            || ($actor->type !== 'admin' && (int) $invoice->user_id !== (int) $actor->id && (int) $invoice->seller_id !== (int) $actor->id)) {
            throw new \DomainException('La factura afectada no corresponde al cliente, moneda, sucursal o usuario de la nota.');
        }
        $reservation = $db->table('fiscal_number_reservations')->where('document_id', $invoice->id)->lockForUpdate()->first();
        if ($reservation) $reservation = FiscalReservation::effective($db, $reservation, true);
        if (!$reservation || $reservation->status !== 'issued') {
            throw new \DomainException('La factura afectada debe tener su emisión fiscal confirmada.');
        }
        self::date($invoice->date_of_issue, $context['date_of_issue']);
        $snapshot = json_decode($reservation->fiscal_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $result = json_decode($reservation->provider_result ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        return [
            'external' => false, 'document_id' => (int) $invoice->id, 'document_type_id' => '01',
            'series' => $snapshot['series'], 'number' => (string) $reservation->document_number,
            'date_of_issue' => $invoice->date_of_issue, 'control_number' => $reservation->control_number,
            'device_serial' => $result['device_serial'] ?? null,
        ];
    }

    private static function date(string $invoiceDate, string $noteDate): void
    {
        foreach ([$invoiceDate, $noteDate] as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new \DomainException('La referencia requiere fechas válidas de factura y nota.');
            }
        }
        if ($invoiceDate > $noteDate) {
            throw new \DomainException('La nota no puede preceder a la fecha de la factura afectada.');
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
