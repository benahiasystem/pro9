<?php
namespace Tests\Unit;
use App\CoreFacturalo\Facturalo;
use App\Support\Exact\Decimal as D;
/** @group postgres-integration */
final class InvoiceEditedLineDiscountPostgresTest extends \PHPUnit\Framework\TestCase
{
 private PrepaymentConsumersPostgresTest $fixture;
 protected function setUp():void {$this->fixture=new PrepaymentConsumersPostgresTest();(new \ReflectionMethod($this->fixture,'setUp'))->invoke($this->fixture);}
 protected function tearDown():void {try{(new \ReflectionMethod($this->fixture,'tearDown'))->invoke($this->fixture);}finally{parent::tearDown();}}
 private function input():array{return (new \ReflectionMethod($this->fixture,'input'))->invoke($this->fixture,'1',false);}
 private function discount(string $amount,array $original):array{return ['discount_type_id'=>'01','description'=>'Descuento de prueba','factor'=>'0','percentage'=>null,'base'=>'1','amount'=>$amount,'amount_exact'=>$original,'is_amount'=>true];}
 public function test_invoice_line_edit_uses_the_new_decimal_instead_of_stale_original():void
 {
  $input=$this->input();$input['items'][0]['discounts']=[$this->discount('0.333333333333',['n'=>'1','d'=>'3'])];
  $invoice=(new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
  self::assertTrue($invoice->exactValue('total')->isEqualTo('2/3'));
  $line=$invoice->items()->firstOrFail();$row=$input['items'][0];$row['record_id']=$line->id;
  $row['discounts'][0]['amount']='0.1';
  $edit=array_replace($input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,'payments'=>[],'received_retentions'=>[],'guarantee_fund'=>null,'items'=>[$row]]);
  (new Facturalo())->update($edit,$invoice->id);
  self::assertTrue($invoice->fresh()->exactValue('total')->isEqualTo('0.9'));
  self::assertTrue($invoice->fresh()->items()->firstOrFail()->exactValue('total_discount')->isEqualTo('0.1'));
 }
 public function test_invoice_manual_discount_uses_edited_decimal_when_legacy_unrounded_amount_is_stale():void
 {
  $input=$this->input();$row=$this->discount('0.1',['n'=>'1','d'=>'3']);unset($row['amount_exact']);$row['amount_without_rounded']='0.333333333333';
  $input['items'][0]['discounts']=[$row];$document=(new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
  self::assertTrue($document->exactValue('total')->isEqualTo('0.9'));
 }

 public function test_invoice_distributed_discount_uses_the_new_decimal_after_edit(): void
 {
  $input = $this->input();
  $discount = $this->discount('0.333333333333', ['n'=>'1','d'=>'3']);
  $discount['from_global_distribution'] = true;
  $input['items'][0]['discounts'] = [$discount];
  $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
  self::assertTrue($invoice->exactValue('total')->isEqualTo('2/3'));

  $row = $input['items'][0];
  $row['record_id'] = $invoice->items()->firstOrFail()->id;
  $row['discounts'][0]['amount'] = '0.1';
  $edit = array_replace($input, ['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,
   'payments'=>[],'received_retentions'=>[],'guarantee_fund'=>null,'items'=>[$row]]);
  (new Facturalo())->update($edit, $invoice->id);

  self::assertTrue($invoice->fresh()->exactValue('total')->isEqualTo('0.9'));
  $saved = $invoice->items()->firstOrFail();
  self::assertTrue($saved->exactValue('total_discount')->isEqualTo('0.1'));
  $stored = \App\Support\Exact\JsonDecimals::decode($saved->getRawOriginal('discounts'));
  self::assertSame(['n'=>'1','d'=>'10'], $stored[0]['amount_exact']);
 }

 public function test_invoice_distributed_discount_resolves_stale_legacy_amount(): void
 {
  $input = $this->input();
  $discount = $this->discount('0.1', ['n'=>'1','d'=>'3']);
  unset($discount['is_amount'], $discount['amount_exact']);
  $discount['amount_without_rounded'] = '0.333333333333';
  $discount['from_global_distribution'] = true;
  $input['items'][0]['discounts'] = [$discount];
  $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();

  self::assertTrue($invoice->exactValue('total')->isEqualTo('0.9'));
  self::assertTrue($invoice->items()->firstOrFail()->exactValue('total_discount')->isEqualTo('0.1'));
 }

 public function test_invoice_distributed_discount_preserves_a_fraction_sent_without_decimal(): void
 {
  $input = $this->input();
  $discount = $this->discount('0.333333333333', ['n'=>'1','d'=>'3']);
  unset($discount['amount']);
  $discount['from_global_distribution'] = true;
  $input['items'][0]['discounts'] = [$discount];
  $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();

  self::assertTrue($invoice->exactValue('total')->isEqualTo('2/3'));
 }

 public function test_invoice_distributed_discount_preserves_a_legacy_cents_projection(): void
 {
  $input = $this->input();
  $discount = $this->discount('0.33', ['n'=>'1','d'=>'3']);
  unset($discount['is_amount'], $discount['amount_exact']);
  $discount['amount_without_rounded'] = '0.333333333333';
  $discount['from_global_distribution'] = true;
  $input['items'][0]['discounts'] = [$discount];
  $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();

  self::assertTrue($invoice->exactValue('total')->isEqualTo('0.666666666667'));
  self::assertTrue($invoice->items()->firstOrFail()->exactValue('total_discount')->isEqualTo('0.333333333333'));
 }

 public function test_malformed_or_negative_original_discounts_are_rejected_without_writes():void
 {
  $input=$this->input();$db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);$count=$db->table('documents')->count();
  foreach([['n'=>'-1','d'=>'3'],['n'=>'1','d'=>'0'],['n'=>'nan','d'=>'3']] as $fraction){
   $input['external_id']=(string)\Illuminate\Support\Str::uuid();$input['items'][0]['discounts']=[$this->discount('0.1',$fraction)];
   try{(new Facturalo())->forWebDocuments()->save($input);self::fail('No se admite una fracción inválida.');}
   catch(\Illuminate\Validation\ValidationException $error){self::assertNotEmpty($error->errors());}
   self::assertSame($count,$db->table('documents')->count());self::assertSame(0,$db->transactionLevel());
  }
 }

