<?php

namespace App\Services\Payments;

use App\Services\Fiscal\FiscalAmounts;
use App\Support\Exact\Decimal as D;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Allocate documentary excess only to tender actually received as cash. */
final class ReceiptChange
{
    public static function apply(array $rows, $payable, string $documentCurrency): array
    {
        $excess = D::sum(array_map(fn ($row) => D::readRequired($row, 'payment'), $rows))->minus(D::of($payable));
        if (!$excess->isPositive()) return $rows;

        $remaining = $excess;
        $cashMethods = Classification::cashIds();
        foreach ($rows as $index => $row) {
            // A historical receipt has already settled its own cash and change.
            if (!empty($row['_reused_receipt']) || !empty($row['source_sale_note_payment_id'])
                || !empty($row['source_quotation_payment_id']) || !empty($row['source_technical_service_payment_id'])
                || (!empty($row['id']) && (!empty($row['sale_note_id']) || !empty($row['quotation_id']) || !empty($row['technical_service_id'])))) continue;
            if (!in_array((string) ($row['payment_method_type_id'] ?? ''), $cashMethods, true) || !$remaining->isPositive()) continue;
            $applied = D::min($remaining, D::readRequired($row, 'payment'));
            $change = D::convert($applied, $documentCurrency, $row['currency_type_id'], $row['exchange_rate']);
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
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
