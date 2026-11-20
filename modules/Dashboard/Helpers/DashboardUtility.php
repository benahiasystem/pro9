<?php
// ######## INICIO TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
// ######## FIN TASAS OCHO DECIMALES: CONVERSIONES EXACTAS ########
namespace Modules\Dashboard\Helpers;

use App\Models\Tenant\{DocumentItem, SaleNoteItem};
use App\Support\Exact\{ConsolidatedLines, DashboardSales, Decimal as D, JsonDecimals, ReadSnapshot};
use Illuminate\Database\Eloquent\Collection;
use Modules\Expense\Models\Expense;
use Modules\Report\Services\EstimatedPurchaseCost;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Estimated utility: current positive catalogue cost, then eligible purchase history. */
class DashboardUtility
{
    public function data($request)
    {
        $filters=DashboardFilterHelper::resolve($request);
        return ['utilities'=>$this->calculateUtilityTotals($filters['establishment_id'],$filters['date_start'],
            $filters['date_end'],filter_var($request['enabled_expense'] ?? true,FILTER_VALIDATE_BOOLEAN),$request['item_id'] ?? null)];
    }

    public function calculateUtilityTotals($establishment_id, $d_start, $d_end, $enabled_expense=true, $item_id=null)
    {
        return ReadSnapshot::run((new DocumentItem)->getConnection(),function () use ($establishment_id,$d_start,$d_end,$enabled_expense,$item_id) {
            $income=$cost=$expenses=D::of(0);
            foreach (['document','sale_note'] as $source) {
                $records=$this->lines($source,$establishment_id,$d_start,$d_end,$item_id);
                [$sale,$purchase]=$this->lineTotals($records,$source);
                $income=$income->plus($sale)->simplified(); $cost=$cost->plus($purchase)->simplified();
            }
            if ($enabled_expense) {
                $query=Expense::withoutEagerLoads()->where('state_type_id','!=','11');
                $this->filter($query,$establishment_id,$d_start,$d_end);
                foreach ($query->lazyById(500) as $expense) $expenses=$expenses->plus(DashboardSales::inVes($expense))->simplified();
            }
            $egress=$cost->plus($expenses)->simplified();
            $values=['total_income'=>$income,'total_egress'=>$egress,'utility'=>$income->minus($egress)->simplified()];
            $projection=DashboardSales::numericProjection($values);
            return ['totals'=>array_map(static fn($value)=>D::format($value,2,'.',''),$values)+['calculation_snapshot'=>D::snapshot($values)],
                'cost_is_estimated'=>true,'cost_policy'=>'current_positive_catalog_then_eligible_purchase',
                'graph'=>['labels'=>['Ingreso','Egreso'],'datasets'=>[[
                    'label'=>'Utilidades','data'=>[$projection['total_income'],$projection['total_egress']],
                    'data_exact'=>[$projection['total_income_exact'],$projection['total_egress_exact']],
                    'calculation_snapshot'=>D::snapshot(['income'=>$income,'egress'=>$egress]),
                    'backgroundColor'=>['rgb(36, 71, 232, .1)','rgb(254, 0, 108, .1)'],
                    'borderColor'=>['rgb(36, 71, 232)','rgb(254, 0, 108)']]]]];
        });
    }

    private function lines(string $source, $establishment, $start, $end, $item)
    {
        $query=$source === 'document' ? DocumentItem::withoutEagerLoads() : SaleNoteItem::withoutEagerLoads();
        $query->with([$source=>function ($parent) use ($source) {
            $parent->withoutEagerLoads();
            if ($source === 'document') $parent->with('note');
        },'relation_item'=>fn($catalog)=>$catalog->withoutEagerLoads()]);
        $query->whereHas($source,function ($parent) use ($source,$establishment,$start,$end) {
            $parent->whereIn('state_type_id',DashboardSales::ACCEPTED_STATES);
            if ($source === 'document') $parent->whereIn('document_type_id',['01','07','08']);
            else $parent->where('changed',false);
            $this->filter($parent,$establishment,$start,$end);
        });
        if ($item) $query->where('item_id',$item);
        return $query->lazyById(500);
    }

    private function filter($query, $establishment, $start, $end): void
    {
        if ($establishment) $query->where('establishment_id',$establishment);
        if ($start && $end) $query->whereBetween('date_of_issue',[$start,$end]);
    }

    private function lineTotals($records, string $source): array
    {
        $income=$cost=D::of(0);
        $reader=new ConsolidatedLines((new DocumentItem)->getConnection());
        foreach ($records->chunk(500) as $chunk) {
            $chunk=new Collection($chunk->values()->all());
            $fallback=new Collection(); $costs=[];
            foreach ($chunk as $line) {
                $parent=$line->$source; $catalog=$line->relation_item;
                if (!$catalog) throw new \UnexpectedValueException('No se encontró el producto para estimar su costo.');
                $line->source_type=$source; $line->source_id=$parent->id; $line->currency_type_id=$parent->currency_type_id;
                $line->exchange_rate_sale=DashboardSales::historicalRate($parent);
                $line->document_type_id=$source === 'document' ? $parent->document_type_id : '80';
                $monetaryAdjustment=$line->document_type_id === '08' || ($line->document_type_id === '07'
                    && !in_array($parent->note->note_credit_type_id ?? null,['01','07'],true));
                if ($monetaryAdjustment || $catalog->unit_type_id === 'SERV') {
                    $costs[spl_object_id($line)]=D::of(0); continue;
                }
                $unit=D::readRequired($catalog,'purchase_unit_price');
                if ($unit->isPositive()) {
                    $unit=D::convert($unit,$catalog->currency_type_id,'VES',$line->exchange_rate_sale);
                    $costs[spl_object_id($line)]=$unit->multipliedBy(D::readRequired($line,'quantity'))
                        ->multipliedBy($this->getQuantityUnitPresentation($line))->simplified();
                } else $fallback->push($line);
            }
            if ($fallback->isNotEmpty()) {
                foreach (EstimatedPurchaseCost::forLines($fallback,$source,true) as $key=>$estimate) $costs[$key]=D::max(0,$estimate['amount']);
            }
            foreach ($reader->rows($chunk) as $position=>$row) {
                $line=$chunk[$position]; $amount=$costs[spl_object_id($line)];
                if ($line->document_type_id === '07') $amount=$amount->negated();
                $income=$income->plus(D::readRequired($row,'total_sale'))->simplified();
                $cost=$cost->plus($amount)->simplified();
            }
        }
        return [$income,$cost];
    }

    public function getQuantityUnitPresentation($model_item): \Brick\Math\BigRational
    {
        $item=$model_item->getRawOriginal('item');
        if (is_string($item)) $item=JsonDecimals::decode($item);
        $presentation=(array)(((array)$item)['presentation'] ?? []);
        $quantity=D::inputRational($presentation['quantity_unit'] ?? '1','presentation.quantity_unit');
        if (!$quantity->isPositive()) throw new \UnexpectedValueException('La presentación requiere una cantidad positiva.');
        return $quantity;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
