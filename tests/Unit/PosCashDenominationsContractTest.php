<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PosCashDenominationsContractTest extends TestCase
{
    // ########## INICIO CAMBIO QUITAR SELECCIÓN DE BILLETES
    /** @test */
    public function main_pos_payment_keeps_manual_cash_entry_without_quick_denominations(): void
    {
        $basePath = dirname(__DIR__, 2).'/resources/js/views/tenant/pos';
        $payment = file_get_contents($basePath.'/partials/payment.vue');
        $pos = file_get_contents($basePath.'/index.vue');
        $fastPos = file_get_contents($basePath.'/fast.vue');

        self::assertNotFalse($payment);
        self::assertNotFalse($pos);
        self::assertNotFalse($fastPos);
        self::assertStringContainsString('ref="enter_amount"', $payment);
        self::assertStringContainsString('@input="enterAmount()"', $payment);
        self::assertStringContainsString("v-text=\"isMissingAmount ? 'Faltante' : 'Vuelto'\"", $payment);
        self::assertStringContainsString('{{ differenceText }}', $payment);
        self::assertStringContainsString('setAmountCash(amount)', $payment);
        self::assertStringNotContainsString('setAmountCash(10)', $payment);
        self::assertStringNotContainsString('setAmountCash(20)', $payment);
        self::assertStringNotContainsString('setAmountCash(50)', $payment);
        self::assertStringNotContainsString('setAmountCash(100)', $payment);
        self::assertStringContainsString('INICIO CAMBIO QUITAR SELECCIÓN DE BILLETES', $payment);
        self::assertStringContainsString('FIN CAMBIO QUITAR SELECCIÓN DE BILLETES', $payment);
        self::assertStringContainsString('import PaymentForm from "./partials/payment.vue";', $pos);
        self::assertStringContainsString('import FastPayment from "./partials/fast_payment.vue";', $fastPos);
        self::assertStringNotContainsString('fast_bk', $fastPos);
    }
    // ######### FIN CAMBIO QUITAR SELECCIÓN DE BILLETES
}
