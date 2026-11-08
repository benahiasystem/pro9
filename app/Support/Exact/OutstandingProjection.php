<?php

namespace App\Support\Exact;

/** Project each currency separately and restore the original report order. */
final class OutstandingProjection
{
    public static function money(ReportDataset $records): ReportDataset
    {
        $position=0;
        $indexed=$records->map(function ($row) use (&$position) { $row['_outstanding_position']=$position++; return $row; });
        $groups=[];
        foreach (['VES','USD'] as $currency) {
            $group=$indexed->filter(fn($row)=>($row['currency_type_id'] ?? null)===$currency);
            $groups[]=ReportProjection::money($group,['total','total_to_pay','total_ves_equivalent','balance_ves_equivalent']);
        }
        $joined=new ReportDataset((function () use ($groups) { foreach ($groups as $group) foreach ($group as $row) yield $row; })());
        if (count($joined)!==count($indexed)) throw new \UnexpectedValueException('Moneda inválida en el saldo.');
        return $joined->sortBy('_outstanding_position')->map(function ($row) { unset($row['_outstanding_position']); return $row; });
    }
}
