<?php

namespace Tests\Unit;

use App\Database\PostgresConnection;
use PHPUnit\Framework\TestCase;

final class BigintTransportTest extends TestCase
{
    /** @dataProvider rows */
    public function test_query_transport_keeps_distinct_64_bit_integers_and_existing_scalar_types($input,$expected): void
    {
        self::assertSame($expected,PostgresConnection::transport(['value'=>$input])['value']);
        self::assertSame($expected,PostgresConnection::transport((object)['value'=>$input])->value);
    }

    public static function rows(): array
    {
        return [
            'ordinary key'=>[41,41], 'zero'=>[0,0], 'negative'=>[-41,-41],
            'last safe'=>[9007199254740991,9007199254740991],
            'first unsafe'=>[9007199254740992,'9007199254740992'],
            'adjacent unsafe'=>[9007199254740993,'9007199254740993'],
            'signed maximum'=>[PHP_INT_MAX,'9223372036854775807'],
            'signed minimum'=>[PHP_INT_MIN,'-9223372036854775808'],
            'negative unsafe'=>[-9007199254740992,'-9007199254740992'],
            'numeric token'=>['9007199254740993','9007199254740993'],
            'decimal'=>['0.333333333333','0.333333333333'],
            'float projection'=>[0.25,0.25], 'unknown'=>[null,null],
            'true flag'=>[true,1], 'false flag'=>[false,0],
        ];
    }

    /** @dataProvider models */
    public function test_eloquent_getters_arrays_and_resources_do_not_recast_unsafe_keys_to_json_numbers(string $class): void
    {
        // No boot events, resolver or database is needed to exercise Eloquent's actual casts.
        $model=(new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $model->setRawAttributes(['id'=>'9007199254740993']);
        self::assertSame('9007199254740993',$model->id);
        self::assertSame('9007199254740993',$model->getKey());
        self::assertSame('9007199254740993',$model->attributesToArray()['id']);
        self::assertSame('9007199254740993',json_decode($model->toJson(),true,512,JSON_THROW_ON_ERROR)['id']);
        $model->setRawAttributes(['id'=>41]);
        self::assertSame(41,$model->id);
        self::assertSame(41,$model->attributesToArray()['id']);
    }

    public static function models(): array
    {
        return [
            'tenant base'=>[\App\Models\Tenant\BankAccount::class],
            'system base'=>[\App\Models\System\Client::class],
            'direct system model'=>[\App\Models\System\Configuration::class],
        ];
    }
}