 public function test_edit_rejects_invalid_product_and_line_keys_before_writing():void
 {
  $input=$this->input();$input['items'][0]['discounts']=[$this->discount('0.333333333333',['n'=>'1','d'=>'3'])];
  $invoice=(new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
  $line=$invoice->items()->firstOrFail();$row=$input['items'][0];$row['record_id']=$line->id;
  $base=array_replace($input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,
   'payments'=>[],'received_retentions'=>[],'guarantee_fund'=>null,'items'=>[$row]]);
  $db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
  $before=[];foreach(['documents','document_items','item_warehouse','document_payments'] as $table)$before[$table]=$db->table($table)->orderBy('id')->get()->toArray();
  $invalidProducts=[(string)$line->item_id.'.9',(string)$line->item_id.'e0',(float)$line->item_id,true,'0','-1','9223372036854775808'];
  $cases=[];
  foreach($invalidProducts as $value){$edit=$base;$edit['items'][0]['item_id']=$value;$cases[]=$edit;}
  foreach(['9223372036854775808','18446744073709551615',true] as $value){$edit=$base;$edit['items'][0]['record_id']=$value;$cases[]=$edit;}
  $edit=$base;$edit['items'][]=$row;$cases[]=$edit;
  $edit=$base;unset($edit['items'][0]['record_id']);$edit['items'][0]['item_id']='1.9';$cases[]=$edit;
  foreach($cases as $edit){
   try{(new Facturalo())->forWebDocuments()->update($edit,$invoice->id);self::fail('Una clave técnica inválida no puede modificar la factura.');}
   catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey('items',$error->errors());}
   foreach($before as $table=>$rows)self::assertEquals($rows,$db->table($table)->orderBy('id')->get()->toArray(),$table);
   self::assertSame(0,$db->transactionLevel());
  }
  self::assertTrue($invoice->fresh()->exactValue('total')->isEqualTo('2/3'));
 }

 public function test_record_key_overflow_cannot_alias_the_largest_valid_signed_bigint():void
 {
  $db=(new \ReflectionProperty($this->fixture,'db'))->getValue($this->fixture);
  self::assertSame(8,PHP_INT_SIZE);
  $db->selectOne("SELECT setval(pg_get_serial_sequence('document_items','id'),CAST(? AS bigint),false)",['9223372036854775807']);
  $input=$this->input();$input['items'][0]['quantity']='0.333333333333';$input['items'][0]['quantity_input']=['amount'=>'1','unit_price'=>'3'];
  $invoice=(new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();$line=$invoice->items()->firstOrFail();
  self::assertSame('9223372036854775807',(string)$line->id);
  $before=$db->table('document_items')->orderBy('id')->get()->toArray();
  foreach(['9223372036854775808','18446744073709551615','92233720368547758070'] as $id){
   try{
    $db->transaction(fn()=>\App\Services\Fiscal\DocumentEditSettlements::restoreLineSnapshots($invoice,[['record_id'=>$id,'item_id'=>$line->item_id]]));
    self::fail('El overflow no puede recuperar el snapshot de PHP_INT_MAX.');
   }catch(\Illuminate\Validation\ValidationException $error){self::assertArrayHasKey('items',$error->errors());}
   self::assertEquals($before,$db->table('document_items')->orderBy('id')->get()->toArray());self::assertSame(0,$db->transactionLevel());
  }
  $restored=$db->transaction(fn()=>\App\Services\Fiscal\DocumentEditSettlements::restoreLineSnapshots($invoice,[
   ['record_id'=>'9223372036854775807','item_id'=>$line->item_id,'calculation_snapshot'=>D::snapshot(['quantity'=>'999'])]]));
  self::assertSame(['n'=>'1','d'=>'3'],D::fraction(D::read($restored[0],'quantity')));
  $new=$db->transaction(fn()=>\App\Services\Fiscal\DocumentEditSettlements::restoreLineSnapshots($invoice,[
   ['item_id'=>$line->item_id,'calculation_snapshot'=>D::snapshot(['quantity'=>'999'])]]));
  self::assertArrayNotHasKey('calculation_snapshot',$new[0]);self::assertSame(0,$db->transactionLevel());
 }
}
