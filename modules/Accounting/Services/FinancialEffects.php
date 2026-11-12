<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Domain\{ApiProblem, Exact, YearClosingPermit};
use Modules\Accounting\Models\{Account, JournalEntry};
final class FinancialEffects
{
    public static function rate(string $value): string
    {
        $rate = Exact::of($value, 12);
        if (!$rate->isPositive() || $rate->isGreaterThan('9999999999999999.999999999999')) {
            throw new ApiProblem('INVALID_RATE', 'La tasa debe ser positiva y estar dentro de la precisión admitida.');
        }
        return (string) $rate;
    }
    public static function line(string $account, ?string $party, string $book, bool $debit, string $description, string $currency = 'VES', ?string $original = null, ?string $rate = null, ?string $reference = null): array
    {
        return ['account_id' => $account, 'third_party_id' => $party, 'debit' => $debit ? $book : '0.00', 'credit' => $debit ? '0.00' : $book, 'description' => $description, 'document_reference' => $reference, 'original_currency' => $currency, 'original_amount' => $original, 'exchange_rate' => $rate];
    }
    public static function carryingRate(string $book, string $original, string $currency): string
    {
        return $currency === 'VES' ? '1.000000000000' : self::rate((string) BigDecimal::of($book)->dividedBy($original, 12, RoundingMode::HALF_UP));
    }
    public static function sameLines(mixed $actual, array $expected, string $code = 'FINANCIAL_LINES_CHANGED'): void
    {
        $fingerprint = function ($lines) {
            $keys = [];
            foreach ($lines as $line) {
                $get = fn($key, $default = null) => is_array($line) ? $line[$key] ?? $default : $line->{$key} ?? $default;
                $keys[] = json_encode([$get('account_id'), $get('third_party_id'), (string) Exact::of($get('debit', '0'), 8), (string) Exact::of($get('credit', '0'), 8), $get('original_currency', 'VES'), $get('original_amount') !== null ? (string) Exact::of($get('original_amount'), 12) : null, $get('exchange_rate') !== null ? (string) Exact::of($get('exchange_rate'), 12) : null]);
            }
            sort($keys);
            return $keys;
        };
        if ($fingerprint($actual) !== $fingerprint($expected)) {
            throw new ApiProblem($code, 'Los importes y sus auxiliares deben coincidir con la operación revisada. Modifica su propuesta desde el flujo de origen y vuelve a aprobarla.');
        }
    }
    public function validate(JournalEntry $entry, mixed $lines, ?YearClosingPermit $permit = null): void
    {
        $effect = $entry->metadata['financial_effect'] ?? null;
        if (in_array($effect['kind'] ?? null, ['year_close', 'year_reopen'], true)) {
            app(YearClosingService::class)->validate($entry, $effect, $lines, $permit);
            return;
        }
        if ($entry->reversal_of) {
            $original = JournalEntry::findOrFail($entry->reversal_of);
            $expected = $original->lines()->get()->map(fn($l) => [...$l->toArray(), 'debit' => $l->credit, 'credit' => $l->debit])->all();
            self::sameLines($lines, $expected, 'REVERSAL_LINES_CHANGED');
            return;
        }
        if ($effect) {
            self::sameLines($lines, $effect['lines']);
            if ($effect['kind'] === 'settlement') {
                app(SettlementService::class)->validate($entry, $effect);
            } elseif ($effect['kind'] === 'opening') {
                app(OpeningService::class)->validate($entry, $effect);
            } else {
                throw new ApiProblem('UNSUPPORTED_FINANCIAL_EFFECT', 'La operación requiere un flujo de auxiliar reconocido.');
            }
        } elseif (!$entry->source_document_id) {
            foreach ($lines as $line) {
                if (in_array(Account::findOrFail($line->account_id)->auxiliary, ['receivable', 'payable'], true)) {
                    throw new ApiProblem('AUXILIARY_OPERATION_REQUIRED', 'Esta cuenta requiere el detalle de clientes o proveedores. Usa una factura, apertura, cobro, pago o ajuste del auxiliar.');
                }
            }
        }
    }
    public function apply(JournalEntry $entry): void
    {
        $effect = $entry->metadata['financial_effect'] ?? null;
        if (($effect['kind'] ?? null) === 'settlement') {
            app(SettlementService::class)->apply($entry, $effect);
        }
        if (($effect['kind'] ?? null) === 'opening') {
            app(OpeningService::class)->apply($entry, $effect);
        }
    }
}
