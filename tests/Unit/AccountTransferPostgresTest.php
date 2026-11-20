<?php
namespace Tests\Unit;

use App\Models\Tenant\{BankAccount, TransferAccountPayment};
use App\Support\Exact\Decimal;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Http\Controllers\BalanceController;

/** @group postgres-integration */
final class AccountTransferPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
    }
    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();}
        finally {parent::tearDown();}
    }
    public function test_transfer_validates_both_legs_before_rounding_and_preserves_large_decimal_text(): void
    {
        [$db,,$input]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,false);
        $accounts=[];
        foreach (['origin','destination'] as $name) {
            $accounts[$name]=BankAccount::create(['bank_id'=>$db->table('banks')->value('id'),
                'description'=>'Cuenta temporal '.$name,'number'=>'TEMP-'.$name,
                'currency_type_id'=>'VES','initial_balance'=>'0']);
        }
        $controller=new BalanceController();
        $data=['from'=>'B:'.$accounts['origin']->id,'to'=>'B:'.$accounts['destination']->id,
            'amount_transform'=>'1','amount_transform_temp'=>'1'];
        $invalid=['1.0000000000001','0.0000000000001','1000000000000000000','-1','0',0.1,true,null,'invalid',['n'=>'1','d'=>'3']];
        foreach (['amount_transform_temp','amount_transform'] as $field) foreach ($invalid as $value) {
            $attempt=array_replace($data,[$field=>$value]);
            try {
                $controller->makeTransfer(Request::create('/','POST',['data'=>$attempt]));
                self::fail('El traslado debe rechazar '.$field.'='.json_encode($value).' antes de cualquier escritura.');
            } catch (ValidationException $error) {
                self::assertArrayHasKey('data.'.$field,$error->errors());
            }
            self::assertSame(0,$db->table('transfer_account_payments')->count());
            self::assertSame(0,$db->table('global_payments')->count());
            self::assertSame(0,$db->transactionLevel());
        }
        $max='999999999999999999.999999999999';
        $min='0.000000000001';
        foreach ([$max,$min,'0.1'] as $amount) {
            $data['amount_transform']=$data['amount_transform_temp']=$amount;
            $before=$db->table('transfer_account_payments')->count();
            $controller->makeTransfer(Request::create('/','POST',['data'=>$data]));
            $legs=TransferAccountPayment::orderBy('id')->skip($before)->get();
            self::assertCount(2,$legs);
            self::assertSame($amount,$legs[0]->amount);
            self::assertSame('-'.$amount,$legs[1]->amount);
            self::assertSame($amount,$legs[0]->getAmount());
            self::assertSame('-'.$amount,$legs[1]->getAmount());
            foreach ($legs as $leg) {
                self::assertTrue($leg->exactValue('amount')->isEqualTo($leg->getAmount()));
                $movement=\App\Services\Payments\Movement::row($leg->global_payment);
                self::assertSame('VES',$movement['currency_type_id']);
                self::assertTrue(Decimal::readRequired($movement,'amount')->isEqualTo($amount));
            }
            self::assertTrue(Decimal::sum($legs->map(fn($leg)=>$leg->exactValue('amount')))->isZero());
            self::assertSame(0,$db->transactionLevel());
        }
        self::assertSame(6,$db->table('transfer_account_payments')->count());
        self::assertSame(6,$db->table('global_payments')->count());
    }
}
