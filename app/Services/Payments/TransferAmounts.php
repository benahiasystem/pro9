<?php
namespace App\Services\Payments;

use App\Support\Exact\Decimal;

/** Validate both received transfer legs before deriving the signed debit. */
final class TransferAmounts
{
    public static function decimal($value, string $field = 'amount'): string
    {
        if (!is_string($value) && !is_int($value)) {
            Classification::error($field, 'Transmita el importe decimal como texto, sin convertirlo a flotante.');
        }
        return Decimal::input($value, $field);
    }

    public static function fromInput(array $input): array
    {
        $values=[];
        foreach (['amount_transform','amount_transform_temp'] as $field) {
            $value=Decimal::of(self::decimal($input[$field] ?? null, 'data.'.$field));
            if (!$value->isPositive()) Classification::error('data.'.$field, 'El importe del traslado debe ser positivo.');
            $values[$field]=$value;
        }
        return ['credit'=>Decimal::represent($values['amount_transform']),
            'debit'=>Decimal::represent($values['amount_transform_temp']->negated())];
    }
}
