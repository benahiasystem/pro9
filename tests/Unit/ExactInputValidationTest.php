<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\ConfigurationRequest;
use App\Support\Exact\{Decimal,PdfPrice};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExactInputValidationTest extends TestCase
{
    public function test_original_inputs_survive_model_mass_assignment(): void
    {
        $inputs=['quantity_input'=>['amount'=>'500','unit_price'=>'3'],
            'price_input'=>['amount'=>'3','currency_type_id'=>'VES','includes_iva'=>true]];
        foreach ([\App\Models\Tenant\DocumentItem::class,\App\Models\Tenant\SaleNoteItem::class,\App\Models\Tenant\PurchaseItem::class] as $class) {
            $line=new $class($inputs+['quantity'=>'166.666666666667','unit_price'=>'3']);
            self::assertSame($inputs['quantity_input'],$line->quantity_input);
            self::assertSame($inputs['price_input'],$line->price_input);
            self::assertSame('3',$line->unit_price);
        }
    }
    public function test_pdf_setting_rejects_invalid_values_at_the_http_boundary(): void
    {
        $rules=(new ConfigurationRequest())->rules();
        foreach ([1,13,'6.5',null,'invalid'] as $value) {
            $validator=Validator::make(['cron'=>false,'decimal_quantity'=>2,
                'change_decimal_quantity_unit_price_pdf'=>true,'decimal_quantity_unit_price_pdf'=>$value],$rules);
            self::assertTrue($validator->fails());
            self::assertTrue($validator->errors()->has('decimal_quantity_unit_price_pdf'));
            self::assertSame(2,PdfPrice::precision(['change_decimal_quantity_unit_price_pdf'=>true,'decimal_quantity_unit_price_pdf'=>$value]));
        }
        foreach ([2,6,12] as $value) {
            self::assertTrue(Validator::make(['cron'=>false,'decimal_quantity'=>2,'change_decimal_quantity_unit_price_pdf'=>true,
                'decimal_quantity_unit_price_pdf'=>$value],$rules)->passes());
        }
    }

    public function test_precision_and_range_are_rejected_before_database_coercion(): void
    {
        self::assertSame('999999999999999999.999999999999',Decimal::input('999999999999999999.999999999999'));
        foreach (['0.0000000000001','1000000000000000000','invalid'] as $value) {
            try { Decimal::input($value,'quantity'); self::fail('An invalid decimal was accepted.'); }
            catch (ValidationException $expected) { self::assertArrayHasKey('quantity',$expected->errors()); }
        }
    }
}
