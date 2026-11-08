<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class DispatchReportAmounts
{
    /** Materialize the commercial rows while the report's read snapshot is open. */
    public static function rows(iterable $lines): array
    {
        $configuration = \App\Models\Tenant\Configuration::first();
        $rows = [];
        foreach ($lines as $line) $rows[$line->id] = $line->getCollectionData($configuration);
        return $rows;
    }

    /** Only the unit frozen in the dispatch line can identify its physical quantity. */
    public static function unit($line): ?string
    {
        $item = (array)$line->item;
        $unit = $item['unit_type_id'] ?? null;
        return is_string($unit) && $unit !== '' ? $unit : null;
    }

    public static function totalsByUnit(iterable $lines): array
    {
        $totals = [];
        foreach ($lines as $line) {
            $unit = self::unit($line);
            if ($unit === null) continue;
            $totals[$unit] = ($totals[$unit] ?? D::of(0))->plus(D::readRequired($line, 'quantity'))->simplified();
        }
        ksort($totals);
        return $totals;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
