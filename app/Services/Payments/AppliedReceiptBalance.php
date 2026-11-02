<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal as D;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
/** Documentary balance uses applied amounts, not received foreign currency. */
final class AppliedReceiptBalance
{
    public static function values($record, bool $includeCost = false, bool $lockPayments = false): array
    {
        $total=D::read($record, 'total');
        if ($includeCost) $total=$total->plus(D::read($record, 'cost'));
        // A locking read observes receipts committed after an earlier REPEATABLE READ snapshot.
        $payments=$record->payments();
        if ($lockPayments) $payments->lockForUpdate();
        $paid=D::sum($payments->get()->map(fn ($payment) => D::readRequired($payment, 'payment')));
        return ['total'=>$total, 'total_paid'=>$paid, 'total_difference'=>$total->minus($paid)];
    }

    public static function transport(array $values): array
    {
        return array_map(fn ($value) => D::represent($value), $values)
            + ['calculation_snapshot'=>D::snapshot($values)];
    }

    public static function assertFits(array $values, array $receipt): void
    {
        if (D::readRequired($receipt, 'payment')->isGreaterThan($values['total_difference'])) {
            Classification::error('payment', 'El importe aplicado supera el saldo pendiente.');
        }
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
