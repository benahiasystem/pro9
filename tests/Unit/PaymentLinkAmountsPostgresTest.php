<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Support\Exact\Decimal as D;
use Modules\Payment\Http\Controllers\PaymentLinkController;

/** @group postgres-integration */
final class PaymentLinkAmountsPostgresTest extends \PHPUnit\Framework\TestCase
{
    private PrepaymentConsumersPostgresTest $fixture;
    private $db;
    protected function setUp(): void
    {
        $this->fixture=new PrepaymentConsumersPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
        $this->db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
    }
    protected function tearDown(): void
    {
        try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally{parent::tearDown();}
    }
    private function input(string $total): array
    {
        return (new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,$total,false);
    }
    public function test_link_sum_keeps_every_applied_cent_beyond_safe_float_magnitude(): void
    {
        $actual=(new \ReflectionMethod(PaymentLinkController::class,'getTotalDocuments'))->invoke(new PaymentLinkController(),[
            ['payment'=>'9007199254740993.01'],['payment'=>'0.02']]);
        self::assertTrue(D::of($actual)->isEqualTo('9007199254740993.03'));
    }
    public function test_link_pending_keeps_the_large_documentary_settlement(): void
    {
        $document=(new Facturalo())->save($this->input('9007199254740993.03'))->getDocument()->fresh();
        self::assertTrue($document->exactBalance()->isEqualTo('9007199254740993.03'));
        $actual=(new PaymentLinkController())->getPendingPayment($document);
        self::assertTrue(D::of($actual)->isEqualTo('9007199254740993.03'));
    }
    public function test_link_pending_uses_the_original_settlement_boundary_instead_of_a_rounded_header(): void
    {
        $input=$this->input('1');$input['items'][0]['quantity']='0.005';
        $input['items'][0]['quantity_input']=['amount'=>'0.014999999999','unit_price'=>'3'];
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('0.005',$document->total);
        self::assertTrue($document->exactValue('total')->isLessThan('1/200'));
        self::assertTrue($document->exactBalance()->isZero());
        self::assertTrue(D::of((new PaymentLinkController())->getPendingPayment($document))->isZero());
    }
    public function test_link_pending_keeps_existing_applied_receipt_and_reversal_rules(): void
    {
        $document=(new Facturalo())->save($this->input('100'))->getDocument()->fresh();
        foreach([['30',true],['10',false]] as [$amount,$reversed]){
            $this->db->table('document_payments')->insert(['document_id'=>$document->id,'date_of_payment'=>'2026-11-12',
                'payment_method_type_id'=>'01','payment'=>$amount,'currency_type_id'=>'VES','exchange_rate'=>'1',
                'original_amount'=>$amount,'payment_method_snapshot'=>'{}','operation_key'=>(string)\Illuminate\Support\Str::uuid(),
                'reversed_at'=>$reversed?'2026-11-12 10:00:00':null,
                'calculation_snapshot'=>json_encode(D::snapshot(['payment'=>$amount,'original_amount'=>$amount,'exchange_rate'=>'1','tax_amount'=>'0']),JSON_THROW_ON_ERROR)]);
        }
        self::assertTrue($document->fresh()->exactBalance()->isEqualTo('90'));
        self::assertTrue(D::of((new PaymentLinkController())->getPendingPayment($document->fresh()))->isEqualTo('90'));
    }
}
