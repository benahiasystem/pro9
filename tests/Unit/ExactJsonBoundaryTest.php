<?php

namespace Tests\Unit;

use App\Http\Middleware\PreserveJsonDecimals;
use App\Http\Middleware\RemoveClientCalculationSnapshots;
use App\Support\Exact\Decimal;
use App\Support\Exact\JsonDecimals;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactJsonBoundaryTest extends TestCase
{
    public function test_decimal_lexemes_survive_middleware_and_untrusted_snapshots_do_not(): void
    {
        $json = <<<'JSON'
{"id":7,"enabled":true,"text":"1.234 \" 2.5","items":[{"quantity":0.123456789012,"unit_price":999999999999999999.123456789012,"power":1e-12,"calculation_snapshot":{"version":1,"values":{}}}]}
JSON;
        $request = Request::create('/documents', 'POST', [], [], [], ['CONTENT_TYPE'=>'application/json'], $json);
        (new PreserveJsonDecimals())->handle($request, function ($request) use ($json) {
            return (new RemoveClientCalculationSnapshots())->handle($request, function ($request) use ($json) {
                $data = $request->all();
                self::assertSame(7, $data['id']);
                self::assertTrue($data['enabled']);
                self::assertSame('0.123456789012', $data['items'][0]['quantity']);
                self::assertSame('999999999999999999.123456789012', $data['items'][0]['unit_price']);
                self::assertSame('1e-12', $data['items'][0]['power']);
                self::assertArrayNotHasKey('calculation_snapshot', $data['items'][0]);
                self::assertSame($json, $request->getContent());
                return new Response();
            });
        });
        $middleware = (new \ReflectionClass(\App\Http\Kernel::class))->getDefaultProperties()['middleware'];
        self::assertLessThan(array_search(\App\Http\Middleware\TrimStrings::class, $middleware), array_search(PreserveJsonDecimals::class, $middleware));
    }

    public function test_invalid_json_stays_invalid(): void
    {
        $this->expectException(BadRequestHttpException::class);
        (new PreserveJsonDecimals())->handle(Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE'=>'application/json'], '{"amount":01.2}'), fn()=>new Response());
    }

    public function test_strings_integers_and_nested_arrays_are_preserved(): void
    {
        self::assertSame(['s'=>'a\\b 1.2', 'id'=>'92233720368547758080', 'a'=>['-0.000', '2E+12', null, false]],
            JsonDecimals::decode('{"s":"a\\\\b 1.2","id":92233720368547758080,"a":[-0.000,2E+12,null,false]}'));
    }

    public function test_required_reads_recover_fraction_without_projected_column(): void
    {
        self::assertSame('1/3', (string)Decimal::readRequired(['calculation_snapshot'=>Decimal::snapshot(['total'=>'1/3'])], 'total'));
        self::assertTrue(Decimal::readRequired(['total'=>'0'], 'total')->isZero());
    }

    public function test_required_read_cache_tracks_replaced_and_mutated_snapshots(): void
    {
        $record = new \Illuminate\Support\Fluent(['total'=>'0.333333333333',
            'calculation_snapshot'=>json_encode(Decimal::snapshot(['total'=>'1/3']))]);
        self::assertSame('1/3', (string)Decimal::readRequired($record,'total'));
        self::assertSame('1/3', (string)Decimal::readRequired($record,'total'));
        $snapshot = json_decode(json_encode(Decimal::snapshot(['total'=>'2/3'])));
        $record->calculation_snapshot = $snapshot;
        self::assertSame('2/3', (string)Decimal::readRequired($record,'total'));
        $snapshot->values->total->n = '4';
        self::assertSame('4/3', (string)Decimal::readRequired($record,'total'));
        $snapshot->version = 9;
        $this->expectException(\UnexpectedValueException::class);
        Decimal::readRequired($record,'total');
    }

    /** @dataProvider invalidReads */
    public function test_incomplete_or_corrupt_reads_fail(array $row): void
    {
        $this->expectException(\UnexpectedValueException::class);
        Decimal::readRequired($row, 'total');
    }

    public static function invalidReads(): array
    {
        return [[[]], [['total'=>null]], [['total'=>'1', 'calculation_snapshot'=>['version'=>9, 'values'=>[]]]]];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
