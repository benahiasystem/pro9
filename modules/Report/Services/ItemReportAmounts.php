<?php

namespace Modules\Report\Services;

use App\Models\Tenant\PurchaseItem;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

/** Original line amounts exclude document-wide adjustments; units and currencies remain distinct. */
final class ItemReportAmounts
{
    public static function row($item): array
    {
        $purchase=$item instanceof PurchaseItem;
        $document=$purchase ? $item->purchase : $item->document;
        $void=in_array($document->state_type_id,['09','11'],true);
        $credit=!$purchase && $document->document_type_id === '07';
        $quantity=$void ? D::of(0) : D::read($item,'quantity');
        $total=$void ? D::of(0) : D::read($item,'total');
        if ($credit) { $quantity=$quantity->negated(); $total=$total->negated(); }
        $values=['quantity'=>$quantity,'total'=>$total];
        return array_merge(['report_key'=>($purchase?'purchase_item:':'document_item:').$item->id,'model'=>$item,'document'=>$document,
            'currency_type_id'=>$document->currency_type_id ?: 'Sin moneda',
            'unit_type_id'=>($item->item->unit_type_id ?? null) ?: 'Sin unidad', 'calculation_snapshot'=>D::snapshot($values)],D::values($values));
    }

    public static function presentation($records): array
    {
        $rows=collect($records)->map(fn ($item)=>self::row($item))->values();
        $amounts=[]; $quantities=[]; $display=[];
        foreach ($rows as $row) {
            $currency=$row['currency_type_id']; $unit=$row['unit_type_id'];
            $amounts[$currency]=($amounts[$currency] ?? D::of(0))->plus(D::readRequired($row,'total'));
            $quantities[$unit]=($quantities[$unit] ?? D::of(0))->plus(D::readRequired($row,'quantity'));
        }
        foreach ($rows->groupBy('currency_type_id') as $group) {
            $plain=$group->map(function ($row) { unset($row['model'],$row['document']); return $row; });
            foreach (ReportProjection::money(new ReportDataset($plain),['total']) as $row) $display[$row['report_key']]=$row['display']['total'];
        }
        return ['rows'=>$rows->map(function ($row) use ($display) { $row['display_total']=$display[$row['report_key']]; return $row; }),
            'amounts'=>$amounts,'quantities'=>$quantities];
    }

    public static function displayForPage(iterable $records, iterable $keys): array
    {
        $wanted=[]; foreach ($keys as $key) $wanted[$key]=true;
        if (!$wanted) return [];
        $currencies=[];
        $dataset=new ReportDataset((function () use ($records, &$currencies) {
            foreach ($records as $item) {
                $row=self::row($item); $currencies[$row['currency_type_id']]=true;
                unset($row['model'],$row['document']); yield $row;
            }
        })());
        $display=[];
        foreach (array_keys($currencies) as $currency) {
            $group=$dataset->filter(fn ($row)=>$row['currency_type_id']===$currency);
            foreach (ReportProjection::money($group,['total']) as $row)
                if (isset($wanted[$row['report_key']])) $display[$row['report_key']]=$row['display']['total'];
        }
        return $display;
    }
}
