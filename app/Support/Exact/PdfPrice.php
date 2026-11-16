<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS SAB ########
final class PdfPrice
{
    public static function precision($configuration): int
    {
        $enabled = is_array($configuration) ? ($configuration['change_decimal_quantity_unit_price_pdf'] ?? false)
            : ($configuration->change_decimal_quantity_unit_price_pdf ?? false);
        $scale = is_array($configuration) ? ($configuration['decimal_quantity_unit_price_pdf'] ?? 2)
            : ($configuration->decimal_quantity_unit_price_pdf ?? 2);
        $integer=filter_var($scale,FILTER_VALIDATE_INT);
        return $enabled && $integer !== false && $integer >= 2 && $integer <= 12 ? $integer : 2;
    }

    public static function format($value, $configuration): string
    {
        return Decimal::represent($value, self::precision($configuration), true);
    }

    public static function configured($value): string
    {
        return self::format($value, \App\Models\Tenant\Configuration::getDataDecimalQuantity());
    }

    /** Whether rounding the displayed unit price changes the displayed line base. */
    public static function needsRoundingNotice($items, $configuration): bool
    {
        foreach ($items as $item) {
            $price=Decimal::read($item,'unit_price');
            $quantity=Decimal::read($item,'quantity');
            if (self::roundingChangesLine($price,$quantity,$configuration)) return true;
        }
        return false;
    }

    public static function roundingChangesLine($price, $quantity, $configuration): bool
    {
        $price=Decimal::of($price); $quantity=Decimal::of($quantity);
        $shown=Decimal::of(self::format($price,$configuration));
        return Decimal::represent($shown->multipliedBy($quantity),2,true)
            !== Decimal::represent($price->multipliedBy($quantity),2,true);
    }
}
// ######## FIN CALCULOS EXACTOS SAB ########
