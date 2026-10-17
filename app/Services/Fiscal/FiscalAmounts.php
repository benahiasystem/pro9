<?php
namespace App\Services\Fiscal;

use Illuminate\Validation\ValidationException;

// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
/** Uses the existing document rounding and VES per USD rate convention. */
final class FiscalAmounts
{
    public static function error(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    // ######## INICIO CALCULOS EXACTOS PRO9 ########
    public static function convert($amount, string $from, string $to, $rate): string
    {
        return \App\Support\Exact\Decimal::represent(\App\Support\Exact\Decimal::convert($amount, $from, $to, $rate));
    }
    // ######## FIN CALCULOS EXACTOS PRO9 ########

    public static function payment(array $input, string $documentCurrency, $documentRate, bool $igtfEnabled, $igtfRate): array
    {
        $currency = $input['currency_type_id'] ?? $documentCurrency;
        $explicit = array_key_exists('currency_type_id', $input);
        $rate = $input['exchange_rate'] ?? ($explicit && $currency !== $documentCurrency ? null : $documentRate);
        if (!is_numeric($rate) || $rate <= 0) self::error('exchange_rate', 'Indique una tasa positiva en VES por USD.');
        $rate = \App\Services\ExchangeRates\ExchangeRateMath::rate($rate);
        if ($rate <= 0) self::error('exchange_rate', 'La tasa debe ser positiva con la precisión del sistema.');
        if ($explicit && $currency !== $documentCurrency && !isset($input['original_amount'])) {
            self::error('original_amount', 'Indique el importe en la moneda recibida.');
        }
        $original = $input['original_amount'] ?? $input['payment'] ?? null;
        if (!is_numeric($original) || $original <= 0) self::error('original_amount', 'El importe debe ser positivo.');
        $requested = \App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::input($original, 'original_amount'));
        $original = \App\Support\Exact\Decimal::represent($requested, 2, true);
        if (\App\Support\Exact\Decimal::of($original)->isLessThanOrEqualTo(0)) self::error('original_amount', 'El importe liquidable debe ser positivo.');
        $status = $input['igtf_status'] ?? 'not_applicable';
        if (!in_array($status, ['subject', 'exempt', 'not_applicable'], true)) self::error('igtf_status', 'Clasificación IGTF inválida.');
        if ($status === 'exempt' && trim($input['exemption_reason'] ?? '') === '') self::error('exemption_reason', 'Indique el motivo de exención.');
        if ($status === 'subject' && (!$igtfEnabled || !is_numeric($igtfRate) || $igtfRate <= 0)) {
            self::error('igtf_status', 'Configure y habilite el IGTF antes de registrar pagos sujetos.');
        }
        if ($status === 'subject' && $currency !== 'USD') self::error('igtf_status', 'La percepción IGTF de ventas requiere un pago en divisas.');
        // ######## INICIO CALCULOS EXACTOS PRO9 ########
        $taxExact = $status === 'subject' ? \App\Support\Exact\Decimal::of($original)->multipliedBy(\App\Support\Exact\Decimal::of($igtfRate))->dividedBy(100) : \App\Support\Exact\Decimal::of(0);
        $converted = \App\Support\Exact\Decimal::convert($original, $currency, $documentCurrency, $rate);
        $applied = \App\Support\Exact\Decimal::of(\App\Support\Exact\Decimal::represent($converted, 2));
        $tax = \App\Support\Exact\Decimal::represent($taxExact, 2, true);
        $values = ['original_amount' => \App\Support\Exact\Decimal::of($original), 'payment' => $applied,
            'converted_principal' => $converted, 'payment_adjustment' => $applied->minus($converted),
            'requested_amount' => $requested, 'cash_adjustment' => \App\Support\Exact\Decimal::of($original)->minus($requested),
            'tax_amount' => \App\Support\Exact\Decimal::of($tax), 'tax_exact' => $taxExact];
        return [
            'currency_type_id' => $currency, 'exchange_rate' => $rate,
            'original_amount' => $original, 'payment' => \App\Support\Exact\Decimal::represent($applied),
            'tax_amount' => $tax, 'igtf_status' => $status,
            'calculation_snapshot' => \App\Support\Exact\Decimal::snapshot($values),
        // ######## FIN CALCULOS EXACTOS PRO9 ########
            'exemption_reason' => $status === 'exempt' ? trim($input['exemption_reason']) : null,
            'exchange_rate_source' => $input['exchange_rate_source'] ?? 'manual',
            'exchange_rate_date' => $input['exchange_rate_date'] ?? ($input['date_of_payment'] ?? null),
        ];
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
