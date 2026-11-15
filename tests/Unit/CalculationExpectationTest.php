<?php
namespace Tests\Unit;

use App\Services\Payments\CalculationExpectation;
use PHPUnit\Framework\TestCase;

class CalculationExpectationTest extends TestCase
{
    public function test_identifiers_are_not_compared_as_money(): void
    {
        $compare=new \ReflectionMethod(CalculationExpectation::class,'equal');
        $compare->setAccessible(true);
        self::assertFalse($compare->invoke(null,['payment_method_type_id'=>'01'],['payment_method_type_id'=>'1']));
        self::assertFalse($compare->invoke(null,['cash_id'=>'01'],['cash_id'=>'1']));
        self::assertTrue($compare->invoke(null,['payment'=>'1.00'],['payment'=>'1']));
        self::assertTrue($compare->invoke(null,['amount'=>['n'=>'1','d'=>'3']],['amount'=>['n'=>'2','d'=>'6']]));
        self::assertFalse($compare->invoke(null,['reused'=>'1'],['reused'=>true]));
    }
}
