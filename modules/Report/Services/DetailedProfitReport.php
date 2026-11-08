<?php

namespace Modules\Report\Services;

use App\Models\Tenant\DocumentItem;
use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

/** Estimated line profit, in documentary currency and before global adjustments. */
final class DetailedProfitReport
{
    public static function rows($records)
    {
        $records=collect($records); $costs=[];
        foreach (['document','sale_note'] as $source) {
            $lines=$records->filter(fn($line)=>($line instanceof DocumentItem) === ($source==='document'))
                ->filter(fn($line)=>($line->item->unit_type_id ?? null)!=='SERV');
            if ($lines->isNotEmpty()) $costs += EstimatedPurchaseCost::forLines($lines,$source);
        }
        $rows=$records->map(function($line) use($costs) {
            $document=$line instanceof DocumentItem; $parent=$document ? $line->document : $line->sale_note;
            $item=$line->item; $presentation=(array)($item->presentation ?? []);
            $factor=D::inputRational($presentation['quantity_unit'] ?? '1','presentation.quantity_unit');
            if (!$factor->isPositive()) throw new \UnexpectedValueException('La presentación requiere una cantidad positiva.');
            $quantity=D::readRequired($line,'quantity')->multipliedBy($factor);
            $price=D::readRequired($line,'unit_price')->dividedBy($factor);
            $cost=$costs[spl_object_id($line)]['amount'] ?? D::of(0);
            $unitCost=$quantity->isZero() ? D::of(0) : $cost->dividedBy($quantity);
            $values=['quantity'=>$quantity,'purchase_unit_price'=>$unitCost,'unit_price'=>$price,
                'unit_gain'=>$price->minus($unitCost),'overall_profit'=>$price->multipliedBy($quantity)->minus($cost)];
            $customer=$parent->customer;
            return array_merge(['report_key'=>($document?'document_item:':'sale_note_item:').$line->id,
                'id'=>$line->id,'date_of_issue'=>$parent->date_of_issue->format('Y-m-d'),
                'type_document'=>$document?'FACTURA':'NOTA DE VENTA','serie'=>$parent->number_full,
                'customer_number'=>format_person_identity_document($customer),'customer_name'=>$customer->name ?? '',
                'name'=>$item->description ?? ($line->relation_item->description ?? ''),
                'currency_type_id'=>$parent->currency_type_id ?: 'Sin moneda',
                'unit_type_id'=>$item->unit_type_id ?? 'Sin unidad','calculation_snapshot'=>D::snapshot($values)],D::values($values));
        })->values();
        $display=[];
        foreach ($rows->groupBy('currency_type_id') as $group)
            foreach (ReportProjection::money(new ReportDataset($group),['overall_profit']) as $row) $display[$row['report_key']]=$row['display']['overall_profit'];
        return $rows->map(function($row) use($display) { $row['display_profit']=$display[$row['report_key']]; return $row; });
    }

    public static function totals($rows): array
    {
        $totals=[];
        foreach ($rows as $row) $totals[$row['currency_type_id']]=($totals[$row['currency_type_id']] ?? D::of(0))->plus(D::readRequired($row,'overall_profit'));
        ksort($totals); return $totals;
    }
}
