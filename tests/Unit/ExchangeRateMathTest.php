<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace Tests\Unit;
use App\Services\ExchangeRates\ExchangeRateMath as Rate;
use App\Services\Fiscal\FiscalAmounts;
use PHPUnit\Framework\TestCase;

class ExchangeRateMathTest extends TestCase
{
    public function test_decimal_normalization_does_not_round_or_drop_zeroes(): void
    {
        self::assertSame('873.86700000', Rate::rate('873.86700000'));
        self::assertSame('873.86712345', Rate::rate('873.86712345'));
        self::assertSame('1.00000000', Rate::rate('1'));
        self::assertSame('0.00000001', Rate::rate('0.00000001'));
        self::assertSame('9999999999.99999999', Rate::rate('9999999999.99999999'));
    }
    public function test_conversion_keeps_eight_digits_until_final_money_rounding(): void
    {
        self::assertSame('873867.12', Rate::multiply('1000', '873.86712345', 2));
        self::assertSame('873867.00', Rate::multiply('1000', '873.86700000', 2));
        self::assertSame('0.57216937', Rate::divide('500', '873.86712345', 8));
        $roundTrip = Rate::rational('500')->dividedBy('873.86712345')->multipliedBy('873.86712345');
        self::assertSame('500.00', Rate::finalAmount($roundTrip));
        self::assertSame(873867.12, FiscalAmounts::convert('1000', 'USD', 'VES', '873.86712345'));
        self::assertSame('-1.01', Rate::finalAmount('-1.005'));
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
