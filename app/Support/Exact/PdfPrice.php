<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
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
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
