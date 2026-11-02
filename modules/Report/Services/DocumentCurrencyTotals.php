<?php

namespace Modules\Report\Services;

use App\Support\Exact\Decimal;

/** Totales documentales por moneda, sin convertir ni redondear cada fila. */
final class DocumentCurrencyTotals
{
    private const FIELDS = ['total', 'total_taxed', 'total_igv', 'total_charge', 'total_exonerated'];

    public static function fractions($document): array
    {
        $values = [];
        foreach (self::FIELDS as $field) $values[$field] = Decimal::fraction(Decimal::read($document, $field));
        return $values;
    }

    public static function empty(): array
    {
        $totals = [];
        foreach (['VES', 'USD'] as $currency) {
            foreach (self::FIELDS as $field) $totals[$currency][$field] = Decimal::of(0);
        }
        return $totals;
    }

    public static function add(array $totals, $document): array
    {
        $currency = $document->currency_type_id;
        if (!isset($totals[$currency])) return $totals;
        $type = (string) $document->getDocumentType()->id;
        $state = (string) $document->state_type_id;
        if (in_array($state, ['09', '11'], true)) return $totals;
        foreach (self::FIELDS as $field) {
            $amount = Decimal::read($document, $field);
            $totals[$currency][$field] = $totals[$currency][$field]->plus($type === '07' ? $amount->negated() : $amount);
        }
        return $totals;
    }
}
