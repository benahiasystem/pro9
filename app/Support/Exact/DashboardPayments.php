<?php

namespace App\Support\Exact;

use App\Models\Tenant\Document;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Applied documentary payments in VES, preserving the dashboard's existing amount basis. */
final class DashboardPayments
{
    public static function range($establishment, $start, $end): array
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($establishment,$start,$end) {
            $queries=[
                'document'=>DashboardSales::documents($establishment,$start,$end)->whereIn('document_type_id',['01','08']),
                'sale_note'=>DashboardSales::notes($establishment,$start,$end),
            ];
            $totals=[];
            foreach ($queries as $source=>$query) {
                $query->with(['payments'=>function ($payments) use ($source) {
                    $payments->withoutEagerLoads()->with([
                        'payment_method_type'=>fn($method)=>$method->select('id','description'),
                        'global_payment'=>fn($movement)=>$movement->withoutEagerLoads()->select('id','payment_id','payment_type','report_context'),
                    ]);
                    if ($source==='document') $payments->whereNull('reversed_at');
                }]);
                foreach ($query->lazyById(500) as $parent) {
                    foreach ($parent->payments as $payment) {
                        $label=self::method($payment);
                        $amount=DashboardSales::inVesAmount($parent,Decimal::readRequired($payment,'payment'));
                        $totals[$label]=($totals[$label] ?? Decimal::of(0))->plus($amount)->simplified();
                    }
                }
            }
            uksort($totals,static fn($left,$right)=>$totals[$right]->compareTo($totals[$left]) ?: strcmp((string)$left,(string)$right));
            $labels=$values=$exact=$fractions=[]; $total=Decimal::of(0);
            foreach ($totals as $label=>$amount) {
                if (!$amount->isPositive()) continue;
                $text=Decimal::format($amount,2,'.','');
                $labels[]=(string)$label; $values[]=(float)$text;
                $exact[]=$text; $fractions[]=Decimal::fraction($amount);
                $total=$total->plus($amount)->simplified();
            }
            return ['labels'=>$labels,'values'=>$values,'values_exact'=>$exact,'values_fractions'=>$fractions]
                + DashboardSales::numericProjection(['total'=>$total]);
        });
    }

    private static function method($payment): string
    {
        $frozen=$payment->payment_method_snapshot ?? [];
        $context=$payment->global_payment?->report_context ?? [];
        foreach ([$frozen['description'] ?? null,$context['method']['description'] ?? null,
            $payment->payment_method_type?->description] as $label) {
            if ($label !== null && $label !== '') return (string)$label;
        }
        return 'Otros';
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
