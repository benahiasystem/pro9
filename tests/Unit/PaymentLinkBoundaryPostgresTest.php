<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Http\Controllers\PaymentLinkController;
use Modules\Payment\Http\Requests\PaymentLinkRequest;
use Modules\Payment\Models\PaymentLink;

/** @group postgres-integration */
final class PaymentLinkBoundaryPostgresTest extends \PHPUnit\Framework\TestCase
{
    private PrepaymentConsumersPostgresTest $fixture;
    private $db;
    protected function setUp(): void
    {
        $this->fixture=new PrepaymentConsumersPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
        $this->db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
        app()->instance('url',new \Illuminate\Routing\UrlGenerator(new \Illuminate\Routing\RouteCollection(),Request::create('https://temporary.invalid')));
        $this->db->table('payment_link_types')->insertOrIgnore(['id'=>'01','description'=>'Prueba temporal']);
    }
    protected function tearDown(): void
    {
        try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally{parent::tearDown();}
    }
    private function document(string $total='100',bool $tiny=false): Document
    {
        $input=(new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,$total,false);
        if($tiny){$input['items'][0]['quantity']='0.005';$input['items'][0]['quantity_input']=['amount'=>'0.014999999999','unit_price'=>'3'];}
        return (new Facturalo())->save($input)->getDocument()->fresh();
    }
    private function request(Document $document,string $payment): PaymentLinkRequest
    {
        return PaymentLinkRequest::create('','POST',['with_customer'=>true,'customer_id'=>$document->customer_id,
            'documents'=>[['instance_type'=>'document','document_id'=>$document->id,'payment'=>$payment]],'total'=>$payment]);
    }
    private function rows(Document $document,string $payment): array
    {
        return (new \ReflectionMethod(PaymentLinkController::class,'getInputDocuments'))->invoke(new PaymentLinkController(),
            $this->request($document,$payment),Person::findOrFail($document->customer_id));
    }
    private function receipt(Document $document,string $amount,bool $reversed=false): void
    {
        $this->db->table('document_payments')->insert(['document_id'=>$document->id,'date_of_payment'=>'2026-11-12',
            'payment_method_type_id'=>'01','payment'=>$amount,'currency_type_id'=>'VES','exchange_rate'=>'1','original_amount'=>$amount,
            'payment_method_snapshot'=>'{}','operation_key'=>(string)\Illuminate\Support\Str::uuid(),
            'reversed_at'=>$reversed?'2026-11-12 10:00:00':null,
            'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount,'original_amount'=>$amount,'exchange_rate'=>'1','tax_amount'=>'0']),JSON_THROW_ON_ERROR)]);
    }
    public function test_document_selection_retains_a_large_partial_payment(): void
    {
        $document=$this->document('9007199254740993.03');
        $rows=$this->rows($document,'9007199254740993.02');
        self::assertCount(1,$rows);self::assertTrue(D::of($rows[0]['payment'])->isEqualTo('9007199254740993.02'));
    }
    public function test_document_selection_rejects_one_cent_above_the_large_balance(): void
    {
        $document=$this->document('9007199254740993.03');
        $this->expectException(\Exception::class);$this->expectExceptionMessage('supera el saldo pendiente');
        $this->rows($document,'9007199254740993.04');
    }
    public function test_search_keeps_an_invoice_whose_only_payment_was_reversed(): void
    {
        $document=$this->document();$this->receipt($document,'100',true);
        $rows=(new PaymentLinkController())->searchDocuments(Request::create('','GET',['customer_id'=>$document->customer_id]))['documents'];
        $row=collect($rows)->firstWhere('id',$document->id);
        self::assertNotNull($row);self::assertTrue(D::of($row['pending'])->isEqualTo('100'));
    }
    public function test_search_excludes_an_original_balance_that_settles_to_zero_cents(): void
    {
        $document=$this->document('1',true);
        self::assertTrue($document->exactBalance()->isZero());
        $rows=(new PaymentLinkController())->searchDocuments(Request::create('','GET',['customer_id'=>$document->customer_id]))['documents'];
        self::assertNull(collect($rows)->firstWhere('id',$document->id));
    }
    public function test_search_applies_its_twenty_result_limit_after_exact_balance_filtering(): void
    {
        $target=$this->document('20');$this->db->table('documents')->where('id',$target->id)->update(['created_at'=>'2026-01-01 00:00:00']);
        for($i=0;$i<21;$i++){
            $settled=$this->document('1',true);
            $this->db->table('documents')->where('id',$settled->id)->update(['created_at'=>'2026-11-12 12:00:00']);
        }
        $rows=(new PaymentLinkController())->searchDocuments(Request::create('','GET',['customer_id'=>$target->customer_id]))['documents'];
        self::assertCount(1,$rows);self::assertSame($target->id,collect($rows)->first()['id']);
    }
    public function test_store_preserves_manual_large_total_in_parent_and_pending_detail(): void
    {
        $document=$this->document('1');$request=$this->request($document,'9007199254740993.03');$request->merge(['documents'=>[]]);
        $result=(new PaymentLinkController())->store($request);self::assertTrue($result['success']);
        $link=PaymentLink::findOrFail($result['data']['id']);
        self::assertTrue($link->exactValue('total')->isEqualTo('9007199254740993.03'));
        self::assertTrue($link->payments()->sole()->exactValue('total')->isEqualTo('9007199254740993.03'));
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_ves_invoice_keeps_the_sign_of_an_adjustment_below_column_precision(): void
    {
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
        foreach ([['10000000000001', '10000000000000', '-1'], ['9999999999999', '10000000000000', '1']] as [$numerator, $denominator, $sign]) {
            $input = (new \ReflectionMethod($this->fixture, 'input'))->invoke($this->fixture, '1', false);
            $input['items'][0]['price_input'] = [
                'amount' => ['n' => $numerator, 'd' => $denominator], 'currency_type_id' => 'VES', 'includes_iva' => true,
            ];
            $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
            self::assertSame('VES', $invoice->currency_type_id);
            self::assertSame('0', $invoice->settlement_adjustment);
            self::assertSame(['n' => $sign, 'd' => $denominator], D::fraction($invoice->exactValue('settlement_adjustment')));
            self::assertTrue($invoice->exactValue('total_payable')->isEqualTo('1'));
            self::assertTrue($invoice->exactBalance()->isEqualTo('1'));
        }
    }

    public function test_store_and_edit_resources_keep_original_document_settlement_and_link_amount(): void
    {
        $document=$this->document('9007199254740993.03');
        $result=(new PaymentLinkController())->store($this->request($document,'9007199254740993.02'));
        self::assertTrue($result['success']);$link=PaymentLink::findOrFail($result['data']['id']);
        self::assertTrue($link->exactValue('total')->isEqualTo('9007199254740993.02'));
        $row=$link->getDocumentsResource()[0];self::assertTrue(D::of($row['pending'])->isEqualTo('9007199254740993.03'));
        self::assertTrue(D::of($row['payment'])->isEqualTo('9007199254740993.02'));
        self::assertTrue(D::of($row['total'])->isEqualTo('9007199254740993.03'));
        self::assertTrue(D::of($link->getPublicDocumentsResource()[0]['total'])->isEqualTo('9007199254740993.02'));
    }
    public function test_invoice_selection_never_converts_an_invalid_row_into_a_manual_link(): void
    {
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
        $this->db->select("SELECT setval(pg_get_serial_sequence('documents', 'id'), 9007199254740992)");
        $document = $this->document();
        $key = (string) $document->id;
        self::assertSame('9007199254740993', $key);
        $controller = new PaymentLinkController();
        $line = ['instance_type' => 'document', 'document_id' => $key, 'payment' => '30.25'];
        foreach ([
            ['documents.0.document_id', [array_replace($line, ['document_id' => $key.'x'])]],
            ['documents.1.document_id', [$line, array_replace($line, ['document_id' => '9223372036854775808'])]],
            ['documents.0.document_id', [array_replace($line, ['document_id' => '0'.$key])]],
            ['documents.0.document_id', [array_replace($line, ['document_id' => 'abc'])]],
            ['documents.0.document_id', [array_diff_key($line, ['document_id' => true])]],
            ['documents.0', ['abc']],
            ['documents', 'abc'],
        ] as [$field, $rows]) {
            $request = $this->request($document, '30.25');
            $request->merge(['documents' => $rows]);
            try {
                $controller->store($request);
                self::fail('El enlace debe rechazar la fila inválida.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey($field, $error->errors());
            }
            self::assertSame(0, $this->db->transactionLevel());
            self::assertSame(0, PaymentLink::query()->count());
            self::assertSame(0, $this->db->table('payment_link_payments')->count());
        }

        $request = $this->request($document, '30.25');
        $request->merge(['documents' => [$line]]);
        $stored = $controller->store($request);
        $link = PaymentLink::findOrFail($stored['data']['id']);
        self::assertSame($key, (string) $link->payments()->sole()->record_id);
        self::assertTrue($link->exactValue('total')->isEqualTo('30.25'));
    }

    public function test_link_from_invoice_rejects_bad_keys_and_keeps_missing_document_semantics(): void
    {
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
        $controller = new PaymentLinkController();
        foreach (['9223372036854775808', 'abc', null, ['1']] as $id) {
            try {
                $controller->storeFromDocument(Request::create('', 'POST', ['document_id' => $id]));
                self::fail('La clave inválida debe generar un error de validación.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('document_id', $error->errors());
            }
        }

        self::assertSame(0, PaymentLink::query()->count());
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->storeFromDocument(Request::create('', 'POST', ['document_id' => '9223372036854775807']));
    }

    public function test_public_checkout_retains_the_link_total_without_trusting_the_url_amount(): void
    {
        $apply=false;$total=(new PaymentLinkController())->getTotal(['has_payment'=>false,'total'=>'9007199254740993.03'],'0.01',$apply);
        self::assertFalse($apply);self::assertTrue(D::of($total)->isEqualTo('9007199254740993.03'));
    }
    public function test_public_checkout_preserves_historical_conversion_and_cents(): void
    {
        $apply=false;$total=(new PaymentLinkController())->getTotal(['has_payment'=>true,'total'=>'9007199254740993.03',
            'associated_record_payment'=>['currency_type_id'=>'USD','exchange_rate_sale'=>'1.00000001']],'0.01',$apply);
        self::assertTrue($apply);self::assertTrue(D::of($total)->isEqualTo(D::represent(D::of('9007199254740993.03')->multipliedBy('1.00000001'),2)));
    }
}
