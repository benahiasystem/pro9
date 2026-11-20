<?php
namespace Tests\Unit;

use App\Support\Exact\Decimal as D;
use Modules\Sale\Models\TechnicalService;
use Modules\Sale\Http\Requests\TechnicalServiceRequest;

/** @group postgres-integration */
final class TechnicalServiceHeaderOriginalsPostgresTest extends \PHPUnit\Framework\TestCase
{
    private PrepaymentConsumersPostgresTest $fixture;
    private $db;
    private array $base;
    protected function setUp(): void
    {
        $this->fixture=new PrepaymentConsumersPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
        $this->db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
        $this->base=(new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,'1',false);
    }
    protected function tearDown(): void
    {
        try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally{parent::tearDown();}
    }
    private function input(): array
    {
        return ['customer_id'=>$this->base['customer_id'],'establishment_id'=>$this->base['establishment_id'],
            'fiscal_environment'=>'demo','currency_type_id'=>'VES','exchange_rate_sale'=>'1',
            'date_of_issue'=>'2026-09-10','time_of_issue'=>'12:00:00','cellphone'=>'',
            'description'=>'Servicio de prueba temporal','state'=>'','reason'=>'','serial_number'=>'PG-EXACT',
            'cost'=>'0','prepayment'=>'0','total_prepayment'=>'0','discounts'=>[],'charges'=>[],
            'items'=>[$this->base['items'][0]]];
    }
    private function save(array $input): TechnicalService
    {
        // Exercise the real controller transaction and persisted schema. File generation
        // is outside this adjustment regression and is tested by the PDF suites.
        $controller=new class extends \Modules\Sale\Http\Controllers\TechnicalServiceController {
            public function createPdf($technical_service=null,$format_pdf=null,$filename=null) {}
        };
        $result=$controller->store(TechnicalServiceRequest::create('','POST',$input));
        self::assertTrue($result['success']);
        return TechnicalService::withoutEagerLoads()->latest('id')->firstOrFail();
    }
    private function adjustment(string $type,string $fraction,bool $discount): array
    {
        return [($discount?'discount_type_id':'charge_type_id')=>$type,'description'=>'Ajuste exacto',
            'base'=>'1','factor'=>D::represent($fraction),'amount'=>D::represent($fraction),
            'amount_exact'=>D::fraction($fraction),'is_amount'=>true];
    }
    public function test_actual_service_store_preserves_the_discount_original(): void
    {
        $input=$this->input();$input['discounts']=[$this->adjustment('05','1/3',true)];
        $service=$this->save($input);
        self::assertTrue($service->exactValue('total_discount')->isEqualTo('1/3'));
        self::assertTrue($service->exactValue('total')->isEqualTo('2/3'));
        self::assertSame(['n'=>'1','d'=>'3'],$service->calculation_snapshot['inputs']['service_adjustments']['discounts'][0]['amount_exact']);
    }
    public function test_actual_service_store_preserves_the_charge_original(): void
    {
        $input=$this->input();$input['charges']=[$this->adjustment('50','1/7',false)];
        $service=$this->save($input);
        self::assertTrue($service->exactValue('total_charge')->isEqualTo('1/7'));
        self::assertTrue($service->exactValue('total')->isEqualTo('8/7'));
        self::assertSame(['n'=>'1','d'=>'7'],$service->calculation_snapshot['inputs']['service_adjustments']['charges'][0]['amount_exact']);
    }
    public function test_actual_service_store_preserves_the_taxed_advance_discount(): void
    {
        $input=$this->input();$input['cost']='1';$input['total_prepayment']='0.333333333333';
        $input['discounts']=[$this->adjustment('04','25/87',true)];
        $service=$this->save($input);
        self::assertTrue($service->exactValue('total_discount')->isEqualTo('25/87'));
        self::assertTrue($service->exactValue('total_igv')->isEqualTo('8/87'));
        self::assertTrue($service->exactValue('total')->isEqualTo('2/3'));
    }

