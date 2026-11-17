<?php

namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentPayment;
use App\Services\Fiscal\InvoicePaperPdfData;
use App\Support\Exact\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Tests\TestCase;

class InvoicePaperPdfDataTest extends TestCase
{
    private function invoice(array $values = []): Fluent
    {
        return new Fluent(array_replace(['series'=>'', 'number'=>34, 'currency_type_id'=>'VES',
            'control_number'=>null, 'exchange_rate_sale'=>'40.12345678', 'payment_condition_id'=>'02',
            'total'=>'116', 'subtotal'=>'116', 'total_value'=>'100', 'total_igv'=>'16',
            'payments'=>collect(), 'taxes'=>collect(), 'received_retentions'=>collect(),
            'fiscal_data'=>null, 'guarantee_fund'=>null, 'currency_totals'=>null], $values));
    }

    private function receipt(array $values = []): Fluent
    {
        return new Fluent(array_replace(['id'=>1, 'currency_type_id'=>'VES', 'original_amount'=>'116',
            'tax_amount'=>'0', 'payment'=>'116', 'change'=>'0', 'exchange_rate'=>'40.12345678',
            'payment_method_snapshot'=>[], 'calculation_snapshot'=>null, 'change_disbursements'=>collect(),
            'reversed_at'=>null, 'receipt_parent_id'=>null], $values));
    }

    private function present($document): array
    {
        return InvoicePaperPdfData::forDocument($document, new Fluent(['number'=>'J087897598']));
    }

    public function test_identity_and_rate_are_presentation_only(): void
    {
        $document=$this->invoice();
        $data=$this->present($document);
        self::assertSame('J-08789759-8', $data['rif']);
        self::assertSame('00000034', $data['number']);
        self::assertSame('No asignado', $data['control']);
        self::assertSame('40.12345678', $data['rate']);
        self::assertSame('sin formato', InvoicePaperPdfData::rif('sin formato'));
        $document->series='AB-CD'; $document->control_number='00-00000123'; $document->exchange_rate_sale=null;
        $data=$this->present($document);
        self::assertSame('AB-CD-00000034', $data['number']);
        self::assertSame('00-00000123', $data['control']);
        self::assertNull($data['rate']);
        self::assertSame('AB-CD', $document->series);
    }

    public function test_received_tender_includes_igtf_once_and_change_uses_actual_currencies(): void
    {
        $payment=$this->receipt(['currency_type_id'=>'USD','original_amount'=>'60','tax_amount'=>'1.80',
            'payment'=>'60','change'=>'8.20', 'calculation_snapshot'=>D::snapshot([
                'tendered_original_amount'=>'68.20', 'received_change'=>'8.20']),
            'change_disbursements'=>collect([new Fluent(['currency_type_id'=>'USD','original_amount'=>'2','reversed_at'=>null]),
                new Fluent(['currency_type_id'=>'VES','original_amount'=>'248.76367933','reversed_at'=>null]),
                new Fluent(['currency_type_id'=>'VES','original_amount'=>'99','reversed_at'=>'2026-11-01'])])]);
        $document=$this->invoice(['currency_type_id'=>'USD','total'=>'61.80','subtotal'=>'60',
            'payments'=>collect([$payment, $this->receipt(['id'=>2,'receipt_parent_id'=>1,'payment'=>'1.80']),
                $this->receipt(['id'=>3,'reversed_at'=>'2026-11-01'])])]);
        $data=$this->present($document);
        self::assertCount(1, $data['receipts']);
        self::assertSame('70.00', $data['receipts'][0]['amount']);
        self::assertSame('0.00', $data['balance']);
        self::assertSame(['USD','VES'], array_column($data['changes'], 'currency'));
        self::assertSame(['2.00','248.76'], array_column($data['changes'], 'amount'));
        self::assertFalse($data['changes'][0]['equivalent']);
        $document->payments=collect([$payment]);
        self::assertSame('0.00', $this->present($document)['balance'], 'Preview simulates the tax application, not another receipt.');
        $payment->calculation_snapshot=D::snapshot(['total_tendered'=>'70','tendered_original_amount'=>'68.20','received_change'=>'8.20']);
        self::assertSame('70.00', $this->present($document)['receipts'][0]['amount']);
        $payment->calculation_snapshot=null;
        self::assertSame('61.80', $this->present($document)['receipts'][0]['amount']);
        self::assertTrue($this->present($document)['receipts'][0]['net']);
        $payment->change='0';
        self::assertFalse($this->present($document)['receipts'][0]['net']);
        $payment->original_amount=null;
        self::assertNull($this->present($document)['receipts'][0]['amount']);
    }

