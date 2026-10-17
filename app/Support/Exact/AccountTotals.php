<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class AccountTotals
{
    public static function payments(iterable $payments): array
    {
        $paid=$due=Decimal::of(0);
        foreach ($payments as $payment) {
            $value=Decimal::read($payment,'payment');
            if ($payment->state) $paid=$paid->plus($value)->simplified();
            else $due=$due->plus($value)->simplified();
        }
        $values=['total_due'=>$due,'total_paid'=>$paid,'total'=>$due->plus($paid),'total_difference'=>$due];
        return Decimal::values($values)+['calculation_snapshot'=>Decimal::snapshot($values)];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
