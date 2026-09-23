<?php
namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNoteItems
{
    /** Preserve stored economics; only combine fully equivalent, simple rows. */
    public static function group(array $items): array
    {
        // Eloquent item accessors expose JSON snapshots as stdClass instances.
        $items = json_decode(json_encode($items, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        $positions = [];
        foreach ($items as $row) {
            $hasDetails = false;
            foreach (['discounts', 'charges', 'attributes', 'IdLoteSelected'] as $field) {
                if (!empty($row[$field])) $hasDetails = true;
            }
            foreach (['lots', 'lots_group', 'IdLoteSelected', 'series', 'presentation'] as $field) {
                if (!empty($row['item'][$field])) $hasDetails = true;
            }
            if ($hasDetails) { $result[] = $row; continue; }
            $signature = $row;
            foreach (['id', 'sale_note_id', 'document_id', 'created_at', 'updated_at', 'quantity'] as $field) unset($signature[$field]);
            foreach (array_keys($signature) as $field) if (str_starts_with($field, 'total_') || $field === 'total') unset($signature[$field]);
            $key = json_encode(self::canonical($signature), JSON_THROW_ON_ERROR);
            if (!array_key_exists($key, $positions)) {
                $positions[$key] = count($result);
                $result[] = $row;
                continue;
            }
            $position = $positions[$key];
            foreach ($row as $field => $value) {
                if ($field === 'quantity' || $field === 'total' || str_starts_with($field, 'total_')) {
                    if ($value !== null && is_numeric($value)) {
                        $result[$position][$field] = bcadd((string) ($result[$position][$field] ?? 0), (string) $value, 6);
                    }
                }
            }
        }
        return $result;
    }

    private static function canonical(array $value): array
    {
        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) ksort($value);
        foreach ($value as &$child) if (is_array($child)) $child = self::canonical($child);
        return $value;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
