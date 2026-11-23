<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\Purchase;
use Carbon\Carbon;
use App\Support\Exact\{Decimal as D, DashboardSales, DashboardSeries};

/**
 * Clase DashboardKpi
 *
 * Totales de KPI para el nuevo dashboard. Replica las reglas del dashboard
 * anterior (tipos de estado aceptados, tipos de documento de venta, resta de
 * notas de crédito y conversión de moneda VES/USD). Ventas y compras se
 * acumulan desde snapshots exactos; las series gráficas se entregan como números.
 *
 * Por defecto abarca todo el negocio; pasa establishment_id para limitarlo a
 * una sola sucursal.
 *
 * @package Modules\Dashboard\Helpers
 */
class DashboardKpi
{
    private $acceptedStates = ['01', '03', '05', '07', '13'];

    private $salesDocumentTypes = ['01', '03', '08'];

    private $monthLabels = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    public function data($request)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $establishment=$request['establishment_id'] ?? null; $compare=$request['compare'] ?? 'none';
            [$start,$end]=$this->resolveRange($request);
            $sales=$this->salesAmount($establishment,$start,$end);
            $purchases=$this->purchasesAmount($establishment,$start,$end);
            [$previousStart,$previousEnd]=$this->resolveCompareRange($start,$end,$compare);
            $priorSales=$previousStart ? $this->salesAmount($establishment,$previousStart,$previousEnd) : D::of(0);
            $priorPurchases=$previousStart ? $this->purchasesAmount($establishment,$previousStart,$previousEnd) : D::of(0);
            $year=Carbon::parse($end);
            $values=['month_sales'=>$sales,'month_purchases'=>$purchases,
                'accumulated_sales'=>$this->salesAmount($establishment,$year->copy()->startOfYear()->format('Y-m-d'),$year->copy()->endOfYear()->format('Y-m-d'))];
            $result=['accumulated_year'=>$year->format('Y')];
            foreach ($values as $key=>$amount) $result[$key]=$this->formatNumber($amount);
            $values['previous_sales']=$priorSales; $values['previous_purchases']=$priorPurchases;
            foreach (['month_sales_variation'=>[$sales,$priorSales],'month_purchases_variation'=>[$purchases,$priorPurchases]] as $key=>[$current,$previous]) {
                $amount=$compare==='none' ? D::of(0) : $this->variationAmount($current,$previous);
                $result[$key.'_exact']=D::format($amount,1,'.',''); $result[$key]=(float)$result[$key.'_exact'];
                $values[$key]=$amount;
            }
            return $result+['calculation_snapshot'=>D::snapshot($values)];
        });
    }

    /**
     * Resuelve el rango de fechas [inicio, fin] a partir del request. Usa el
     * mes actual cuando no se proporciona un rango explícito.
     */
    private function resolveRange($request)
    {
        $start = isset($request['date_start']) && $request['date_start'] ? $request['date_start'] : null;
        $end = isset($request['date_end']) && $request['date_end'] ? $request['date_end'] : null;

        if ((!$start || !$end) && ($request['period'] ?? null)==='all') {
            $starts=$ends=[];$establishment=$request['establishment_id'] ?? null;
            $documents=Document::withoutEagerLoads()->whereIn('state_type_id',$this->acceptedStates)
                ->whereIn('document_type_id',['01','03','07','08']);
            $notes=SaleNote::withoutEagerLoads()->whereStateTypeAccepted()
                ->where(fn ($q)=>$q->where('changed',false)->orWhereNotNull('document_id'));
            foreach ([$documents,$notes,Purchase::withoutEagerLoads()->whereStateTypeAccepted()] as $query) {
                if ($establishment) $query->where('establishment_id',$establishment);
                $first=(clone $query)->min('date_of_issue');$last=(clone $query)->max('date_of_issue');
                if ($first!==null) $starts[]=(string)$first;
                if ($last!==null) $ends[]=(string)$last;
            }
            $start=$start ?? ($starts ? min($starts) : null);$end=$end ?? ($ends ? max($ends) : null);
        }

        if (!$start || !$end) {
            $now = Carbon::now();
            $start = $now->copy()->startOfMonth()->format('Y-m-d');
            $end = $now->copy()->endOfMonth()->format('Y-m-d');
        }

        return [$start, $end];
    }

    /**
     * Resuelve el rango de comparación. Devuelve [null, null] cuando no hay
     * comparación.
     */
    private function resolveCompareRange($start, $end, $compare)
    {
        if ($compare === 'previous_year') {
            return [
                Carbon::parse($start)->subYearNoOverflow()->format('Y-m-d'),
                Carbon::parse($end)->subYearNoOverflow()->format('Y-m-d'),
            ];
        }

        if ($compare === 'previous_period') {
            $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
            $prev_end = Carbon::parse($start)->subDay();
            $prev_start = $prev_end->copy()->subDays($days - 1);

            return [$prev_start->format('Y-m-d'), $prev_end->format('Y-m-d')];
        }

        return [null, null];
    }

    /**
     * Ventas vs compras desglosadas según el período seleccionado:
     *  - today : 24 segmentos por hora (00h..23h)
     *  - week  : 7 segmentos por día (Lun..Dom)
     *  - month : segmentos semanales dentro del mes (Sem 1..N)
     *  - year  : 12 segmentos por mes (ENE..DIC)
     *
     * Por defecto abarca todo el negocio; pasa establishment_id para limitarlo
     * a una sola sucursal.
     */
    public function monthlyComparison($request)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Document)->getConnection(), function () use ($request) {
        $establishment_id = isset($request['establishment_id']) ? $request['establishment_id'] : null;
        $period = isset($request['period']) ? $request['period'] : 'month';

        [$range_start, $range_end] = $this->resolveRange($request);

        switch ($period) {
            case 'today':
                return $this->bucketsByHour($establishment_id, $range_start);
            case 'week':
                return $this->bucketsByDay($establishment_id, $range_start, $range_end);
            case 'year':
            case 'all':
                return $this->bucketsByMonth($establishment_id, $range_start, $range_end);
            case 'month':
            default:
                return $this->bucketsByWeek($establishment_id, $range_start, $range_end);
        }
        });
    }

    /**
     * 24 segmentos por hora para un solo día.
     */
    private function bucketsByHour($establishment_id, $day)
    {
        $categories=$sales=$purchases=[];
        for ($hour=0;$hour<24;$hour++) {
            $categories[]=str_pad($hour,2,'0',STR_PAD_LEFT).'h';
            $sales[]=$this->salesAmount($establishment_id,$day,$day,$hour);
            $purchases[]=$this->purchasesAmount($establishment_id,$day,$day,$hour);
        }
        return $this->comparisonResult($categories,$sales,$purchases);
    }

    /**
     * Un segmento por día a lo largo del rango [inicio, fin] (usado en la vista
     * semanal).
     */
    private function bucketsByDay($establishment_id, $start, $end)
    {
        $dayLabels=[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'];
        $cursor=Carbon::parse($start)->startOfDay(); $last=Carbon::parse($end)->startOfDay();
        $categories=$sales=$purchases=[];
        while ($cursor->lessThanOrEqualTo($last)) {
            $day=$cursor->format('Y-m-d'); $categories[]=$dayLabels[$cursor->dayOfWeekIso];
            $sales[]=$this->salesAmount($establishment_id,$day,$day);
            $purchases[]=$this->purchasesAmount($establishment_id,$day,$day); $cursor->addDay();
        }
        return $this->comparisonResult($categories,$sales,$purchases);
    }

    /**
     * Segmentos semanales (bloques de 7 días) dentro del mes que contiene el
     * rango.
     */
    private function bucketsByWeek($establishment_id, $start, $end)
    {
        $cursor=Carbon::parse($start)->startOfDay(); $last=Carbon::parse($end)->startOfDay(); $week=1;
        $categories=$sales=$purchases=[];
        while ($cursor->lessThanOrEqualTo($last)) {
            $until=$cursor->copy()->addDays(6); if ($until->greaterThan($last)) $until=$last->copy();
            $categories[]='Sem '.$week;
            $sales[]=$this->salesAmount($establishment_id,$cursor->format('Y-m-d'),$until->format('Y-m-d'));
            $purchases[]=$this->purchasesAmount($establishment_id,$cursor->format('Y-m-d'),$until->format('Y-m-d'));
            $cursor=$until->copy()->addDay();$week++;
        }
        return $this->comparisonResult($categories,$sales,$purchases);
    }

    /**
     * Un segmento por mes a lo largo del rango [inicio, fin] (usado en la vista
     * anual).
     */
    private function bucketsByMonth($establishment_id, $start, $end)
    {
        $first=Carbon::parse($start)->startOfDay();$last=Carbon::parse($end)->startOfDay();$cursor=$first->copy()->startOfMonth();
        $sameYear=$first->year===$last->year; $categories=$sales=$purchases=[];
        while ($cursor->lessThanOrEqualTo($last)) {
            $from=$cursor->lessThan($first) ? $first->copy() : $cursor->copy();
            $until=$cursor->copy()->endOfMonth(); if ($until->greaterThan($last)) $until=$last->copy();
            $categories[]=$this->monthLabels[$cursor->month].($sameYear ? '' : ' '.$cursor->format('Y'));
            $sales[]=$this->salesAmount($establishment_id,$from->format('Y-m-d'),$until->format('Y-m-d'));
            $purchases[]=$this->purchasesAmount($establishment_id,$from->format('Y-m-d'),$until->format('Y-m-d'));
            $cursor->addMonthNoOverflow();
        }
        return $this->comparisonResult($categories,$sales,$purchases);
    }

    private function comparisonResult(array $categories, array $sales, array $purchases): array
    {
        $sale=DashboardSeries::project($sales);$purchase=DashboardSeries::project($purchases);
        return ['categories'=>$categories,'sales'=>$sale['data'],'purchases'=>$purchase['data'],
            'sales_exact'=>$sale['exact'],'purchases_exact'=>$purchase['exact'],
            'sales_fractions'=>$sale['fractions'],'purchases_fractions'=>$purchase['fractions']]
            + DashboardSales::numericProjection(['sales_total'=>$sale['total'],'purchases_total'=>$purchase['total']]);
    }

    /**
     * Ventas mes a mes del año actual vs el año anterior, desde enero hasta el
     * mes actual. Por defecto abarca todo el negocio.
     */
    public function salesGrowth($request)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $establishment=$request['establishment_id'] ?? null;[, $rangeEnd]=$this->resolveRange($request);
            $now=Carbon::now();$endDate=Carbon::parse($rangeEnd);$currentYear=$endDate->year;$previousYear=$currentYear-1;
            $lastMonth=$currentYear===$now->year ? $now->month : 12;
            $categories=$current=$previous=[];
            for ($month=1;$month<=$lastMonth;$month++) {
                $currentStart=Carbon::create($currentYear,$month,1)->startOfDay();$previousStart=Carbon::create($previousYear,$month,1)->startOfDay();
                $categories[]=$this->monthLabels[$month];
                $current[]=$this->salesAmount($establishment,$currentStart->format('Y-m-d'),$currentStart->copy()->endOfMonth()->format('Y-m-d'));
                $previous[]=$this->salesAmount($establishment,$previousStart->format('Y-m-d'),$previousStart->copy()->endOfMonth()->format('Y-m-d'));
            }
            $cur=DashboardSeries::project($current);$prev=DashboardSeries::project($previous);
            return ['categories'=>$categories,'current'=>$cur['data'],'previous'=>$prev['data'],
                'current_exact'=>$cur['exact'],'previous_exact'=>$prev['exact'],
                'current_fractions'=>$cur['fractions'],'previous_fractions'=>$prev['fractions'],
                'current_label'=>(string)$currentYear,'previous_label'=>(string)$previousYear]
                + DashboardSales::numericProjection(['current_total'=>$cur['total'],'previous_total'=>$prev['total']]);
        });
    }

    /**
     * Variación porcentual de $current respecto a $previous.
     * Devuelve 100 cuando el mes anterior no hubo nada pero ahora sí,
     * y 0 cuando ambos son cero, para evitar la división por cero.
     */
    private function variation($current, $previous)
    {
        $D=\App\Support\Exact\Decimal::class;
        $current=$D::of($current);$previous=$D::of($previous);
        if ($previous->isZero()) {
            return $current->isPositive() ? 100.0 : 0.0;
        }
        return (float) $D::format($current->minus($previous)->dividedBy($previous)->multipliedBy(100),1,'.','');
    }

    /**
     * Ventas = (documentos de venta - notas de crédito) + notas de venta, con
     * USD convertido a VES.
     * Pasa $hour (0-23) para limitar a una sola hora del día indicado.
     */
    private function salesAmount($establishment_id, $start, $end, $hour = null)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Document)->getConnection(), function () use ($establishment_id,$start,$end,$hour) {
        $D = \App\Support\Exact\Decimal::class;
        $sum = $D::of(0);
        $invoices = Document::withoutEagerLoads()->select('id')->whereIn('state_type_id', $this->acceptedStates)
            ->where('document_type_id','01')->whereBetween('date_of_issue',[$start,$end])
            ->when($establishment_id,fn ($q)=>$q->where('establishment_id',$establishment_id))
            ->when(!is_null($hour),$this->hourFilter($hour));
        $documents = Document::withoutEagerLoads()->whereIn('state_type_id',$this->acceptedStates)
            ->whereIn('document_type_id',['01','03','07','08'])->whereBetween('date_of_issue',[$start,$end])
            ->when($establishment_id,fn ($q)=>$q->where('establishment_id',$establishment_id))
            ->when(!is_null($hour),$this->hourFilter($hour));
        $notes = SaleNote::withoutEagerLoads()->whereStateTypeAccepted()->whereBetween('date_of_issue',[$start,$end])
            ->where(fn ($q)=>$q->where('changed',false)->orWhereNotNull('document_id'))
            ->where(fn ($q)=>$q->whereNull('document_id')->orWhereNotIn('document_id',$invoices))
            ->when($establishment_id,fn ($q)=>$q->where('establishment_id',$establishment_id))
            ->when(!is_null($hour),$this->hourFilter($hour));
        foreach ([$documents,$notes] as $query) {
            foreach ($query->lazyById(500) as $record) {
                $currency=$record->currency_type_id;
                $rate=$currency === 'USD' ? \App\Support\Exact\DashboardSales::historicalRate($record) : '1';
                $amount=$D::convert($D::readRequired($record,'total'),$currency,'VES',$rate);
                if ($record instanceof Document && $record->document_type_id === '07') $amount=$amount->negated();
                $sum=$sum->plus($amount)->simplified();
            }
        }
        return $sum;
        });
    }

    /**
     * Compras convertidas a VES.
     * Pasa $hour (0-23) para limitar a una sola hora del día indicado.
     */
    private function purchasesAmount($establishment_id, $start, $end, $hour = null)
    {
        return \App\Support\Exact\ReadSnapshot::run((new Purchase)->getConnection(), function () use ($establishment_id,$start,$end,$hour) {
            $D=\App\Support\Exact\Decimal::class;
            $sum=$D::of(0);
            $purchases=Purchase::withoutEagerLoads()->whereStateTypeAccepted()->whereBetween('date_of_issue',[$start,$end])
                ->when($establishment_id,fn ($q)=>$q->where('establishment_id',$establishment_id))
                ->when(!is_null($hour),$this->hourFilter($hour));
            foreach ($purchases->lazyById(500) as $purchase) {
                $currency=$purchase->currency_type_id;
                $rate=$currency === 'USD' ? \App\Support\Exact\DashboardSales::historicalRate($purchase) : '1';
                $amount=$D::readRequired($purchase,'total');
                $sum=$sum->plus($D::convert($amount,$currency,'VES',$rate))->simplified();
            }
            return $sum;
        });
    }

    private function salesTotal($establishment_id, $start, $end, $hour = null)
    {
        return $this->formatNumber($this->salesAmount($establishment_id,$start,$end,$hour));
    }

    private function purchasesTotal($establishment_id, $start, $end, $hour = null)
    {
        return $this->formatNumber($this->purchasesAmount($establishment_id,$start,$end,$hour));
    }

    private function variationAmount($current, $previous): \Brick\Math\BigRational
    {
        $current=D::of($current);$previous=D::of($previous);
        if ($previous->isZero()) return D::of($current->isPositive() ? 100 : 0);
        return $current->minus($previous)->dividedBy($previous)->multipliedBy(100)->simplified();
    }

    /**
     * Devuelve un closure de consulta que restringe time_of_issue a la hora
     * indicada.
     */
    private function hourFilter($hour)
    {
        $hh = str_pad((int) $hour, 2, '0', STR_PAD_LEFT);

        return function ($q) use ($hh) {
            $q->whereBetween('time_of_issue', ["{$hh}:00:00", "{$hh}:59:59"]);
        };
    }

    private function formatNumber($number)
    {
        return \App\Support\Exact\Decimal::format($number, 2, '.', '');
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
