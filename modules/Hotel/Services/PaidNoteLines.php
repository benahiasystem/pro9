<?php

namespace Modules\Hotel\Services;

use App\Services\Payments\Classification;
use App\Support\Exact\Decimal as D;

/** Compare each transported decimal to its exact persisted line before summing. */
final class PaidNoteLines
{
    public static function assertMatch(array $paidItems, iterable $noteItems, $noteTotal): void
    {
        $expected=[]; $actual=[]; $actualTotal=D::of(0);
        foreach ($paidItems as $item) {
            if (!is_array($item) || empty($item['item_id'])) Classification::error('sale_note', 'Las partidas pagadas de Hotel no son válidas.');
            $expected[(string)$item['item_id']][]=D::represent(D::readRequired($item,'total'));
        }
        foreach ($noteItems as $item) {
            $key=(string)(is_array($item) ? $item['item_id'] : $item->item_id);
            $lineTotal=D::readRequired($item,'total');
            $actual[$key][]=D::represent($lineTotal);
            $actualTotal=$actualTotal->plus($lineTotal);
        }
        if (count($actual)!==count($expected)) Classification::error('sale_note', 'Las partidas de la nota no coinciden con las partidas pagadas de Hotel.');
        foreach ($expected as $key=>$amounts) {
            sort($amounts, SORT_STRING);
            if (isset($actual[$key])) sort($actual[$key], SORT_STRING);
            if (!isset($actual[$key]) || $actual[$key] !== $amounts) {
                Classification::error('sale_note', 'Los importes de la nota no coinciden con las partidas pagadas de Hotel.');
            }
        }
        if (D::represent($noteTotal)!==D::represent($actualTotal)) {
            Classification::error('sale_note', 'El total de la nota no coincide con las partidas pagadas de Hotel.');
        }
    }
}
