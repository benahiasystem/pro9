<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

/** Reportable note amounts, keeping exact fractions separate from displayed cents. */
final class SaleNoteReportAmounts
{
    public const FIELDS = ['total_discount','total_exportation','total_unaffected','total_exonerated','total_taxed','total_igv','total'];

    public static function row($note): array
    {
        $void = in_array($note->state_type_id, ['09', '11'], true);
        $values = [];
        foreach (self::FIELDS as $field) $values[$field] = $void ? D::of(0) : D::readRequired($note, $field);
        return array_merge(['report_key'=>'sale_note:'.$note->id, 'model'=>$note,
            'currency_type_id'=>$note->currency_type_id, 'calculation_snapshot'=>D::snapshot($values)], D::values($values));
    }

    public static function presentation(iterable $records): array
    {
        $rows = collect($records)->map(fn ($note) => self::row($note))->values();
        $totals = []; $display = [];
        foreach ($rows->groupBy('currency_type_id') as $currency=>$group) {
            $dataset = new ReportDataset($group->map(function ($row) { unset($row['model']); return $row; }));
            foreach (self::FIELDS as $field) $totals[$currency][$field] = $dataset->sum($field);
            foreach (ReportProjection::money($dataset, self::FIELDS) as $row) $display[$row['report_key']] = $row['display'];
        }
        return ['rows'=>$rows->map(function ($row) use ($display) { $row['display']=$display[$row['report_key']]; return $row; }), 'totals'=>$totals];
    }

    public static function displayForPage(iterable $records, iterable $ids): array
    {
        $wanted = [];
        foreach ($ids as $id) $wanted['sale_note:'.$id] = true;
        if (!$wanted) return [];
        $currencies = [];
        $dataset = new ReportDataset((function () use ($records, &$currencies) {
            foreach ($records as $record) {
                $row = self::row($record); $currencies[$row['currency_type_id']] = true;
                unset($row['model']); yield $row;
            }
        })());
        $display = [];
        foreach (array_keys($currencies) as $currency) {
            $group = $dataset->filter(fn ($row) => $row['currency_type_id'] === $currency);
            foreach (ReportProjection::money($group, self::FIELDS) as $row)
                if (isset($wanted[$row['report_key']])) $display[$row['report_key']] = $row['display'];
        }
        return $display;
    }
}
