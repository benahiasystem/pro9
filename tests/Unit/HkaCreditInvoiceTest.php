<?php
namespace Tests\Unit;

use App\Services\Fiscal\{HkaCreditInvoice, HkaPayloadBuilder};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HkaCreditInvoiceTest extends TestCase
{
    private function snapshot(): array
    {
        $d=json_decode(file_get_contents(__DIR__.'/../Fixtures/Hka/invoice.json'),true);
        return array_replace($d,['payment_condition_id'=>'02','payments'=>[], 'fiscal_environment'=>'demo',
            'fiscal_emission_mode'=>'digital',HkaCreditInvoice::KEY=>HkaCreditInvoice::VERSION,'exchange_rate_sale'=>'10.12300000']);
    }

    public function test_declaration_uses_final_hka_money_without_mutating_the_sale(): void
    {
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $d=$this->snapshot();$before=$d;
        $h=(new HkaPayloadBuilder)->build($d)['documentoElectronico']['encabezado'];
        self::assertSame('Crédito',$h['identificacionDocumento']['tipoDePago']);
        self::assertSame('01',$h['identificacionDocumento']['tipoDocumento']);
        self::assertSame('01/10/2026',$h['identificacionDocumento']['fechaVencimiento']);
        self::assertSame([['forma'=>'99','descripcion'=>HkaCreditInvoice::DESCRIPTION,'moneda'=>'VES','monto'=>'1174.27',
            'fecha'=>'01/10/2026','tipoCambio'=>'10.12300000']],$h['totales']['formasPago']);
        self::assertSame('116.00',$h['totalesOtraMoneda']['totalAPagar']);
        self::assertSame($before,$d);
        self::assertArrayNotHasKey(HkaCreditInvoice::KEY,$h);
    }

    public function test_ves_declaration_and_original_prepared_payloads_are_independent(): void
    {
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $d=$this->snapshot();$d['currency_type_id']='VES';$d['series']='';$before=$d;
        $h=(new HkaPayloadBuilder)->build($d)['documentoElectronico']['encabezado'];
        self::assertSame('116.00',$h['totales']['formasPago'][0]['monto']);
        self::assertSame('',$h['identificacionDocumento']['serie']);
        self::assertSame($before,$d);
        unset($d[HkaCreditInvoice::KEY]);
        self::assertSame([],(new HkaPayloadBuilder)->build($d)['documentoElectronico']['encabezado']['totales']['formasPago']);
    }

    public function test_unverified_contract_never_adds_a_fiscal_payment(): void
    {
        config(['hka_credit_invoices.demo_no_advance_verified'=>false]);
        $this->expectException(ValidationException::class);
        (new HkaPayloadBuilder)->build($this->snapshot());
    }

    public function test_all_excluded_variants_fail_the_contract_eligibility(): void
    {
        $d=$this->snapshot();self::assertTrue(HkaCreditInvoice::eligible($d));
        $variants=[['document_type_id'=>'07'],['payment_condition_id'=>'01'],['fiscal_environment'=>'production'],
            ['fiscal_emission_mode'=>'machine'],['currency_type_id'=>'EUR'],['total'=>0],['total_prepayment'=>'1'],
            ['has_prepayment'=>true],['prepayments'=>[['number'=>1]]],['total_exportation'=>1],
            ['invoice'=>['operation_type_id'=>'0201']],['fiscal_data'=>['transaction_type_id'=>'99']],
            ['fiscal_data'=>['third_party'=>['name'=>'Other']]],['fiscal_data'=>['special_tax_regime_id'=>1]],
            ['fiscal_data'=>['conditional_data'=>['extra'=>true]]],
            ['fiscal_data'=>['iva_retention_prevision'=>['amount'=>1]]],['fiscal_data'=>['islr_retention_prevision'=>['amount'=>1]]],
            ['received_retentions'=>[['application_status'=>'applied']]],['guarantee_fund'=>['amount'=>1]],
            ['payments'=>[['payment'=>1]]],['payments'=>[['receipt_parent_id'=>1]]],['taxes'=>[['tax_kind'=>'IGTF']]]];
        foreach($variants as $variant) self::assertFalse(HkaCreditInvoice::eligible(array_replace($d,$variant)),json_encode($variant));
        self::assertTrue(HkaCreditInvoice::eligible(array_replace($d,['payments'=>[['payment'=>1,'reversed_at'=>'2026-10-01']]])));
        $d['calculation_snapshot']['values']['total_prepayment']=['n'=>'1','d'=>'10000000000000'];
        self::assertFalse(HkaCreditInvoice::eligible($d),'Exact nonzero prepayments cannot disappear in the stored decimal representation.');
    }

    public function test_a_new_abono_cannot_be_completed_with_a_fictitious_full_credit(): void
    {
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $d=$this->snapshot();$d['payments']=[['payment'=>1]];
        $this->expectException(ValidationException::class);
        HkaCreditInvoice::append(['documentoElectronico'=>['encabezado'=>[]]],$d);
    }

    public function test_contado_keeps_existing_forms_even_with_a_preserved_creation_marker(): void
    {
        config(['hka_credit_invoices.demo_no_advance_verified'=>true]);
        $d=$this->snapshot();$d['payment_condition_id']='01';
        $payload=['documentoElectronico'=>['encabezado'=>['totales'=>['formasPago'=>[['forma'=>'01','monto'=>'116.00']]]]]];
        self::assertSame($payload,HkaCreditInvoice::append($payload,$d));
    }
}
