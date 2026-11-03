<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal as D;

/** Calculation only: the caller must persist the result with its receipt. */
final class BankCommission
{
    public static function calculate($received, $percentage = null, $minimum = null): array
    {
        $base = D::of($received);
        $rate = D::of($percentage ?? '0');
        $floor = D::of($minimum ?? '0');
        if ($base->isNegative() || $rate->isNegative() || $rate->isGreaterThan('100') || $floor->isNegative()) {
            Classification::error('commission', 'Los valores de la comisión bancaria no son válidos.');
        }
        $calculated = $base->multipliedBy($rate)->dividedBy(100);
        $exact = D::max($calculated, $floor);
        $settled = D::of(D::represent($exact, 2, true));
        if ($exact->isGreaterThan($base) || $settled->isGreaterThan($base)) {
            Classification::error('commission', 'La comisión bancaria no puede superar el importe recibido.');
        }
        return [
            'amount' => D::represent($settled, 2, true),
            'calculation_snapshot' => D::snapshot([
                'base' => $base, 'percentage' => $rate, 'minimum' => $floor,
                'calculated' => $calculated, 'exact' => $exact, 'amount' => $settled,
            ]),
        ];
    }
}
