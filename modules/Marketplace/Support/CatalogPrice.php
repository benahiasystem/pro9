<?php

namespace Modules\Marketplace\Support;

use App\Support\Exact\Decimal;
use Illuminate\Validation\ValidationException;

/** Exact storage, with the existing catalogue limit and public JSON number. */
final class CatalogPrice
{
    public static function input($value, string $field = 'price'): ?string
    {
        if ($value === null) return null;
        $text = Decimal::input($value, $field, 12, 8);
        $number = Decimal::of($text);
        if ($number->isLessThan(0) || $number->isGreaterThan('99999999.99')) {
            throw ValidationException::withMessages([$field => 'El precio debe estar entre 0 y 99999999.99.']);
        }
        return $text;
    }

    public static function publicValue($value): ?float
    {
        if ($value === null) return null;
        // Final two-decimal presentation only: never use this number for calculations.
        return (float) Decimal::format($value, 2, '.', '');
    }
}
