<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal as D;

/** Exact order header and original line figures, without combining units. */
final class OrderGeneralAmounts
{
    public const FIELDS = ['total','total_discount','subtotal_items','discount_items'];

    public static function row($order): array
    {
        $subtotal=D::of(0); $discount=D::of(0); $units=[];
        foreach ($order->items as $item) {
            $quantity=D::read($item,'quantity');
            $unit=($item->item->unit_type_id ?? null) ?: 'Sin unidad';
            $units[$unit]=($units[$unit] ?? D::of(0))->plus($quantity)->simplified();
            $subtotal=$subtotal->plus(D::read($item,'unit_price')->multipliedBy($quantity))->simplified();
            $discount=$discount->plus(D::read($item,'total_discount'))->simplified();
        }
        ksort($units);
        $values=['total'=>D::read($order,'total'),'total_discount'=>D::read($order,'total_discount'),
            'subtotal_items'=>$subtotal,'discount_items'=>$discount];
        $entries=[]; $parts=[];
        foreach ($units as $unit=>$quantity) {
            $entries[]=['id'=>'order:'.$order->id,'field'=>'quantity:'.$unit,'value'=>D::fraction($quantity)];
            $parts[]=D::represent($quantity).' '.$unit;
        }
        return array_merge(['report_key'=>'order:'.$order->id,'currency_type_id'=>$order->currency_type_id ?: 'Sin moneda',
            'quantity_by_unit'=>implode('; ',$parts),'unit_fractions'=>$entries,'calculation_snapshot'=>D::snapshot($values)],D::values($values));
    }
}
