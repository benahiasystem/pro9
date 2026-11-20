<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\Document;
use App\Support\Exact\{Decimal as D, ReadSnapshot, DashboardSales, DashboardTotals, Receivables, OutstandingSummary};
use App\Models\Tenant\Configuration;
use App\Models\Tenant\DocumentPayment;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNotePayment;
use Carbon\Carbon;
use App\Models\Tenant\Person;
use App\Models\Tenant\Item;
use App\Models\Tenant\Purchase;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Expense\Models\Expense;
use Modules\Dashboard\Traits\TotalsTrait;
use Illuminate\Support\Facades\DB;


class DashboardData
{

    use TotalsTrait;

    public function data($request)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $establishment_id = $request['establishment_id'];
            $period = $request['period'];
            $date_start = $request['date_start'];
            $date_end = $request['date_end'];
            $month_start = $request['month_start'];
            $month_end = $request['month_end'];

            $d_start = null;
            $d_end = null;

            switch ($period) {
                case 'month':
                    $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_start.'-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'between_months':
                    $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_end.'-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'date':
                    $d_start = $date_start;
                    $d_end = $date_start;
                    break;
                case 'between_dates':
                    $d_start = $date_start;
                    $d_end = $date_end;
                    break;
                case 'last_week':
                    $d_start = $date_start;
                    $d_end = $date_end;
                    break;
            }

            // $customers = Person::whereType('customers')->orderBy('name')->take(100)->get()->transform(function($row) {
            //     return [
            //         'id' => $row->id,
            //         'description' => $row->number.' - '.$row->name,
            //         'name' => $row->name,
            //         'number' => $row->number,
            //         'identity_document_type_id' => $row->identity_document_type_id,
            //     ];
            // });

