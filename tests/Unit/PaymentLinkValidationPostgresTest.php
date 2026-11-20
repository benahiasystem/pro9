<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use App\Support\Exact\Decimal as D;
use Illuminate\Http\Request;
use Modules\Payment\Http\Controllers\PaymentLinkController;
use Modules\Payment\Http\Requests\PaymentLinkRequest;
use Modules\Payment\Models\PaymentLink;

/** @group postgres-integration */
final class PaymentLinkValidationPostgresTest extends \PHPUnit\Framework\TestCase
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
    public function test_invalid_manual_link_amounts_roll_back_before_parent_or_detail_is_written(): void
    {
        $document=$this->document('1');$controller=new PaymentLinkController();
        foreach(['-1','0','0.001','NaN','1.2.3',null,['n'=>'1','d'=>'3'],'1000000000000000000','0.1234567890123'] as $bad){
            $request=$this->request($document,'1');$request->merge(['documents'=>[],'total'=>$bad]);
            try{$controller->store($request);self::fail('El importe inválido no debe guardar un enlace.');}
            catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey('total',$error->errors());}
            self::assertSame(0,$this->db->table('payment_links')->count());
            self::assertSame(0,$this->db->table('payment_link_payments')->count());
            self::assertSame(0,$this->db->transactionLevel());
        }
    }
    public function test_actual_document_payment_applies_a_large_exact_link_amount_once(): void
    {
        $document=$this->document('9007199254740993.03');
        \App\Models\Tenant\Cash::where('user_id',$document->user_id)->where('state',true)->sole();
        $controller=new PaymentLinkController();$stored=$controller->store($this->request($document,'9007199254740993.02'));
        $request=Request::create('','POST',['id'=>$stored['data']['id']]);$result=$controller->confirmPayment($request);
        self::assertTrue($result['success']);$link=PaymentLink::findOrFail($stored['data']['id']);
        self::assertTrue($link->is_paid);self::assertSame(1,$document->payments()->count());
        self::assertTrue($document->payments()->sole()->exactValue('payment')->isEqualTo('9007199254740993.02'));
        self::assertTrue($document->fresh()->exactBalance()->isEqualTo('0.01'));
        self::assertFalse($controller->confirmPayment($request)['success']);
        self::assertSame(1,$document->payments()->count());
    }
    public function test_link_resources_export_only_private_temporary_browser_fixtures(): void
    {
        $document=$this->document('9007199254740993.03');$controller=new PaymentLinkController();
        $stored=$controller->store($this->request($document,'9007199254740993.02'));
        $large=PaymentLink::findOrFail($stored['data']['id']);
        $request=$this->request($document,'90071992547409.87');$request->merge(['documents'=>[]]);
        $safe=PaymentLink::findOrFail($controller->store($request)['data']['id']);
        self::assertTrue(D::of($large->getFormPublicData()['total'])->isEqualTo('9007199254740993.02'));
        self::assertTrue(D::of($safe->getFormPublicData()['total'])->isEqualTo('90071992547409.87'));
        $source=\Tests\Support\PostgresTestEnvironment::source();$run=getenv('PRO9_POSTGRES_RUN_ID');
        if(!preg_match('/^pro9-pgtest-[a-f0-9]{12}$/D',(string)$run))throw new \RuntimeException('Se requiere el ejecutor temporal.');
        $fixture=['version'=>1,'run_id'=>$run,'source_sha256'=>$source['sha256'],
            'large'=>$large->getFormPublicData(),'safe'=>$safe->getFormPublicData(),
            'form'=>$large->getRowResourceWithoutPayment(),'company'=>['name'=>'Empresa temporal','number'=>'J000000000'],
            'search'=>$controller->searchDocuments(Request::create('','GET',['customer_id'=>$document->customer_id]))];
        file_put_contents(base_path('informes/evidencias/postgresql_17_implementacion/resultados/'.$run.'-payment-links-fixture.json'),json_encode($fixture,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
