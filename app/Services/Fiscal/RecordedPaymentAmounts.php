<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\DocumentPayment;

// ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
/** Read-only compatibility after the actual receipt has been loaded under lock. */
final class RecordedPaymentAmounts
{
    public static function context(DocumentPayment $stored, array $input): array
    {
        if (!$stored->exists) FiscalAmounts::error('operation_key', 'El cobro histórico no está registrado.');
        $expected = ['igtf_status'=>$stored->igtf_status, 'exemption_reason'=>$stored->exemption_reason,
            'exception_basis'=>$stored->fiscal_context['decision']['exception_basis'] ?? null];
        foreach ($expected as $field=>$value) {
            // Older clients may omit retired fields or publish them empty.
            if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== $value) {
                FiscalAmounts::error('operation_key', 'La operación ya existe con otro contexto fiscal.');
            }
            $input[$field] = $value;
        }
        return $input;
    }

    public static function forRetry(DocumentPayment $stored, array $input, string $currency, $rate): array
    {
        $input = self::context($stored, $input);
        unset($input['exemption_reason'], $input['exception_basis']);
        $input['igtf_status'] = 'not_applicable';
        if (array_key_exists('tendered_amount', $input)) {
            $requested = $stored->calculation_snapshot['values']['requested_total_tendered'] ?? null;
            if ($requested === null || !\App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::input($input['tendered_amount'], 'tendered_amount'))->isEqualTo(\App\Support\Exact\Decimal::of($requested))) {
                FiscalAmounts::error('operation_key', 'La operación ya existe con otro total entregado.');
            }
            $input['original_amount'] = $stored->original_amount;
            unset($input['tendered_amount']);
        }
        // Normalize the requested received amount for the existing replay checks.
        // Applied principal, tax and snapshot are frozen, including cent settlements.
        // ######## INICIO NETO CERO TRAS VUELTO ########
        $snapshot = $stored->calculation_snapshot['values'] ?? [];
        $gross = $snapshot['tendered_original_amount'] ?? null;
        $change = $snapshot['received_change'] ?? null;
        $fullyReturned = $gross !== null && $change !== null
            && \App\Support\Exact\Decimal::of($stored->original_amount)->isZero()
            && \App\Support\Exact\Decimal::of($change)->isPositive()
            && \App\Support\Exact\Decimal::of($gross)->isEqualTo(\App\Support\Exact\Decimal::of($change));
        $values = FiscalAmounts::payment($input, $currency, $rate, false, null, $fullyReturned);
        // ######## FIN NETO CERO TRAS VUELTO ########
        $tendered = $stored->calculation_snapshot['values']['tendered_original_amount'] ?? null;
        if ($tendered !== null && \App\Support\Exact\Decimal::of($values['original_amount'])->isEqualTo(\App\Support\Exact\Decimal::of($tendered))) {
            $values['original_amount'] = $stored->original_amount;
        }
        return array_replace($values, ['igtf_status'=>$stored->igtf_status, 'exemption_reason'=>$stored->exemption_reason,
            'payment'=>$stored->payment, 'tax_amount'=>$stored->tax_amount, 'calculation_snapshot'=>$stored->calculation_snapshot]);
    }
}
// ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
