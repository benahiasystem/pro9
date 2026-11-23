<?php

namespace Tests\Unit;

use Modules\FullSuscription\Models\Tenant\UserRelSuscriptionPlan;
use Modules\FullSuscription\Services\SuscriptionDocumentService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SubscriptionStoredItemsTest extends TestCase
{
    public function test_subscription_reads_stored_decimal_tokens_without_float_conversion(): void
    {
        $subscription = new UserRelSuscriptionPlan();
        $subscription->setRawAttributes(['items' => '[{"item_id":4,"quantity":0.000000000001,"unit_price":99999999999999.99,"item":{"has_igv":true}}]']);
        $producer = new class { use SuscriptionDocumentService; };
        $items = (new ReflectionMethod($producer, 'storedSubscriptionItems'))->invoke($producer, $subscription);

        self::assertSame(4, $items[0]['item_id']);
        self::assertSame('0.000000000001', $items[0]['quantity']);
        self::assertSame('99999999999999.99', $items[0]['unit_price']);
        self::assertTrue($items[0]['item']['has_igv']);
    }

    public function test_empty_subscription_items_preserve_the_plan_fallback(): void
    {
        $producer = new class { use SuscriptionDocumentService; };
        $method = new ReflectionMethod($producer, 'storedSubscriptionItems');
        foreach ([null, 'null', '[]'] as $raw) {
            $subscription = new UserRelSuscriptionPlan();
            $subscription->setRawAttributes(['items' => $raw]);
            self::assertSame([], $method->invoke($producer, $subscription));
        }
    }
}
