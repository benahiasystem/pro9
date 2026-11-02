<?php

namespace Tests\Unit;

use App\Models\Tenant\TransferAccountPayment;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
final class TransferAccountAmountTest extends TestCase
{
    public function test_transfer_input_is_not_converted_to_float(): void
    {
        config(['database.connections.tenant'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
        app('db')->purge('tenant');
        Auth::shouldReceive('user')->once()->andReturn((object)['id'=>1]);
        $transfer=(new TransferAccountPayment())->TransforFromCashToBank(1,2,'123456789.123456789123');
        self::assertSame('123456789.123456789123',$transfer->getAttributes()['amount']);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
