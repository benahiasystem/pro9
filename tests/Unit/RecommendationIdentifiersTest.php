<?php

namespace Tests\Unit;

use App\Database\IntegerTransport;
use PHPUnit\Framework\TestCase;

final class RecommendationIdentifiersTest extends TestCase
{
    /** @dataProvider identifiers */
    public function test_positive_key_validation_never_truncates_or_clamps_an_identifier($value,?int $expected): void
    {
        self::assertSame($expected,IntegerTransport::positiveKey($value));
    }

    public static function identifiers(): array
    {
        return [
            'ordinary integer'=>[41,41],'ordinary text'=>['41',41],
            'first large key'=>['9007199254740993',9007199254740993],
            'adjacent large key'=>['9007199254740994',9007199254740994],
            'signed maximum'=>['9223372036854775807',PHP_INT_MAX],
            'signed maximum integer'=>[PHP_INT_MAX,PHP_INT_MAX],
            'overflow'=>['9223372036854775808',null],
            'unsigned maximum'=>['18446744073709551615',null],
            'suffix'=>['9007199254740993x',null],'decimal'=>['41.0',null],
            'exponent'=>['4.1e1',null],'leading zero'=>['041',null],
            'blank'=>['',null],'padded'=>[' 41 ',null],
            'zero'=>[0,null],'negative'=>[-1,null], 'float'=>[41.0,null],
            'true flag'=>[true,null],'null'=>[null,null], 'array'=>[[],null],
            'very long token'=>[str_repeat('9',10000),null],
        ];
    }

    /** @dataProvider limits */
    public function test_batch_limit_is_normalized_before_multiplication(int $requested,int $expected): void
    {
        $service=new RecordingRecommendations();
        $service->forItems(['9007199254740993','9007199254740994'],$requested);
        self::assertSame([['id'=>9007199254740993,'limit'=>$expected],['id'=>9007199254740994,'limit'=>$expected]],$service->calls);
    }

    public static function limits(): array
    {
        return ['maximum'=>[PHP_INT_MAX,32],'minimum'=>[PHP_INT_MIN,2],'negative'=>[-1,2],
            'zero'=>[0,2],'normal'=>[8,16],'ceiling'=>[16,32],'above ceiling'=>[17,32]];
    }
}

final class RecordingRecommendations extends \App\Services\Tenant\FrequentlyBoughtTogetherService
{
    public array $calls=[];
    public function forItem(int $itemId,int $limit=self::DEFAULT_LIMIT): array
    {
        $this->calls[]=['id'=>$itemId,'limit'=>$limit];
        return [['id'=>101,'times_bought_together'=>1]];
    }
}
