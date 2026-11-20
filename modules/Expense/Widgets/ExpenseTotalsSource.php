<?php

namespace Modules\Expense\Widgets;

use Carbon\Carbon;
use App\Support\Exact\{Decimal as D, DashboardSales, ReadSnapshot};
use Modules\Expense\Models\Expense;
use Modules\Dashboard\Widgets\WidgetContext;
use Modules\Dashboard\Widgets\WidgetDataset;
use Modules\Dashboard\Widgets\WidgetSource;

/**
 * Fuente de ejemplo del patrón de extensión (ver modules/Dashboard/WIDGETS.md):
 * un módulo ajeno a Dashboard aporta una métrica al catálogo de widgets
 * registrándose en su propio ServiceProvider.
 */
// ######## INICIO CALCULOS EXACTOS SAB ########
class ExpenseTotalsSource extends WidgetSource
{
    public function key()
    {
        return 'gastos.totales';
    }

    public function label()
    {
        return 'Gastos';
    }

    public function module()
    {
        return 'gastos';
    }

    public function moduleLabel()
    {
        return 'Gastos';
    }

    public function icon()
    {
        return 'ti-cash-banknote-off';
    }

    public function focus()
    {
        return 'serie';
    }

    public function defaultType()
    {
        return 'area';
    }

    public function resolve(WidgetContext $context, array $options = [])
    {
        return ReadSnapshot::run((new Expense)->getConnection(),function () use ($context) {
            $range=\Modules\Dashboard\Helpers\DashboardFilterHelper::resolve($context->filters());
            $start=$range['date_start'];$end=$range['date_end'];
            $current=$this->totalsByDate($range['establishment_id'],$start,$end);$previousTotal=null;
            if ($start && $end) {
                $days=Carbon::parse($start)->diffInDays(Carbon::parse($end))+1;
                $previousEnd=Carbon::parse($start)->subDay();$previousStart=$previousEnd->copy()->subDays($days-1);
                $previous=$this->totalsByDate($range['establishment_id'],$previousStart->format('Y-m-d'),$previousEnd->format('Y-m-d'));
                $previousTotal=D::sum($previous['values']);
            }
            return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
                ->serie($current['labels'],[['name'=>'Gastos','data'=>$current['values']]])
                ->totals(D::sum($current['values']),$previousTotal);
        });
    }

    private function totalsByDate($establishment, $start, $end)
    {
        $expenses=Expense::withoutEagerLoads()->where('state_type_id','05')
            ->when($establishment,fn($query)=>$query->where('establishment_id',$establishment))
            ->when($start && $end,fn($query)=>$query->whereBetween('date_of_issue',[$start,$end]))
            ->select('id','date_of_issue','total','currency_type_id','exchange_rate_sale','calculation_snapshot');
        $byDate=[];
        foreach ($expenses->lazyById(500) as $expense) {
            $key=(string)$expense->getRawOriginal('date_of_issue');
            $byDate[$key]=($byDate[$key] ?? D::of(0))->plus(DashboardSales::inVes($expense))->simplified();
        }
        $labels=$values=[];
        if ($start && $end) {
            $cursor=Carbon::parse($start)->startOfDay();$last=Carbon::parse($end)->startOfDay();
            $format=$cursor->year===$last->year ? 'd/m' : 'd/m/Y';
            while ($cursor->lessThanOrEqualTo($last)) {
                $key=$cursor->format('Y-m-d');$labels[]=$cursor->format($format);$values[]=$byDate[$key] ?? D::of(0);
                $cursor->addDay();
            }
        } else {
            ksort($byDate);
            foreach ($byDate as $key=>$value) {$labels[]=Carbon::parse($key)->format('d/m/Y');$values[]=$value;}
        }
        return ['labels'=>$labels,'values'=>$values];
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
