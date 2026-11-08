<?php

namespace App\Support\Exact;

/** Quantity totals for local document PDFs, grouped by the unit frozen on each line. */
final class PdfQuantities
{
    /** Keep the stored fraction until the final twelve-decimal presentation. */
    public static function line($item): string
    {
        $exact = Decimal::readRequired($item, 'quantity');
        $quantity = Decimal::represent($exact);
        if ($quantity === '0' && !$exact->isZero()) return $exact->isNegative() ? '>-0.000000000001' : '<0.000000000001';
        return str_contains($quantity, '.') ? $quantity : preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $quantity);
    }

    public static function summary(iterable $items): string
    {
        $groups = [];
        foreach ($items as $item) {
            $unit = trim((string) data_get($item, 'item.unit_type_id', ''));
            $key = $unit === '' ? 'sin unidad' : $unit;
            $quantity = Decimal::read($item, 'quantity');
            $groups[$key] = isset($groups[$key]) ? $groups[$key]->plus($quantity)->simplified() : $quantity;
        }

        if (!$groups) return '0';

        $parts = [];
        foreach ($groups as $unit => $quantity) $parts[] = Decimal::represent($quantity).' '.$unit;
        return implode(', ', $parts);
    }

    /** Additional millimetres for long quantities displayed on their own line in a 58 mm ticket. */
    public static function extraHeight58(iterable $items): int
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        $height = 0;
        foreach ($items as $item) {
            $quantity = Decimal::represent(Decimal::read($item, 'quantity'));
            if (strlen($quantity) > 9) $height += 5 * intdiv(strlen($quantity) + strlen('Cantidad: ') + 35, 36);
        }
        $summary = self::summary($items);
        if (strlen($summary) > 9) $height += 5 * intdiv(strlen($summary) + strlen('Cantidades: ') + 35, 36);
        return $height;
    }
}
