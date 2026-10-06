<?php
namespace Tests\Unit;
use App\Services\Fiscal\{HkaPayloadBuilder,FiscalAmounts};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
class HkaPayloadBuilderTest extends TestCase
{
    /** @dataProvider documents */
    public function test_payloads_match_the_pinned_contract($fixture,$type): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/'.$fixture.'.json'),true);
        $p=(new HkaPayloadBuilder())->build($d)['documentoElectronico'];
        self::assertSame($type,$p['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('VES',$p['encabezado']['totalesOtraMoneda']['moneda']);
        self::assertSame('10.123',$p['encabezado']['totalesOtraMoneda']['tipoCambio']);
        self::assertSame($fixture==='igtf-debit' ? '35.23' : '1174.27',$p['encabezado']['totalesOtraMoneda']['totalAPagar']);
        self::assertArrayNotHasKey('token',$p);
        if($fixture==='igtf-debit') self::assertSame([],$p['detallesItems']);
    }
    public function documents(): array { return [['invoice','01'],['credit','02'],['debit','03'],['igtf-debit','03']]; }
    public function test_missing_identity_mapping_blocks_preparation(): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);unset($d['customer']['hka_identity_code']);
        $this->expectException(ValidationException::class);(new HkaPayloadBuilder())->build($d);
    }
    public function test_exports_are_not_enabled_by_catalog_presence(): void
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);$d['invoice']['operation_type_id']='0201';
        $this->expectException(ValidationException::class);(new HkaPayloadBuilder())->build($d);
    }
    public function test_payment_currency_and_tax_use_received_amount_and_current_precision(): void
    {
        $p=FiscalAmounts::payment(['payment'=>999,'original_amount'=>100,'currency_type_id'=>'USD','exchange_rate'=>10.1234,'igtf_status'=>'subject'], 'VES',1,true,3);
        self::assertSame(10.123,$p['exchange_rate']);self::assertSame(1012.30,$p['payment']);self::assertSame(3.0,$p['tax_amount']);
        self::assertSame(100.0,$p['original_amount']);
    }
    public function test_exempt_and_not_applicable_payments_do_not_collect_igtf(): void
    {
        $exempt=FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'exempt','exemption_reason'=>'Comprobante de exención'], 'USD',10,false,null);
        self::assertSame(0,$exempt['tax_amount']);self::assertSame('Comprobante de exención',$exempt['exemption_reason']);
        $local=FiscalAmounts::payment(['payment'=>100],'VES',1,false,null);self::assertSame('not_applicable',$local['igtf_status']);self::assertSame(0,$local['tax_amount']);
    }
    public function test_rates_below_current_precision_cannot_be_used_for_conversion(): void
    {
        $this->expectException(ValidationException::class);FiscalAmounts::payment(['original_amount'=>100,'currency_type_id'=>'USD','exchange_rate'=>0.0004],'VES',1,false,null);
    }
    public function test_disabled_igtf_does_not_use_hka_catalog_rate(): void
    {
        $this->expectException(ValidationException::class);
        FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'subject'],'USD',10,false,null);
    }
    public function test_exempt_payment_requires_reason(): void
    {
        $this->expectException(ValidationException::class);FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'exempt'],'USD',10,true,3);
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
