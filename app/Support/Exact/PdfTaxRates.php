<?php

namespace App\Support\Exact;

final class PdfTaxRates
{
    /** Return a rate only when all positive item rates agree exactly. */
    public static function single($items): ?string
    {
        $rate=null;
        foreach ($items as $item) {
            $current=Decimal::read($item,'percentage_igv');
            if ($current->isZero()) continue;
            if ($rate!==null && !$rate->isEqualTo($current)) return null;
            $rate=$current;
        }
        return $rate===null ? null : Decimal::represent($rate);
    }
}
