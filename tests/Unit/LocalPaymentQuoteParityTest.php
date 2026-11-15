<?php
namespace Tests\Unit;

use App\Services\Fiscal\PaymentQuote;
use App\Support\Exact\{Decimal as D, TenderAllocation};
use Illuminate\Support\Facades\{DB,Schema};
use Tests\Support\SpeProfiles;

class LocalPaymentQuoteParityTest extends \Tests\TestCase
{
    public function test_local_engine_matches_the_server_for_shared_cases(): void
    {
        config()->set('database.connections.tenant',['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        DB::purge('tenant');
        Schema::connection('tenant')->create('payment_method_types',function ($table) {
            $table->string('id')->primary();$table->string('collection_kind');$table->boolean('is_active')->default(true);
        });
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'10','collection_kind'=>'cash'],['id'=>'04','collection_kind'=>'cash'],['id'=>'01','collection_kind'=>'account']]);
        $process = new \Symfony\Component\Process\Process(['node','tests/js/helpers/local-quote-runner.cjs'],base_path());
        $process->mustRun();
        $local = json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
        $fixtures = json_decode(file_get_contents(base_path('tests/fixtures/local-payment-quotes.json')),true);
        foreach ($fixtures as $i=>$case) {
            $rows = array_map(fn ($row)=>array_replace(['date_of_payment'=>$case['date'] ?? '2026-09-10','exchange_rate'=>$case['rate']],$row),$case['payments']);
            if (($case['kind'] ?? '01') === '80') {
                $prepared = array_map(function ($row) use ($case) {
                    $original=D::of(D::represent($row['original_amount'],2));
                    return array_replace($row,['payment'=>D::represent(D::convert($original,$row['currency_type_id'],$case['currency'],$row['exchange_rate']),2)]);
                },$rows);
                $allocated = TenderAllocation::calculate($case['due'],$prepared,['10','04']);
                foreach ($allocated as $j=>$row) {
                    $change=D::convert($row['change'],$case['currency'],$row['currency_type_id'],$row['exchange_rate']);
                    self::assertSame(D::represent($change,2,true),$local[$i]['quote']['payments'][$j]['change'],$case['name']);
                    self::assertSame(D::represent(D::of($rows[$j]['original_amount'])->minus($change),2,true),$local[$i]['quote']['payments'][$j]['original_amount'],$case['name']);
                }
                continue;
            }
            try {
                $quote=PaymentQuote::calculate($rows,$case['currency'],$case['rate'],$case['due'],fn()=>SpeProfiles::profile(['spe'=>$case['issuer_spe'] ?? true]),$case['enabled'] ?? true,$case['igtf_rate'] ?? '3');
                $quote += ['igtf_enabled'=>$case['enabled'] ?? true,'igtf_rate'=>$case['igtf_rate'] ?? '3'];
                $quote['payments']=array_map(fn($row)=>$row+['payment_exact'=>D::fraction($row['payment'])],$quote['payments']);
                self::assertArrayNotHasKey('error',$local[$i],$case['name']);
                self::assertEquals($quote,array_intersect_key($local[$i]['quote'],$quote),$case['name']);
            } catch (\Illuminate\Validation\ValidationException $e) {
                self::assertTrue($case['error'] ?? false,$case['name'].': '.json_encode($e->errors()));
                self::assertArrayHasKey('error',$local[$i],$case['name']);
            }
        }
    }
}
