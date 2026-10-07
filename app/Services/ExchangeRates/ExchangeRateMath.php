<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace App\Services\ExchangeRates;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class ExchangeRateMath
{
    public static function rate($value, string $field = 'exchange_rate'): string
    {
        try {
            if (!is_string($value) && !is_int($value) && !is_float($value)) throw new \InvalidArgumentException();
            $rate = BigDecimal::of(str_replace(',', '.', (string) $value))->toScale(8, RoundingMode::UNNECESSARY);
            if ($rate->isLessThanOrEqualTo(0) || $rate->isGreaterThan('9999999999.99999999')) throw new \InvalidArgumentException();
            return (string) $rate;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([$field => 'Indique una tasa positiva con hasta ocho decimales, sin redondeo.']);
        }
    }

    public static function rational($value): BigRational
    {
        return $value instanceof BigRational ? $value : BigRational::of((string) $value);
    }

    public static function multiply($amount, $rate, ?int $scale = null): string
    {
        $result = self::rational($amount)->multipliedBy(self::rate($rate));
        return $scale === null ? (string) $result->toBigDecimal() : self::finalAmount($result, $scale);
    }

    public static function divide($amount, $rate, int $scale = 2): string
    {
        return self::finalAmount(self::rational($amount)->dividedBy(self::rate($rate)), $scale);
    }

    public static function finalAmount($value, int $scale = 2): string
    {
        return (string) self::rational($value)->toScale($scale, RoundingMode::HALF_UP);
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
