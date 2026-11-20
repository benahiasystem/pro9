<?php

namespace App\Support\Exact;

use Brick\Math\BigRational;

/** Disposable invoice presentation values; the original document stays unchanged. */
final class InvoicePdfSummary
{
    /** Final quantity text; a nonzero snapshot must never be presented as zero. */
    public static function quantity($line): string
    {
        return self::quantityText(Decimal::readRequired($line,'quantity'));
    }

    public static function quantities(iterable $items): string
    {
        $groups=[];
        foreach ($items as $line) {
            $unit=trim((string)data_get($line,'item.unit_type_id','')) ?: 'sin unidad';
            $quantity=Decimal::readRequired($line,'quantity');
            $groups[$unit]=isset($groups[$unit]) ? $groups[$unit]->plus($quantity) : $quantity;
        }
        $parts=[];
        foreach ($groups as $unit=>$quantity) $parts[]=self::quantityText($quantity).' '.$unit;
        return $parts ? implode(', ',$parts) : '0';
    }

    private static function quantityText(BigRational $quantity): string
    {
        $text=Decimal::represent($quantity);
        return $text==='0' && !$quantity->isZero()
            ? ($quantity->isNegative() ? '>-0.000000000001' : '<0.000000000001') : $text;
    }

    public static function ticketTotals($document, bool $thirdParty): array
    {
        $discount=PdfAmounts::read($document,$thirdParty ? 'total_discount' : 'total_discount_with_igv');
        $igtf=Decimal::of(0);
        if ($thirdParty) foreach ($document->taxes ?? [] as $tax) {
            if (data_get($tax,'tax_kind')==='IGTF') $igtf=$igtf->plus(Decimal::read($tax,'amount'));
        }
        return ['discount'=>$discount,'igtf'=>$igtf,
            'subtotal'=>PdfAmounts::read($document,'subtotal')->plus($discount)
                ->minus($thirdParty ? PdfAmounts::read($document,'total_charge') : 0)];
    }

    /** Preserve the existing product/service selection for the packages legend. */
    public static function packages(iterable $items): BigRational
    {
        $total=Decimal::of(0);
        foreach ($items as $line) {
            if (!in_array(data_get($line,'item.item_type_id'),['02']) && data_get($line,'item.unit_type_id')!=='SERV') {
                $total=$total->plus(Decimal::read($line,'quantity'));
            }
        }
        return $total;
    }

    /** Attribute 5032 is the historical net-weight source of this invoice layout. */
    public static function netWeight(iterable $items): BigRational
    {
        $total=Decimal::of(0);
        foreach ($items as $line) {
            $values=is_array($line) ? $line : (method_exists($line,'getAttributes') ? $line->getAttributes() : get_object_vars($line));
            $attributes=$values['attributes'] ?? [];
            if (is_string($attributes)) $attributes=JsonDecimals::decode($attributes);
            foreach ($attributes ?? [] as $attribute) {
                if (data_get($attribute,'attribute_type_id')==='5032') {
                    $weight=data_get($attribute,'value');
                    if ($weight===null || $weight==='') continue;
                    $total=$total->plus(Decimal::of($weight)->multipliedBy(Decimal::read($line,'quantity')));
                }
            }
        }
        return $total;
    }

    public static function packageText(BigRational $quantity): string
    {
        $text=self::quantityText($quantity);
        return str_contains($text,'.') ? $text : preg_replace('/\B(?=(\d{3})+(?!\d))/',',',$text);
    }
}
