<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Cash;
use App\Support\Exact\Decimal;
use Modules\CashReport\Services\ClassifiedCashReport;

/** @group postgres-integration */
final class ClassifiedCreditPostgresTest extends \PHPUnit\Framework\TestCase
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
    public function test_cash_credit_accumulator_keeps_original_fractions_before_display_rounding(): void
    {
        [$db,,$base]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture);
        $db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $base['payment_condition_id']='02';$base['payments']=[];
        $cash=Cash::where('user_id',$base['user_id'])->where('state',true)->firstOrFail();
        $expected=['VES'=>Decimal::of(['n'=>'10049999999999999','d'=>'10000000000000000']),'USD'=>Decimal::of(1)];
        $documents=[];
        foreach (['VES','USD','USD','USD'] as $currency) {
            $input=$base;$input['currency_type_id']=$currency;
            $input['external_id']=(string)\Illuminate\Support\Str::uuid();
            $document=(new Facturalo())->save($input)->getDocument()->fresh();
            // Exercise stored historical fractional obligations; the report must not replace them with 12-place text.
            $value=$currency==='VES' ? $expected['VES'] : Decimal::of(1)->dividedBy(3);
            $snapshot=$document->calculation_snapshot;
            foreach (['total','total_payable'] as $field) $snapshot['values'][$field]=Decimal::fraction($value);
            $db->table('documents')->where('id',$document->id)->update(['total'=>Decimal::represent($value),
                'total_payable'=>Decimal::represent($value),'calculation_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]);
            $cash->cash_documents_credit()->updateOrCreate(['document_id'=>$document->id],['status'=>'PENDING']);
            $documents[$document->id]=$db->table('documents')->where('id',$document->id)->value('calculation_snapshot');
        }
        $count=$db->table('global_payments')->count();
        foreach (['cash_summary','cash_simple','income_summary','payments_associated'] as $type) {
            $payload=(new ClassifiedCashReport())->build($cash->fresh(),$type,[]);
            $data=$payload['classified'];
            $read=['calculation_snapshot'=>$data['credit_pending_snapshot'] ?? null]+$data['credit_pending'];
            self::assertSame('1.00',Decimal::format(Decimal::readRequired($read,'VES')));
            self::assertSame('1.00',Decimal::format(Decimal::readRequired($read,'USD')));
            foreach ($expected as $currency=>$amount) {
                self::assertSame(Decimal::fraction($amount),Decimal::fraction(Decimal::readRequired($read,$currency)));
            }
            self::assertSame('1.005',$data['credit_pending']['VES']);
            self::assertSame('1',$data['credit_pending']['USD']);
            self::assertSame(0,$db->transactionLevel());
        }
        foreach ($documents as $id=>$snapshot) self::assertSame($snapshot,$db->table('documents')->where('id',$id)->value('calculation_snapshot'));
        self::assertSame($count,$db->table('global_payments')->count());
    }
}
