<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoicePaymentPreviewCurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.tenant', ['driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'']);
        Schema::connection('tenant')->create('payment_method_types', function ($table) {
            $table->string('id')->primary();
            $table->string('collection_kind');
            // ######## INICIO INTERMEDIACION AUTOMATICA DEL COBRO ########
            $table->boolean('is_active')->default(true);
            // ######## FIN INTERMEDIACION AUTOMATICA DEL COBRO ########
        });
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'01', 'collection_kind'=>'account'],
            ['id'=>'10', 'collection_kind'=>'cash'],
        ]);
    }

    private function preview(array $rows, string $total = '100'): Document
    {
        $document = new Document(['currency_type_id'=>'VES', 'total'=>$total, 'exchange_rate_sale'=>'10']);
        $document->setRelation('payments', new Collection());
        $engine = new class extends Facturalo {
            public function __construct() { $this->company = new Fluent(['igtf_enabled'=>false]); }
        };
        $engine->setPaymentsPreview($document, $rows);
        return $document;
    }

    public function test_preview_closes_dollar_rounding_in_single_and_mixed_cash_and_prints_the_exact_adjustment(): void
    {
        $usd = ['payment_method_type_id'=>'10','currency_type_id'=>'USD','original_amount'=>'5.57',
            'exchange_rate'=>'897.53830000','payment'=>'4999.29'];
        $ves = ['payment_method_type_id'=>'10','currency_type_id'=>'VES','original_amount'=>'1000',
            'exchange_rate'=>'897.53830000','payment'=>'1000'];
        foreach ([[$usd],[$ves,array_replace($usd,['original_amount'=>'4.46','payment'=>'4003.02'])],
            [array_replace($usd,['original_amount'=>'4.46','payment'=>'4003.02']),$ves]] as $rows) {
            $document = $this->preview($rows, '5000');
            $applied = \App\Support\Exact\Decimal::sum($document->payments->map(fn($p)=>$p->exactValue('payment')));
            self::assertTrue($applied->isEqualTo('5000'));
            $payment = $document->payments->firstWhere('currency_type_id','USD');
            $html = view('pdf.partials.invoice_receipt',['row'=>$payment,'document'=>$document])->render();
            self::assertStringContainsString('Ajuste de liquidación VES', $html);
            self::assertStringContainsString(count($rows) === 1 ? '0.711669' : '-3.020818', $html);
        }
    }

    public function test_preview_converts_cash_change_in_the_received_currency(): void
    {
        $document = $this->preview([['payment_method_type_id'=>'10', 'currency_type_id'=>'USD',
            'original_amount'=>'12.00', 'exchange_rate'=>'10', 'payment'=>'120']]);
        $payment = $document->payments->first();
        self::assertSame('USD', $payment->currency_type_id);
        self::assertSame('10', $payment->original_amount);
        self::assertSame('100', $payment->payment);
        self::assertSame('2', $payment->change);
        self::assertSame('100', \App\Support\Exact\Decimal::represent($payment->exactValue('payment')));
        self::assertSame('10', \App\Support\Exact\Decimal::represent($payment->exactValue('original_amount')));
        $payment->payment_method_snapshot = ['description'=>'Efectivo dólares'];
        $html = view('pdf.partials.invoice_receipt', ['row'=>$payment, 'document'=>$document])->render();
        self::assertStringContainsString('Efectivo dólares', $html);
        self::assertStringContainsString('Recibido USD 12.00', $html);
        self::assertStringContainsString('Aplicado VES 100.00', $html);
        self::assertStringContainsString('Vuelto USD 2.00', $html);
    }

    public function test_preview_shows_tax_and_total_after_change(): void
    {
        $document = new Document(['currency_type_id'=>'VES', 'total'=>'100', 'total_taxes'=>'0',
            'total_other_taxes'=>'0', 'exchange_rate_sale'=>'10']);
        $document->setRelation('payments', new Collection());
        $engine = new class extends Facturalo {
            public function __construct() { $this->company = new Fluent(['igtf_enabled'=>true, 'igtf_rate'=>'3']); }
        };
        $engine->setPaymentsPreview($document, [['payment_method_type_id'=>'10','currency_type_id'=>'USD',
            'original_amount'=>'12','exchange_rate'=>'10','payment'=>'120','igtf_status'=>'subject']]);
        $payment = $document->payments->first();
        self::assertSame('0.3', $payment->tax_amount);
        self::assertSame('103', $document->total);
        self::assertSame('3', $document->total_other_taxes);
        self::assertSame('3', $document->taxes->first()->amount);
        $payment->payment_method_snapshot = ['description'=>'Efectivo dólares'];
        $html = view('pdf.partials.invoice_receipt', ['row'=>$payment, 'document'=>$document])->render();
        self::assertStringContainsString('Recibido USD 12.30', $html);
        self::assertStringContainsString('Vuelto USD 2.00', $html);
        self::assertStringContainsString('IGTF USD 0.30', $html);
    }

    public function test_edit_preview_keeps_recorded_tax_when_company_setting_changes(): void
    {
        $receipt=(new \App\Models\Tenant\DocumentPayment())->forceFill(['id'=>7,'document_id'=>3,
            'payment_method_type_id'=>'10','currency_type_id'=>'USD','original_amount'=>'10','exchange_rate'=>'10',
            'payment'=>'100','tax_amount'=>'0.30','igtf_status'=>'subject']);
        $tax=new \App\Models\Tenant\DocumentTax(['tax_kind'=>'IGTF','percentage'=>'3','amount'=>'3']);
        $existing=new Document();$existing->forceFill(['id'=>3]);
        $existing->setRelation('payments',new Collection([$receipt]));
        $existing->setRelation('taxes',new Collection([$tax]));
        $engine = new class extends Facturalo {
            public function __construct() { $this->company = new Fluent(['igtf_enabled'=>false]); }
        };
        foreach ([['100','0'],['103','3']] as [$total,$other]) {
            $preview=new Document(['currency_type_id'=>'VES','total'=>$total,'total_taxes'=>$other,
                'total_other_taxes'=>$other,'exchange_rate_sale'=>'10']);
            $preview->setRelation('payments',new Collection());
            $engine->setPaymentsPreview($preview,[['id'=>7,'payment'=>'1']],$existing);
            self::assertSame('103',$preview->total);
            self::assertSame('3',$preview->total_other_taxes);
            self::assertSame('0.3',$preview->payments->first()->tax_amount);
            self::assertSame('subject',$preview->payments->first()->igtf_status);
            self::assertSame(1,$preview->taxes->count());
        }
    }

    public function test_preview_rejects_bank_excess_even_when_its_id_was_originally_cash(): void
    {
        $this->expectException(ValidationException::class);
        $this->preview([['payment_method_type_id'=>'01', 'currency_type_id'=>'VES',
            'original_amount'=>'120', 'exchange_rate'=>'10', 'payment'=>'120']]);
    }

    public function test_preview_ignores_client_supplied_change(): void
    {
        $document = $this->preview([['payment_method_type_id'=>'01', 'currency_type_id'=>'VES',
            'original_amount'=>'100', 'exchange_rate'=>'10', 'payment'=>'100', 'change'=>'900']]);
        self::assertNull($document->payments->first()->change);
    }

    public function test_invoice_payment_collection_exposes_gross_tender_and_received_change(): void
    {
        $row = new \App\Models\Tenant\DocumentPayment(['date_of_payment'=>'2026-10-26', 'currency_type_id'=>'USD',
            'original_amount'=>'10', 'payment'=>'100', 'change'=>'2',
            'payment_method_snapshot'=>['description'=>'Efectivo histórico']]);
        $row->calculation_snapshot = ['version'=>1,'values'=>['tendered_original_amount'=>['n'=>'12','d'=>'1'],
            'received_change'=>['n'=>'2','d'=>'1']]];
        $row->setRelation('global_payment',null);
        $row->setRelation('payment_file',null);
        $result = (new \App\Http\Resources\Tenant\DocumentPaymentCollection(new Collection([$row])))
            ->toArray(\Illuminate\Http\Request::create('/'));
        self::assertSame('12',$result[0]['tendered_original_amount']);
        self::assertSame('2',$result[0]['received_change']);
        self::assertSame('10',$result[0]['original_amount']);
    }

    public function test_preview_shows_subcent_settlement_adjustment_without_inventing_change(): void
    {
        $document = $this->preview([['payment_method_type_id'=>'10', 'currency_type_id'=>'USD',
            'original_amount'=>'1', 'exchange_rate'=>'3', 'payment'=>'3']], '2.99');
        $payment=$document->payments->first();
        self::assertSame('2.99', \App\Support\Exact\Decimal::represent($payment->exactValue('payment')));
        self::assertSame('1', \App\Support\Exact\Decimal::represent($payment->exactValue('original_amount')));
        self::assertSame('-0.01', \App\Support\Exact\Decimal::represent($payment->exactValue('payment_adjustment')));
        self::assertEmpty($payment->change);
    }

    public function test_preview_ignores_client_supplied_settlement_adjustment(): void
    {
        $document = $this->preview([['payment_method_type_id'=>'10', 'currency_type_id'=>'USD',
            'original_amount'=>'10', 'exchange_rate'=>'10', 'payment'=>'100', '_settlement_payment'=>'1']]);
        self::assertSame('100', \App\Support\Exact\Decimal::represent($document->payments->first()->exactValue('payment')));
    }

    public function test_preview_rejects_change_not_representable_in_received_cents(): void
    {
        $this->expectException(ValidationException::class);
        $this->preview([['payment_method_type_id'=>'10', 'currency_type_id'=>'USD',
            'original_amount'=>'1', 'exchange_rate'=>'3', 'payment'=>'3']], '2.98');
    }

    public function test_sale_note_detail_uses_the_received_change_fraction_and_frozen_method(): void
    {
        $row = (new \App\Models\Tenant\SaleNotePayment())->forceFill([
            'currency_type_id'=>'USD', 'original_amount'=>'10', 'payment'=>'100', 'change'=>'20', 'exchange_rate'=>'10',
            'payment_method_snapshot'=>['description'=>'Efectivo histórico'],
            'calculation_snapshot'=>json_encode(['values'=>['received_change'=>['n'=>'2','d'=>'1']]]),
        ]);
        $document = new Document(['currency_type_id'=>'VES']);
        $html = view('pdf.partials.sale_note_receipt', compact('row','document'))->render();
        self::assertStringContainsString('Efectivo histórico', $html);
        self::assertStringContainsString('Recibido USD 12.00', $html);
        self::assertStringContainsString('Aplicado VES 100.00', $html);
        self::assertStringContainsString('Vuelto USD 2.00', $html);
        self::assertStringNotContainsString('USD 30.00', $html);
    }

    public function test_reused_receipts_are_identified_and_do_not_repeat_original_change(): void
    {
        $origin = ['description'=>'Método original', 'receipt_origin'=>['type'=>'quotation', 'id'=>'4']];
        $document = new Document(['currency_type_id'=>'VES']);
        $row = (new \App\Models\Tenant\SaleNotePayment())->forceFill([
            'currency_type_id'=>'USD', 'original_amount'=>'10', 'payment'=>'100', 'change'=>'0', 'exchange_rate'=>'10',
            'payment_method_snapshot'=>$origin, 'calculation_snapshot'=>json_encode(['values'=>['change'=>['n'=>'0','d'=>'1']]]),
        ]);
        $html = view('pdf.partials.sale_note_receipt', compact('row','document'))->render();
        self::assertStringContainsString('Cobro previo', $html);
        self::assertStringContainsString('Recibido USD 10.00', $html);
        self::assertStringContainsString('Aplicado VES 100.00', $html);
        self::assertStringNotContainsString('Vuelto', $html);
        $row = new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'USD','original_amount'=>'10','payment'=>'100',
            'change'=>'0','exchange_rate'=>'10','payment_method_snapshot'=>$origin]);
        $html = view('pdf.partials.invoice_receipt', compact('row','document'))->render();
        self::assertStringContainsString('Cobro previo', $html);
        self::assertStringContainsString('Recibido USD 10.00', $html);
    }

    public function test_legacy_invoice_without_original_amount_does_not_invent_a_foreign_receipt(): void
    {
        $document = new Document(['currency_type_id'=>'VES']);
        $row = new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'USD', 'payment'=>'100', 'change'=>'20',
            'exchange_rate'=>'10', 'payment_method_snapshot'=>['description'=>'Histórico']]);
        $html = view('pdf.partials.invoice_receipt', compact('row','document'))->render();
        self::assertStringContainsString('Aplicado VES 100.00', $html);
        self::assertStringNotContainsString('Recibido USD', $html);
        self::assertStringNotContainsString('Vuelto USD', $html);
    }

    public function test_ticket_shows_credit_installments_and_frozen_receipt_without_fixed_method(): void
    {
        $document = new Document(['currency_type_id'=>'VES', 'payment_condition_id'=>'02']);
        $fee = new Fluent(['date'=>\Carbon\Carbon::parse('2026-11-26'), 'amount'=>'50',
            'currency_type'=>new Fluent(['symbol'=>'Bs.'])]);
        $document->setRelation('fee', new Collection([$fee]));
        $row = new \App\Models\Tenant\DocumentPayment(['currency_type_id'=>'USD','original_amount'=>'5','payment'=>'50',
            'exchange_rate'=>'10','payment_method_snapshot'=>['description'=>'Banco histórico']]);
        $payments = new Collection([$row]);
        $html = view('pdf.partials.invoice_ticket_payments', compact('document','payments'))->render();
        self::assertStringContainsString('CONDICIÓN: CRÉDITO', $html);
        self::assertStringContainsString('Cuota #1', $html);
        self::assertStringContainsString('Banco histórico', $html);
        self::assertStringContainsString('Recibido USD 5.00', $html);
        self::assertStringContainsString('Aplicado VES 50.00', $html);
        foreach (['default/invoice_ticket_58','nrus/invoice_ticket_58','marca_de_agua/invoice_ticket_50',
            'marca_de_agua/invoice_ticket_58','Plantilla_personalizable/invoice_ticket_58'] as $template) {
            $source=file_get_contents(base_path('app/CoreFacturalo/Templates/pdf/'.$template.'.blade.php'));
            self::assertStringContainsString("pdf.partials.invoice_ticket_payments",$source);
            self::assertStringNotContainsString("PaymentMethodType::where('id', '09')",$source);
        }
    }
}
