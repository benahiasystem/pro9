<?php

namespace Tests\Unit;

use App\Services\Payments\MethodCurrency;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MethodCurrencyTest extends TestCase
{
    // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
    public function test_new_receipts_derive_intermediation_and_reject_explicit_contradictions(): void
    {
        foreach (['cash'=>'none', 'account'=>'financial_institution'] as $kind=>$expected) {
            $method = new \App\Models\Tenant\PaymentMethodType(['id'=>'custom','collection_kind'=>$kind,'is_active'=>true]);
            foreach ([[], ['financial_intermediation'=>null], ['financial_intermediation'=>$expected]] as $input) {
                $resolved = \App\Services\Payments\Classification::withFinancialIntermediation($input, $method);
                self::assertSame($expected, $resolved['financial_intermediation']);
            }
            try {
                \App\Services\Payments\Classification::withFinancialIntermediation(
                    ['financial_intermediation'=>$expected === 'none' ? 'financial_institution' : 'none'], $method);
                self::fail('La clasificación publicada no puede contradecir el método.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('financial_intermediation', $error->errors());
            }
        }
    }

    public function test_credit_unclassified_and_inactive_methods_cannot_be_real_receipts(): void
    {
        foreach ([['credit',true], ['unclassified',true], ['cash',false]] as [$kind,$active]) {
            $method = new \App\Models\Tenant\PaymentMethodType(['id'=>'custom','collection_kind'=>$kind,'is_active'=>$active]);
            try {
                \App\Services\Payments\Classification::withFinancialIntermediation([], $method);
                self::fail('El método no representa un cobro disponible.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('payment_method_type_id', $error->errors());
            }
        }
    }
    // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########

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
