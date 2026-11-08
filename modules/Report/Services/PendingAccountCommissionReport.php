<?php

namespace Modules\Report\Services;

use App\CoreFacturalo\Helpers\Functions\FunctionsHelper;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use App\Support\Exact\Decimal as D;
use Carbon\Carbon;
use Modules\Sale\Models\PendingAccountCommission;

/** Collections in the period, grouped in applied documentary currency. */
final class PendingAccountCommissionReport
{
    public static function forUser($user, $request, $finances): array
    {
        $params=$request->all(); $start=$params['date_start'] ?? null; $end=$params['date_end'] ?? null;
        FunctionsHelper::setDateInPeriod($params,$start,$end);
        $userField=$params['user_type'] ?? 'user_id';
        if (!in_array($userField,['user_id','seller_id'],true)) throw \Illuminate\Validation\ValidationException::withMessages(['user_type'=>'Seleccione un tipo de usuario válido.']);
        $active=(bool)data_get($finances,'restriction_expired_debt',false);
        $maxDays=max(0,(int)data_get($finances,'max_expired_days',0));
        $config=PendingAccountCommission::where('seller_id',$user->id)->first();
        $type=$config->commission_type ?? null; $rate=$config ? D::read($config,'amount') : D::of(0);
        $fixed=in_array($type,['monto','amount'],true) && !$rate->isZero();
        $percent=in_array($type,['porcentaje','percent','percentage'],true);
        $unsupported=$config && !$rate->isZero() && !$fixed && !$percent;
        $buckets=[];
        foreach ([Document::class,SaleNote::class] as $model) {
            $document=$model===Document::class;
            $query=$model::query()->where('payment_condition_id','02')->whereStateTypeAccepted()->where($userField,$user->id);
            $viewer=auth()->user();
            if ($document && $viewer->type !== 'admin') {
                $query->where(fn ($scope) => $scope->where('user_id',$viewer->id)->orWhere('seller_id',$viewer->id));
            } elseif (!$document && $viewer->type === 'seller') {
                $query->where('user_id',$viewer->id);
            }
            if ($document) $query->whereIn('document_type_id',['01','08']);
            else $query->where(function ($q) {
                $q->whereNull('document_id')->orWhereNotIn('document_id',Document::withoutEagerLoads()->select('id')->whereNotIn('state_type_id',['09','11']));
            });
            if (!empty($params['establishment_id'])) $query->where('establishment_id',$params['establishment_id']);
            $query->whereHas('payments',function ($q) use ($start,$end,$document) {
                $q->whereBetween('date_of_payment',[$start,$end]); if ($document) $q->whereNull('reversed_at');
            })->with(['payments'=>function ($q) use ($start,$end,$document) {
                $q->whereBetween('date_of_payment',[$start,$end]); if ($document) $q->whereNull('reversed_at');
            }]);
            foreach ($query->get() as $record) {
                $due=$document ? optional($record->invoice)->date_of_due : $record->due_date;
                foreach ($record->payments as $payment) {
                    $currency=$record->currency_type_id ?: 'Sin moneda';
                    if (!isset($buckets[$currency])) $buckets[$currency]=['within'=>D::of(0),'outside'=>D::of(0),'commission'=>D::of(0),'commission_reason'=>null];
                    $amount=D::read($payment,'payment');
                    $days=$due ? Carbon::parse($due)->startOfDay()->diffInDays(Carbon::parse($payment->date_of_payment)->startOfDay(),false) : null;
                    $eligible=$active && $days !== null && $days>0 && $days<=$maxDays;
                    if (!$eligible) { $buckets[$currency]['outside']=$buckets[$currency]['outside']->plus($amount); continue; }
                    $buckets[$currency]['within']=$buckets[$currency]['within']->plus($amount);
                    if ($fixed) $buckets[$currency]['commission_reason']='Moneda del monto fijo no definida';
                    elseif ($unsupported) $buckets[$currency]['commission_reason']='Tipo de comisión no reconocido';
                    elseif ($percent) $buckets[$currency]['commission']=$buckets[$currency]['commission']->plus($amount->multipliedBy($rate)->dividedBy(100));
                }
            }
        }
        ksort($buckets); $rows=[];
        foreach ($buckets as $currency=>$bucket) {
            $values=['total_within_range'=>$bucket['within'],'total_out_of_range'=>$bucket['outside'],
                'total_completed'=>$bucket['within']->plus($bucket['outside']),'commission'=>$bucket['commission']];
            $snapshot=$values;
            if ($bucket['commission_reason']) unset($snapshot['commission']);
            $rows[]=array_merge(['report_key'=>'user:'.$user->id.':'.$currency,'currency_type_id'=>$currency,
                'commission_reason'=>$bucket['commission_reason'],'calculation_snapshot'=>D::snapshot($snapshot)],
                array_map(fn($value)=>D::format($value,2,'.',''),$values),
                ['commission'=>$bucket['commission_reason'] ? null : D::format($values['commission'],2,'.','')]);
        }
        $result=['id'=>$user->id,'user_name'=>$user->name,'currency_rows'=>$rows];
        // The previously compiled table can still display these fields without mixing currencies.
        foreach (['total_out_of_range','total_within_range','total_completed','commission'] as $field) {
            $result[$field] = $rows ? implode('; ',array_map(function ($row) use ($field) {
                $value=$field === 'commission' && $row['commission_reason'] ? $row['commission_reason'] : $row[$field];
                return $row['currency_type_id'].' '.$value;
            },$rows)) : 'Sin cobros';
        }
        return $result;
    }
}
