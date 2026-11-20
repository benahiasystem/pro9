<?php
namespace Tests\Unit;

/** @group postgres-integration */
final class TechnicalServiceEditedAdjustmentsPostgresTest extends \PHPUnit\Framework\TestCase
{
    private TechnicalServiceHeaderOriginalsPostgresTest $fixture;
    protected function setUp(): void
    {
        $this->fixture=new TechnicalServiceHeaderOriginalsPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
    }
    protected function tearDown(): void
    {
        try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally{parent::tearDown();}
    }
    /** @dataProvider editedAdjustments */
    public function test_a_changed_positive_decimal_supersedes_its_previous_fraction(string $field,string $type,string $fraction,string $edited,string $expected): void
    {
        $input=(new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture);
        $row=(new \ReflectionMethod($this->fixture,'adjustment'))->invoke($this->fixture,$type,$fraction,$field==='discounts');
        $input[$field]=[$row];$save=new \ReflectionMethod($this->fixture,'save');$service=$save->invoke($this->fixture,$input);
        $input['id']=$service->id;$input['items'][0]['id']=$service->items()->firstOrFail()->id;
        $input[$field][0]['amount']=$edited;
        $service=$save->invoke($this->fixture,$input);
        self::assertTrue($service->exactValue('total')->isEqualTo($expected));
        self::assertSame(\App\Support\Exact\Decimal::fraction($edited),$service->calculation_snapshot['inputs']['service_adjustments'][$field][0]['amount_exact']);
    }
    public static function editedAdjustments(): array
    {
        return ['manual discount'=>['discounts','05','1/3','0.1','0.9'],
            'manual charge'=>['charges','50','1/7','0.2','1.2']];
    }
}
