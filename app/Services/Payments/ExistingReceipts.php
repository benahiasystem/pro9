<?php

namespace App\Services\Payments;

use App\Models\Tenant\Cash;
use App\Support\Exact\Decimal;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Preserve recorded receipts when editing the commercial document. */
final class ExistingReceipts
{
    public static function assertCurrencyFields($record, array $input): void
    {
        foreach (['currency_type_id', 'original_amount', 'exchange_rate'] as $field) {
            if (!isset($input[$field])) continue;
            $old = $record->{$field};
            $same = $old !== null && ($field === 'currency_type_id'
                ? (string)$old === (string)$input[$field]
                : Decimal::of($old)->isEqualTo(Decimal::of(Decimal::input($input[$field], $field))));
            if (!$same) Classification::error($field, 'El cobro ya registrado conserva su moneda, importe y tasa; elimínelo y registre uno nuevo.');
        }
        foreach (['exchange_rate_source', 'exchange_rate_date'] as $field) {
            if (!isset($input[$field])) continue;
            $old = $record->{$field};
            if ($field === 'exchange_rate_date' && $old) $old = $old->format('Y-m-d');
            if ((string)$old !== (string)$input[$field]) Classification::error($field, 'El cobro ya registrado conserva la fuente y fecha de su tasa.');
        }
    }

    public static function synchronize($document, array $payments, $previousCurrency): array
    {
        $existing = $document->payments()->lockForUpdate()->get()->keyBy('id');
        if ($existing->isNotEmpty() && (string)$previousCurrency !== (string)$document->currency_type_id) {
            Classification::error('currency_type_id', 'La moneda del documento no puede cambiar mientras tenga cobros registrados.');
        }
        $seen = [];
        $new = [];
        foreach ($payments as $payment) {
            if (empty($payment['id'])) { $new[] = $payment; continue; }
            $id = (string)$payment['id'];
            $record = $existing->get($id);
            if (!$record || isset($seen[$id])) Classification::error('payments', 'El cobro indicado no pertenece a este documento o está duplicado.');
            $seen[$id] = true;
            self::assertCurrencyFields($record, $payment);
            $global = $record->global_payment;
            $destination = $global ? ($global->destination_type === Cash::class ? 'cash' : (string)$global->destination_id)
                : data_get($record->payment_method_snapshot, 'receipt_origin.destination_id');
            $same = (string)($payment['payment_method_type_id'] ?? '') === (string)$record->payment_method_type_id
                && (string)($payment['payment_destination_id'] ?? '') === (string)$destination
                && (string)($payment['reference'] ?? '') === (string)$record->reference
                && (string)($payment['date_of_payment'] ?? '') === ($record->date_of_payment ? $record->date_of_payment->format('Y-m-d') : '')
                && (string)($payment['currency_type_id'] ?? '') === (string)$record->currency_type_id;
            foreach (['payment', 'original_amount', 'exchange_rate'] as $field) {
                $old = $record->{$field};
                $value = $payment[$field] ?? null;
                if ($old === null || $value === null) $same = $same && $old === null && $value === null;
                else $same = $same && Decimal::of($old)->isEqualTo(Decimal::of(Decimal::input($value, $field)));
            }
            if (!$same) Classification::error('payments', 'Para modificar un cobro registrado, elimínelo y registre uno nuevo.');
        }
        foreach ($existing as $record) {
            if (isset($seen[(string)$record->id])) continue;
            if (method_exists($record, 'cashDocumentPayments')) $record->cashDocumentPayments()->delete();
            $record->delete();
        }
        return $new;
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
