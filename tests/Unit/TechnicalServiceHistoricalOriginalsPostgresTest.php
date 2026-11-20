<?php
namespace Tests\Unit;

use App\Support\Exact\{Decimal as D,TechnicalServiceCalculation};

/** @group postgres-integration */
final class TechnicalServiceHistoricalOriginalsPostgresTest extends \PHPUnit\Framework\TestCase
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
    private function service()
    {
        $input=(new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture);
        $line=$input['items'][0];$line['quantity']='0.333333333333';$line['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $input['items']=[$line,$line,$line];
        return (new \ReflectionMethod($this->fixture,'save'))->invoke($this->fixture,$input);
    }
    public function test_recoverability_sums_original_line_fractions_instead_of_projected_fields(): void
    {
        $service=$this->service();$lines=$service->items()->withoutEagerLoads()->get();
        self::assertCount(3,$lines);
        foreach($lines as $line)self::assertTrue($line->exactValue('total')->isEqualTo('1/3'));
        self::assertTrue($service->exactValue('total')->isEqualTo(1));
        // Simulate the documented legacy contract, retaining its original numeric
        // values but omitting the optional newer header-adjustment input record.
        $snapshot=$service->calculation_snapshot;unset($snapshot['inputs']['service_adjustments']);
        $service->calculation_snapshot=$snapshot;
        TechnicalServiceCalculation::assertHistoricalAdjustmentsRecoverable($service,$lines);
        self::assertTrue($service->exactValue('total')->isEqualTo(1));
        self::assertTrue($service->fresh()->exactValue('total')->isEqualTo(1));
    }
    public function test_recoverability_rejects_a_lossy_legacy_header_without_writing_anything(): void
    {
        $service=$this->service();$lines=$service->items()->withoutEagerLoads()->get();
        $service->setExactValues(['total'=>'0.999999999999']);
        $snapshot=$service->calculation_snapshot;unset($snapshot['inputs']['service_adjustments']);
        $service->calculation_snapshot=$snapshot;
        try{
            TechnicalServiceCalculation::assertHistoricalAdjustmentsRecoverable($service,$lines);
            self::fail('The projected sum was accepted as the original total.');
        }catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey('items',$error->errors());}
        self::assertTrue($service->fresh()->exactValue('total')->isEqualTo(1));
        foreach($service->items()->withoutEagerLoads()->get() as $line)self::assertTrue(D::readRequired($line,'total')->isEqualTo('1/3'));
    }
}
