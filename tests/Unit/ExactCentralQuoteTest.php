<?php

namespace Tests\Unit;

use App\Models\System\PaymentOrder;
use App\Support\Exact\{CentralPaymentQuote, Decimal as D};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactCentralQuoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();DB::purge('system');
        config(['database.connections.system'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'venezuela.currency.id'=>'VES']);
        Schema::connection('system')->create('payment_orders',function ($t) {
            $t->increments('id');$t->string('uuid');$t->text('amount');$t->integer('client_id');
            $t->integer('order_state_id');$t->json('calculation_snapshot')->nullable();
        });
    }

    public function test_new_order_freezes_currency_exact_price_payable_and_adjustment_without_changing_price(): void
    {
        $order=PaymentOrder::create(['amount'=>D::of('1/3'),'client_id'=>1,'order_state_id'=>1])->fresh();
        $quote=CentralPaymentQuote::read($order);
        self::assertSame('VES',$quote['currency']);
        self::assertSame('1/3',(string)$quote['amount']);
        self::assertSame('33',$quote['minor_units']);
        self::assertSame('-1/300',(string)$quote['adjustment']);
        config(['venezuela.currency.id'=>'USD']);
        self::assertSame('VES',CentralPaymentQuote::read($order)['currency']);
        $order->order_state_id=2;$order->save();
        self::assertSame('1/3',(string)$order->fresh()->exactValue('amount'));
    }

    public function test_corrupt_or_obsolete_quote_is_rejected_without_reconstructing_a_historical_currency(): void
    {
        $order=PaymentOrder::create(['amount'=>'500','client_id'=>1,'order_state_id'=>1]);
        $order->amount='499';
        try { CentralPaymentQuote::read($order); self::fail('La cotización anterior no autoriza otro importe.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('amount',$error->errors()); }
        $old=new PaymentOrder();$old->setRawAttributes(['id'=>2,'uuid'=>'old','amount'=>'500','order_state_id'=>1]);
        try { CentralPaymentQuote::read($old); self::fail('No se puede inventar la moneda histórica.'); }
        catch (ValidationException $error) { self::assertArrayHasKey('currency',$error->errors()); }
        self::assertSame(1,PaymentOrder::count());
    }

    public function test_negative_order_rolls_back_before_sql(): void
    {
        try { PaymentOrder::create(['amount'=>'-1','client_id'=>1,'order_state_id'=>1]);self::fail('No se admite un cobro negativo.'); }
        catch (ValidationException $error) { self::assertSame(0,PaymentOrder::count()); }
    }

    public function test_direct_usd_payload_cannot_bypass_ves_checkout_before_database_credentials_or_network(): void
    {
        $request=Request::create('/','POST',['amount'=>'1','currency_code'=>'USD','currency'=>'USD','uuid'=>'foreign']);
        $public=new \App\Http\Controllers\System\PaymentOrderPublicController();
        $gateway=new \Modules\Payment\Http\Controllers\PaymentGatewayController();
        foreach (['culqiCharge','izipayPayment','izipayTransaction'] as $method) {
            try { $public->$method('unknown-order',$request);self::fail('No puede consultar ni cobrar en una pasarela no disponible.'); }
            catch (ValidationException $error) { self::assertArrayHasKey('currency',$error->errors()); }
        }
        foreach (['culqiCreateCharge','izipayCreatePayment','izipayTransaction'] as $method) {
            try { $gateway->$method($request);self::fail('No puede consultar credenciales ni llamar al proveedor.'); }
            catch (ValidationException $error) { self::assertArrayHasKey('currency',$error->errors()); }
        }
        self::assertSame(0,PaymentOrder::count());
    }

    public function test_browser_success_text_cannot_mark_an_order_paid_or_repeat_the_payment(): void
    {
        config(['venezuela.currency.id'=>'USD']);
        $order=PaymentOrder::create(['amount'=>'500','client_id'=>1,'order_state_id'=>1]);
        $controller=new \App\Http\Controllers\System\PaymentOrderController();
        DB::connection('system')->enableQueryLog();
        DB::connection('system')->flushQueryLog();
        foreach (['venta_exitosa','PAID','approved','authorized'] as $status) {
            $result=$controller->paymentViewPay(Request::create('/','POST',['uuid'=>$order->uuid,'status'=>$status]));
            self::assertFalse($result['paid']);
            self::assertSame(1,$order->fresh()->order_state_id);
        }
        $queries=DB::connection('system')->getQueryLog();
        foreach ($queries as $query) self::assertStringStartsWith('select',strtolower(ltrim($query['query'])));
        $order->order_state_id=2;$order->save();
        DB::connection('system')->flushQueryLog();
        $result=$controller->paymentViewPay(Request::create('/','POST',['uuid'=>$order->uuid,'status'=>'operacion_denegada']));
        self::assertTrue($result['paid']);
        self::assertSame('500',D::represent($order->fresh()->exactValue('amount')));
        foreach (DB::connection('system')->getQueryLog() as $query) self::assertStringStartsWith('select',strtolower(ltrim($query['query'])));
        DB::connection('system')->disableQueryLog();
    }

    public function test_browser_confirmation_also_obeys_unavailable_ves_checkout_before_any_database_read(): void
    {
        DB::connection('system')->enableQueryLog();
        DB::connection('system')->flushQueryLog();
        try {
            (new \App\Http\Controllers\System\PaymentOrderController())->paymentViewPay(
                Request::create('/','POST',['uuid'=>'unknown','status'=>'PAID']));
            self::fail('Un texto del cliente no habilita una pasarela no disponible.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('currency',$error->errors());
            self::assertSame([],DB::connection('system')->getQueryLog());
        } finally { DB::connection('system')->disableQueryLog(); }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