            return [
                'document' => $this->document_totals($establishment_id, $d_start, $d_end),
                'sale_note' => $this->sale_note_totals($establishment_id, $d_start, $d_end),
                'general' => $this->totals($establishment_id, $d_start, $d_end, $period, $month_start, $month_end),
                'balance' => $this->balance($establishment_id, $d_start, $d_end),
                'items' => $this->getItems(),
                // 'quantity' => Configuration::first()->quantity_documents,
            ];
        });
    }

    private function resolveFilters(array $request = [])
    {
        return DashboardFilterHelper::resolve($request);
    }

    private function applyDateRange($query, $date_start, $date_end, $column = 'date_of_issue')
    {
        if ($date_start && $date_end) {
            $query->whereBetween($column, [$date_start, $date_end]);
        }

        return $query;
    }

    public function globalData(array $request = [])
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $filters=$this->resolveFilters($request);
            $kpis=$this->monthlyKpis(6,$filters);
            return array_merge(['total_cpe'=>Configuration::first()->quantity_documents,
                'document_total_global'=>$this->document_totals_globals($filters['date_start'],$filters['date_end']),
                'sale_note_total_global'=>$this->sale_note_totals_global($filters['date_start'],$filters['date_end'])],$kpis);
        });
    }

    private function monthlyKpis($months = 6, array $filters = [])
    {
        $months_es=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
        $periods=[];
        if (!empty($filters['date_start']) && !empty($filters['date_end']) && (($filters['period'] ?? null) !== 'month')) {
            $previous=$this->previousRange($filters['date_start'],$filters['date_end']);
            $periods=[['Anterior',$previous['start'],$previous['end']],['Actual',$filters['date_start'],$filters['date_end']]];
        } else {
            $base=!empty($filters['date_start']) ? Carbon::parse($filters['date_start'])->startOfMonth() : Carbon::now()->startOfMonth();
            for ($i=$months-1;$i>=0;$i--) {
                $ref=$base->copy()->subMonths($i);
                $periods[]=[$months_es[$ref->month],$ref->format('Y-m-d'),$ref->copy()->endOfMonth()->format('Y-m-d')];
            }
        }
        $keys=['monthly_sales','average_ticket','accounts_receivable','net_utility'];
        $trend=array_fill_keys($keys,[]); $trend['labels']=[]; $trend['exact']=array_fill_keys($keys,[]); $trend['calculation_snapshots']=[];
        $current=[];
        foreach ($periods as [$label,$start,$end]) {
            $current=$this->kpisForRange($start,$end,$filters['establishment_id'] ?? null);
            $trend['labels'][]=$label;
            foreach ($keys as $key) {
                $trend[$key][]=$current[$key]; $trend['exact'][$key][]=$current[$key.'_exact'];
            }
            $trend['calculation_snapshots'][]=$current['calculation_snapshot'];
        }
        return array_merge($current,['trend'=>$trend]);
    }

    /**
     * KPIs de un rango (todas las sucursales), normalizado a VES.
     * net_utility: ventas - costo de productos vendidos - gastos (por item).
     */
    private function previousRange($date_start, $date_end)
    {
        $start = Carbon::parse($date_start);
        $end = Carbon::parse($date_end);
        $days = $start->diffInDays($end) + 1;
        $previous_end = $start->copy()->subDay();

        return [
            'start' => $previous_end->copy()->subDays($days - 1)->format('Y-m-d'),
            'end' => $previous_end->format('Y-m-d'),
        ];
    }

    private function previousComparisonRange(array $filters)
    {
        if (($filters['period'] ?? null) === 'month') {
            $previous = Carbon::parse($filters['date_start'])->startOfMonth()->subMonth();

            return [
                'start' => $previous->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $previous->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        if (($filters['period'] ?? null) === 'between_months') {
            $start = Carbon::parse($filters['date_start'])->startOfMonth();
            $end = Carbon::parse($filters['date_end'])->startOfMonth();
            $months = $start->diffInMonths($end) + 1;
            $previous_start = $start->copy()->subMonths($months)->startOfMonth();
            $previous_end = $start->copy()->subMonth()->endOfMonth();

            return [
                'start' => $previous_start->format('Y-m-d'),
                'end' => $previous_end->format('Y-m-d'),
            ];
        }

        return $this->previousRange($filters['date_start'], $filters['date_end']);
    }

    private function kpisForRange($date_start, $date_end, $establishment_id = null)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($date_start,$date_end,$establishment_id) {
            $sales=DashboardSales::range($establishment_id,$date_start,$date_end)['total'];
            $count=D::of(DashboardSales::documents($establishment_id,$date_start,$date_end)
                ->whereIn('document_type_id',['01','08'])->count())->plus(DashboardSales::notes($establishment_id,$date_start,$date_end)->count());
            $filters=['period'=>'between_dates','date_start'=>$date_start,'date_end'=>$date_end,
                'establishment_id'=>$establishment_id,'stablishmentUnpaidAll'=>empty($establishment_id)];
            $receivables=OutstandingSummary::summarize(Receivables::dataset($filters));
            $utility=(new DashboardUtility())->calculateUtilityTotals($establishment_id,$date_start,$date_end,true,null)['totals'];
            $values=['monthly_sales'=>$sales,'average_ticket'=>$count->isPositive() ? $sales->dividedBy($count) : D::of(0),
                'accounts_receivable'=>D::readRequired($receivables,'consolidated_balance_ves'),
                'net_utility'=>D::readRequired($utility,'utility'),'income'=>$sales,'egress'=>D::readRequired($utility,'total_egress')];
            return DashboardSales::numericProjection($values)+['cost_is_estimated'=>true];
        });
    }

    public function lowStock(array $request = [])
    {
        $filters = $this->resolveFilters($request);
        $rows = \App\Support\Exact\DashboardStock::dataset($filters['establishment_id']);
        return ['items'=>iterator_to_array($rows),'total'=>count($rows)];
    }

    public function monthGoal()
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () {
            $configuration=Configuration::first();
            $goal=$configuration ? D::read($configuration,'dashboard_goal_amount') : D::of(0);
            $ref=Carbon::now();
            $sales=DashboardSales::range(null,$ref->copy()->startOfMonth()->format('Y-m-d'),$ref->copy()->endOfMonth()->format('Y-m-d'))['total'];
            $values=['goal'=>$goal,'sales'=>$sales,'remaining'=>D::max($goal->minus($sales),0),
                'projected'=>$sales->multipliedBy($ref->daysInMonth)->dividedBy($ref->day)->simplified()];
            $percent=$goal->isPositive() ? $sales->dividedBy($goal)->multipliedBy(100) : D::of(0);
            $result=DashboardSales::numericProjection($values);
            $result['enabled']=(bool)($configuration->dashboard_goal_enabled ?? false);
            $result['percent_exact']=D::format($percent,1,'.','');
            $result['percent']=(float)$result['percent_exact'];
            $result['calculation_snapshot']['values']['percent']=D::fraction($percent);
            return $result;
        });
    }

    public function debtors($request = [], $limit = 4)
    {
        if (is_numeric($request)) { $limit = (int)$request; $request = []; }
        $filters = $this->resolveFilters((array)$request);
        $filters['stablishmentUnpaidAll'] = empty($filters['establishment_id']);
        $rows = \App\Support\Exact\Receivables::dataset($filters);
        return \App\Support\Exact\DashboardDebtors::fromRows($rows,(int)$limit);
    }

    private function paymentMethodsSubtitle(array $filters)
    {
        $subtitles = [
            'date' => 'Distribucion de cobros - dia seleccionado',
            'between_dates' => 'Distribucion de cobros - rango seleccionado',
            'month' => 'Distribucion de cobros - mes seleccionado',
            'between_months' => 'Distribucion de cobros - meses seleccionados',
            'last_week' => 'Distribucion de cobros - semana seleccionada',
            'all' => 'Distribucion de cobros - todos los registros',
        ];

        return $subtitles[$filters['period'] ?? 'month'] ?? 'Distribucion de cobros - periodo filtrado';
    }

    public function paymentMethods(array $request = [])
    {
        $filters=$this->resolveFilters($request);
        return \App\Support\Exact\DashboardPayments::range(
            $filters['establishment_id'],$filters['date_start'],$filters['date_end'])
            + ['subtitle'=>$this->paymentMethodsSubtitle($filters)];
    }

    public function salesWeek(array $request = [])
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $filters=$this->resolveFilters($request);
            $filtered=($filters['period'] ?? null)!=='all' && !empty($filters['date_start']) && !empty($filters['date_end']);
            if ($filtered) {
                $start=$filters['date_start']; $end=$filters['date_end'];
                $previous=$this->previousComparisonRange($filters);
            } else {
                $week=Carbon::now()->startOfWeek(Carbon::MONDAY);
                $start=$week->format('Y-m-d'); $end=$week->copy()->addDays(6)->format('Y-m-d');
                $previous=['start'=>$week->copy()->subWeek()->format('Y-m-d'),
                    'end'=>$week->copy()->subDay()->format('Y-m-d')];
            }
            $current=\App\Support\Exact\DashboardSeries::sales($filters['establishment_id'],$start,$end);
            $prior=\App\Support\Exact\DashboardSeries::sales($filters['establishment_id'],$previous['start'],$previous['end']);
            if (!$filtered) $current['labels']=['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
            $points=max(count($current['values']),count($prior['values']));
            $labels=$previousLabels=$currentValues=$previousValues=[];
            for ($i=0;$i<$points;$i++) {
                $labels[]=$current['labels'][$i] ?? $prior['labels'][$i].' (anterior)';
                $previousLabels[]=$prior['labels'][$i] ?? null;
                $currentValues[]=$current['values'][$i] ?? null;
                $previousValues[]=$prior['values'][$i] ?? null;
            }
            $currentProjection=\App\Support\Exact\DashboardSeries::project($currentValues);
            $previousProjection=\App\Support\Exact\DashboardSeries::project($previousValues);
            return ['labels'=>$labels,'previous_labels'=>$previousLabels,
                'current'=>$currentProjection['data'],'previous'=>$previousProjection['data'],
                'current_exact'=>$currentProjection['exact'],'previous_exact'=>$previousProjection['exact'],
                'current_fractions'=>$currentProjection['fractions'],'previous_fractions'=>$previousProjection['fractions'],
                'subtitle'=>$filtered ? 'Barras solidas = periodo filtrado - gris = periodo anterior'
                    : 'Barras solidas = esta semana - gris = semana anterior']
                + DashboardSales::numericProjection(['current_total'=>$currentProjection['total'],'previous_total'=>$previousProjection['total']]);
        });
    }

    private function cashFlowSubtitle(array $filters)
    {
        $subtitles = [
            'date' => 'Ingresos vs egresos - dia seleccionado',
            'between_dates' => 'Ingresos vs egresos - rango seleccionado',
            'month' => 'Ingresos vs egresos - mes seleccionado',
            'between_months' => 'Ingresos vs egresos - meses seleccionados',
            'last_week' => 'Ingresos vs egresos - semana seleccionada',
            'all' => 'Ingresos vs egresos - ultimos 6 meses',
        ];

        return $subtitles[$filters['period'] ?? 'all'] ?? 'Ingresos vs egresos - periodo filtrado';
    }

    public function cashFlow($request = [], $months = 6)
    {
        if (is_numeric($request)) {$months=(int)$request;$request=[];}
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request,$months) {
            $filters=$this->resolveFilters((array)$request); $buckets=[];
            if (($filters['period'] ?? null)!=='all' && !empty($filters['date_start']) && !empty($filters['date_end'])) {
                $buckets=\App\Support\Exact\DashboardSeries::buckets($filters['date_start'],$filters['date_end']);
            } else {
                $monthsEs=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
                $base=!empty($filters['date_start']) ? Carbon::parse($filters['date_start'])->startOfMonth() : Carbon::now()->startOfMonth();
                $first=$base->copy()->subMonths($months-1); $sameYear=$first->year===$base->year;
                for ($i=$months-1;$i>=0;$i--) {
                    $ref=$base->copy()->subMonths($i);
                    $label=$monthsEs[$ref->month].($sameYear ? '' : ' '.$ref->format('Y'));
                    $buckets[]=['label'=>$label,'start'=>$ref->format('Y-m-d'),'end'=>$ref->copy()->endOfMonth()->format('Y-m-d')];
                }
            }
            $labels=$income=$egress=[];
            foreach ($buckets as $bucket) {
                $kpis=$this->kpisForRange($bucket['start'],$bucket['end'],$filters['establishment_id']);
                $labels[]=$bucket['label']; $income[]=D::readRequired($kpis,'income'); $egress[]=D::readRequired($kpis,'egress');
            }
            $in=\App\Support\Exact\DashboardSeries::project($income); $out=\App\Support\Exact\DashboardSeries::project($egress);
            return ['labels'=>$labels,'income'=>$in['data'],'egress'=>$out['data'],
                'income_exact'=>$in['exact'],'egress_exact'=>$out['exact'],
                'income_fractions'=>$in['fractions'],'egress_fractions'=>$out['fractions'],
                'cost_is_estimated'=>true,'subtitle'=>$this->cashFlowSubtitle($filters)]
                + DashboardSales::numericProjection(['income_total'=>$in['total'],'egress_total'=>$out['total'],
                    'net_total'=>$in['total']->minus($out['total'])->simplified()]);
        });
    }

    /**
     * @param $establishment_id
     * @param $date_start
     * @param $date_end
     * @return array
     */
    private function collectionGraph(string $label, array $source): array
    {
        $total=D::readRequired($source,'total'); $paid=D::readRequired($source,'total_payment');
        $values=['total'=>$total,'total_payment'=>$paid,'total_to_pay'=>$total->minus($paid)->simplified()];
        $text=array_map(static fn($value)=>D::format($value,2,'.',''),$values);
        $projection=DashboardSales::numericProjection(['paid'=>$paid,'pending'=>$values['total_to_pay']]);
        return ['totals'=>$text+['calculation_snapshot'=>D::snapshot($values)],
            'graph'=>['labels'=>['Total cobrado','Pendiente de cobro'],'datasets'=>[[
                'label'=>$label,'data'=>[$projection['paid'],$projection['pending']],
                'data_exact'=>[$text['total_payment'],$text['total_to_pay']],
                'calculation_snapshot'=>D::snapshot(['paid'=>$paid,'pending'=>$values['total_to_pay']]),
                'backgroundColor'=>['rgb(36, 71, 232, .1)','rgb(254, 0, 108, .1)'],
                'borderColor'=>['rgb(36, 71, 232)','rgb(254, 0, 108)']]]]];
    }

    private function sale_note_totals($establishment_id, $date_start, $date_end)
    {
        return $this->collectionGraph('Notas de venta',$this->get_sale_note_totals($establishment_id,$date_start,$date_end)['totals']);
    }


    /**
     *
     * Obtener totales de cpe
     *
     * Presentación del panel. El límite de emisión usa App\Services\Plans\SalesLimit
     * dentro de la escritura, con sus fracciones y bloqueo propios.
     *
     * @param  string $start_date
     * @param  string $end_date
     * @return string
     */
    public function sale_note_totals_global($start_date = null, $end_date = null)
    {
        $total=DashboardSales::sourceTotal('sale_note',null,$start_date,$end_date);
        return D::format($total,2,'.','');
    }

    /**
     * @param $establishment_id
     * @param $date_start
     * @param $date_end
     * @return array
     */
    private function document_totals($establishment_id, $date_start, $date_end)
    {
        return $this->collectionGraph('Comprobantes',$this->get_document_totals($establishment_id,$date_start,$date_end)['totals']);
    }


    /**
     *
     * Obtener totales de cpe
     *
     * Presentación del panel. El límite de emisión usa App\Services\Plans\SalesLimit
     * dentro de la escritura, con sus fracciones y bloqueo propios.
     *
     * @param  string $start_date
     * @param  string $end_date
     * @return string
     */
    public function document_totals_globals($start_date = null, $end_date = null)
    {
        $total=DashboardSales::sourceTotal('document',null,$start_date,$end_date);
        return D::format($total,2,'.','');
    }

    /**
     * Total de ventas (comprobantes netos de NC + notas de venta), normalizado a VES
     *
     * @param int    $establishment_id
     * @param string $date_start  Y-m-d
     * @param string $date_end    Y-m-d
     *
     * @return array
     */
    public function salesTotalByRange($establishment_id, $date_start, $date_end)
    {
        return \App\Support\Exact\DashboardSales::numericProjection(
            \App\Support\Exact\DashboardSales::range($establishment_id,$date_start,$date_end));
    }

    /**
     * @param $establishment_id
     * @param $date_start
     * @param $date_end
     * @param $period
     * @param $month_start
     * @param $month_end
     * @return array
     */
    private function totals($establishment_id, $date_start, $date_end, $period, $month_start, $month_end)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($establishment_id,$date_start,$date_end,$period,$month_start,$month_end) {
            $rows=DashboardSales::dataset($establishment_id,$date_start,$date_end);
            $values=DashboardSales::datasetTotals($rows);
            if ($period === 'month' || ($period === 'between_months' && $month_start === $month_end)) {
                $series=$this->getDocumentsByDays($rows,$date_start,$date_end);
            } elseif ($period === 'between_months') {
                $series=$this->getDocumentsByMonths($rows,$month_start,$month_end);
            } elseif ($date_start && $date_start === $date_end) {
                $series=$this->getDocumentsByHours($rows);
            } elseif ($date_start && $date_end) {
                $series=$this->getDocumentsByDays($rows,$date_start,$date_end);
            } else {
                $first=$last=null;
                foreach ($rows as $row) {
                    $date=$row['date_of_issue'];
                    if ($first === null || strcmp($date,$first)<0) $first=$date;
                    if ($last === null || strcmp($date,$last)>0) $last=$date;
                }
                $first=$first ?? Carbon::now()->format('Y-m-d'); $last=$last ?? $first;
                $series=$this->getDocumentsByMonths($rows,substr($first,0,7),substr($last,0,7));
            }
            $totals=['total_documents'=>$values['documents_total'],'total_sale_notes'=>$values['sale_notes_total'],'total'=>$values['total']];
            return ['totals'=>array_map(static fn($value)=>D::format($value,2,'.',''),$totals)+['calculation_snapshot'=>D::snapshot($totals)],
                'graph'=>['labels'=>array_keys($series['total_array']),'datasets'=>[[
                    'label'=>'Total','data'=>array_values($series['total_array']),
                    'data_exact'=>array_values($series['total_array_exact']),
                    'calculation_snapshot'=>$series['total_array_snapshot'],
                    'backgroundColor'=>'rgba(177, 184, 194, .1)','borderColor'=>'rgb(177, 184, 194)',
                    'borderWidth'=>2,'fill'=>true,'lineTension'=>0.3,'pointRadius'=>1]]]];
        });
    }

    private function getDocumentsByHours(\App\Support\Exact\ReportDataset $rows)
    {
        $buckets=[];
        for ($hour=0;$hour<24;$hour++) {
            $key=str_pad((string)$hour,2,'0',STR_PAD_LEFT); $buckets[$key]=$key.'h';
        }
        return DashboardSales::chart($rows,$buckets,'hour');
    }

    private function getDocumentsByDays(\App\Support\Exact\ReportDataset $rows, $date_start, $date_end)
    {
        $cursor=Carbon::parse($date_start); $end=Carbon::parse($date_end);
        $sameMonth=$cursor->format('Y-m') === $end->format('Y-m'); $buckets=[];
        while ($cursor <= $end) {
            $key=$cursor->format('Y-m-d'); $buckets[$key]=($sameMonth ? $cursor->format('d') : $key).'d';
            $cursor->addDay();
        }
        return DashboardSales::chart($rows,$buckets,'day');
    }

    private function getDocumentsByMonths(\App\Support\Exact\ReportDataset $rows, $month_start, $month_end)
    {
        $cursor=Carbon::parse($month_start.'-01')->startOfMonth();
        $end=Carbon::parse($month_end.'-01')->startOfMonth();
        $sameYear=$cursor->format('Y') === $end->format('Y'); $buckets=[];
        while ($cursor <= $end) {
            $key=$cursor->format('Y-m'); $buckets[$key]=($sameYear ? $cursor->format('m') : $key).'m';
            $cursor->addMonth();
        }
        return DashboardSales::chart($rows,$buckets,'month');
    }





    private function balance($establishment_id, $date_start, $date_end){
        $values=[];
        foreach (['document'=>$this->get_document_totals($establishment_id,$date_start,$date_end),
            'sale_note'=>$this->get_sale_note_totals($establishment_id,$date_start,$date_end),
            'purchase'=>$this->get_purchase_totals($establishment_id,$date_start,$date_end),
            'expense'=>$this->get_expense_totals($establishment_id,$date_start,$date_end)] as $source=>$result) {
            $values['total_'.$source]=D::readRequired($result['totals'],'total');
            $values['total_payment_'.$source]=D::readRequired($result['totals'],'total_payment');
        }
        $values['all_totals']=$values['total_document']->plus($values['total_sale_note'])->minus($values['total_expense'])->minus($values['total_purchase'])->simplified();
        $values['all_totals_payment']=$values['total_payment_document']->plus($values['total_payment_sale_note'])->minus($values['total_payment_purchase'])->minus($values['total_payment_expense'])->simplified();
        $projection=DashboardSales::numericProjection(['total'=>$values['all_totals'],'paid'=>$values['all_totals_payment']]);
        return ['totals'=>array_map(static fn($value)=>D::format($value,2,'.',','),$values)+['calculation_snapshot'=>D::snapshot($values)],
            'graph'=>['labels'=>['Totales','Total pagos'],'datasets'=>[[
                'label'=>'Grafico','data'=>[$projection['total'],$projection['paid']],
                'data_exact'=>[$projection['total_exact'],$projection['paid_exact']],
                'calculation_snapshot'=>D::snapshot(['total'=>$values['all_totals'],'paid'=>$values['all_totals_payment']]),
                'backgroundColor'=>['rgb(36, 71, 232, .1)','rgb(254, 0, 108, .1)'],
                'borderColor'=>['rgb(36, 71, 232)','rgb(254, 0, 108)']]]]];
    }

    public function getItems(){

        $items = Item::orderBy('description')->take(20)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => ($row->internal_id) ? "{$row->internal_id} - {$row->description}" :$row->description,
            ];
        });

        return $items;

    }

    public function data_mobile($request)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($request) {
            $establishment_id = $request['establishment_id'];
            $period = $request['period'];
            $date_start = $request['date_start'];
            $date_end = $request['date_end'];
            $month_start = $request['month_start'];
            $month_end = $request['month_end'];

            $d_start = null;
            $d_end = null;

            /** @todo: Eliminar periodo, fechas y cambiar por

            $date_start = $request['date_start'];
            $date_end = $request['date_end'];
            \App\CoreFacturalo\Helpers\Functions\FunctionsHelper\FunctionsHelper::setDateInPeriod($request, $date_start, $date_end);
             */
            switch ($period) {
                case 'month':
                    $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_start.'-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'between_months':
                    $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                    $d_end = Carbon::parse($month_end.'-01')->endOfMonth()->format('Y-m-d');
                    break;
                case 'date':
                    $d_start = $date_start;
                    $d_end = $date_start;
                    break;
                case 'between_dates':
                    $d_start = $date_start;
                    $d_end = $date_end;
                    break;
            }

            return [
                'general' => $this->totals($establishment_id, $d_start, $d_end, $period, $month_start, $month_end),
            ];
        });
    }


    /**
     *
     * Método para acceder a los totales (método privado)
     * El gráfico no incluye pedidos
     *
     * Usado en:
     * ReportController - App
     *
     * @param  int $establishment_id
     * @param  string $d_start
     * @param  string $d_end
     * @param  string $period
     * @param  string $month_start
     * @param  string $month_end
     * @return array
     */
    public function getGeneralTotals($establishment_id, $d_start, $d_end, $period, $month_start, $month_end)
    {
        return ReadSnapshot::run((new Document)->getConnection(),function () use ($establishment_id,$d_start,$d_end,$period,$month_start,$month_end) {
            $data=$this->totals($establishment_id,$d_start,$d_end,$period,$month_start,$month_end);
            $orders=DashboardTotals::orderNotes($establishment_id,$d_start,$d_end);
            $total=$orders->plus(D::readRequired($data['totals'],'total'))->simplified();
            $data['totals']['total_order_notes']=D::format($orders,2,'.','');
            $data['totals']['total']=D::format($total,2,'.','');
            $data['totals']['calculation_snapshot']['values']['total_order_notes']=D::fraction($orders);
            $data['totals']['calculation_snapshot']['values']['total']=D::fraction($total);
            return $data;
        });
    }


}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
