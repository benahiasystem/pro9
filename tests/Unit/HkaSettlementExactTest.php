<?php

namespace Tests\Unit;

use App\Services\Fiscal\{HkaDomesticInvoicePolicy, HkaIslrRetentionCapability, HkaIvaRetentionCapability, HkaNoteCapability, HkaSettlement};
use App\Support\Exact\Decimal as D;
use Illuminate\Validation\ValidationException;

final class HkaSettlementExactTest extends \Tests\TestCase
{
    public static function settlements(): array
    {
        $third=D::of(1)->dividedBy(3);
        return [
            'large shortage'=>['9007199254740993','9007199254740992',false],
            'large paid'=>['9007199254740993.01','9007199254740993.01',true],
            'inclusive tolerance'=>['100','99.995',true],
            'next position outside tolerance'=>['100','99.994999999999',false],
            'overpayment tolerance'=>['100','100.005',true],
            'overpayment outside tolerance'=>['100','100.005000000001',false],
            'periodic paid'=>[D::fraction($third),D::fraction($third),true],
            'periodic at tolerance'=>[D::fraction($third),D::fraction($third->minus('0.005')),true],
            'subprecision shortage'=>[D::fraction($third),D::fraction($third->minus('0.0050000000001')),false],
        ];
    }

    /** @dataProvider settlements */
    public function test_invoice_and_debit_policies_use_the_same_exact_real_settlement($total,$paid,bool $complete): void
    {
        config(['hka_notes.contract_probe'=>false,'hka_notes.demo_non_cash_notes_verified'=>false]);
        $snapshot=['document_type_id'=>'01','invoice'=>['operation_type_id'=>'0101'],'payment_condition_id'=>'01','currency_type_id'=>'VES',
            'fiscal_emission_mode'=>'digital','fiscal_environment'=>'demo','source_module'=>'WEB','total'=>D::represent($total),'total_exportation'=>'0',
            'calculation_snapshot'=>D::snapshot(['total'=>D::of($total)]),
            'payments'=>[['payment'=>D::represent($paid),'calculation_snapshot'=>D::snapshot(['payment'=>D::of($paid)]),'receipt_parent_id'=>null,'reversed_at'=>null]]];
        $before=$snapshot;self::assertSame($complete,HkaSettlement::complete($snapshot));
        try {HkaDomesticInvoicePolicy::assertSnapshot($snapshot);self::assertTrue($complete);}
        catch (ValidationException $error) {self::assertFalse($complete);self::assertArrayHasKey('payments',$error->errors());}
        $note=array_replace($snapshot,['document_type_id'=>'08','note'=>['creation_key'=>'private-test-note']]);
        self::assertSame($complete ? null : HkaNoteCapability::DEBIT_REASON,HkaNoteCapability::snapshotReason($note));
        self::assertSame($before,$snapshot);
    }

    public function test_reversed_receipts_and_child_only_rows_cannot_satisfy_real_payment(): void
    {
        foreach ([['reversed_at'=>'2026-11-17'],['receipt_parent_id'=>1]] as $extra) {
            self::assertFalse(HkaSettlement::complete(['total'=>'1','payments'=>[array_replace(['payment'=>'1'],$extra)]]));
        }
        self::assertFalse(HkaSettlement::complete(['total'=>'0','payments'=>[]]));
        self::assertTrue(HkaSettlement::complete(['total'=>'0.3','payments'=>[['payment'=>'0.1'],['payment'=>'0.2']]]));
    }

    public function test_retention_capabilities_compare_exact_receipts_at_the_inclusive_tolerance(): void
    {
        foreach (['IVA' => HkaIvaRetentionCapability::class, 'ISLR' => HkaIslrRetentionCapability::class] as $tax => $policy) {
            $flag = strtolower($tax).'_retention.demo_net_collection_verified';
            config([$flag => false]);
            $snapshot = [
                'document_type_id' => '01', 'total' => '1.005',
                'received_retentions' => [['tax_kind' => $tax, 'application_status' => 'pending_validation']],
                'payments' => [['payment' => '1'], ['payment' => '1', 'reversed_at' => '2026-11-18']],
            ];
            self::assertFalse($policy::blockedSnapshot($snapshot), 'Exactamente medio céntimo aún se admite.');
            $snapshot['payments'][0]['calculation_snapshot'] = D::snapshot(['payment' => '0.9999999999999']);
            self::assertTrue($policy::blockedSnapshot($snapshot), 'Una fracción fuera de tolerancia bloquea aunque redondee al mismo importe.');
            $snapshot['payments'][0]['calculation_snapshot'] = D::snapshot(['payment' => '1.02']);
            self::assertFalse($policy::blockedSnapshot($snapshot), 'La capacidad de retención sólo verifica faltantes.');
            $snapshot['payments'][0]['calculation_snapshot'] = D::snapshot(['payment' => '0']);
            $snapshot['received_retentions'][0]['application_status'] = 'reversed';
            self::assertFalse($policy::blockedSnapshot($snapshot));
            $snapshot['received_retentions'][0]['application_status'] = 'applied';
            self::assertTrue($policy::blockedSnapshot($snapshot));
            config([$flag => true]);
            self::assertFalse($policy::blockedSnapshot($snapshot));
        }
    }

