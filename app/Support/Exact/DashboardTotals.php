<?php

namespace App\Support\Exact;

use App\Models\Tenant\{Document, Purchase};
use Modules\Expense\Models\Expense;
use Modules\Order\Models\OrderNote;

// ######## INICIO CALCULOS EXACTOS SAB ########
/** Cash/documentary totals are accumulated separately, in the parent's currency. */
final class DashboardTotals
{
    public static function range(string $source, $establishment, $start, $end): array
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($source,$establishment,$start,$end) {
            switch ($source) {
                case 'document': $query=DashboardSales::documents($establishment,$start,$end); break;
                case 'sale_note': $query=DashboardSales::notes($establishment,$start,$end); break;
                case 'purchase': $query=self::filter(Purchase::withoutEagerLoads(),$establishment,$start,$end)
                    ->whereIn('state_type_id',DashboardSales::ACCEPTED_STATES); break;
                case 'expense': $query=self::filter(Expense::withoutEagerLoads(),$establishment,$start,$end)
                    ->where('state_type_id','05'); break;
                default: throw new \InvalidArgumentException('Origen de totales no soportado.');
            }
            $query->with(['payments'=>function ($payments) use ($source) {
                $payments->withoutEagerLoads();
                if ($source === 'document') $payments->whereNull('reversed_at');
            }]);
            $total=$paid=Decimal::of(0);
            foreach ($query->lazyById(500) as $record) {
                $amount=$source === 'document' ? DashboardSales::documentAmount($record) : DashboardSales::inVes($record);
                // Nullable purchase perception means no perception, rather than a missing price operand.
                if ($source === 'purchase' && $record->total_perception !== null) {
                    $amount=$amount->plus(DashboardSales::inVes($record,'total_perception'));
                }
                $total=$total->plus($amount)->simplified();
                foreach ($record->payments as $payment) {
                    $paid=$paid->plus(DashboardSales::inVesAmount($record,Decimal::readRequired($payment,'payment')))->simplified();
                }
            }
            return ['totals'=>DashboardSales::numericProjection(['total'=>$total,'total_payment'=>$paid])];
        });
    }

    private static function filter($query, $establishment, $start, $end)
    {
        return $query->when($establishment,fn($q)=>$q->where('establishment_id',$establishment))
            ->when($start && $end,fn($q)=>$q->whereBetween('date_of_issue',[$start,$end]));
    }

    public static function orderNotes($establishment, $start, $end): \Brick\Math\BigRational
    {
        return ReadSnapshot::run((new OrderNote)->getConnection(),function () use ($establishment,$start,$end) {
            $total=Decimal::of(0);
            foreach (OrderNote::withoutEagerLoads()->filterTotalsReport($establishment,$start,$end)->lazyById(500) as $record) {
                $total=$total->plus(DashboardSales::inVes($record))->simplified();
            }
            return $total;
        });
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
