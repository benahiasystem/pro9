<?php

namespace Tests\Unit;

use App\Services\Payments\MethodCurrency;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MethodCurrencyTest extends TestCase
{
    public function test_same_currency_can_use_the_document_currency(): void
    {
        self::assertSame('VES', MethodCurrency::resolve('VES', 'VES', []));
        self::assertSame('USD', MethodCurrency::resolve('USD', 'USD', ['currency_type_id' => 'USD']));
    }

    public function test_cross_currency_needs_explicit_amount_and_rate(): void
    {
        self::assertSame('USD', MethodCurrency::resolve('USD', 'VES', [
            'currency_type_id' => 'USD', 'original_amount' => '10', 'exchange_rate' => '400',
        ]));
        foreach ([[], ['currency_type_id' => 'VES'], ['currency_type_id' => 'USD']] as $input) {
            try {
                MethodCurrency::resolve('USD', 'VES', $input);
                self::fail('Debe rechazarse el cobro sin moneda o datos explícitos.');
            } catch (ValidationException $e) {
                self::assertNotEmpty($e->errors());
            }
        }
    }
}
