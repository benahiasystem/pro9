<?php

namespace App\Services\Fiscal;

use App\Support\Exact\Decimal as D;

/** Real, non-reversed receipts with the existing half-cent settlement tolerance. */
final class HkaSettlement
{
    private const TOLERANCE = '0.005';

    public static function complete(array $snapshot): bool
    {
        $amounts=[]; $real=false;
        foreach (self::activePayments($snapshot) as $payment) {
            $amount=D::read($payment,'payment');
            $amounts[]=$amount;
            if (empty(data_get($payment,'receipt_parent_id')) && $amount->isPositive()) $real=true;
        }
        return $real && D::sum($amounts)->minus(D::read($snapshot,'total'))->abs()->isLessThanOrEqualTo(self::TOLERANCE);
    }

    public static function hasCollectionShortfall(array $snapshot): bool
    {
        $amounts = [];
        foreach (self::activePayments($snapshot) as $payment) $amounts[] = D::read($payment, 'payment');
        return D::sum($amounts)->isLessThan(D::read($snapshot, 'total')->minus(self::TOLERANCE));
    }

    private static function activePayments(array $snapshot): \Generator
    {
        foreach ($snapshot['payments'] ?? [] as $payment) {
            if (empty(data_get($payment, 'reversed_at'))) yield $payment;
        }
    }
}
