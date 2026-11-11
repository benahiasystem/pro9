<?php

namespace App\Services\Payments;

use App\Services\Fiscal\FiscalAmounts;
use App\Support\Exact\Decimal as D;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Settle received cents, then allocate documentary excess only to real cash. */
final class ReceiptChange
{
    public static function apply(array $rows, $payable, string $documentCurrency): array
    {
        // This marker is produced only from the complete, server-normalized batch.
        foreach ($rows as &$row) unset($row['_settlement_payment']);
        unset($row);
        $payable = D::of($payable);
        $sum = D::sum(array_map(fn ($row) => D::readRequired($row, 'payment'), $rows));
        $cashMethods = Classification::cashIds();
        $closingIndex = null;
        foreach ($rows as $index => $row) {
            if ($documentCurrency === 'VES' && ($row['currency_type_id'] ?? null) === 'USD'
                && !self::recorded($row) && in_array((string) ($row['payment_method_type_id'] ?? ''), $cashMethods, true)) {
                $closingIndex = $index;
            }
        }
        if ($closingIndex !== null) {
            $row = $rows[$closingIndex];
            $principal = D::readRequired($row, 'payment');
            $remaining = $payable->minus($sum->minus($principal));
            if ($remaining->isPositive()) {
                $receivedDue = D::of(D::represent(D::convert($remaining, $documentCurrency, 'USD', $row['exchange_rate']), 2));
                // Equality to HALF_UP cents also defines the exact half-cent boundary.
                if ($receivedDue->isEqualTo(D::readRequired($row, 'original_amount'))) {
                    if (!$principal->isEqualTo($remaining)) {
                        $row = self::withSettlement($row, $remaining);
                        $row['_settlement_payment'] = D::fraction($remaining);
                        $rows[$closingIndex] = $row;
                    }
                    return $rows;
                }
            }
        }
        $excess = $sum->minus($payable);
        if (!$excess->isPositive()) return $rows;

        $remaining = $excess;
        foreach ($rows as $index => $row) {
            // A historical receipt has already settled its own cash and change.
            if (self::recorded($row)) continue;
            if (!in_array((string) ($row['payment_method_type_id'] ?? ''), $cashMethods, true) || !$remaining->isPositive()) continue;
            $applied = D::min($remaining, D::readRequired($row, 'payment'));
            $change = D::convert($applied, $documentCurrency, $row['currency_type_id'], $row['exchange_rate']);
            // The final documentary balance may differ from the nearest received cent.
            // Keep the real received amount and let persistence record the difference
            // as a payment adjustment instead of inventing an unpayable cash change.
            if (count($rows) === 1 && D::of(D::represent($change, 2))->isZero()) {
                $row['_settlement_payment'] = D::fraction(D::readRequired($row, 'payment')->minus($applied));
                $rows[$index] = $row;
                $remaining = $remaining->minus($applied);
                continue;
            }
            $row['change'] = D::represent($change, 2);
            if (!D::of($row['change'])->isEqualTo($change)) FiscalAmounts::error('payments', 'El vuelto convertido requiere un importe liquidable en céntimos.');
            $net = D::readRequired($row, 'original_amount')->minus($change);
            if ($net->isNegative()) FiscalAmounts::error('payments', 'El vuelto supera el efectivo recibido.');
            $netApplied = D::readRequired($row, 'payment')->minus($applied);
            $row['original_amount'] = D::represent($net);
            $row['payment'] = D::represent($netApplied);
            $rows[$index] = $row;
            $remaining = $remaining->minus($applied);
        }
        if ($remaining->isPositive()) FiscalAmounts::error('payments', 'El exceso sólo puede devolverse desde el efectivo recibido.');
        return $rows;
    }

    /** Update both representations from an internally computed exact application. */
    public static function withSettlement(array $values, $applied): array
    {
        $applied = D::of($applied);
        $values['payment'] = D::represent($applied);
        $values['calculation_snapshot']['values']['payment'] = D::fraction($applied);
        $values['calculation_snapshot']['values']['payment_adjustment'] = D::fraction(
            $applied->minus(D::readRequired($values, 'converted_principal'))
        );
        return $values;
    }

    private static function recorded(array $row): bool
    {
        return !empty($row['_reused_receipt']) || !empty($row['id']) || !empty($row['receipt_parent_id'])
            || !empty($row['receipt_origin']) || !empty($row['payment_method_snapshot']['receipt_origin'])
            || !empty($row['source_sale_note_payment_id']) || !empty($row['source_quotation_payment_id'])
            || !empty($row['source_technical_service_payment_id']);
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