    public function test_active_forecasts_remain_informative_and_percentages_are_not_rounded(): void
    {
        $retention=new Fluent(['tax_kind'=>'ISLR','application_status'=>'applied','voucher_number'=>'ISLR-1',
            'voucher_date'=>'2026-11-01','currency_type_id'=>'VES','base'=>'100','percentage'=>'4.125',
            'subtrahend'=>'0','amount'=>'4.125','applied_amount'=>'4.125','reversed_at'=>null,
            'fiscal_context'=>['prevision'=>['eligible'=>true,'amount'=>'100']]]);
        $document=$this->invoice(['received_retentions'=>collect([$retention]),
            'fiscal_data'=>new Fluent(['islr_retention_prevision'=>['eligible'=>true,'amount'=>'9','percentage'=>'4.125'],
                'iva_retention_prevision'=>['eligible'=>false,'amount'=>'12','percentage'=>'75']])]);
        $data=$this->present($document);
        self::assertCount(1, $data['forecasts']);
        self::assertSame('4.125', $data['retentions'][0]['percentage']);
        self::assertSame('4.125', $data['forecasts'][0]['percentage']);
        self::assertSame('4.88', $data['forecasts'][0]['remaining']);
        self::assertSame('111.88', $data['balance'], 'Forecast must not reduce the real balance.');
        $document->fiscal_data=null;
        self::assertSame([], $this->present($document)['forecasts'], 'Never reactivate a closed historical forecast.');
        $retention->reversal_status='pending';
        self::assertCount(1, $this->present($document)['retentions']);
        $retention->application_status='reversed'; $retention->applied_amount='0'; $retention->reversed_at='2026-11-02';
        self::assertSame([], $this->present($document)['retentions']);
    }

    public function test_igtf_display_reconciles_fractional_tax_rows(): void
    {
        $document=$this->invoice(['total'=>'116.01','subtotal'=>'116','total_other_taxes'=>'0.01',
            'taxes'=>collect([new Fluent(['tax_kind'=>'IGTF','percentage'=>'3','amount'=>'0.005']),
                new Fluent(['tax_kind'=>'IGTF','percentage'=>'4','amount'=>'0.005'])])]);
        $data=$this->present($document);
        self::assertSame(['0.01','0.00'], array_column($data['igtf'],'amount'));
        self::assertSame('0.01', D::format(D::sum(array_column($data['igtf'],'amount'))));
        $document->taxes->last()->percentage='3';
        self::assertSame([['percentage'=>'3.00','amount'=>'0.01']], $this->present($document)['igtf']);
    }

    public function test_usd_preview_projects_ves_from_the_draft_not_an_older_snapshot(): void
    {
        $old=new Fluent(['iva'=>'640','total'=>'4000']);
        $document=$this->invoice(['currency_type_id'=>'USD','total'=>'200','total_igv'=>'32',
            'exchange_rate_sale'=>'50.00000000','currency_totals'=>$old]);
        $data=$this->present($document);
        self::assertSame('1,600.00',D::format(D::read($data['ves_totals'],'iva')));
        self::assertSame('10,000.00',D::format(D::read($data['ves_totals'],'total')));
        self::assertSame('4000',$old->total);
        $document->exchange_rate_sale=null;
        self::assertNull($this->present($document)['ves_totals']);
    }

