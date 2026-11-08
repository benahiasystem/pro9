<?php

namespace App\Support\Exact;

/** Printable service balances use recorded applied receipts, in document currency. */
final class TechnicalServicePdfAmounts
{
    public static function values($service): array
    {
        $service->loadMissing('payments');
        $cost = Decimal::readRequired($service, 'cost');
        $products = Decimal::readRequired($service, 'total');
        $total = $cost->plus($products);
        $paid = Decimal::sum($service->payments->map(fn ($row) => Decimal::readRequired($row, 'payment')));
        $balance = $total->minus($paid);
        $amounts = compact('cost', 'products', 'total', 'paid', 'balance');
        $display = array_map(fn ($amount) => Decimal::format($amount), $amounts);
        // The adjustment makes independently rounded rows add up without changing
        // any of the recorded amounts or hiding the rounding discrepancy.
        $rounded = array_map(fn ($amount) => Decimal::of(Decimal::represent($amount, 2)), $amounts);
        $display['product_rounding'] = Decimal::format($rounded['total']->minus($rounded['cost'])->minus($rounded['products']));
        $display['balance_rounding'] = Decimal::format($rounded['balance']->minus($rounded['total']->minus($rounded['paid'])));
        $display['legacy_advance'] = Decimal::format(Decimal::read($service, 'prepayment'));
        $display['has_legacy_advance'] = !Decimal::read($service, 'prepayment')->isZero();
        return $display;
    }
}
