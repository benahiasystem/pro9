<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\{DocumentInput,DocumentUpdateInput};
use App\Support\Exact\Decimal as D;
use Tests\Unit\PrepaymentConsumersPostgresTest;

/** @group postgres-integration */
final class HeaderAdjustmentTransportPostgresTest extends \PHPUnit\Framework\TestCase
{
    private PrepaymentConsumersPostgresTest $fixture;
    protected function setUp(): void
    {
        $this->fixture=new PrepaymentConsumersPostgresTest();
        (new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);
    }
    protected function tearDown(): void
    {
        try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}
        finally{parent::tearDown();}
    }
    private function input(bool $advance=false): array
    {
        return (new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,'1',$advance);
    }
    private function adjustment(string $field,string $fraction): array
    {
        return [($field==='charges'?'charge_type_id':'discount_type_id')=>$field==='charges'?'50':'05',
            'description'=>'Ajuste temporal','factor'=>D::represent($fraction),'amount'=>D::represent($fraction),
            'amount_exact'=>D::fraction($fraction),'base'=>'1','is_amount'=>true];
    }
    private function normalize(string $class,string $field,array $rows): array
    {
        return (new \ReflectionMethod($class,$field))->invoke(null,[$field=>$rows]);
    }
    /** @dataProvider headerNormalizers */
    public function test_header_normalizers_retain_original_adjustment_amounts(string $class,string $field): void
    {
        $row=$this->adjustment($field,'1/3');$normalized=$this->normalize($class,$field,[$row]);
        self::assertSame(['n'=>'1','d'=>'3'],$normalized[0]['amount_exact']??null);
        self::assertSame('0.333333333333',$normalized[0]['amount']);
    }
    public static function headerNormalizers(): array
    {
        return ['create discount'=>[DocumentInput::class,'discounts'],'edit discount'=>[DocumentUpdateInput::class,'discounts'],
            'create charge'=>[DocumentInput::class,'charges'],'edit charge'=>[DocumentUpdateInput::class,'charges']];
    }
    public function test_invoice_creation_uses_the_charge_original_after_its_input_normalizer(): void
    {
        $input=$this->input();$input['charges']=$this->normalize(DocumentInput::class,'charges',[$this->adjustment('charges','1/7')]);
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($document->exactValue('total_charge')->isEqualTo('1/7'));
        self::assertTrue($document->exactValue('total')->isEqualTo('8/7'));
    }
    public function test_invoice_edit_keeps_the_discount_original_from_the_creation_contract(): void
    {
        $input=$this->input();$input['discounts']=$this->normalize(DocumentInput::class,'discounts',[$this->adjustment('discounts','1/3')]);
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($document->exactValue('total')->isEqualTo('2/3'));
        $input=array_replace($input,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number]);
        $input['discounts']=$this->normalize(DocumentUpdateInput::class,'discounts',$input['discounts']);
        (new Facturalo())->update($input,$document->id);
        self::assertTrue($document->fresh()->exactValue('total_discount')->isEqualTo('1/3'));
        self::assertTrue($document->fresh()->exactValue('total')->isEqualTo('2/3'));
    }
    public function test_editing_a_taxed_advance_invoice_retains_its_total_and_consumed_balance(): void
    {
        $input=$this->input(true);$input['affectation_type_prepayment']='10';
        $input['items'][0]['affectation_igv_type_id']='10';$input['items'][0]['quantity']='0.333333333333';
        $input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
        $source=(new Facturalo())->save($input)->getDocument()->fresh();$source->state_type_id='05';$source->save();
        $row=(new \Modules\Document\Http\Controllers\DocumentController())->prepayments('10')->firstWhere('id',$source->id);
        $target=$this->input();$target['items'][0]['affectation_igv_type_id']='10';$target['total_prepayment']=$row['total'];
        $target['prepayments']=[['number'=>$row['description'],'document_type_id'=>$row['document_type_id'],
            'amount'=>$row['amount'],'total'=>$row['total'],'calculation_snapshot'=>$row['calculation_snapshot']]];
        $discount=$this->adjustment('discounts','25/87');$discount['discount_type_id']='04';$discount['base']='0.862068965517';
        $target['discounts']=$this->normalize(DocumentInput::class,'discounts',[$discount]);
        $document=(new Facturalo())->save($target)->getDocument()->fresh();
        self::assertTrue($document->exactValue('total')->isEqualTo('2/3'));
        $target=array_replace($target,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number]);
        foreach(['prepayments','discounts'] as $field)$target[$field]=$this->normalize(DocumentUpdateInput::class,$field,$target[$field]);
        (new Facturalo())->update($target,$document->id);
        self::assertTrue($source->fresh()->exactValue('pending_amount_prepayment')->isZero());
        self::assertSame(1,(int)$source->fresh()->was_deducted_prepayment);
        self::assertTrue($document->fresh()->exactValue('total_prepayment')->isEqualTo('1/3'));
        self::assertTrue($document->fresh()->exactValue('total')->isEqualTo('2/3'));
    }

    public function test_combined_adjustments_remain_exact_after_repeated_edits(): void
    {
        $input=$this->input();
        $input['discounts']=$this->normalize(DocumentInput::class,'discounts',[$this->adjustment('discounts','1/3')]);
        $input['charges']=$this->normalize(DocumentInput::class,'charges',[$this->adjustment('charges','1/7')]);
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($document->exactValue('total')->isEqualTo('17/21'));
        $input=array_replace($input,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number]);
        foreach(['discounts','charges'] as $field)$input[$field]=$this->normalize(DocumentUpdateInput::class,$field,$input[$field]);
        foreach([1,2] as $repeat){
            (new Facturalo())->update($input,$document->id);
            $current=$document->fresh();
            self::assertTrue($current->exactValue('total_discount')->isEqualTo('1/3'));
            self::assertTrue($current->exactValue('total_charge')->isEqualTo('1/7'));
            self::assertTrue($current->exactValue('total')->isEqualTo('17/21'));
        }
    }
    public function test_plain_decimal_adjustments_keep_the_existing_fallback_contract(): void
    {
        $input=$this->input();$discount=$this->adjustment('discounts','0.1');$charge=$this->adjustment('charges','0.2');
        unset($discount['amount_exact'],$charge['amount_exact']);
        $input['discounts']=$this->normalize(DocumentInput::class,'discounts',[$discount]);
        $input['charges']=$this->normalize(DocumentInput::class,'charges',[$charge]);
        $document=(new Facturalo())->save($input)->getDocument()->fresh();
        self::assertTrue($document->exactValue('total')->isEqualTo('1.1'));
        $input=array_replace($input,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number]);
        foreach(['discounts','charges'] as $field)$input[$field]=$this->normalize(DocumentUpdateInput::class,$field,$input[$field]);
        (new Facturalo())->update($input,$document->id);
        self::assertTrue($document->fresh()->exactValue('total_discount')->isEqualTo('0.1'));
        self::assertTrue($document->fresh()->exactValue('total_charge')->isEqualTo('0.2'));
        self::assertTrue($document->fresh()->exactValue('total')->isEqualTo('1.1'));
    }
}
