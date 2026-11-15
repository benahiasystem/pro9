<?php
require_once dirname(__DIR__,3).'/tests/Unit/FiscalEmissionSchemaTest.php';
class TransferIntegrityTest extends \Tests\Unit\FiscalEmissionSchemaTest {
 public function test_same_currency_transfer_accepts_inconsistent_legs(): void {
  $method=new ReflectionMethod(\Tests\Unit\FiscalEmissionSchemaTest::class,'bootHkaFixture');
  [$db,$app,$input]=$method->invoke($this,false);
  $bankId=$db->table('banks')->value('id');
  $a=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'Origen','number'=>'AUD-ORIGIN','currency_type_id'=>'VES','initial_balance'=>'100']);
  $b=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'Destino','number'=>'AUD-DEST','currency_type_id'=>'VES','initial_balance'=>'0']);
  $request=\Illuminate\Http\Request::create('/', 'POST', ['data'=>['from'=>'B:'.$a->id,'to'=>'B:'.$b->id,'amount_transform_temp'=>'1','amount_transform'=>'1000']]);
  (new \Modules\Finance\Http\Controllers\BalanceController())->makeTransfer($request);
  $legs=\App\Models\Tenant\TransferAccountPayment::orderBy('id')->get()->map(fn($leg)=>$leg->getAttributes()['amount'])->all();
  fwrite(STDERR, 'AUDIT_LEGS='.json_encode($legs).PHP_EOL);
  self::assertSame(['1000.000000000000','-1.000000000000'],$legs);
 }
 public function test_zero_balance_cross_currency_transfer(): void {
  $method=new ReflectionMethod(\Tests\Unit\FiscalEmissionSchemaTest::class,'bootHkaFixture');
  [$db,$app,$input]=$method->invoke($this,false);
  $bankId=$db->table('banks')->value('id');
  $a=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'Origen USD','number'=>'AUD-ZERO-USD','currency_type_id'=>'USD','initial_balance'=>'0']);
  $b=\App\Models\Tenant\BankAccount::create(['bank_id'=>$bankId,'description'=>'Destino VES','number'=>'AUD-ZERO-VES','currency_type_id'=>'VES','initial_balance'=>'0']);
  $request=\Illuminate\Http\Request::create('/', 'POST', ['data'=>['from'=>'B:'.$a->id,'to'=>'B:'.$b->id,'amount_transform_temp'=>'1','amount_transform'=>'100']]);
  $controller=new \Modules\Finance\Http\Controllers\BalanceController();
  $controller->makeTransfer($request);
  $legs=\App\Models\Tenant\TransferAccountPayment::orderBy('id')->get();
  $currencies=$legs->pluck('currency_type_id')->all();
  $movementCurrencies=$legs->map(fn($leg)=>$leg->global_payment->report_context['currency_type_id'])->all();
  $balances=[$controller->getBalanceBySingleBankAcount($a)['balance'],$controller->getBalanceBySingleBankAcount($b)['balance']];
  fwrite(STDERR, 'AUDIT_CURRENCIES='.json_encode($currencies).' MOVEMENTS='.json_encode($movementCurrencies).' BALANCES='.json_encode($balances).PHP_EOL);
  self::assertSame(['VES','USD'],$currencies);
  self::assertSame(['VES','USD'],$movementCurrencies);
  self::assertSame(['-1','100'],$balances);
 }
 public function test_cash_credit_marks_small_positive_balance_processed(): void {
  $method=new ReflectionMethod(\Tests\Unit\FiscalEmissionSchemaTest::class,'bootHkaFixture');
  [$db,$app,$input]=$method->invoke($this,false);
  config(['view.compiled'=>sys_get_temp_dir(), 'view.paths'=>[base_path('resources/views')]]);
  $app->register(\Illuminate\View\ViewServiceProvider::class);
  $cash=\App\Models\Tenant\Cash::create(['user_id'=>$input['user_id'],'state'=>true,'date_opening'=>'2026-10-26','time_opening'=>'09:00:00','beginning_balance'=>'0']);
  $input['payments']=[];$input['payment_condition_id']='02';
  $engine=new \App\CoreFacturalo\Facturalo();$engine->forWebDocuments();$engine->save($input);$doc=$engine->getDocument();
  $snapshot=$doc->calculation_snapshot;$snapshot['values']['total_payable']=['n'=>'1','d'=>'1000'];
  $db->table('documents')->where('id',$doc->id)->update(['total_payable'=>'0.001','calculation_snapshot'=>json_encode($snapshot)]);
  $document=$doc->fresh();$before=$document->balance;
  $response=(new \App\Http\Controllers\Tenant\CashController())->cash_document(\Illuminate\Http\Request::create('/', 'POST',['document_id'=>$document->id]));
  $status=$db->table('cash_document_credits')->where('document_id',$document->id)->where('cash_id',$cash->id)->value('status');
  fwrite(STDERR,'AUDIT_BALANCE='.$before.' STATUS='.$status.PHP_EOL);
  self::assertSame('0.001',$before);self::assertSame('PROCESSED',$status);
 }
}
