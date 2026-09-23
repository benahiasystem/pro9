<?php
namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalSaleNoteEconomics
{
    public const TOTALS = ['total_prepayment', 'total_charge', 'total_discount', 'total_exportation', 'total_free',
        'total_taxed', 'total_unaffected', 'total_exonerated', 'total_igv', 'total_igv_free', 'total_base_other_taxes',
        'total_other_taxes', 'total_taxes', 'total_value', 'subtotal', 'total'];

    /** Source totals already include their adjustments; never deduct or distribute them again. */
    public static function capture(iterable $sources): array
    {
        $snapshot = ['source_ids' => [], 'totals' => array_fill_keys(self::TOTALS, '0.00'), 'discounts' => [], 'charges' => []];
        foreach ($sources as $source) {
            $source = is_object($source) && method_exists($source, 'toArray') ? $source->toArray() : (array) $source;
            $snapshot['source_ids'][] = (int) $source['id'];
            foreach (self::TOTALS as $field) $snapshot['totals'][$field] = bcadd($snapshot['totals'][$field], (string) ($source[$field] ?? 0), 2);
            foreach (['discounts', 'charges'] as $field) {
                $adjustments = $source[$field] ?? [];
                if (is_string($adjustments)) $adjustments = json_decode($adjustments, true, 512, JSON_THROW_ON_ERROR);
                foreach ($adjustments ?: [] as $adjustment) $snapshot[$field][] = (array) $adjustment;
            }
        }
        sort($snapshot['source_ids'], SORT_NUMERIC);
        if (!$snapshot['source_ids'] || count(array_unique($snapshot['source_ids'])) !== count($snapshot['source_ids'])) {
            throw new \DomainException('Los orígenes económicos de la conversión no son válidos.');
        }
        return $snapshot;
    }

    public static function apply(array $input, array $snapshot): array
    {
        return array_replace($input, $snapshot['totals'], ['discounts' => $snapshot['discounts'], 'charges' => $snapshot['charges']]);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
