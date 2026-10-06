<?php
namespace Tests\Unit;

use App\CoreFacturalo\Requests\Api\Transform\DocumentTransform;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalApiPaymentTransformTest extends TestCase
{
    private function payments(array $row): array
    {
        $method=new \ReflectionMethod(DocumentTransform::class,'payments');
        $method->setAccessible(true);
        return $method->invoke(null,['codigo_tipo_documento'=>'01','codigo_tipo_moneda'=>'VES','tipo_cambio_venta'=>1,
            'fecha_de_emision'=>'2026-09-10','pagos'=>[array_replace(['codigo_metodo_pago'=>'01','codigo_destino_pago'=>'cash','monto'=>80],$row)]]);
    }
    public function test_legacy_api_payments_keep_document_currency_and_amount(): void
    {
        $p=$this->payments([])[0];
        self::assertSame('VES',$p['currency_type_id']);self::assertSame(80,$p['original_amount']);self::assertSame(1,$p['exchange_rate']);
    }
    public function test_api_cannot_replace_a_missing_payment_rate_with_the_invoice_rate(): void
    {
        $this->expectException(ValidationException::class);
        $this->payments(['currency_type_id'=>'USD','original_amount'=>8,'operation_key'=>(string)\Illuminate\Support\Str::uuid()]);
    }
    public function test_api_keeps_the_original_amount_rate_and_operation_key(): void
    {
        $key=(string)\Illuminate\Support\Str::uuid();
        $p=$this->payments(['currency_type_id'=>'USD','original_amount'=>8,'exchange_rate'=>10.123,'operation_key'=>$key])[0];
        self::assertSame(8,$p['original_amount']);self::assertSame(10.123,$p['exchange_rate']);self::assertSame($key,$p['operation_key']);
    }
    public function test_api_requires_an_operation_key_for_payments_with_their_own_currency(): void
    {
        $this->expectException(ValidationException::class);
        $this->payments(['currency_type_id'=>'USD','original_amount'=>8,'exchange_rate'=>10]);
    }
}
