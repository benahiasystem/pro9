<?php

namespace App\Services\Payments;

use App\Support\Exact\Decimal as D;
use Modules\Finance\Models\GlobalPayment;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class FinancialReport
{
    public static function groups(string $start, string $end, ?int $userId = null): array
    {
        return \App\Support\Exact\ReadSnapshot::run(\Illuminate\Support\Facades\DB::connection('tenant'),fn()=>self::readGroups($start,$end,$userId));
    }

    private static function readGroups(string $start, string $end, ?int $userId): array
    {
        $groups=[];$seen=[];
        $query=GlobalPayment::query()->whereNotNull('report_context')->whereBetween('report_context->date',[$start,$end])->with('payment');
        if ($userId !== null) $query->where('user_id',$userId);
        $query->chunkById(250,function($payments) use (&$groups,&$seen,$start,$end) {
            Movement::preload($payments);
            foreach ($payments as $global) {
                $row=Movement::row($global);
                if (!$row || $row['date'] < $start || substr($row['date'],0,10) > $end) continue;
                if (isset($seen[$row['source']])) continue;
                $seen[$row['source']]=true;
                $key=implode(':',[$row['method']['catalog'],$row['method']['id'],$row['method']['description'],$row['currency_type_id'],$row['destination_type'],$row['destination_id']]);
                $groups[$key] ??= ['version'=>2,'method'=>$row['method'],'currency_type_id'=>$row['currency_type_id'],
                    'destination_id'=>$row['destination_id'],'destination_label'=>$row['destination_label'],'opening'=>D::of(0),'income'=>D::of(0),'egress'=>D::of(0)];
                $field=$row['opening'] ? 'opening' : $row['direction'];
                $groups[$key][$field]=$groups[$key][$field]->plus(D::readRequired($row,'amount'));
            }
        });
        return array_values(array_map(function($row) {
            $row['balance']=$row['opening']->plus($row['income'])->minus($row['egress']);
            $fields=['opening','income','egress','balance'];
            $row['calculation_snapshot']=D::snapshot(array_intersect_key($row,array_flip($fields)));
            foreach ($fields as $field) $row[$field]=D::represent($row[$field]);
            return $row;
        },$groups));
    }

    public static function totals(iterable $globals): array
    {
        $result=[];
        foreach ($globals as $global) {
            $row=Movement::row($global);
            if (!$row) continue;
            $version=2; $currency=$row['currency_type_id']; $value=D::readRequired($row,'amount');
            $key=$version.':'.$currency;
            $result[$key] ??= ['version'=>$version,'currency_type_id'=>$currency,'total'=>D::of(0)];
            $result[$key]['total']=$result[$key]['total']->plus($value);
        }
        return array_values(array_map(fn($row)=>array_replace($row,['total'=>D::represent($row['total'])]),$result));
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
