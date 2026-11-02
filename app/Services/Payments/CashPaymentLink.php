<?php

namespace App\Services\Payments;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
final class CashPaymentLink
{
    /** The caller holds the cash row lock, including while resolving existing links. */
    public static function upsert($query, array $attributes, array $values = [])
    {
        $record = $query->lockForUpdate()->firstOrNew($attributes);
        $record->fill($values);
        $record->save();
        return $record;
    }

    public static function belongsToSession($payment, $cashId): bool
    {
        if ($payment->receipt_parent_id || !empty($payment->payment_method_snapshot['receipt_origin'])) return false;
        $global = $payment->global_payment;
        if (($global?->report_context['version'] ?? null) !== 2) return true;
        return $global->cash_session_id !== null && (string) $global->cash_session_id === (string) $cashId;
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
