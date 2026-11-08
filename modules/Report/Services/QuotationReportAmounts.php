<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;

final class QuotationReportAmounts
{
    public const FIELDS = ['total_exportation','total_unaffected','total_exonerated','total_taxed','total_igv','total'];

    public static function row($quotation): array
    {
        $void = in_array($quotation->state_type_id, ['09','11'], true);
        $values = [];
        foreach (self::FIELDS as $field) $values[$field] = $void ? D::of(0) : D::read($quotation, $field);
        return array_merge(['report_key'=>'quotation:'.$quotation->id, 'calculation_snapshot'=>D::snapshot($values)], D::values($values));
    }
}