    public function test_persisted_postgresql_invoice_and_debit_keep_the_exact_settlement_boundary(): void
    {
        if (getenv('SAB_POSTGRES_TESTS')!=='1') $this->markTestSkipped('Requiere PostgreSQL temporal aislado.');
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): array {parent::setUp();return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this,true);}
            public function stop(): void {parent::tearDown();}
        };
        try {
            [$db,$app,$input]=$fixture->start();\Tests\Support\PostgresTestEnvironment::assertServer($db);
            $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);$db->table('configurations')->update(['auto_send_hka'=>false]);
            $app->instance(\Illuminate\Http\Client\Factory::class,(new \Illuminate\Http\Client\Factory)->fake(static fn()=>throw new \RuntimeException('No se permite transporte HKA real.')));
            $invoice=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
            $noteInput=(new \ReflectionMethod($fixture,'noteInput'))->invoke($fixture,$input,$invoice,'08','02',[
                ['affected_document_item_id'=>$invoice->items->first()->id,'adjustment_amount'=>'11.60']]);
            $note=(new \App\CoreFacturalo\Facturalo)->forWebDocuments()->save($noteInput)->getDocument()->fresh();
            config(['hka_notes.contract_probe'=>false,'hka_notes.demo_non_cash_notes_verified'=>false]);
            $prototype=(array)$db->table('document_payments')->where('document_id',$invoice->id)->first();unset($prototype['id']);
            $notePayment=$db->table('document_payments')->insertGetId(array_replace($prototype,['document_id'=>$note->id,
                'payment'=>'1','original_amount'=>'1','calculation_snapshot'=>json_encode(D::snapshot(['payment'=>D::of(1)]),JSON_THROW_ON_ERROR),
                'operation_key'=>(string)\Illuminate\Support\Str::uuid()]));
            $paymentIds=[$invoice->id=>$invoice->payments()->firstOrFail()->id,$note->id=>$notePayment];
            $cases=[['9007199254740993','9007199254740992',false],['100','99.995',true],['100','99.994999999999',false],
                ['100',D::fraction(D::of('99.995')->minus('0.0000000000001')),false]];
            foreach ($cases as [$total,$paid,$complete]) {
                foreach ([$invoice,$note] as $source) {
                    $db->table('documents')->where('id',$source->id)->update(['total'=>$total,'total_payable'=>$total,'fiscal_emission_mode'=>'digital','fiscal_environment'=>'demo',
                        'payment_condition_id'=>'01','calculation_snapshot'=>json_encode(D::snapshot(['total'=>D::of($total),'total_payable'=>D::of($total)]),JSON_THROW_ON_ERROR)]);
                    $db->table('document_payments')->where('id',$paymentIds[$source->id])->update(['payment'=>D::represent($paid),
                        'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>D::of($paid)]),JSON_THROW_ON_ERROR)]);
                }
                $invoice=$invoice->fresh();$note=$note->fresh();
                self::assertSame(D::fraction(D::of($total)->minus(D::of($paid))),D::fraction($invoice->exactBalance()));
                $before=$db->table('document_payments')->orderBy('id')->get()->toArray();
                self::assertSame($complete ? null : HkaNoteCapability::DEBIT_REASON,HkaNoteCapability::reason($note));
                try {HkaDomesticInvoicePolicy::assertPaid($invoice,true);self::assertTrue($complete);}
                catch (ValidationException $error) {self::assertFalse($complete);self::assertArrayHasKey('payments',$error->errors());}
                self::assertEquals($before,$db->table('document_payments')->orderBy('id')->get()->toArray());
            }
        } finally {$fixture->stop();}
    }
}
