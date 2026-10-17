<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** A disposable presentation projection. Never use these values in business operations. */
final class PdfAmounts
{
    private static ?\WeakMap $cache=null;

    public static function lineDiscount($line, bool $excludeGlobal = true): \Brick\Math\BigRational
    {
        $total=Decimal::of(0);
        foreach ($line->discounts ?? [] as $entry) {
            $entry=(array)$entry;
            if ($excludeGlobal && !empty($entry['from_global_distribution'])) continue;
            $value=Decimal::of($entry['amount_exact'] ?? $entry['amount_without_rounded'] ?? $entry['amount'] ?? 0);
            if (($entry['discount_type_id'] ?? '')==='00') $value=$value->multipliedBy(Decimal::of(1)->plus(Decimal::read($line,'percentage_igv')->dividedBy(100)));
            $total=$total->plus($value);
        }
        return $total;
    }

    public static function read($record, string $field): \Brick\Math\BigRational
    {
        if ($field==='total_discount_with_igv' && is_object($record) && method_exists($record,'exactDiscountWithIgv')) return $record->exactDiscountWithIgv();
        $original=Decimal::read($record,$field);
        if (!in_array($field,['total','subtotal','total_value','total_igv','total_other_taxes','total_taxes',
            'total_taxed','total_exonerated','total_exportation','total_base_igv'],true)) return $original;
        $signature=null;
        if (is_object($record) && method_exists($record,'getAttributes')) {
            $attributes=array_intersect_key($record->getAttributes(),array_flip(['calculation_snapshot','total','subtotal',
                'total_value','total_igv','total_other_taxes','total_taxes','total_prepayment','total_taxed',
                'total_exonerated','total_exportation','total_base_igv']));
            $signature=json_encode($attributes,JSON_THROW_ON_ERROR);
            self::$cache ??= new \WeakMap();
            $entry=self::$cache[$record] ?? null;
            if ($entry && $entry['signature']===$signature) return $entry['values'][$field] ?? $original;
        }
        $value=Decimal::read($record,'total_value');
        $iva=Decimal::read($record,'total_igv');
        $other=Decimal::read($record,'total_other_taxes');
        $total=Decimal::read($record,'total');
        $gross=$value->plus($iva)->plus($other);
        $advance=$gross->minus($total);
        if ($value->isNegative() || $iva->isNegative() || $other->isNegative() || $gross->isZero()
            || (!$advance->isZero() && !$advance->isEqualTo(Decimal::read($record,'total_prepayment')))) return $original;
        $payable=Decimal::of(Decimal::represent($total,2));
        $grossDisplay=$payable->plus(Decimal::of(Decimal::represent($advance,2)));
        [$net,$tax,$additional]=array_map([Decimal::class,'of'],Decimal::allocate($grossDisplay,[$value,$iva,$other],2));
        $values=['total'=>$payable,'total_value'=>$net,'total_igv'=>$tax,'total_other_taxes'=>$additional,'total_taxes'=>$tax->plus($additional)];
        // Reconcile the displayed bases with the net value and its non-base adjustments.
        $bases=[];
        foreach (['total_taxed','total_exonerated','total_exportation'] as $base) $bases[$base]=Decimal::read($record,$base);
        $baseSum=Decimal::sum($bases);
        if ($baseSum->isGreaterThan(0)) {
            $target=$net->minus(Decimal::of(Decimal::represent($value->minus($baseSum),2)));
            if (!$target->isNegative()) {
                $parts=Decimal::allocate($target,array_values($bases),2);
                foreach (array_keys($bases) as $i=>$base) $values[$base]=Decimal::of($parts[$i]);
            }
        }
        $lineBase=Decimal::read($record,'total_base_igv');
        if ($lineBase->isGreaterThan(0)) {
            $base=$net->minus(Decimal::of(Decimal::represent($value->minus($lineBase),2)));
            if (!$base->isNegative()) $values['total_base_igv']=$base;
        }
        $taxRows=is_array($record) ? ($record['taxes'] ?? []) : ($record->taxes ?? []);
        $igtf=Decimal::of(0);
        foreach ($taxRows as $row) {
            $kind=is_array($row) ? ($row['tax_kind'] ?? '') : ($row->tax_kind ?? '');
            if ($kind==='IGTF') $igtf=$igtf->plus(Decimal::read($row,'amount'));
        }
        if ($total->minus($igtf)->isEqualTo(Decimal::read($record,'subtotal'))) {
            $igtfDisplay=$igtf->isZero() ? Decimal::of(0) : Decimal::of(Decimal::allocate($additional,[$igtf,Decimal::max(0,$other->minus($igtf))],2)[0]);
            $values['subtotal']=$payable->minus($igtfDisplay);
        }
        if ($signature !== null) self::$cache[$record]=['signature'=>$signature,'values'=>$values];
        return $values[$field] ?? $original;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
