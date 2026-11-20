<?php

namespace App\Support\Exact;

use Carbon\Carbon;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Calendar buckets and final chart projections; original fractions remain available. */
final class DashboardSeries
{
    public static function buckets(string $start, string $end): array
    {
        $cursor=Carbon::parse($start)->startOfDay(); $last=Carbon::parse($end)->startOfDay();
        if ($cursor->greaterThan($last)) return [];
        $kind=$cursor->diffInDays($last)<31 ? 'day' : 'month';
        $buckets=[];
        while ($cursor->lessThanOrEqualTo($last)) {
            $until=$kind==='day' ? $cursor->copy() : $cursor->copy()->endOfMonth();
            if ($until->greaterThan($last)) $until=$last->copy();
            $buckets[]=['key'=>$kind==='day' ? $cursor->format('Y-m-d') : $cursor->format('Y-m'),
                'label'=>$kind==='day' ? $cursor->format('d/m') : $cursor->format('m/Y'),
                'start'=>$cursor->format('Y-m-d'),'end'=>$until->format('Y-m-d'),'kind'=>$kind];
            $cursor=$until->copy()->addDay()->startOfDay();
        }
        return $buckets;
    }

    /** Internal values are rational; controllers project them only after comparison alignment. */
    public static function sales($establishment, string $start, string $end): array
    {
        $buckets=self::buckets($start,$end); $values=[];
        foreach ($buckets as $bucket) $values[$bucket['key']]=Decimal::of(0);
        $rows=DashboardSales::dataset($establishment,$start,$end);
        foreach ($rows as $row) {
            $kind=$buckets[0]['kind'] ?? 'day';
            $key=$kind==='day' ? $row['date_of_issue'] : substr($row['date_of_issue'],0,7);
            if (!array_key_exists($key,$values)) continue;
            $values[$key]=$values[$key]->plus(Decimal::readRequired($row,'amount'))->simplified();
        }
        return ['labels'=>array_column($buckets,'label'),'values'=>array_values($values)];
    }

    /** Null is a calendar gap in an aligned comparison, not an invented zero sale. */
    public static function project(array $values): array
    {
        $data=$exact=$fractions=[]; $total=Decimal::of(0);
        foreach ($values as $value) {
            if ($value===null) {$data[]=null;$exact[]=null;$fractions[]=null;continue;}
            $value=Decimal::of($value); $text=Decimal::format($value,2,'.','');
            $data[]=(float)$text; $exact[]=$text; $fractions[]=Decimal::fraction($value);
            $total=$total->plus($value)->simplified();
        }
        return ['data'=>$data,'exact'=>$exact,'fractions'=>$fractions,'total'=>$total];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
