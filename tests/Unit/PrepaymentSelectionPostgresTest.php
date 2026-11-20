<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Support\Exact\Decimal as D;
use Tests\Unit\PrepaymentConsumersPostgresTest;

/** @group postgres-integration */
final class PrepaymentSelectionPostgresTest extends \PHPUnit\Framework\TestCase
{
    private PrepaymentConsumersPostgresTest $fixture;
    protected function setUp(): void
    {
        $this->fixture=new PrepaymentConsumersPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
    }
    protected function tearDown(): void
    {
        try {(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally {parent::tearDown();}
    }
    private function input(string $total,bool $advance): array
    {
        return (new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,$total,$advance);
    }
    private function source(bool $fraction=true): Document
    {
        $input=$this->input($fraction?'1':'9007199254740993.01',true);
        $input['affectation_type_prepayment']='20';
        if($fraction){$input['items'][0]['quantity']='0.333333333333';$input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];}
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        $source->state_type_id='05';$source->save();
        return $source->fresh();
    }
    private function listing(Document $source): array
    {
        $controller=(new \ReflectionClass(\App\Http\Controllers\Tenant\DocumentController::class))->newInstanceWithoutConstructor();
        $row=$controller->table('prepayment_documents')->firstWhere('id',$source->id);
        self::assertNotNull($row,'The actual available advance must be present in the controller response.');
        return (array)$row;
    }
    public function test_actual_available_listing_preserves_the_original_fraction(): void
    {
        $source=$this->source();self::assertTrue($source->exactValue('total')->isEqualTo('1/3'));
        $row=$this->listing($source);
        self::assertSame('0.333333333333',$row['total']);
        self::assertSame('0.333333333333',$row['amount']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['total']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['amount']);
    }
    public function test_actual_available_listing_preserves_a_price_above_the_javascript_safe_integer(): void
    {
        $row=$this->listing($this->source(false));
        self::assertSame('9007199254740993.01',$row['total']);
        self::assertSame('9007199254740993.01',$row['amount']);
    }
    public function test_both_document_normalizers_retain_the_exact_reference_values(): void
    {
        $row=['number'=>'FF01-1','document_type_id'=>'02','amount'=>'0.333333333333','total'=>'0.333333333333',
            'calculation_snapshot'=>D::snapshot(['amount'=>D::of('1/3'),'total'=>D::of('1/3')])];
        foreach([\App\CoreFacturalo\Requests\Inputs\DocumentInput::class,\App\CoreFacturalo\Requests\Inputs\DocumentUpdateInput::class] as $class){
            $normalized=(new \ReflectionMethod($class,'prepayments'))->invoke(null,['prepayments'=>[$row]]);
            self::assertSame($row['calculation_snapshot'],$normalized[0]['calculation_snapshot']??null,$class);
        }
    }
    public function test_consuming_the_available_reference_exhausts_it_without_a_fractional_residue(): void
    {
        $source=$this->source();$row=$this->listing($source);
        $reference=['number'=>$row['description'],'document_type_id'=>$row['document_type_id'],'amount'=>$row['amount'],'total'=>$row['total']];
        if(isset($row['calculation_snapshot']))$reference['calculation_snapshot']=$row['calculation_snapshot'];
        $target=$this->input('1',false);$target['prepayments']=(new \ReflectionMethod(\App\CoreFacturalo\Requests\Inputs\DocumentInput::class,'prepayments'))->invoke(null,['prepayments'=>[$reference]]);
        $target['total_prepayment']=$row['total'];
        $document=(new Facturalo())->save($target)->getDocument()->fresh();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isZero());
        self::assertTrue($document->exactValue('total_prepayment')->isEqualTo('1/3'));
        self::assertTrue($document->exactValue('total')->isEqualTo('2/3'));
        self::assertSame(1,(int)$source->fresh()->was_deducted_prepayment);
    }

    public function test_actual_form_endpoint_retains_the_full_available_fraction(): void
    {
        $source=$this->source();
        $row=(new \Modules\Document\Http\Controllers\DocumentController())->prepayments('20')->firstWhere('id',$source->id);
        self::assertNotNull($row);
        self::assertSame('0.333333333333',$row['total']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['total']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['amount']);
    }

