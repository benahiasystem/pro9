<?php

namespace Tests\Unit;

use App\Services\Payments\ReceiptPresentation;
use Tests\TestCase;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
class ReceiptPresentationTest extends TestCase
{
    public function test_first_method_omits_reversed_receipts_and_uses_frozen_description(): void
    {
        $rows=collect([
            (object)['reversed_at'=>'2026-10-01','payment_method_snapshot'=>['description'=>'Revertido']],
            (object)['reversed_at'=>null,'payment_method_snapshot'=>['description'=>'Original']],
        ]);
        self::assertSame('Original',ReceiptPresentation::firstMethod($rows));
        self::assertSame('',ReceiptPresentation::firstMethod($rows->take(1)));
    }

    public function test_it_prefers_frozen_method_and_bank_destination(): void
    {
        $payment=(object)[
            'payment_method_snapshot'=>['description'=>'Zelle original'],
            'global_payment'=>(object)['report_context'=>['method'=>['description'=>'Zelle en movimiento'],'destination_label'=>'Cuenta USD original'],
                'destination_description'=>'Cuenta renombrada'],
            'payment_method_type'=>(object)['description'=>'Zelle renombrado'],
        ];
        self::assertSame('Zelle original',ReceiptPresentation::method($payment));
        self::assertSame('Cuenta USD original',ReceiptPresentation::destination($payment));
    }

    public function test_it_uses_available_historical_fallbacks(): void
    {
        $payment=(object)['payment_method_snapshot'=>null,
            'global_payment'=>(object)['report_context'=>['method'=>['description'=>'Método del movimiento']], 'destination_description'=>'Cuenta anterior'],
            'payment_method_type'=>(object)['description'=>'Catálogo']];
        self::assertSame('Método del movimiento',ReceiptPresentation::method($payment));
        self::assertSame('Cuenta anterior',ReceiptPresentation::destination($payment));
        $payment->global_payment=null;
        self::assertSame('Catálogo',ReceiptPresentation::method($payment));
        self::assertNull(ReceiptPresentation::destination($payment));
        $payment->payment_method_snapshot=['receipt_origin'=>['destination_label'=>'Cuenta USD original']];
        self::assertSame('Cuenta USD original',ReceiptPresentation::destination($payment));
        $payment->payment_method_type=null;
        self::assertSame('No disponible',ReceiptPresentation::method($payment));
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
