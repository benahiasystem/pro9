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

    public static function convert($amount, string $from, string $to, $rate): float
    {
        if (!in_array($from, ['VES', 'USD'], true) || !in_array($to, ['VES', 'USD'], true)) {
            self::error('currency_type_id', 'Sólo se admiten VES y USD.');
        }
        if ($from === $to) return round((float) $amount, 2);
        if (!is_numeric($rate) || $rate <= 0) self::error('exchange_rate', 'Indique una tasa positiva en VES por USD.');
        return round($from === 'USD' ? $amount * $rate : $amount / $rate, 2);
    }

    public static function payment(array $input, string $documentCurrency, $documentRate, bool $igtfEnabled, $igtfRate): array
    {
        $currency = $input['currency_type_id'] ?? $documentCurrency;
        $explicit = array_key_exists('currency_type_id', $input);
        $rate = $input['exchange_rate'] ?? ($explicit && $currency !== $documentCurrency ? null : $documentRate);
        if (!is_numeric($rate) || $rate <= 0) self::error('exchange_rate', 'Indique una tasa positiva en VES por USD.');
        $rate = round((float) $rate, 3);
        if ($rate <= 0) self::error('exchange_rate', 'La tasa debe ser positiva con la precisión del sistema.');
        if ($explicit && $currency !== $documentCurrency && !isset($input['original_amount'])) {
            self::error('original_amount', 'Indique el importe en la moneda recibida.');
        }
        $original = $input['original_amount'] ?? $input['payment'] ?? null;
        if (!is_numeric($original) || $original <= 0) self::error('original_amount', 'El importe debe ser positivo.');
        $original = round((float) $original, 2);
        if ($original <= 0) self::error('original_amount','El importe debe ser positivo con la precisión del sistema.');
        $status = $input['igtf_status'] ?? 'not_applicable';
        if (!in_array($status, ['subject', 'exempt', 'not_applicable'], true)) self::error('igtf_status', 'Clasificación IGTF inválida.');
        if ($status === 'exempt' && trim($input['exemption_reason'] ?? '') === '') self::error('exemption_reason', 'Indique el motivo de exención.');
        if ($status === 'subject' && (!$igtfEnabled || !is_numeric($igtfRate) || $igtfRate <= 0)) {
            self::error('igtf_status', 'Configure y habilite el IGTF antes de registrar pagos sujetos.');
        }
        if ($status === 'subject' && $currency !== 'USD') self::error('igtf_status', 'La percepción IGTF de ventas requiere un pago en divisas.');
        $tax = $status === 'subject' ? round($original * $igtfRate / 100, 2) : 0;
        return [
            'currency_type_id' => $currency, 'exchange_rate' => $rate,
            'original_amount' => $original, 'payment' => self::convert($original, $currency, $documentCurrency, $rate),
            'tax_amount' => $tax, 'igtf_status' => $status,
            'exemption_reason' => $status === 'exempt' ? trim($input['exemption_reason']) : null,
            'exchange_rate_source' => $input['exchange_rate_source'] ?? 'manual',
            'exchange_rate_date' => $input['exchange_rate_date'] ?? ($input['date_of_payment'] ?? null),
        ];
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
