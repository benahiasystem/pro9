<?php

namespace App\Support\Exact;

use Carbon\Carbon;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** The widget and receivables use the same documentary balances, never SQL projections. */
final class DashboardDebtors
{
    public static function fromRows(ReportDataset $rows, int $limit = 4): array
    {
        if ($limit < 1 || $limit > 500) throw new \InvalidArgumentException('Límite de clientes fuera de rango.');
        $ordered = $rows->sortBy('customer_id');
        $customers = new ReportDataset((function () use ($ordered) {
            $current = null; $total = Decimal::of(0); $customer = null;
            foreach ($ordered as $row) {
                if ($current !== null && $current !== $row['customer_id']) {
                    yield self::customer($current,$customer,$total);
                    $total = Decimal::of(0);
                }
                $current = $row['customer_id']; $customer = $row['customer_name'];
                $total = $total->plus(Decimal::readRequired($row,'balance_ves_equivalent'))->simplified();
            }
            if ($current !== null) yield self::customer($current,$customer,$total);
        })());
        $top = $customers->sortExact('total_to_pay',true)->paginate($limit,1)->items();
        $byCustomer = [];
        foreach ($top as $row) $byCustomer[$row['customer_id']] = $row + ['debts'=>[]];
        $today = Carbon::today();
        foreach ($rows as $row) {
            if (!isset($byCustomer[$row['customer_id']])) continue;
            $amount = Decimal::readRequired($row,'balance_ves_equivalent');
            $due = empty($row['due_date_iso']) ? null : Carbon::parse($row['due_date_iso'])->startOfDay();
            $days = $due ? $today->diffInDays($due,false) : null;
            $status = $days !== null && $days < 0 ? 'vencido' : ($days !== null && $days <= 7 ? 'por_vencer' : 'al_dia');
            $text = $days === null ? 'sin vencimiento' : ($days < 0 ? 'venció hace '.abs($days).' '.(abs($days) === 1 ? 'día' : 'días')
                : ($days === 0 ? 'vence hoy' : ($days === 1 ? 'vence mañana' : "vence en {$days} días")));
            $byCustomer[$row['customer_id']]['debts'][] = [
                'id'=>$row['id'],'report_key'=>$row['report_key'],'number'=>$row['number_full'],
                'total_to_pay'=>Decimal::represent($amount),'calculation_snapshot'=>Decimal::snapshot(['total_to_pay'=>$amount]),
                'currency_type_id'=>$row['currency_type_id'],'original_balance'=>$row['total_to_pay'],
                'original_balance_exact'=>Decimal::fraction(Decimal::readRequired($row,'total_to_pay')),
                'due_days'=>$days,'due_text'=>$text,'status'=>$status,
            ];
        }
        foreach ($byCustomer as &$row) {
            // Calendar ordering, with a stable compound source identity on ties.
            usort($row['debts'],static fn($a,$b)=>($a['due_days'] ?? PHP_INT_MAX) <=> ($b['due_days'] ?? PHP_INT_MAX)
                ?: strnatcmp($a['report_key'],$b['report_key']));
            $urgent = $row['debts'][0];
            $row += ['status'=>$urgent['status'],'due_days'=>$urgent['due_days'],'due_text'=>$urgent['due_text'],
                'urgent_amount'=>$urgent['total_to_pay'],'urgent_amount_exact'=>Decimal::fraction(Decimal::readRequired($urgent,'total_to_pay')),
                'debts_count'=>count($row['debts'])];
        }
        unset($row);
        $total = $customers->sum('total_to_pay');
        return ['items'=>array_values($byCustomer),'count'=>count($customers),'total'=>Decimal::represent($total),
            'calculation_snapshot'=>Decimal::snapshot(['total'=>$total]),'summary'=>OutstandingSummary::summarize($rows)];
    }

    private static function customer($id, string $name, $total): array
    {
        return ['customer_id'=>$id,'customer'=>$name,'total_to_pay'=>Decimal::represent($total),
            'calculation_snapshot'=>Decimal::snapshot(['total_to_pay'=>$total])];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
