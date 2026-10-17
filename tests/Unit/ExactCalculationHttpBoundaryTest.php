<?php

namespace Tests\Unit;

use App\Http\Middleware\RemoveClientCalculationSnapshots;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ExactCalculationHttpBoundaryTest extends TestCase
{
    public function test_client_results_are_removed_from_json_forms_and_queries_without_changing_original_inputs(): void
    {
        $payload=['calculation_snapshot'=>['values'=>['total'=>['n'=>'1','d'=>'1']]],
            'items'=>[['quantity'=>'166.666666666667','unit_price'=>'3',
                'quantity_input'=>['amount'=>'500','unit_price'=>'3'],
                'price_input'=>['amount'=>'3','currency_type_id'=>'VES','includes_iva'=>true],
                'calculation_snapshot'=>['values'=>['total'=>['n'=>'1','d'=>'1']]]]]];
        $query=['calculation_snapshot'=>['values'=>['total'=>['n'=>'2','d'=>'1']]],'filter'=>'valid'];
        $url='/documents?'.http_build_query($query);
        $json=json_encode($payload,JSON_THROW_ON_ERROR);
        $requests=[Request::create($url,'POST',[],[],[],['CONTENT_TYPE'=>'application/json'],$json),
            Request::create($url,'POST',$payload),Request::create($url,'GET',$payload)];
        foreach ($requests as $request) {
            $raw=$request->getContent();
            (new RemoveClientCalculationSnapshots())->handle($request,function (Request $clean) use ($payload): Response {
                $data=$clean->all();
                self::assertArrayNotHasKey('calculation_snapshot',$data);
                self::assertArrayNotHasKey('calculation_snapshot',$data['items'][0]);
                self::assertSame($payload['items'][0]['quantity_input'],$data['items'][0]['quantity_input']);
                self::assertSame($payload['items'][0]['price_input'],$data['items'][0]['price_input']);
                self::assertSame('166.666666666667',$data['items'][0]['quantity']);
                return new Response();
            });
            self::assertSame($raw,$request->getContent());
        }
        $middleware=(new \ReflectionClass(\App\Http\Kernel::class))->getDefaultProperties()['middleware'];
        self::assertContains(RemoveClientCalculationSnapshots::class,$middleware);
    }
}
