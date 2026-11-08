<?php

namespace Modules\Report\Services;

use App\CoreFacturalo\Helpers\Functions\FunctionsHelper;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;

/** Commission on item quantity and unit price, before document-wide adjustments. */
final class SalesCommissionReport
{
    public static function forUser($user, $request): array
    {
        $params=$request->all(); $start=$params['date_start'] ?? null; $end=$params['date_end'] ?? null;
        FunctionsHelper::setDateInPeriod($params,$start,$end);
        $field=$params['user_type'] ?? 'user_id';
        if (!in_array($field,['user_id','seller_id'],true)) throw ValidationException::withMessages(['user_type'=>'Seleccione un tipo de usuario válido.']);
        $buckets=[]; $count=0;
        foreach ([Document::class,SaleNote::class] as $model) {
            $document=$model===Document::class;
            $query=$model::query()->whereStateTypeAccepted()->whereBetween('date_of_issue',[$start,$end])->where($field,$user->id);
            if (!empty($params['establishment_id'])) $query->where('establishment_id',$params['establishment_id']);
            $viewer=auth()->user();
            if ($document && $viewer->type !== 'admin') $query->where(fn($q)=>$q->where('user_id',$viewer->id)->orWhere('seller_id',$viewer->id));
            elseif (!$document && $viewer->type === 'seller') $query->where('user_id',$viewer->id);
            if ($document) $query->whereIn('document_type_id',['01','07','08']);
            else {
                $invoices=Document::withoutEagerLoads()->select('id')->whereStateTypeAccepted()
                    ->where('document_type_id','01')->whereBetween('date_of_issue',[$start,$end])->where($field,$user->id);
                if (!empty($params['establishment_id'])) $invoices->where('establishment_id',$params['establishment_id']);
                if ($viewer->type !== 'admin') $invoices->where(fn($q)=>$q->where('user_id',$viewer->id)->orWhere('seller_id',$viewer->id));
                $query->where(fn($q)=>$q->whereNull('document_id')->orWhereNotIn('document_id',$invoices));
            }
            foreach ($query->with('items.relation_item')->get() as $record) {
                $currency=$record->currency_type_id ?: 'Sin moneda';
                if (!isset($buckets[$currency])) $buckets[$currency]=['sales'=>D::of(0),'commission'=>D::of(0),'reason'=>null,'count'=>0];
                $bucket=&$buckets[$currency]; $sign=$document && $record->document_type_id==='07' ? -1 : 1;
                $bucket['sales']=$bucket['sales']->plus(D::read($record,'total')->multipliedBy($sign));
                $bucket['count']++; $count++;
                foreach ($record->items as $item) {
                    $product=$item->relation_item;
                    if (!$product) { $bucket['reason']='Producto no disponible para calcular comisión'; continue; }
                    $rate=D::read($product,'commission_amount');
                    if ($rate->isZero()) continue;
                    if (!$product->commission_type || $product->commission_type==='amount') { $bucket['reason']='Moneda del monto fijo no definida'; continue; }
                    if ($product->commission_type!=='percentage') { $bucket['reason']='Tipo de comisión no reconocido'; continue; }
                    $amount=D::read($item,'quantity')->multipliedBy(D::read($item,'unit_price'))->multipliedBy($rate)->dividedBy(100)->multipliedBy($sign);
                    $bucket['commission']=$bucket['commission']->plus($amount);
                }
                unset($bucket);
            }
        }
        ksort($buckets); $rows=[];
        foreach ($buckets as $currency=>$bucket) {
            $values=['acum_sales'=>$bucket['sales'],'total_commision'=>$bucket['commission']];
            $snapshot=$values; if ($bucket['reason']) unset($snapshot['total_commision']);
            $rows[]=['report_key'=>'user:'.$user->id.':'.$currency,'currency_type_id'=>$currency,'total_transactions'=>$bucket['count'],
                'acum_sales'=>D::format($bucket['sales'],2,'.',''),
                'total_commision'=>$bucket['reason'] ? null : D::format($bucket['commission'],2,'.',''),
                'commission_reason'=>$bucket['reason'],'calculation_snapshot'=>D::snapshot($snapshot)];
        }
        $result=['id'=>$user->id,'user_name'=>$user->name,'total_transactions'=>$count,'currency_rows'=>$rows];
        foreach (['acum_sales','total_commision'] as $key) $result[$key]=$rows ? implode('; ',array_map(fn($row)=>$row['currency_type_id'].' '.($row[$key] ?? $row['commission_reason']),$rows)) : 'Sin ventas';
        return $result;
    }
}
