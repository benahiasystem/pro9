<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\DocumentPaymentController;
use Modules\MobileApp\Http\Controllers\Api\ReceivedPaymentController;
use Modules\MobileApp\Http\Requests\Api\ReceivedPaymentClaimRequest;
use Modules\MobileApp\Models\ReceivedPayment;
use Tests\TestCase;

class ReceivedPaymentCurrencyTest extends TestCase
{
    public function test_capture_rules_reject_invalid_currency_and_non_decimal_text(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        \Illuminate\Support\Facades\DB::purge('tenant');
        \Illuminate\Support\Facades\Schema::connection('tenant')->create('cat_currency_types',function ($table) {
            $table->string('id'); $table->boolean('active');
        });
        \Illuminate\Support\Facades\DB::connection('tenant')->table('cat_currency_types')->insert([
            ['id'=>'USD','active'=>true], ['id'=>'VES','active'=>false],
        ]);
        $data=['provider'=>'Zelle','amount'=>'10.25','currency_type_id'=>'USD','raw_text'=>'Pago',
            'package_name'=>'mobile','posted_at'=>'2026-10-28 12:00:00','device_id'=>'device','dedup_hash'=>'hash'];
        $rules=(new \Modules\MobileApp\Http\Requests\Api\ReceivedPaymentStoreRequest())->rules();
        self::assertFalse(validator($data,$rules)->fails());
        self::assertFalse(validator(array_replace($data,['currency_type_id'=>'VES']),$rules)->fails());
        self::assertTrue(validator(array_replace($data,['currency_type_id'=>'XXX']),$rules)->fails());
        self::assertTrue(validator(array_replace($data,['amount'=>10.25]),$rules)->fails());
        unset($data['currency_type_id']);
        self::assertTrue(validator($data,$rules)->fails());
    }

    public function test_claim_keeps_the_captured_amount_in_the_explicit_received_currency(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        $received = (new ReceivedPayment())->forceFill([
            'provider'=>'Zelle', 'sender_name'=>'Cliente', 'amount'=>'10.25', 'currency_type_id'=>'USD', 'posted_at'=>'2026-10-28 12:00:00',
        ]);
        $request = ReceivedPaymentClaimRequest::create('/', 'POST', [
            'document_id'=>1, 'payment_method_type_id'=>'13', 'payment_destination_id'=>7,
            'currency_type_id'=>'USD', 'exchange_rate'=>'400.00000000',
            'original_amount'=>'9999', 'financial_intermediation'=>'none',
        ]);
        $controller = \Mockery::mock(DocumentPaymentController::class);
        $controller->shouldReceive('store')->once()->withArgs(function ($payload) {
            self::assertSame('USD', $payload->input('currency_type_id'));
            self::assertSame('10.25', (string) $payload->input('original_amount'));
            self::assertSame('400.00000000', $payload->input('exchange_rate'));
            self::assertTrue(\Illuminate\Support\Str::isUuid($payload->input('operation_key')));
            return true;
        })->andReturn(['id'=>9]);
        app()->instance(DocumentPaymentController::class, $controller);
        $method = new \ReflectionMethod(ReceivedPaymentController::class, 'runDocumentPaymentStore');
        $method->setAccessible(true);
        self::assertSame(['id'=>9], $method->invoke(new ReceivedPaymentController(), $received, $request));
    }

    public function test_claim_uses_exact_captured_decimal_and_rejects_currency_override(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        $received=(new ReceivedPayment())->forceFill(['provider'=>'Zelle','sender_name'=>'Cliente','amount'=>'99999999999999.99',
            'currency_type_id'=>'USD','posted_at'=>'2026-10-28 12:00:00']);
        $request=ReceivedPaymentClaimRequest::create('/', 'POST', ['document_id'=>1,'payment_method_type_id'=>'13',
            'payment_destination_id'=>7,'exchange_rate'=>'400']);
        $controller=\Mockery::mock(DocumentPaymentController::class);
        $controller->shouldReceive('store')->once()->withArgs(function ($payload) {
            self::assertSame('99999999999999.99',$payload->input('original_amount'));
            self::assertSame('USD',$payload->input('currency_type_id'));
            return true;
        })->andReturn(['id'=>10]);
        app()->instance(DocumentPaymentController::class,$controller);
        $method=new \ReflectionMethod(ReceivedPaymentController::class,'runDocumentPaymentStore');
        $method->setAccessible(true);
        $method->invoke(new ReceivedPaymentController(),$received,$request);
        $request->merge(['currency_type_id'=>'VES']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $method->invoke(new ReceivedPaymentController(),$received,$request);
    }
}
