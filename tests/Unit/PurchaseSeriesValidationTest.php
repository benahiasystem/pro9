<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\PurchaseRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class PurchaseSeriesValidationTest extends TestCase
{
    public function test_purchase_series_must_fit_the_consolidated_schema(): void
    {
        $rules = (new PurchaseRequest())->rules()['series'];
        $factory = new Factory(new Translator(new ArrayLoader(), 'es'));
        self::assertTrue($factory->make(['series'=>'P001'], ['series'=>$rules])->passes());
        self::assertFalse($factory->make(['series'=>'PRUEBA'], ['series'=>$rules])->passes());
        self::assertFalse($factory->make(['series'=>''], ['series'=>$rules])->passes());
    }
}
