<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Ecommerce\Http\Controllers\SocialProofController;

/** @group postgres-integration */
final class RecommendationIdentifiersPostgresTest extends \PHPUnit\Framework\TestCase
{
    public function test_actual_public_controller_validates_both_identifier_entry_points_before_calling_the_service(): void
    {
        $fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        try {
            $fixture->start();
            [$db]=(new \ReflectionMethod($fixture,'bootHkaFixture'))->invoke($fixture,false);
            \Tests\Support\PostgresTestEnvironment::assertServer($db);
            $controller=new SocialProofController();
            $service=new RecordingRecommendations();
            foreach(['9007199254740993x','9223372036854775808','18446744073709551615','041','-1','0',str_repeat('9',10000)] as $invalid) {
                $service->calls=[];
                self::assertSame([],$controller->frequentlyBoughtTogether(Request::create('/'),$service,$invalid)['data']);
                self::assertSame([],$service->calls,'Invalid route identifiers must not be cast to another product.');
                self::assertSame([],$controller->frequentlyBoughtTogether(Request::create('/','GET',['item_id'=>$invalid]),$service)['data']);
                self::assertSame([],$service->calls,'Invalid query identifiers must not be cast to another product.');
            }
            foreach(['9007199254740993','9007199254740994','9223372036854775807'] as $valid) {
                $service->calls=[];
                self::assertCount(1,$controller->frequentlyBoughtTogether(Request::create('/'),$service,$valid)['data']);
                self::assertSame([['id'=>(int)$valid,'limit'=>8]],$service->calls);
            }
            $service->calls=[];
            $result=$controller->frequentlyBoughtTogether(Request::create('/','GET',[
                'item_ids'=>'9007199254740993x,9223372036854775808, 9007199254740993,9007199254740994,9007199254740993',
                'limit'=>PHP_INT_MAX]),$service);
            self::assertCount(1,$result['data']);
            self::assertSame([['id'=>9007199254740993,'limit'=>32],['id'=>9007199254740994,'limit'=>32]],$service->calls);
            $service->calls=[];
            self::assertSame([],$controller->frequentlyBoughtTogether(Request::create('/','GET',['item_ids'=>['41']]),$service)['data']);
            self::assertSame([],$service->calls);
            self::assertSame(0,$db->transactionLevel());
        } finally {$fixture->stop();}
    }
}
