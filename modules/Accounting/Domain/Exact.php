<?php

namespace Modules\Accounting\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
final class Exact
{
    public static function of(mixed $value, int $scale = 8): BigDecimal
    {
        if (!is_string($value) || !preg_match('/^-?(?:0|[1-9][0-9]{0,19})(?:\.[0-9]{1,12})?$/D', $value)) {
            throw new ApiProblem('INVALID_DECIMAL', 'Los importes y tasas deben ser cadenas decimales exactas.');
        }
        try {
            return BigDecimal::of($value)->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (\Throwable) {
            throw new ApiProblem('INVALID_PRECISION', 'El valor supera la precisión admitida.');
        }
    }
    public static function money(mixed $value): string
    {
        return (string) self::of($value)->toScale(2, RoundingMode::HALF_UP);
    }
    public static function total(array $values, int $scale = 2): string
    {
        $sum = BigDecimal::zero();
        foreach ($values as $value) {
            $sum = $sum->plus(self::of($value));
        }
        return (string) $sum->toScale($scale, RoundingMode::HALF_UP);
    }
    public static function convert(string $amount, string $rate): string
    {
        return (string) self::of($amount, 12)->multipliedBy(self::of($rate, 12))->toScale(2, RoundingMode::HALF_UP);
    }
}
