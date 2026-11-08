<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

/** Signed documentary amounts for the customer report, grouped by currency. */
final class CustomerReportAmounts
{
    public const FIELDS = ['total'];

    public static function row($document): array
    {
        $void = in_array($document->state_type_id, ['09','11'], true);
        $total = $void ? D::of(0) : D::readRequired($document, 'total');
        if (!$void && $document->document_type_id === '07') $total = $total->negated();
        return ['report_key'=>'document:'.$document->id, 'model'=>$document,
            'currency_type_id'=>$document->currency_type_id, 'total'=>D::represent($total),
            'calculation_snapshot'=>D::snapshot(['total'=>$total])];
    }

    public static function presentation(iterable $records): array
    {
        $rows = collect($records)->map(fn ($document) => self::row($document))->values();
        $totals = []; $display = [];
        foreach ($rows->groupBy('currency_type_id') as $currency=>$group) {
            $dataset = new ReportDataset($group->map(function ($row) { unset($row['model']); return $row; }));
            $totals[$currency] = $dataset->sum('total');
            foreach (ReportProjection::money($dataset, self::FIELDS) as $row) $display[$row['report_key']] = $row['display'];
        }
        return ['rows'=>$rows->map(function ($row) use ($display) { $row['display']=$display[$row['report_key']]; return $row; }), 'totals'=>$totals];
    }

    public static function displayForPage(iterable $records, iterable $ids): array
    {
        $wanted=[]; foreach ($ids as $id) $wanted['document:'.$id]=true;
        if (!$wanted) return [];
        $currencies=[];
        $dataset=new ReportDataset((function () use ($records, &$currencies) {
            foreach ($records as $record) {
                $row=self::row($record); $currencies[$row['currency_type_id']]=true;
                unset($row['model']); yield $row;
            }
        })());
        $display=[];
        foreach (array_keys($currencies) as $currency) {
            $group=$dataset->filter(fn ($row) => $row['currency_type_id'] === $currency);
            foreach (ReportProjection::money($group, self::FIELDS) as $row)
                if (isset($wanted[$row['report_key']])) $display[$row['report_key']]=$row['display'];
        }
        return $display;
    }
}
