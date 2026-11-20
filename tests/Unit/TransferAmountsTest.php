<?php
namespace Tests\Unit;

use App\Services\Payments\TransferAmounts;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TransferAmountsTest extends TestCase
{
    public function test_signed_legs_preserve_twelve_places_and_the_full_numeric_range(): void
    {
        foreach (['0.1','0.000000000001','999999999999999999.999999999999'] as $amount) {
            self::assertSame(['credit'=>$amount,'debit'=>'-'.$amount],TransferAmounts::fromInput([
                'amount_transform'=>$amount,'amount_transform_temp'=>$amount]));
        }
        self::assertSame(['credit'=>'2','debit'=>'-1'],TransferAmounts::fromInput([
            'amount_transform'=>2,'amount_transform_temp'=>1]));
    }
    public function test_model_amount_accessors_preserve_decimal_strings_instead_of_float_signatures(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        app('db')->purge('tenant');
        $model=new \App\Models\Tenant\TransferAccountPayment();
        foreach (['999999999999999999.999999999999','-0.000000000001','0.1'] as $value) {
            $model->setAmount($value);
            self::assertSame($value,$model->getAmount());
            self::assertTrue($model->exactValue('amount')->isEqualTo($value));
        }
        $model->setAmount(null);
        self::assertNull($model->getAmount());
        $this->expectException(ValidationException::class);
        $model->setAmount(0.1);
    }
    /** @dataProvider invalidAmounts */
    public function test_invalid_or_binary_inputs_cannot_be_normalized_into_valid_debits($value): void
    {
        foreach (['amount_transform','amount_transform_temp'] as $field) {
            try {
                TransferAmounts::fromInput(array_replace(['amount_transform'=>'1','amount_transform_temp'=>'1'],[$field=>$value]));
                self::fail('No se permite adaptar un importe inválido antes de validarlo.');
            } catch (ValidationException $error) {self::assertArrayHasKey('data.'.$field,$error->errors());}
        }
    }
    public static function invalidAmounts(): array
    {
        return array_map(fn($value)=>[$value],[null,true,false,0.1,1.0,[],['n'=>'1','d'=>'3'],'','invalid','-1','0',
            '0.0000000000001','1000000000000000000']);
    }
}
