<?php

namespace App\Support\Exact;

use App\Models\Tenant\Item;
use Illuminate\Validation\ValidationException;

/** Replays product price edits against the locked, server-owned item values. */
final class ItemProfitEdits
{
    private const FIELDS = ['sale_unit_price', 'purchase_unit_price', 'percentage_of_profit'];

    public static function resolve(Item $item, array $base, array $events, array $submitted): array
    {
        if (count($events) > 1024 || !array_is_list($events)) self::error('exact_profit_sequence', 'La secuencia de edición no es válida.');
        $values = [];
        foreach (self::FIELDS as $field) {
            $fraction = $base[$field] ?? null;
            if (!is_array($fraction) || !isset($fraction['n'], $fraction['d'])
                || !is_string($fraction['n']) || !is_string($fraction['d'])) {
                self::error('exact_profit_base', 'El precio de origen no es válido.');
            }
            $current = $item->exists ? Decimal::read($item, $field) : Decimal::of(0);
            // Compare canonical strings with the server-owned value; never parse a client-supplied rational.
            $expected = Decimal::fraction($current);
            if ($fraction['n'] !== $expected['n'] || $fraction['d'] !== $expected['d']) self::error('exact_profit_base', 'El producto cambió. Recargue sus precios antes de guardar.');
            $values[$field] = $current;
        }
        foreach ($events as $index => $event) {
            if (!is_array($event) || !in_array($event['field'] ?? null, self::FIELDS, true)) self::error("exact_profit_sequence.$index", 'La edición no es válida.');
            $field = $event['field'];
            $values[$field] = Decimal::of(Decimal::input($event['value'] ?? null, "exact_profit_sequence.$index.value"));
            if (in_array($event['recalculate'] ?? true, [false, 0, '0'], true)) continue;
            if ($field === 'sale_unit_price') {
                $cost = $values['purchase_unit_price'];
                $values['percentage_of_profit'] = $cost->isZero() ? Decimal::of(0)
                    : $values['sale_unit_price']->minus($cost)->dividedBy($cost)->multipliedBy(100);
            } else {
                $values['sale_unit_price'] = $values['purchase_unit_price']
                    ->multipliedBy($values['percentage_of_profit']->plus(100))->dividedBy(100);
            }
        }
        foreach (self::FIELDS as $field) {
            $shown = Decimal::of(Decimal::input($submitted[$field] ?? null, $field));
            if (!$shown->isEqualTo(Decimal::represent($values[$field]))) self::error($field, 'El precio calculado cambió. Revise los valores antes de guardar.');
            if (!Persistence::enabled($item) && !$values[$field]->isEqualTo(Decimal::represent($values[$field]))) {
                self::error($field, 'El producto no puede conservar este cálculo exacto en su esquema actual.');
            }
        }
        return $values;
    }

    private static function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
