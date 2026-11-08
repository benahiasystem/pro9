<?php

namespace App\Support\Exact;

/** Presentation of the balance after receipts applied in the quotation currency. */
final class QuotationPdfAmounts
{
    /** Only the frozen gross-weight attribute (5031) is a weight in kilograms. */
    public static function weight(iterable $items): array
    {
        $sum=Decimal::of(0); $hasWeight=false; $complete=true;
        foreach ($items as $item) {
            $attributes=is_object($item) && method_exists($item, 'getAttributes') ? $item->getAttributes() : (array)$item;
            $raw=$attributes['attributes'] ?? [];
            if (is_string($raw)) $raw=JsonDecimals::decode($raw);
            $matches=[];
            foreach ((array)$raw as $attribute) {
                $attribute=(array)$attribute;
                if ((string)($attribute['attribute_type_id'] ?? '') === '5031') $matches[]=$attribute['value'] ?? null;
            }
            if (!$matches) { $complete=false; continue; }
            $hasWeight=true;
            if (count($matches) !== 1 || !is_scalar($matches[0]) || is_bool($matches[0])
                || !preg_match('/^\d+(?:\.\d+)?$/D', (string)$matches[0])) {
                $complete=false; continue;
            }
            $sum=$sum->plus(Decimal::of((string)$matches[0])->multipliedBy(Decimal::readRequired($item,'quantity')));
        }
        return ['has_weight'=>$hasWeight, 'display'=>$complete ? Decimal::format($sum).' Kg' : 'No disponible'];
    }

    public static function values($quotation): array
    {
        $total = Decimal::readRequired($quotation, 'total');
        $paid = Decimal::of(0);
        $displayedPaid = Decimal::of(0);
        foreach ($quotation->payments as $receipt) {
            $amount = Decimal::readRequired($receipt, 'payment');
            $paid = $paid->plus($amount);
            $displayedPaid = $displayedPaid->plus(Decimal::of(Decimal::represent($amount, 2)));
        }
        $balance = $total->minus($paid);
        $displayedBalance = Decimal::of(Decimal::represent($balance, 2));
        $displayedTotal = Decimal::of(Decimal::represent($total, 2));
        $rounding = $displayedBalance->minus($displayedTotal->minus($displayedPaid));

        return [
            'balance'=>Decimal::format($balance),
            'rounding'=>Decimal::format($rounding),
            'has_rounding'=>!$rounding->isZero(),
        ];
    }
}