    public function test_combined_original_adjustments_survive_repeated_controller_edits(): void
    {
        $input=$this->input();$input['discounts']=[$this->adjustment('05','1/3',true)];
        $input['charges']=[$this->adjustment('50','1/7',false)];
        $service=$this->save($input);self::assertTrue($service->exactValue('total')->isEqualTo('17/21'));
        $input['id']=$service->id;$input['items'][0]['id']=$service->items()->firstOrFail()->id;
        foreach([1,2] as $repeat){
            $service=$this->save($input);
            self::assertTrue($service->exactValue('total_discount')->isEqualTo('1/3'));
            self::assertTrue($service->exactValue('total_charge')->isEqualTo('1/7'));
            self::assertTrue($service->exactValue('total')->isEqualTo('17/21'));
        }
        self::assertSame(1,$this->db->table('technical_services')->count());
        self::assertSame(1,$service->items()->count());
    }
    public function test_plain_decimal_adjustments_retain_their_existing_contract(): void
    {
        $input=$this->input();$discount=$this->adjustment('05','0.1',true);$charge=$this->adjustment('50','0.2',false);
        unset($discount['amount_exact'],$charge['amount_exact']);$input['discounts']=[$discount];$input['charges']=[$charge];
        $service=$this->save($input);
        self::assertTrue($service->exactValue('total_discount')->isEqualTo('0.1'));
        self::assertTrue($service->exactValue('total_charge')->isEqualTo('0.2'));
        self::assertTrue($service->exactValue('total')->isEqualTo('1.1'));
    }
    public function test_percentage_adjustments_still_recompute_from_their_authoritative_percentage(): void
    {
        $input=$this->input();$discount=$this->adjustment('05','1/3',true);$discount['is_amount']=false;$discount['percentage']='10';
        $charge=$this->adjustment('50','1/7',false);$charge['service_auto_percentage']=true;$charge['percentage']='2';
        $input['discounts']=[$discount];$input['charges']=[$charge];$service=$this->save($input);
        self::assertTrue($service->exactValue('total_discount')->isEqualTo('1/10'));
        self::assertTrue($service->exactValue('total_charge')->isEqualTo('9/500'));
        self::assertTrue($service->exactValue('total')->isEqualTo('459/500'));
    }
    /** @dataProvider negativeOriginals */
    public function test_a_negative_original_cannot_be_hidden_by_a_positive_decimal(string $field): void
    {
        $input=$this->input();$row=$this->adjustment($field==='discounts'?'05':'50','-1/3',$field==='discounts');
        $row['amount']='0.333333333333';$input[$field]=[$row];
        try{$this->save($input);self::fail('The negative original was accepted.');}
        catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey($field,$error->errors());}
        self::assertSame(0,$this->db->table('technical_services')->count());
        self::assertSame(0,$this->db->table('technical_service_items')->count());
        self::assertSame(0,$this->db->transactionLevel());
    }
    public static function negativeOriginals(): array {return ['discount'=>['discounts'],'charge'=>['charges']];}
    /** @dataProvider malformedOriginals */
    public function test_an_invalid_original_is_a_validation_failure_and_rolls_back($original): void
    {
        $input=$this->input();$row=$this->adjustment('50','1/7',false);$row['amount_exact']=$original;$input['charges']=[$row];
        try{$this->save($input);self::fail('The malformed original was accepted.');}
        catch(\Illuminate\Validation\ValidationException $error){self::assertNotEmpty($error->errors());}
        self::assertSame(0,$this->db->table('technical_services')->count());
        self::assertSame(0,$this->db->table('technical_service_items')->count());
        self::assertSame(0,$this->db->transactionLevel());
    }
    public static function malformedOriginals(): array {return ['zero denominator'=>[['n'=>'1','d'=>'0']],'invalid decimal'=>['invalid']];}
}
