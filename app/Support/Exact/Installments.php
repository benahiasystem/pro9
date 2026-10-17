<?php

namespace App\Support\Exact;

/** Keep the original shares separately from the amounts scheduled in cents. */
final class Installments
{
    public static function calculate($total, array $rows, $payable = null): array
    {
        if (!$rows) return [];
        $total=Decimal::of($total);
        $payable=Decimal::of($payable ?? Decimal::represent($total,2));
        $submitted=array_map(fn ($row)=>Decimal::input($row['amount'] ?? 0,'fee.amount'),$rows);
        $weighted=count(array_filter($rows,fn ($row)=>isset($row['allocation_weight'])))===count($rows);
        $weights=$weighted ? array_map(fn ($row)=>Decimal::input($row['allocation_weight'],'fee.allocation_weight'),$rows) : $submitted;
        foreach (array_merge($submitted,$weights) as $weight) if (Decimal::of($weight)->isNegative()) {
            \App\Services\Fiscal\FiscalAmounts::error('fee.amount','La participación de una cuota no puede ser negativa.');
        }
        // A manual edit replaces the automatic distribution. Do not reuse stale weights.
        if ($weighted && !Decimal::sum($weights)->isZero()) {
            $expected=Decimal::allocate($payable,$weights,2);
            foreach ($submitted as $i=>$amount) {
                if (!Decimal::of($amount)->isEqualTo($expected[$i])) { $weights=$submitted; break; }
            }
        }
        $weightTotal=Decimal::sum($weights);
        if ($weightTotal->isZero()) { $weights=array_fill(0,count($rows),'1');$weightTotal=Decimal::of(count($rows)); }
        $amounts=Decimal::allocate($payable,$weights,2);
        foreach (array_values($rows) as $index=>$row) {
            $share=$total->multipliedBy(Decimal::of($weights[$index]))->dividedBy($weightTotal);
            unset($row['allocation_weight']);
            $row['amount']=$amounts[$index];
            $row['calculation_snapshot']=Decimal::snapshot(['amount'=>$amounts[$index],'scheduled_exact'=>$share,
                'settlement_adjustment'=>Decimal::of($amounts[$index])->minus($share)],
                ['total'=>Decimal::fraction($total),'weight'=>Decimal::fraction($weights[$index]),
                    'weight_total'=>Decimal::fraction($weightTotal),'requested_amount'=>$submitted[$index],'position'=>$index]);
            $rows[$index]=$row;
        }
        return array_values($rows);
    }
}
