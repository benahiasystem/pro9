<?php

namespace Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\WhatsAppBot\Services\Facturalo\DraftPricing;
use PHPUnit\Framework\TestCase;

class WhatsAppDraftPricingTest extends TestCase
{
    public function test_converts_prices_and_discounts_before_rounding_payment(): void
    {
        $result = DraftPricing::calculate([
            ['item_id'=>1, 'quantity'=>'0.1', 'price'=>'3', 'price_currency'=>'USD', 'includes_iva'=>true],
            ['item_id'=>2, 'quantity'=>'0.2', 'price'=>'10', 'price_currency'=>'VES', 'includes_iva'=>true],
        ], '10.00000000');
        self::assertSame('5.00', $result['total_payable']);
        self::assertSame('30', $result['items'][0]['unit_price']);
        self::assertSame(['n'=>'3','d'=>'1'], $result['items'][0]['calculation_snapshot']['values']['total']);
        self::assertSame('USD', $result['items'][0]['price_input']['currency_type_id']);
        $discounted = DraftPricing::calculate([['quantity'=>'1', 'price'=>'116', 'price_currency'=>'VES', 'includes_iva'=>true]], '10', '10');
        self::assertSame('104.40', $discounted['total_payable']);
    }

    public function test_rejects_missing_or_nonpositive_rate(): void
    {
        $previous = \Illuminate\Support\Facades\Facade::getFacadeApplication();
        $app = new \Illuminate\Container\Container();
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app
        ));
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        try {
            DraftPricing::calculate([['quantity'=>'1', 'price'=>'1', 'price_currency'=>'USD', 'includes_iva'=>true]], '0');
            self::fail('Una tasa cero debe rechazarse.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('exchange_rate_sale', $exception->errors());
        } finally {
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Support\Facades\Facade::setFacadeApplication($previous);
        }
    }

    public function test_orchestrator_explains_that_an_old_draft_must_be_confirmed_again(): void
    {
        $previous = \Illuminate\Support\Facades\Facade::getFacadeApplication();
        $app = new \Illuminate\Container\Container();
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'es'), $app
        ));
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        try {
            $sender=$this->getMockBuilder(\Modules\WhatsAppBot\Services\Evolution\EvolutionSender::class)->disableOriginalConstructor()->getMock();
            $sender->expects(self::never())->method('sendDocumentPdf');
            $sender->expects(self::never())->method('sendText');
            $sessions=$this->getMockBuilder(\Modules\WhatsAppBot\Services\Session\SessionMachine::class)->disableOriginalConstructor()->getMock();
            $intent=$this->getMockBuilder(\Modules\WhatsAppBot\Services\IntentClassifier::class)->disableOriginalConstructor()->getMock();
            $orchestrator=new \Modules\WhatsAppBot\Services\BotOrchestrator($sessions,$sender,$intent);
            $emit=new \ReflectionMethod($orchestrator,'emitDocument');
            $emit->setAccessible(true);
            $result=$emit->invoke($orchestrator,['document_type_label'=>'factura'],new \App\Models\Tenant\User(),'test',null);
            self::assertFalse($result['success']);
            self::assertStringContainsString('prepararse y confirmarse nuevamente',$result['message']);
        } finally {
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
            \Illuminate\Support\Facades\Facade::setFacadeApplication($previous);
        }
    }
}
