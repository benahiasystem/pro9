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
        self::assertSame(str_replace('-', '', $d['operation_key']), $p['encabezado']['identificacionDocumento']['transaccionId']);
        self::assertSame('10.123',$p['encabezado']['totalesOtraMoneda']['tipoCambio']);
        self::assertSame($fixture==='igtf-debit' ? '35.23' : '1174.27',$p['encabezado']['totalesOtraMoneda']['totalAPagar']);
        self::assertArrayNotHasKey('token',$p);
        if($fixture==='igtf-debit') self::assertSame([],$p['detallesItems']);
    }
    public function documents(): array { return [['invoice','01'],['credit','02'],['debit','03'],['igtf-debit','03']]; }
    /** @dataProvider paymentConditions */
    public function test_new_payloads_include_frozen_receiver_contacts_and_payment_condition($condition, $label): void
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['payment_condition_id'] = $condition;
        $d['customer']['email'] = ' customer@example.test ';
        $d['customer']['telephone'] = ' 04121234567 ';
        $before = $d;
        $header = (new HkaPayloadBuilder())->build($d)['documentoElectronico']['encabezado'];
        self::assertSame($label, $header['identificacionDocumento']['tipoDePago']);
        self::assertSame(['customer@example.test'], $header['comprador']['correo']);
        self::assertSame(['04121234567'], $header['comprador']['telefono']);
        self::assertSame('No', $header['comprador']['notificar']);
        self::assertSame($before, $d, 'The builder cannot mutate frozen sale data.');
    }
    public function paymentConditions(): array { return [['01', 'Contado'], ['02', 'Crédito']]; }
    public function test_missing_optional_contacts_are_not_invented(): void
    {
        $d = json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'), true);
        $d['customer']['email'] = ' '; $d['customer']['telephone'] = null;
        $header = (new HkaPayloadBuilder())->build($d)['documentoElectronico']['encabezado'];
        self::assertArrayNotHasKey('correo', $header['comprador']);
        self::assertArrayNotHasKey('telefono', $header['comprador']);
        self::assertArrayNotHasKey('tipoDePago', $header['identificacionDocumento']);
        self::assertSame('No', $header['comprador']['notificar']);
    }
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
        self::assertSame('10.12340000',$p['exchange_rate']);self::assertSame(1012.34,$p['payment']);self::assertSame(3.0,$p['tax_amount']);
        self::assertSame(100.0,$p['original_amount']);
    }
    public function test_exempt_and_not_applicable_payments_do_not_collect_igtf(): void
    {
        $exempt=FiscalAmounts::payment(['payment'=>100,'igtf_status'=>'exempt','exemption_reason'=>'Comprobante de exención'], 'USD',10,false,null);
        self::assertSame(0,$exempt['tax_amount']);self::assertSame('Comprobante de exención',$exempt['exemption_reason']);
        $local=FiscalAmounts::payment(['payment'=>100],'VES',1,false,null);self::assertSame('not_applicable',$local['igtf_status']);self::assertSame(0,$local['tax_amount']);
    }
    public function test_rates_needing_more_than_eight_decimals_cannot_be_used_for_conversion(): void
    {
        $this->expectException(ValidationException::class);FiscalAmounts::payment(['original_amount'=>100,'currency_type_id'=>'USD','exchange_rate'=>'0.000000001'],'VES',1,false,null);
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
