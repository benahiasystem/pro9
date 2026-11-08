<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;

/** Line amounts exclude header adjustments; quantities retain the frozen unit. */
final class DocumentReportLines
{
    public const MONETARY_FIELDS=['total_charge','total_exonerated','total_taxed','total_discount','total_igv','total'];
    private static function active($document): bool
    {
        return !in_array($document->state_type_id,['09','11'],true);
    }

    public static function monetary($document, string $field): \Brick\Math\BigRational
    {
        if (!self::active($document)) return D::of(0);
        $amount=D::readRequired($document,$field);
        return $field !== 'total_discount' && (string)$document->getDocumentType()->id === '07' ? $amount->negated() : $amount;
    }

    public static function monetaryRow($document): array
    {
        $values=[];foreach (self::MONETARY_FIELDS as $field) $values[$field]=self::monetary($document,$field);
        return ['report_key'=>($document instanceof \App\Models\Tenant\SaleNote ? 'sale_note:' : 'document:').$document->id,
            'calculation_snapshot'=>D::snapshot($values)] + D::values($values);
    }

    public static function categoryAmount($document, $categoryId): \Brick\Math\BigRational
    {
        if (!self::active($document)) return D::of(0);
        $amount=D::of(0);
        foreach ($document->items as $line) {
            if ((string)data_get($line,'relation_item.category_id') !== (string)$categoryId) continue;
            $amount=$amount->plus(D::readRequired($line,'total'));
        }
        return (string)$document->getDocumentType()->id === '07' ? $amount->negated() : $amount;
    }

    public static function serviceQuantity($document, $categoryId): \Brick\Math\BigRational
    {
        if (!self::active($document)) return D::of(0);
        $quantity=D::of(0);
        foreach ($document->items as $line) {
            if ((string)data_get($line,'relation_item.category_id') !== (string)$categoryId || data_get($line,'item.unit_type_id') !== 'SERV') continue;
            $quantity=$quantity->plus(D::readRequired($line,'quantity'));
        }
        return $quantity;
    }

    public static function quantities($document): array
    {
        $quantities=[];
        if (!self::active($document)) return $quantities;
        foreach ($document->items as $line) {
            $unit=trim((string)data_get($line,'item.unit_type_id')) ?: 'sin unidad';
            $quantities[$unit]=($quantities[$unit] ?? D::of(0))->plus(D::readRequired($line,'quantity'));
        }
        return $quantities;
    }

    public static function quantityText($document): string
    {
        $parts=[];foreach (self::quantities($document) as $unit=>$amount) $parts[]=D::represent($amount).' '.$unit;
        return $parts ? implode('; ', $parts) : '0';
    }

    public static function fractionEntries(iterable $records, iterable $categories, iterable $serviceCategories): array
    {
        $entries=[];
        foreach ($records as $record) {
            $key=($record instanceof \App\Models\Tenant\SaleNote ? 'sale_note:' : 'document:').$record->id;
            foreach (self::quantities($record) as $unit=>$amount) $entries[]=['id'=>$key,'field'=>'quantity:'.$unit,'value'=>$amount];
            foreach ($categories as $category) $entries[]=['id'=>$key,'field'=>'category_amount:'.$category->id,'value'=>self::categoryAmount($record,$category->id)];
            foreach ($serviceCategories as $category) $entries[]=['id'=>$key,'field'=>'category_services:'.$category->id,'value'=>self::serviceQuantity($record,$category->id)];
        }
        return $entries;
    }
}
