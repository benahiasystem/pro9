<?php

namespace App\Support\Exact;

use App\Models\Tenant\Purchase;
use Carbon\Carbon;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Purchase headers and their own optional perceptions, at each historical rate. */
final class DashboardPurchases
{
    private static function dataset($establishment, $start, $end): ReportDataset
    {
        $query=Purchase::withoutEagerLoads()->whereStateTypeAccepted()
            ->when($establishment,fn($q)=>$q->where('establishment_id',$establishment))
            ->when($start && $end,fn($q)=>$q->whereBetween('date_of_issue',[$start,$end]))
            ->select('id','date_of_issue','currency_type_id','exchange_rate_sale','total','total_perception','calculation_snapshot');
        return new ReportDataset((function () use ($query) {
            foreach ($query->lazyById(500) as $purchase) {
                $purchaseAmount=DashboardSales::inVes($purchase);
                // A null perception means that this purchase has no perception.
                $perception=DashboardSales::inVesAmount($purchase,Decimal::read($purchase,'total_perception'));
                $values=['purchases_total'=>$purchaseAmount,'purchases_total_perception'=>$perception,
                    'total'=>$purchaseAmount->plus($perception)->simplified()];
                yield ['date_of_issue'=>(string)$purchase->getRawOriginal('date_of_issue')]
                    + Decimal::values($values)+['calculation_snapshot'=>Decimal::snapshot($values)];
            }
        })());
    }

    private static function totals(ReportDataset $rows): array
    {
        return ['purchases_total'=>$rows->sum('purchases_total'),
            'purchases_total_perception'=>$rows->sum('purchases_total_perception'),'total'=>$rows->sum('total')];
    }

    public static function range($establishment, $start, $end): array
    {
        return ReadSnapshot::run((new Purchase)->getConnection(),fn()=>DashboardSales::numericProjection(
            self::totals(self::dataset($establishment,$start,$end))));
    }

    public static function chart($establishment, $start, $end): array
    {
        return ReadSnapshot::run((new Purchase)->getConnection(),function () use ($establishment,$start,$end) {
            $rows=self::dataset($establishment,$start,$end);$first=$start;$last=$end;
            if (!$start || !$end) {
                foreach ($rows as $row) {
                    $date=$row['date_of_issue'];
                    if ($first===null || strcmp($date,$first)<0) $first=$date;
                    if ($last===null || strcmp($date,$last)>0) $last=$date;
                }
            }
            $first=Carbon::parse($first ?? Carbon::now()->startOfYear()->format('Y-m-d'))->startOfMonth();
            $last=Carbon::parse($last ?? Carbon::now()->endOfYear()->format('Y-m-d'))->startOfMonth();
            $sameYear=$first->year===$last->year;$labels=$buckets=[];
            $months=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            while ($first->lessThanOrEqualTo($last)) {
                $key=$first->format('Y-m');$labels[]=$months[$first->month].($sameYear ? '' : ' '.$first->format('Y'));
                $buckets[$key]=['purchases_total'=>Decimal::of(0),'purchases_total_perception'=>Decimal::of(0),'total'=>Decimal::of(0)];
                $first->addMonthNoOverflow();
            }
            foreach ($rows as $row) {
                $key=substr($row['date_of_issue'],0,7);
                foreach ($buckets[$key] as $field=>$value) $buckets[$key][$field]=$value->plus(Decimal::readRequired($row,$field))->simplified();
            }
            $datasets=[];
            foreach (['purchases_total_perception'=>['Total percepciones','254, 0, 108'],
                'purchases_total'=>['Total compras','36, 71, 232'],'total'=>['Total','177, 184, 194']] as $field=>[$label,$rgb]) {
                $projection=DashboardSeries::project(array_column($buckets,$field));
                $datasets[]=['label'=>$label,'data'=>$projection['data'],'data_exact'=>$projection['exact'],
                    'data_fractions'=>$projection['fractions'],'backgroundColor'=>'rgba('.$rgb.', .1)',
                    'borderColor'=>'rgb('.$rgb.')','borderWidth'=>2,'fill'=>true,'lineTension'=>0.3,'pointRadius'=>1];
            }
            $totals=self::totals($rows);
            return ['totals'=>array_map(static fn($value)=>Decimal::format($value,2,'.',','),$totals)
                    + ['calculation_snapshot'=>Decimal::snapshot($totals),'date_of_issue'=>['start'=>$start,'end'=>$end]],
                'graph'=>['labels'=>$labels,'datasets'=>$datasets]];
        });
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
