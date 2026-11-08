<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

/** Purchase totals retain their documentary fractions and are grouped by displayed currency. */
final class PurchaseReportAmounts
{
    public const FIELDS = ['total_exportation','total_free','total_unaffected','total_exonerated','total_taxed','total_igv','total_perception','total'];

    public static function converted($flag): bool
    {
        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    public static function row($purchase, bool $convert): array
    {
        $currency = $purchase->currency_type_id;
        $target = $convert && $currency === 'USD' ? 'VES' : $currency;
        $void = in_array($purchase->state_type_id, ['09','11'], true);
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $void ? D::of(0) : D::read($purchase, $field);
            if (!$void && $field === 'total') $value = $value->plus(D::read($purchase, 'total_perception'));
            $values[$field] = $void || $target === $currency ? $value : D::convert($value, $currency, $target, $purchase->exchange_rate_sale);
        }
        $prefix = $purchase instanceof \Modules\Purchase\Models\FixedAssetPurchase ? 'fixed_asset_purchase' : 'purchase';
        return array_merge(['report_key'=>$prefix.':'.$purchase->id,'model'=>$purchase,
            'currency_type_id'=>$target,'original_currency_type_id'=>$currency,
            'converted'=>$target !== $currency,'calculation_snapshot'=>D::snapshot($values)], D::values($values));
    }

    public static function presentation($records, bool $convert): array
    {
        $rows = collect($records)->map(fn ($record) => self::row($record, $convert))->values();
        $totals = []; $display = [];
        foreach ($rows->groupBy('currency_type_id') as $currency=>$group) {
            $plain = $group->map(function ($row) { unset($row['model']); return $row; });
            $dataset = new ReportDataset($plain);
            foreach (self::FIELDS as $field) $totals[$currency][$field] = $dataset->sum($field);
            foreach (ReportProjection::money($dataset, self::FIELDS) as $row) $display[$row['report_key']] = $row['display'];
        }
        return ['rows'=>$rows->map(function ($row) use ($display) { $row['display']=$display[$row['report_key']]; return $row; }), 'totals'=>$totals];
    }

    /** Project the complete filtered report, retaining only the current page's display values. */
    public static function displayForPage(iterable $records, iterable $ids, bool $convert, string $prefix = 'purchase'): array
    {
        $wanted = [];
        foreach ($ids as $id) $wanted[$prefix.':'.$id] = true;
        if (!$wanted) return [];
        $currencies = [];
        $dataset = new ReportDataset((function () use ($records, $convert, &$currencies) {
            foreach ($records as $record) {
                $row = self::row($record, $convert);
                $currencies[$row['currency_type_id']] = true;
                unset($row['model']);
                yield $row;
            }
        })());
        $display = [];
        foreach (array_keys($currencies) as $currency) {
            $group = $dataset->filter(fn ($row) => $row['currency_type_id'] === $currency);
            foreach (ReportProjection::money($group, self::FIELDS) as $row) {
                if (isset($wanted[$row['report_key']])) $display[$row['report_key']] = $row['display'];
            }
        }
        return $display;
    }
}
