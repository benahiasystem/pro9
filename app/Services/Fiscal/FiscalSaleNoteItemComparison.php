<?php
namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNoteItemComparison
{
    public static function assertEquivalent(iterable $sources, iterable $submitted): void
    {
        if (self::buckets($sources) !== self::buckets($submitted)) {
            throw new \DomainException('Los precios, impuestos, descuentos y cantidades deben corresponder a las notas de venta originales.');
        }
    }

    private static function buckets(iterable $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $item = self::jsonArray($row['item'] ?? []);
            $key = [(int) $row['item_id'], (string) ($row['price_type_id'] ?? ''), (string) ($row['affectation_igv_type_id'] ?? ''),
                (string) ($item['unit_type_id'] ?? ''), self::decimal($row['quantity_factor'] ?? 1, 4)];
            foreach (['unit_value' => 6, 'unit_price' => 6, 'percentage_igv' => 2, 'percentage_other_taxes' => 2] as $field => $scale) {
                $key[] = self::decimal($row[$field] ?? 0, $scale);
            }
            $hasAdjustments = false;
            foreach (['discounts' => 'discount_type_id', 'charges' => 'charge_type_id'] as $field => $type) {
                $adjustments = [];
                foreach (self::jsonArray($row[$field] ?? []) as $adjustment) {
                    $adjustment = (array) $adjustment;
                    $adjustments[] = [(string) ($adjustment[$type] ?? ''), self::decimal($adjustment['factor'] ?? 0, 6),
                        self::decimal($adjustment['base'] ?? 0, 2), self::decimal($adjustment['amount'] ?? 0, 2)];
                }
                sort($adjustments);
                $hasAdjustments = $hasAdjustments || count($adjustments) > 0;
                $key[] = $adjustments;
            }
            $key = json_encode($key, JSON_THROW_ON_ERROR);
            $buckets[$key]['adjusted_rows'] = ($buckets[$key]['adjusted_rows'] ?? 0) + ($hasAdjustments ? 1 : 0);
            foreach (['quantity' => 6, 'total_base_igv' => 2, 'total_igv' => 2, 'total_base_other_taxes' => 2,
                'total_other_taxes' => 2, 'total_taxes' => 2, 'total_value' => 2, 'total_discount' => 2, 'total_charge' => 2, 'total' => 2] as $field => $scale) {
                $buckets[$key][$field] = bcadd($buckets[$key][$field] ?? '0', self::decimal($row[$field] ?? 0, $scale), $scale);
            }
        }
        ksort($buckets);
        return $buckets;
    }

    private static function decimal($value, int $scale): string
    {
        if (is_float($value)) $value = sprintf('%.12F', $value);
        if (!is_scalar($value) || !preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string) $value)) {
            throw new \DomainException('El importe o cantidad de la conversión no es válido.');
        }
        return bcadd((string) $value, '0', $scale);
    }

    private static function jsonArray($value): array
    {
        if (is_string($value)) $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return $value === null ? [] : (array) $value;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
