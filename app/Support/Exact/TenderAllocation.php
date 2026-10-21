<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Actual tender and change are cents; retain the original request independently. */
final class TenderAllocation
{
    private const CASH_METHODS = ['01','10'];
    public static function calculate($payable, array $rows): array
    {
        $target = Decimal::of($payable);
        if ($target->isNegative() || !$target->isEqualTo(Decimal::represent($target, 2))) {
            \App\Services\Fiscal\FiscalAmounts::error('total_payable', 'El importe liquidable debe ser no negativo y expresarse en céntimos.');
        }
        $received = $cash = Decimal::of(0);
        foreach ($rows as $i => $row) {
            if (is_float($row['payment'] ?? null)) {
                \App\Services\Fiscal\FiscalAmounts::error('payments.'.$i.'.payment', 'Transmita el importe decimal como texto, sin convertirlo a flotante.');
            }
            $request = Decimal::of(Decimal::input($row['payment'] ?? null, 'payments.'.$i.'.payment'));
            $amount = Decimal::of(Decimal::represent($request, 2));
            if ($request->isNegative() || ($request->isPositive() && $amount->isZero())) {
                \App\Services\Fiscal\FiscalAmounts::error('payments.'.$i.'.payment', 'El cobro debe ser no negativo y liquidable en céntimos.');
            }
            $rows[$i]['requested'] = $request;
            $rows[$i]['received'] = $amount;
            $received = $received->plus($amount);
            if (in_array($row['payment_method_type_id'] ?? null, self::CASH_METHODS, true)) $cash = $cash->plus($amount);
        }
        $remaining = $received->minus($target);
        if ($remaining->isNegative()) $remaining = Decimal::of(0);
        if ($remaining->isGreaterThan($cash)) {
            \App\Services\Fiscal\FiscalAmounts::error('payments', 'El exceso sólo puede devolverse desde el efectivo recibido.');
        }
        foreach ($rows as $i => $row) {
            $change = in_array($row['payment_method_type_id'] ?? null, self::CASH_METHODS, true)
                ? ($remaining->isLessThan($row['received']) ? $remaining : $row['received']) : Decimal::of(0);
            $remaining = $remaining->minus($change);
            $net = $row['received']->minus($change);
            $snapshot = Decimal::snapshot([
                'payment'=>$net, 'change'=>$change, 'requested_payment'=>$row['requested'],
                'tendered_amount'=>$row['received'], 'cash_adjustment'=>$row['received']->minus($row['requested']),
            ], ['tendered_payment'=>Decimal::input((string)$row['requested']->toBigDecimal()), 'tender_position'=>$i]);
            unset($row['requested'], $row['received'], $row['calculation_snapshot']);
            $row['payment'] = Decimal::represent($net);
            $row['change'] = Decimal::represent($change);
            $row['calculation_snapshot'] = $snapshot;
            $rows[$i] = $row;
        }
        return array_values($rows);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
