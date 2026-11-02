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

    // ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
    public function test_api_preserves_source_receipt_id_and_original_date(): void
    {
        $key=(string)\Illuminate\Support\Str::uuid();
        $row=$this->payments(['source_sale_note_payment_id'=>42,'date_of_payment'=>'2026-08-31',
            'currency_type_id'=>'USD','original_amount'=>'8','exchange_rate'=>'10','operation_key'=>$key])[0];
        self::assertSame(42,$row['source_sale_note_payment_id']);
        self::assertSame('2026-08-31',$row['date_of_payment']);
        self::assertSame('8',$row['original_amount']);
        self::assertSame('10',$row['exchange_rate']);
        self::assertSame('2026-09-10',$this->payments(['date_of_payment'=>'2026-08-31'])[0]['date_of_payment']);
    }
    // ######## FIN CLASIFICACION DE COBROS Y CAJA ########

    public function test_offline_web_payload_keeps_currency_rate_and_stable_operation_key(): void
    {
        $method=new \ReflectionMethod(\App\CoreFacturalo\Requests\Inputs\Transform\DocumentWebTransform::class,'payments');
        $method->setAccessible(true);
        $rows=$method->invoke(null,['document_type_id'=>'01','payments'=>[[
            'date_of_payment'=>'2026-09-10','payment_method_type_id'=>'13','payment_destination_id'=>7,
            'payment'=>'4000','currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'400',
        ]]]);
        self::assertTrue(\Illuminate\Support\Str::isUuid($rows[0]['operation_key']));
        $api=new \ReflectionMethod(DocumentTransform::class,'payments');
        $api->setAccessible(true);
        $result=$api->invoke(null,['codigo_tipo_documento'=>'01','codigo_tipo_moneda'=>'VES','factor_tipo_de_cambio'=>'400',
            'fecha_de_emision'=>'2026-09-10','pagos'=>$rows]);
        self::assertSame('USD',$result[0]['currency_type_id']);
        self::assertSame('10',$result[0]['original_amount']);
        self::assertSame('400',$result[0]['exchange_rate']);
        self::assertSame($rows[0]['operation_key'],$result[0]['operation_key']);
    }
}
