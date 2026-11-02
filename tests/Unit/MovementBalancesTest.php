<?php

namespace Tests\Unit;

use App\Services\Payments\MovementBalances;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
final class MovementBalancesTest extends TestCase
{
    public function test_balances_do_not_mix_received_currencies_and_use_frozen_fractions(): void
    {
        $balances = new MovementBalances();
        self::assertSame('4000', $balances->apply(['currency_type_id'=>'VES', 'direction'=>'income', 'amount'=>'4000']));
        self::assertSame('10', $balances->apply(['currency_type_id'=>'USD', 'direction'=>'income', 'amount'=>'10']));
        self::assertSame('9.9', $balances->apply(['currency_type_id'=>'USD', 'direction'=>'egress', 'amount'=>'0.1']));
        self::assertSame('3999', $balances->apply(['currency_type_id'=>'VES', 'direction'=>'egress', 'amount'=>'1']));
        $third = ['currency_type_id'=>'USD', 'direction'=>'income', 'amount'=>'0.333333333333',
            'calculation_snapshot'=>['values'=>['amount'=>['n'=>'1','d'=>'3']]]];
        $balances->apply($third);
        $balances->apply($third);
        self::assertSame('10.9', $balances->apply($third));
    }

    public function test_invalid_currency_is_not_silently_combined(): void
    {
        $this->expectException(ValidationException::class);
        (new MovementBalances())->apply(['currency_type_id'=>'EUR', 'direction'=>'income', 'amount'=>'5']);
    }

    public function test_presentation_rounds_only_after_the_exact_balance_is_complete(): void
    {
        $balance=(new MovementBalances())->applyExact(['currency_type_id'=>'VES', 'direction'=>'income', 'amount'=>'0.005',
            'calculation_snapshot'=>['values'=>['amount'=>['n'=>'4999999999999','d'=>'1000000000000000']]]]);
        self::assertSame('0.00',\App\Support\Exact\Decimal::format($balance,2,'.',''));
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
