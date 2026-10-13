<?php
namespace Tests\Unit;
use App\Services\Fiscal\HkaNotePayments;
use PHPUnit\Framework\TestCase;

class HkaNotePaymentsTest extends TestCase
{
    private function snapshot(string $type): array
    {
        return ['document_type_id'=>$type,'note'=>['creation_key'=>'test'],'payment_condition_id'=>'02','total'=>'116.00',
            'currency_type_id'=>'USD','exchange_rate_sale'=>'50.12345678','date_of_issue'=>'2026-10-13','payments'=>[],'taxes'=>[]];
    }
    public function test_credit_declares_its_amount_without_receipts_and_keeps_original_rate(): void
    {
        $snapshot=$this->snapshot('07');$original=$snapshot;
        $forms=HkaNotePayments::append($snapshot,[]);
        self::assertSame($original,$snapshot);self::assertSame([], $snapshot['payments']);
        self::assertSame('99',$forms[0]['forma']);self::assertSame('116.00',$forms[0]['monto']);
        self::assertSame('50.12345678',$forms[0]['tipoCambio']);self::assertSame('Crédito comercial por nota de crédito',$forms[0]['descripcion']);
    }
    public function test_partial_debit_keeps_real_forms_and_declares_only_outstanding_principal(): void
    {
        $d=$this->snapshot('08');$d['payments']=[['payment'=>'40.00','id'=>1],['payment'=>'10.00','reversed_at'=>'2026-10-13']];
        $real=[['forma'=>'09','monto'=>'40.00']];$forms=HkaNotePayments::append($d,$real);
        self::assertSame($real[0],$forms[0]);self::assertSame('76.00',$forms[1]['monto']);self::assertSame('99',$forms[1]['forma']);
    }
    public function test_received_igtf_is_not_declared_again_as_credit(): void
    {
        $d=$this->snapshot('08');$d['total']='119.48';$d['payments']=[['payment'=>'116.00','id'=>4],['payment'=>'3.48','id'=>5,'receipt_parent_id'=>4]];
        $d['taxes']=[['tax_kind'=>'IGTF','document_payment_id'=>4,'amount'=>'3.48']];
        self::assertSame([['forma'=>'09']],HkaNotePayments::append($d,[['forma'=>'09']]));
    }
    public function test_invoices_and_cash_debits_keep_their_receipt_contract(): void
    {
        $d=$this->snapshot('08');$d['payment_condition_id']='01';self::assertSame([],HkaNotePayments::append($d,[]));
        unset($d['note']);self::assertSame([],HkaNotePayments::append($d,[]));
    }

    public function test_split_currency_rounding_changes_only_the_fiscal_credit_declaration(): void
    {
        $snapshot=$this->snapshot('08');$original=$snapshot;
        $real=['forma'=>'08','moneda'=>'VES','monto'=>'290.72','tipoCambio'=>'50.12345678'];
        $payload=['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>['moneda'=>'VES'],
            'totales'=>['totalAPagar'=>'581.43','formasPago'=>[$real,['forma'=>'99','moneda'=>'USD','monto'=>'5.80','tipoCambio'=>'50.12345678','descripcion'=>'Crédito comercial pendiente de cobro']]]]]];
        $result=HkaNotePayments::reconcile($payload,$snapshot);$forms=$result['documentoElectronico']['encabezado']['totales']['formasPago'];
        self::assertSame($real,$forms[0]);self::assertSame('VES',$forms[1]['moneda']);self::assertSame('290.71',$forms[1]['monto']);self::assertSame($original,$snapshot);
    }
}