    public function test_persisted_invoice_links_later_igtf_to_its_real_collections_only(): void
    {
        config()->set('database.connections.tenant', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        \Tests\Support\InvoiceA4Ledger::create();
        $db=DB::connection('tenant');
        foreach ([1=>'01',2=>'08',3=>'08',4=>'08',5=>'08',6=>'07'] as $id=>$type) $db->table('documents')->insert([
            'id'=>$id,'document_type_id'=>$type,'state_type_id'=>$id===6 ? '11' : '01','series'=>'','number'=>$id,
            'control_number'=>'00-0000000'.$id,'currency_type_id'=>'VES','customer_id'=>1,'establishment_id'=>1,'total'=>'116','fiscal_emission_mode'=>'digital']);
        foreach ([2=>'ORD',3=>'IGTF',4=>'IGTF',5=>'IGTF'] as $id=>$kind) $db->table('notes')->insert([
            'document_id'=>$id,'affected_document_id'=>1,'creation_key'=>'note-'.$id,'note_debit_type_id'=>$kind]);
        foreach ([10=>1,11=>2,12=>99] as $id=>$origin) $db->table('document_payments')->insert([
            'id'=>$id,'document_id'=>$origin,'payment'=>'50','original_amount'=>'50','tax_amount'=>'1.50',
            'currency_type_id'=>'VES','exchange_rate'=>'40.12345678','change'=>'0']);
        foreach ([3=>10,4=>11,5=>12] as $nd=>$payment) $db->table('document_taxes')->insert([
            'id'=>$nd,'document_id'=>$nd,'document_payment_id'=>$payment,'tax_kind'=>'IGTF','percentage'=>'3','amount'=>'1.50']);
        $db->table('document_emissions')->insert(['document_id'=>3,'status'=>'uncertain']);
        $db->table('document_note_adjustments')->insert(['id'=>1,'document_id'=>6,'affected_document_id'=>1,
            'kind'=>'IGTF','tax_id'=>3,'amount'=>'1.50','status'=>'blocked']);
        $db->table('document_note_refunds')->insert(['id'=>1,'document_id'=>6,'affected_document_id'=>1,
            'adjustment_id'=>1,'component'=>'IGTF','original_amount'=>'1.50','amount'=>'1.50','currency_type_id'=>'VES','reversed_at'=>'2026-11-02']);
        $document=new Document(['id'=>1,'document_type_id'=>'01','customer_id'=>1,'establishment_id'=>1,
            'currency_type_id'=>'VES','total'=>'116','payment_condition_id'=>'02','number'=>1]);
        $document->forceFill(['id'=>1]);
        $document->exists=true;
        foreach (['payments','taxes','received_retentions'] as $relation) $document->setRelation($relation, new \Illuminate\Database\Eloquent\Collection());
        foreach (['fiscal_data','guarantee_fund','currency_totals'] as $relation) $document->setRelation($relation,null);
        $data=$this->present($document);
        self::assertSame('66.00', $data['balance']);
        $preview=new Document(['document_type_id'=>'01','currency_type_id'=>'VES','total'=>'120','payment_condition_id'=>'02']);
        $preview->setRelation('invoice_source_document',$document);
        $previewData=$this->present($preview);
        self::assertSame('70.00',$previewData['balance']);
        self::assertCount(2,$previewData['later_igtf']);
        self::assertCount(2, $data['later_igtf']);
        self::assertSame('Por conciliar', $data['later_igtf'][0]['status']);
        $db->table('documents')->where('id',3)->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('document_emissions')->where('document_id',3)->update(['status'=>'not_requested']);
        self::assertSame('Registrada localmente',$this->present($document)['later_igtf'][0]['status']);
        self::assertSame('Factura', $data['later_igtf'][0]['origin']);
        self::assertSame('ND 00000002', $data['later_igtf'][1]['origin']);
        self::assertSame('Bloqueado', $data['later_igtf'][0]['adjustments'][0]['status']);
        self::assertTrue($data['later_igtf'][0]['adjustments'][0]['refunds'][0]['reversed']);
        $db->table('document_note_adjustments')->where('id',1)->update(['status'=>'confirmed']);
        self::assertSame('Confirmado', $this->present($document)['later_igtf'][0]['adjustments'][0]['status']);
        $db->table('document_note_adjustments')->where('id',1)->update(['reversed_at'=>'2026-11-02']);
        self::assertSame('Revertido', $this->present($document)['later_igtf'][0]['adjustments'][0]['status']);
        $db->table('documents')->where('id',3)->update(['state_type_id'=>'11']);
        self::assertCount(1, $this->present($document)['later_igtf']);
        $db->table('document_payments')->where('id',11)->update(['reversed_at'=>'2026-11-02']);
        self::assertSame([], $this->present($document)['later_igtf']);
    }
}