    public function test_advance_selector_preserves_a_positive_balance_projected_as_zero(): void
    {
        $source = $this->source();
        $selected = $this->listing($source);
        $input = $this->input('1', false);
        $input['total_prepayment'] = $selected['total'];
        $input['prepayments'] = [[
            'number' => $selected['description'],
            'document_type_id' => $selected['document_type_id'],
            'amount' => $selected['amount'],
            'total' => $selected['total'],
        ]];
        (new Facturalo())->save($input);

        $residual = '1/3000000000000';
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo($residual));
        $available = $this->listing($source);
        self::assertSame('0', $available['amount']);
        self::assertSame('0', $available['total']);
        self::assertSame(['n' => '1', 'd' => '3000000000000'], $available['calculation_snapshot']['values']['total']);

        $input = $this->input('1', false);
        $input['total_prepayment'] = $available['total'];
        $input['prepayments'] = [[
            'number' => $available['description'],
            'document_type_id' => $available['document_type_id'],
            'amount' => $available['amount'],
            'total' => $available['total'],
            'calculation_snapshot' => $available['calculation_snapshot'],
        ]];
        $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($invoice->exactValue('total_prepayment')->isEqualTo($residual));
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isZero());
    }

    private function scopedListing(array $params): \Illuminate\Support\Collection
    {
        $request = request();
        app()->instance('request', \Illuminate\Http\Request::create('/documents/prepayments/20', 'GET', $params));
        try {
            return (new \Modules\Document\Http\Controllers\DocumentController())->prepayments('20');
        } finally {
            app()->instance('request', $request);
        }
    }

    public function test_invoice_selector_filters_by_buyer_currency_branch_and_environment(): void
    {
        $source = $this->source();
        $db = (new \ReflectionProperty($this->fixture, 'db'))->getValue($this->fixture);
        $customer = \App\Models\Tenant\Person::findOrFail($source->customer_id)->replicate();
        $customer->name = 'Comprador de otro anticipo';
        $customer->number = '22345678';
        $customer->save();
        $branch = \App\Models\Tenant\Establishment::findOrFail($source->establishment_id)->replicate();
        $branch->description = 'Sucursal de prueba';
        $branch->save();

        $unavailable = [
            ['customer_id' => $customer->id],
            ['currency_type_id' => 'USD'],
            ['establishment_id' => $branch->id],
            ['fiscal_environment' => $source->fiscal_environment === 'demo' ? 'production' : 'demo'],
        ];
        foreach ($unavailable as $index => $context) {
            $row = (array) $db->table('documents')->where('id', $source->id)->first();
            unset($row['id']);
            $db->table('documents')->insert(array_replace($row, [
                'external_id' => (string) \Illuminate\Support\Str::uuid(),
                'number' => $index + 800,
                'filename' => 'anticipo-temporal-'.$index,
                'unique_filename' => 'anticipo-temporal-'.$index,
            ], $context));
        }

        $controller = new \Modules\Document\Http\Controllers\DocumentController();
        $params = ['customer_id' => (string) $source->customer_id, 'currency_type_id' => 'VES',
            'establishment_id' => (string) $source->establishment_id];
        $listing = $this->scopedListing($params);
        self::assertSame([$source->id], $listing->pluck('id')->all());
        self::assertSame(['n'=>'1','d'=>'3'], $listing->first()['calculation_snapshot']['values']['total']);
        self::assertCount(5, $controller->prepayments('20'));
    }

    public function test_invoice_selector_rejects_incomplete_or_invalid_scope(): void
    {
        foreach ([['currency_type_id' => 'USD'], ['customer_id' => '9223372036854775808',
            'currency_type_id' => 'USD', 'establishment_id' => '1']] as $params) {
            try {
                $this->scopedListing($params);
                self::fail('A scoped advance lookup must receive a valid complete scope.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                self::assertArrayHasKey('prepayments', $error->errors());
            }
        }
    }

    public function test_taxed_form_endpoint_keeps_the_net_and_gross_originals(): void
    {
        $input=$this->input('1',true);$input['affectation_type_prepayment']='10';
        $input['items'][0]['affectation_igv_type_id']='10';$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($source->exactValue('total')->isEqualTo('1/3'));
        $source->state_type_id='05';$source->save();
        $row=(new \Modules\Document\Http\Controllers\DocumentController())->prepayments('10')->firstWhere('id',$source->id);
        self::assertNotNull($row);
        self::assertSame('0.333333333333',$row['total']);
        self::assertSame('0.287356321839',$row['amount']);
        self::assertSame(['n'=>'1','d'=>'3'],$row['calculation_snapshot']['values']['total']);
        self::assertSame(['n'=>'25','d'=>'87'],$row['calculation_snapshot']['values']['amount']);
        self::assertNull((new \Modules\Document\Http\Controllers\DocumentController())->prepayments('20')->firstWhere('id',$source->id));
    }

    public function test_taxed_selection_consumption_and_rejection_preserve_both_originals(): void
    {
        $input=$this->input('1',true);$input['affectation_type_prepayment']='10';
        $input['items'][0]['affectation_igv_type_id']='10';$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $source=(new Facturalo())->save($input)->getDocument()->fresh();
        $source->state_type_id='05';$source->save();
        $row=(new \Modules\Document\Http\Controllers\DocumentController())->prepayments('10')->firstWhere('id',$source->id);
        $target=$this->input('1',false);$target['items'][0]['affectation_igv_type_id']='10';
        $target['total_prepayment']=$row['total'];
        $target['prepayments']=[['number'=>$row['description'],'document_type_id'=>$row['document_type_id'],
            'amount'=>$row['amount'],'total'=>$row['total'],'calculation_snapshot'=>$row['calculation_snapshot']]];
        $target['discounts']=[['discount_type_id'=>'04','description'=>'Anticipo temporal','is_amount'=>true,
            'amount'=>$row['amount'],'amount_exact'=>$row['calculation_snapshot']['values']['amount'],
            'base'=>'0.862068965517','factor'=>'0.333333333333']];
        $document=(new Facturalo())->save($target)->getDocument()->fresh();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isZero());
        self::assertTrue($document->exactValue('total_prepayment')->isEqualTo('1/3'));
        self::assertTrue($document->exactValue('total_taxed')->isEqualTo('50/87'));
        self::assertTrue($document->exactValue('total_igv')->isEqualTo('8/87'));
        self::assertTrue($document->exactValue('total')->isEqualTo('2/3'));
        $document->state_type_id='09';$document->save();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
        $document=$document->fresh();$document->state_type_id='11';$document->save();
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
    }

    /** @dataProvider invalidReferences */
    public function test_invalid_reference_rolls_back_without_changing_the_available_balance(string $kind,string $field): void
    {
        $source=$this->source();$db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
        $before=$db->table('documents')->count();
        $row=['number'=>$source->number_full,'document_type_id'=>'02','amount'=>'0.333333333333','total'=>'0.333333333333',
            'calculation_snapshot'=>D::snapshot(['amount'=>D::of('1/3'),'total'=>D::of('1/3')])];
        $target=$this->input('1',false);$target['total_prepayment']='0.333333333333';
        if($kind==='scalar')$row['amount']='0.1';
        if($kind==='version')$row['calculation_snapshot']['version']=0;
        if($kind==='json')$row['calculation_snapshot']='not-json';
        if($kind==='negative'){$row['total']='-0.333333333333';$row['calculation_snapshot']['values']['total']=['n'=>'-1','d'=>'3'];}
        if($kind==='header')$target['total_prepayment']='0.5';
        $target['prepayments']=[$row];
        try{(new Facturalo())->save($target);self::fail('Invalid advance reference was accepted.');}
        catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey($field,$error->errors());}
        self::assertSame($before,$db->table('documents')->count());
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isEqualTo('1/3'));
        self::assertSame(0,(int)$source->fresh()->was_deducted_prepayment);
        self::assertSame(0,$db->transactionLevel());
    }
    public static function invalidReferences(): array
    {
        return ['edited scalar with stale original'=>['scalar','prepayments.amount'],
            'invalid snapshot version'=>['version','prepayments.amount'],
            'invalid snapshot JSON'=>['json','prepayments.amount'],
            'negative original'=>['negative','prepayments.total'],
            'header differs from exact reference sum'=>['header','total_prepayment']];
    }
}
