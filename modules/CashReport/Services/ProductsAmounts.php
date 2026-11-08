<?php

namespace Modules\CashReport\Services;

use App\Support\Exact\Decimal as D;
use App\Support\Exact\ReportDataset;
use App\Support\Exact\ReportProjection;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Product reports keep each line's original fractions and documentary currency. */
final class ProductsAmounts
{
    public static function line($row, string $currency, bool $credit = false): array
    {
        $quantity = D::readRequired($row, 'quantity');
        $unit = D::readRequired($row, 'unit_value');
        $total = D::readRequired($row, 'total');
        if ($credit) { $quantity=$quantity->negated(); $total=$total->negated(); }
        $subtotal = $unit->multipliedBy($quantity);
        return [
            'currency_type_id' => $currency,
            'quantity' => D::represent($quantity),
            'unit_value' => D::represent($unit),
            'sub_total' => D::represent($subtotal),
            'total' => D::represent($total),
            'calculation_snapshot' => D::snapshot([
                'quantity'=>$quantity, 'unit_value'=>$unit, 'sub_total'=>$subtotal, 'total'=>$total,
            ]),
        ];
    }

    public static function subtotals(iterable $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $currency = $row['currency_type_id'];
            if (!in_array($currency, ['VES', 'USD'], true)) continue;
            $totals[$currency] = ($totals[$currency] ?? D::of(0))->plus(D::readRequired($row, 'sub_total'));
        }
        return $totals;
    }

    public static function subtotalsByOperation(iterable $rows): array
    {
        $groups = ['Ventas'=>[], 'Compras'=>[]];
        foreach ($rows as $row) $groups[($row['record_type'] ?? '') === 'purchase_item' ? 'Compras' : 'Ventas'][] = $row;
        return array_map([self::class, 'subtotals'], $groups);
    }

    public static function sold(iterable $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (!in_array($row['record_type'], ['sale_note_item', 'document_item'], true)) continue;
            $key = json_encode([$row['item_id'], $row['currency_type_id'], $row['unit_type_id'] ?? null], JSON_THROW_ON_ERROR);
            if (!isset($groups[$key])) {
                $groups[$key] = ['item_id'=>$row['item_id'], 'currency_type_id'=>$row['currency_type_id'], 'unit_type_id'=>$row['unit_type_id'] ?? null,
                    'quantity'=>D::of(0), 'total'=>D::of(0)];
            }
            $groups[$key]['quantity'] = $groups[$key]['quantity']->plus(D::readRequired($row, 'quantity'));
            $groups[$key]['total'] = $groups[$key]['total']->plus(D::readRequired($row, 'total'));
            $groups[$key]['description'] = $row['description'];
            $groups[$key]['unit_value'] = D::readRequired($row, 'unit_value');
        }
        return array_values($groups);
    }

    /** Project each currency independently, keeping displayed rows equal to their total. */
    public static function display(iterable $rows, string $field): array
    {
        $groups = []; $index = 0; $display = [];
        foreach ($rows as $row) {
            $currency = $row['currency_type_id'];
            $operation = ($row['record_type'] ?? '') === 'purchase_item' ? 'purchase' : 'sale';
            $groups[$operation][$currency][] = ['position'=>$index++, 'calculation_snapshot'=>D::snapshot(['value'=>D::readRequired($row, $field)])];
        }
        foreach ($groups as $currencies) {
            foreach ($currencies as $group) {
                foreach (ReportProjection::money(new ReportDataset($group), ['value']) as $row) {
                    $display[$row['position']] = $row['display']['value'];
                }
            }
        }
        return $display;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
