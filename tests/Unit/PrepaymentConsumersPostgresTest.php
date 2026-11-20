<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;

/** @group postgres-integration */
final class PrepaymentConsumersPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;
    private array $base;
    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): void {parent::setUp();}
            public function stop(): void {parent::tearDown();}
        };
        $this->fixture->start();
        [$this->db,,$this->base]=(new \ReflectionMethod($this->fixture,'bootHkaFixture'))->invoke($this->fixture,true);
        \Tests\Support\PostgresTestEnvironment::assertServer($this->db);
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
    }
    protected function tearDown(): void
    {
        try {if($this->fixture)$this->fixture->stop();} finally {parent::tearDown();}
    }
    private function input(string $total,bool $advance=false): array
    {
        $input=$this->base;$input['external_id']=(string)\Illuminate\Support\Str::uuid();
        $input['payments']=[];$input['payment_condition_id']='02';$input['fee']=[];
        $input['has_prepayment']=$advance;$input['pending_amount_prepayment']=$advance?$total:'0';
        $input['items'][0]['quantity']='1';$input['items'][0]['unit_price']=$total;
        $input['items'][0]['unit_value']=$total;$input['items'][0]['affectation_igv_type_id']='20';
        return $input;
    }
    private function reference(Document $source,string $total): array
    {
        return ['number'=>$source->number_full,'document_type_id'=>'02','amount'=>$total,'total'=>$total];
    }
    private function context(Document $source): Document
    {
        return new Document([
            'establishment_id'=>$source->establishment_id,'fiscal_environment'=>$source->fiscal_environment,
            'customer_id'=>$source->customer_id,'currency_type_id'=>$source->currency_type_id,
        ]);
    }
    public function test_actual_invoice_creation_consumes_a_valid_advance_reference(): void
    {
        $source=(new Facturalo())->save($this->input('0.3',true))->getDocument()->fresh();
        self::assertTrue($source->exactValue('pending_amount_prepayment')->isEqualTo('0.3'));
        $input=$this->input('1');$input['total_prepayment']='0.1';$input['prepayments']=[$this->reference($source,'0.1')];
        $target=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($target->exactValue('total')->isEqualTo('0.9'));
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('0.2'));
        self::assertSame(0,$this->db->transactionLevel());
    }

    /** @dataProvider mismatchedAdvanceCases */
    public function test_invoice_rejects_a_local_advance_for_another_customer_or_currency(string $mismatch): void
    {
        $source = (new Facturalo())->save($this->input('0.3', true))->getDocument()->fresh();
        $input = $this->input('1');
        $input['total_prepayment'] = '0.1';
        $input['prepayments'] = [$this->reference($source, '0.1')];
        if ($mismatch === 'customer') {
            $other = \App\Models\Tenant\Person::findOrFail($source->customer_id)->replicate();
            $other->name = 'Comprador temporal distinto';
            $other->number = '22345678';
            $other->save();
            $input['customer_id'] = $other->id;
        } else {
            $input['currency_type_id'] = 'USD';
            $input['exchange_rate_sale'] = '40';
        }

        try {
            (new Facturalo())->save($input);
            self::fail('The unmatched advance was applied to a new invoice.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            self::assertArrayHasKey('prepayments', $error->errors());
        }
        self::assertFalse(Document::where('external_id', $input['external_id'])->exists());
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('0.3'));
        self::assertSame(0, $this->db->transactionLevel());
    }

    public static function mismatchedAdvanceCases(): array
    {
        return [['customer'], ['currency']];
    }

    public function test_recording_an_external_advance_reference_keeps_its_existing_contract(): void
    {
        $input = $this->input('1');
        $input['total_prepayment'] = '0.1';
        $input['prepayments'] = [['number' => 'EX01-999', 'document_type_id' => '02', 'amount' => '0.1', 'total' => '0.1']];

        $document = (new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('EX01-999', ((array) $document->prepayments)[0]->number);
        self::assertTrue($document->exactValue('total')->isEqualTo('0.9'));
        self::assertSame(0, $this->db->transactionLevel());
    }

    public function test_explicit_decimal_deduction_preserves_the_subprecision_advance_balance(): void
    {
        $source = (new Facturalo())->save($this->input('1', true))->getDocument()->fresh();
        $source->setExactValues(['pending_amount_prepayment' => D::of('1/3')]);
        $source->saveQuietly();

        $amount = '0.333333333333';
        $input = $this->input('1');
        $input['total_prepayment'] = $amount;
        $input['prepayments'] = [$this->reference($source, $amount) + [
            'calculation_snapshot' => D::snapshot(['amount' => $amount, 'total' => $amount]),
        ]];
        $target = (new Facturalo())->save($input)->getDocument()->fresh();

        $source->refresh();
        self::assertTrue($target->exactValue('total')->isEqualTo('0.666666666667'));
        self::assertTrue($source->exactValue('pending_amount_prepayment')->isEqualTo('1/3000000000000'));
        self::assertFalse((bool) $source->was_deducted_prepayment);

        $target->state_type_id = '09';
        $target->save();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
        self::assertSame(0, $this->db->transactionLevel());
    }
    public function test_deduction_preserves_all_thirty_digits_from_the_source_snapshot(): void
    {
        $amount='123456789012345678.123456789012';
        $source=(new Facturalo())->save($this->input($amount,true))->getDocument()->fresh();
        self::assertTrue($source->exactValue('pending_amount_prepayment')->isEqualTo($amount));
        $facturalo=new Facturalo();$facturalo->setDocument($this->context($source));
        (new \ReflectionMethod($facturalo,'updatePrepaymentDocuments'))->invoke($facturalo,['prepayments'=>[$this->reference($source,'0.1')]]);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo(D::of($amount)->minus('0.1')));
    }
    public function test_restoration_preserves_the_partial_original_balance(): void
    {
        $remaining='123456789012345678.123456789012';$original=D::represent(D::of($remaining)->plus('0.1'));
        $source=(new Facturalo())->save($this->input($original,true))->getDocument()->fresh();
        $source->setExactValues(['pending_amount_prepayment'=>$remaining]);$source->saveQuietly();
        $context=$this->context($source);$context->prepayments=[$this->reference($source,'0.1')];
        $reflection=new \ReflectionClass(\Modules\Inventory\Providers\InventoryVoidedServiceProvider::class);
        (new \ReflectionMethod($reflection->getName(),'voidedWasDeductedPrepayment'))->invoke($reflection->newInstanceWithoutConstructor(),$context);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo($original));
    }

    public function test_deduction_and_restoration_use_the_original_fractional_pending_balance(): void
    {
        $source=(new Facturalo())->save($this->input('1',true))->getDocument()->fresh();
        // A valid partially consumed balance; the scalar is twelve positions, the original remains 1/3.
        $source->setExactValues(['pending_amount_prepayment'=>D::of('1/3')]);$source->saveQuietly();
        $facturalo=new Facturalo();$facturalo->setDocument($this->context($source));
        (new \ReflectionMethod($facturalo,'updatePrepaymentDocuments'))->invoke($facturalo,['prepayments'=>[$this->reference($source,'0.1')]]);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('7/30'));
        $context=$this->context($source);$context->prepayments=[$this->reference($source,'0.1')];
        $class=\Modules\Inventory\Providers\InventoryVoidedServiceProvider::class;
        (new \ReflectionMethod($class,'voidedWasDeductedPrepayment'))->invoke((new \ReflectionClass($class))->newInstanceWithoutConstructor(),$context);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
    }
    public function test_repeated_rows_cannot_overdraw_and_the_whole_deduction_rolls_back(): void
    {
        $source=(new Facturalo())->save($this->input('0.3',true))->getDocument()->fresh();
        $facturalo=new Facturalo();$facturalo->setDocument($this->context($source));
        try {
            $this->db->transaction(fn()=>(new \ReflectionMethod($facturalo,'updatePrepaymentDocuments'))->invoke($facturalo,
                ['prepayments'=>[$this->reference($source,'0.2'),$this->reference($source,'0.2')]]));
            self::fail('The advance was overdrawn.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            self::assertArrayHasKey('prepayments',$e->errors());
        }
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('0.3'));
        self::assertSame(0,(int)$source->fresh()->was_deducted_prepayment);
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_negative_deduction_cannot_create_advance_credit(): void
    {
        $source=(new Facturalo())->save($this->input('0.3',true))->getDocument()->fresh();
        $facturalo=new Facturalo();$facturalo->setDocument($this->context($source));
        try {
            $this->db->transaction(fn()=>(new \ReflectionMethod($facturalo,'updatePrepaymentDocuments'))->invoke($facturalo,
                ['prepayments'=>[$this->reference($source,'-0.1')]]));
            self::fail('A negative deduction created credit.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            self::assertArrayHasKey('prepayments',$e->errors());
        }
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('0.3'));
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_twenty_independent_locked_consumers_preserve_every_deduction(): void
    {
        $source=(new Facturalo())->save($this->input('2',true))->getDocument()->fresh();$workers=[];$pids=[];
        $this->db->beginTransaction();
        try {
            $this->db->table('documents')->where('id',$source->id)->lockForUpdate()->first();
            foreach (range(1,20) as $i) {
                $worker=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/prepayment_concurrency_worker.php'],dirname(__DIR__,2));
                $worker->setInput(json_encode(['connection'=>$this->db->getConfig(),'context'=>$this->context($source)->getAttributes(),
                    'reference'=>$this->reference($source,'0.1')],JSON_THROW_ON_ERROR));
                $worker->setTimeout(90);$worker->start();$workers[]=$worker;
            }
            $deadline=microtime(true)+60;
            foreach ($workers as $worker) {
                while (!preg_match('/^READY ([0-9]+)\n/',$worker->getOutput(),$match)) {
                    if (!$worker->isRunning() || microtime(true)>$deadline) self::fail('Advance worker did not reach barrier: '.$worker->getErrorOutput());
                    usleep(10000);
                }
                $pids[]=(int)$match[1];
            }
            self::assertCount(20,array_unique($pids));
            do {
                $waiting=$this->db->table('pg_stat_activity')->whereIn('pid',$pids)->where('wait_event_type','Lock')->count();
                if ($waiting===20) break;
                if (microtime(true)>$deadline) self::fail('Not all twenty advance workers reached the locked source.');
                usleep(10000);
            } while (true);
            self::assertSame(20,$waiting);$this->db->commit();
            foreach ($workers as $worker) {
                self::assertSame(0,$worker->wait(),$worker->getErrorOutput().$worker->getOutput());
                $output=preg_replace('/^READY [0-9]+\n/','',$worker->getOutput());
                self::assertTrue(json_decode($output,true,512,JSON_THROW_ON_ERROR)['ok']);
            }
            self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo(0));
            self::assertSame(1,(int)$source->fresh()->was_deducted_prepayment);
            self::assertSame(0,$this->db->transactionLevel());
        } finally {
            if ($this->db->transactionLevel()) $this->db->rollBack();
            foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
        }
    }

    public function test_rejected_then_voided_state_does_not_restore_the_advance_twice(): void
    {
        $source=(new Facturalo())->save($this->input('2',true))->getDocument()->fresh();
        $input=$this->input('1');$input['total_prepayment']='0.1';$input['prepayments']=[$this->reference($source,'0.1')];
        // Supply the existing branch context so this case reaches the two state callbacks even before the null-context correction.
        $engine=new Facturalo();$engine->setDocument($this->context($source));$target=$engine->save($input)->getDocument()->fresh();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1.9'));
        $target->state_type_id='09';$target->save();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('2'));
        $target=$target->fresh();$target->state_type_id='11';$target->save();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('2'));
        self::assertSame(0,(int)$source->fresh()->was_deducted_prepayment);
    }

    private function targetWithAdvance(Document $source,string $amount='0.1'): array
    {
        $input=$this->input('1');$input['total_prepayment']=$amount;$input['prepayments']=[$this->reference($source,$amount)];
        $engine=new Facturalo();$engine->setDocument($this->context($source));$target=$engine->save($input)->getDocument()->fresh();
        $input=array_replace($input,['id'=>$target->id,'series'=>$target->series,'number'=>$target->number]);
        return [$target,$input];
    }
    public function test_edit_with_the_same_reference_does_not_deduct_the_advance_again(): void
    {
        $source=(new Facturalo())->save($this->input('2',true))->getDocument()->fresh();[$target,$input]=$this->targetWithAdvance($source);
        (new Facturalo())->update($input,$target->id);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1.9'));
        self::assertTrue($target->fresh()->exactValue('total')->isEqualTo('0.9'));
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_edit_removing_the_reference_restores_the_previous_source_balance(): void
    {
        $source=(new Facturalo())->save($this->input('2',true))->getDocument()->fresh();[$target,$input]=$this->targetWithAdvance($source);
        $input['prepayments']=[];$input['total_prepayment']='0';(new Facturalo())->update($input,$target->id);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('2'));
        self::assertTrue($target->fresh()->exactValue('total')->isEqualTo('1'));
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_edit_invalid_replacement_rolls_back_header_lines_and_source_credit(): void
    {
        $source=(new Facturalo())->save($this->input('0.3',true))->getDocument()->fresh();[$target,$input]=$this->targetWithAdvance($source);
        $before=(array)$this->db->table('documents')->where('id',$target->id)->first();$lines=$this->db->table('document_items')->where('document_id',$target->id)->get()->map(fn($r)=>(array)$r)->all();
        $input['total_prepayment']='0.4';$input['prepayments']=[$this->reference($source,'0.4')];
        try {(new Facturalo())->update($input,$target->id);self::fail('Invalid replacement overdrawn the advance.');}
        catch (\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('prepayments',$e->errors());}
        self::assertSame($before,(array)$this->db->table('documents')->where('id',$target->id)->first());
        self::assertSame($lines,$this->db->table('document_items')->where('document_id',$target->id)->get()->map(fn($r)=>(array)$r)->all());
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('0.2'));
        self::assertSame(0,$this->db->transactionLevel());
    }
    public function test_edit_replacing_the_source_releases_only_the_old_reference(): void
    {
        $source=(new Facturalo())->save($this->input('2',true))->getDocument()->fresh();
        $other=(new Facturalo())->save($this->input('3',true))->getDocument()->fresh();[$target,$input]=$this->targetWithAdvance($source);
        $input['total_prepayment']='0.2';$input['prepayments']=[$this->reference($other,'0.2')];(new Facturalo())->update($input,$target->id);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('2'));
        self::assertTrue($other->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('2.8'));
        self::assertTrue($target->fresh()->exactValue('total')->isEqualTo('0.8'));
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_limited_restoration_compares_original_totals_and_skips_an_excess(): void
    {
        $input=$this->input('1',true);$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];$input['pending_amount_prepayment']='0.333333333333';
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($source->exactValue('total')));
        $source->setExactValues(['pending_amount_prepayment'=>D::of('7/30')]);$source->saveQuietly();
        $context=$this->context($source);$context->prepayments=[$this->reference($source,'0.1')];
        \App\Services\Fiscal\PrepaymentBalance::restore($context,true);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
        $before=(array)$this->db->table('documents')->where('id',$source->id)->first();
        \App\Services\Fiscal\PrepaymentBalance::restore($context,true);
        self::assertSame($before,(array)$this->db->table('documents')->where('id',$source->id)->first());
    }

    public function test_initial_advance_balance_retains_the_original_fraction(): void
    {
        $input=$this->input('1',true);$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $input['pending_amount_prepayment']='0.333333333333';
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($source->exactValue('total')));
        self::assertSame(['n'=>'1','d'=>'3'],D::fraction($source->exactValue('pending_amount_prepayment')));
        self::assertSame('0.333333333333',$this->db->table('documents')->where('id',$source->id)->value('pending_amount_prepayment'));
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_new_advance_credit_and_consumption_state_come_from_the_actual_invoice(): void
    {
        $input=$this->input('1',true);$input['pending_amount_prepayment']='999';$input['was_deducted_prepayment']=true;
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($source->exactValue('total')->isEqualTo('1'));
        self::assertTrue($source->exactValue('pending_amount_prepayment')->isEqualTo('1'));
        self::assertSame(0,(int)$source->was_deducted_prepayment);
        self::assertSame(0,$this->db->transactionLevel());
    }

    public function test_later_summary_does_not_reset_a_consumed_fractional_advance(): void
    {
        $input=$this->input('1',true);$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $input['pending_amount_prepayment']='0.333333333333';
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        $target=$this->input('1');$target['total_prepayment']='0.1';$target['prepayments']=[$this->reference($source,'0.1')];
        (new Facturalo())->save($target);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('7/30'));
        \App\Services\Fiscal\ExactDocumentSummary::save($source->fresh());
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('7/30'));
        self::assertTrue($source->fresh()->exactValue('total')->isEqualTo('1/3'));
        self::assertSame(0,$this->db->transactionLevel());
    }
}
