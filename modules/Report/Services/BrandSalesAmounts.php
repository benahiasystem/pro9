<?php

namespace Modules\Report\Services;

use App\Support\Exact\ConsolidatedLines;
use App\Support\Exact\Decimal as D;
use Illuminate\Database\Eloquent\Collection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Current brand and estimated cost; sales are allocated and converted at document rates. */
final class BrandSalesAmounts
{
    public static function presentation(iterable $records): array
    {
        $records = collect($records)->values();
        $totals = ['total_sale_value'=>D::of(0), 'total_profit'=>D::of(0)];
        $units = []; $unknown = false;
        foreach ($records as $row) {
            foreach ($totals as $field=>$value) $totals[$field] = $value->plus(D::readRequired($row,$field))->simplified();
            if (!$row['unit_type_id']) { $unknown = true; continue; }
            $unit = $row['unit_type_id'];
            $units[$unit] = ($units[$unit] ?? D::of(0))->plus(D::readRequired($row,'quantity_sold'))->simplified();
        }
        ksort($units);
        $projected = \App\Support\Exact\ReportProjection::money(new \App\Support\Exact\ReportDataset($records), ['total_sale_value','total_profit']);
        return ['rows'=>collect(iterator_to_array($projected,false)), 'totals'=>$totals, 'units'=>$units, 'unknown'=>$unknown];
    }

    public static function groups($documents, $notes): \Illuminate\Support\Collection
    {
        $groups = [];
        foreach (['document'=>$documents, 'sale_note'=>$notes] as $source=>$lines) {
            $lines = new Collection($lines->values()->all());
            if ($lines->isEmpty()) continue;
            $lines->loadMissing([$source, 'relation_item.brand']);
            if ($source === 'document') $lines->loadMissing('document.note');
            $costs = EstimatedPurchaseCost::forLines($lines, $source, true);
            $reader = new ConsolidatedLines($lines->first()->getConnection());
            foreach ($lines->chunk(500) as $chunk) {
                $chunk = $chunk->values();
                foreach ($chunk as $line) {
                    $parent = $line->$source;
                    $attributes = $parent->getAttributes();
                    $line->source_type = $source;
                    $line->source_id = $parent->id;
                    $line->currency_type_id = $attributes['currency_type_id'];
                    $line->exchange_rate_sale = $attributes['exchange_rate_sale'];
                    $line->document_type_id = $source === 'document' ? $parent->document_type_id : '80';
                }
                foreach ($reader->rows($chunk) as $index=>$value) {
                    $line = $chunk[$index];
                    $brand = $line->relation_item->brand;
                    $unit = $value['item_unit_type_id'] ?: null;
                    $key = json_encode([$brand->id ?? null, $unit], JSON_THROW_ON_ERROR);
                    if (!isset($groups[$key])) $groups[$key] = ['id'=>$key, 'report_key'=>'brand:'.$key,
                        'brand_id'=>$brand->id ?? null, 'brand_name'=>trim((string) ($brand->name ?? '')) ?: 'Sin marca', 'unit_type_id'=>$unit,
                        'quantity_sold'=>D::of(0), 'total_sale_value'=>D::of(0), 'total_profit'=>D::of(0)];
                    $quantity = D::readRequired($value, 'quantity');
                    // Price adjustments and debit notes do not move physical units.
                    $monetaryAdjustment = $line->document_type_id === '08' || ($line->document_type_id === '07'
                        && !in_array($line->document->note->note_credit_type_id ?? null, ['01','07'], true));
                    if ($monetaryAdjustment) $quantity = D::of(0);
                    $sale = D::readRequired($value, 'total_sale');
                    $cost = $monetaryAdjustment ? D::of(0) : $costs[spl_object_id($line)]['amount'];
                    if ($line->document_type_id === '07') $cost = $cost->negated();
                    $groups[$key]['quantity_sold'] = $groups[$key]['quantity_sold']->plus($quantity)->simplified();
                    $groups[$key]['total_sale_value'] = $groups[$key]['total_sale_value']->plus($sale)->simplified();
                    $groups[$key]['total_profit'] = $groups[$key]['total_profit']->plus($sale)->minus($cost)->simplified();
                }
            }
        }
        return collect(array_values(array_map(static function ($row) {
            $values = array_intersect_key($row, array_flip(['quantity_sold','total_sale_value','total_profit']));
            return array_replace($row, D::values($values), ['currency_type_id'=>'VES', 'cost_is_estimated'=>true,
                'calculation_snapshot'=>D::snapshot($values)]);
        }, $groups)));
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
