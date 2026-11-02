<?php

namespace App\Services\Payments;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Presentation of the method and destination frozen with a receipt. */
final class ReceiptPresentation
{
    public static function firstMethod($payments): string
    {
        $first=$payments->first(fn ($row) => !($row->reversed_at ?? false));
        return $first ? self::method($first) : '';
    }

    public static function tender($payment): array
    {
        $snapshot = $payment->calculation_snapshot ?? [];
        if (is_string($snapshot)) $snapshot = json_decode($snapshot, true) ?: [];
        $gross = data_get($snapshot, 'values.tendered_original_amount');
        $change = data_get($snapshot, 'values.received_change');
        return [
            'tendered_original_amount' => $gross === null ? null : \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::of($gross)),
            'received_change' => $change === null ? null : \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::of($change)),
        ];
    }

    public static function method($payment): string
    {
        $snapshot=$payment->payment_method_snapshot ?? [];
        if (!empty($snapshot['description'])) return $snapshot['description'];
        $context=$payment->global_payment?->report_context ?? [];
        return data_get($context,'method.description') ?? $payment->payment_method_type?->description ?? 'No disponible';
    }

    public static function destination($payment): ?string
    {
        $global=$payment->global_payment;
        $context=$global?->report_context ?? [];
        return $context['destination_label'] ?? ($global ? $global->destination_description : data_get($payment->payment_method_snapshot, 'receipt_origin.destination_label'));
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
