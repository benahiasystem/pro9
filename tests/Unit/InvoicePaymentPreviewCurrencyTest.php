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

    public function test_preview_rejects_change_not_representable_in_received_cents(): void
    {
        $this->expectException(ValidationException::class);
        $this->preview([['payment_method_type_id'=>'10', 'currency_type_id'=>'USD',
            'original_amount'=>'1', 'exchange_rate'=>'3', 'payment'=>'3']], '2.99');
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
