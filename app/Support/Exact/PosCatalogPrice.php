<?php

namespace App\Support\Exact;

/** The POS receives the selected catalog fraction as well as its decimal text. */
final class PosCatalogPrice
{
    public static function values($item, $price = null): array
    {
        $values = ['sale_unit_price' => $price ?? Decimal::readRequired($item, 'sale_unit_price')];
        $attributes = $item->getAttributes();
        if (array_key_exists('purchase_unit_price', $attributes) && $attributes['purchase_unit_price'] !== null) {
            $values['purchase_unit_price'] = Decimal::readRequired($item, 'purchase_unit_price');
        }
        return ['sale_unit_price' => Decimal::represent($values['sale_unit_price']),
            'calculation_snapshot' => Decimal::snapshot($values)];
    }
}
