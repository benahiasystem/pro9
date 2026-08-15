<?php

namespace Modules\Expense\Widgets;

use Carbon\Carbon;
use Modules\Expense\Models\Expense;
use Modules\Dashboard\Widgets\WidgetContext;
use Modules\Dashboard\Widgets\WidgetDataset;
use Modules\Dashboard\Widgets\WidgetSource;

/**
 * Fuente de ejemplo del patrón de extensión (ver modules/Dashboard/WIDGETS.md):
 * un módulo ajeno a Dashboard aporta una métrica al catálogo de widgets
 * registrándose en su propio ServiceProvider.
 */
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
        $filters = $context->filters();

        $range = \Modules\Dashboard\Helpers\DashboardFilterHelper::resolve($filters);
        $date_start = $range['date_start'];
        $date_end = $range['date_end'];

        $current = $this->totalsByDate($range['establishment_id'], $date_start, $date_end);

        $previous_total = null;
        if ($date_start && $date_end) {
            $days = Carbon::parse($date_start)->diffInDays(Carbon::parse($date_end)) + 1;
            $prev_end = Carbon::parse($date_start)->subDay();
            $prev_start = $prev_end->copy()->subDays($days - 1);
            $previous = $this->totalsByDate($range['establishment_id'], $prev_start->format('Y-m-d'), $prev_end->format('Y-m-d'));
            $previous_total = array_sum($previous['values']);
        }

        return WidgetDataset::make(WidgetDataset::UNIT_MONEY)
            ->serie($current['labels'], [['name' => 'Gastos', 'data' => $current['values']]])
            ->totals(array_sum($current['values']), $previous_total);
    }

    /**
     * Gastos aceptados agrupados por día, normalizados a PEN
     * (mismas reglas que TotalsTrait::get_expense_totals).
     */
    private function totalsByDate($establishment_id, $date_start, $date_end)
    {
        $expenses = Expense::query()
            ->where('state_type_id', '05')
            ->when($establishment_id, function ($query) use ($establishment_id) {
                $query->where('establishment_id', $establishment_id);
            })
            ->when($date_start && $date_end, function ($query) use ($date_start, $date_end) {
                $query->whereBetween('date_of_issue', [$date_start, $date_end]);
            })
            ->select('date_of_issue', 'total', 'currency_type_id', 'exchange_rate_sale')
            ->get();

        $by_date = [];
        foreach ($expenses as $expense) {
            $factor = $expense->currency_type_id === 'USD' ? (float) $expense->exchange_rate_sale : 1;
            $key = Carbon::parse($expense->date_of_issue)->format('Y-m-d');
            $by_date[$key] = ($by_date[$key] ?? 0) + $expense->total * $factor;
        }

        $labels = [];
        $values = [];

        if ($date_start && $date_end) {
            $cursor = Carbon::parse($date_start);
            $end = Carbon::parse($date_end);
            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $values[] = round($by_date[$key] ?? 0, 2);
                $cursor->addDay();
            }
        } else {
            ksort($by_date);
            foreach ($by_date as $key => $total) {
                $labels[] = Carbon::parse($key)->format('d/m');
                $values[] = round($total, 2);
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
