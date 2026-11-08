<?php

namespace App\Support\Exact;

use Illuminate\Support\Collection;

/** Resolve grouping hints only when each hint identifies one saved invoice line. */
final class PdfInvoiceGroups
{
    public static function split($document): array
    {
        $items=collect($document->items)->values();
        $inputs=collect(data_get($document,'data_json.items',[]))->values();
        $fallback=['grouped'=>collect(),'individual'=>$items];
        if ($items->count()!==$inputs->count()) return $fallback;
        $saved=[];
        foreach ($items as $item) {
            $code=trim((string)data_get($item,'item.internal_id',''));
            if ($code==='' || isset($saved[$code])) return $fallback;
            $saved[$code]=$item;
        }
        $grouped=new Collection(); $individual=new Collection(); $seen=[];
        foreach ($inputs as $input) {
            $code=trim((string)(data_get($input,'codigo_interno') ?? data_get($input,'internal_id') ?? ''));
            if ($code==='' || !isset($saved[$code]) || isset($seen[$code])) return $fallback;
            $seen[$code]=true;
            if (filter_var(data_get($input,'esFusionado',false),FILTER_VALIDATE_BOOLEAN)) $grouped->push($saved[$code]);
            else $individual->push($saved[$code]);
        }
        return ['grouped'=>$grouped,'individual'=>$individual];
    }
}
