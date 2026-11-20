<?php
namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\Models\Tenant\{Company, Item};
use Modules\Inventory\Models\ItemWarehouse;

/** @group postgres-integration */
final class InvoiceDeliveryEditPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();} finally {parent::tearDown();}
    }
    public function test_edit_reverses_only_the_delivery_quantity_actually_moved(): void {$this->checkDeliveryEdit(true);}
    public function test_edit_keeps_a_fully_delivered_invoice_without_another_stock_movement(): void {$this->checkDeliveryEdit(false);}

    private function checkDeliveryEdit(bool $partial): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): array {parent::setUp();return (new \ReflectionMethod(parent::class,'dispatchFixture'))->invoke($this);}
            public function stop(): void {parent::tearDown();}
        };
        [$db,$app,$raw,$invoice]=$this->fixture->start();
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('configurations')->update(['auto_send_hka'=>false]);
        $item=Item::findOrFail($raw['items'][0]['item_id']);$item->setExactValues(['stock'=>'10'])->save();
        $warehouse=ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',$invoice['items'][0]['warehouse_id'])->firstOrFail();
        $warehouse->setExactValues(['stock'=>'10'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>true]);
        $raw['items'][0]['quantity']=$partial?'0.1':'0.3';
        $first=(new Facturalo())->save(DispatchInput::set($raw))->getDocument();
        if ($partial) {
            $raw['creation_key']=(string)\Illuminate\Support\Str::uuid();$raw['items'][0]['quantity']='0.2';
            $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>false]);
            $second=(new Facturalo())->save(DispatchInput::set($raw))->getDocument();
            $invoice['dispatches_relateds']=[$first->number_full,$second->number_full];
        } else $invoice['dispatch_id']=$first->id;
        $invoice['items'][0]['quantity']='0.3';$invoice['items'][0]['unit_price']='3';
        $invoice['items'][0]['affectation_igv_type_id']='20';$invoice['payments']=[];$invoice['payment_condition_id']='02';
        $document=(new Facturalo())->forWebDocuments()->save($invoice)->getDocument()->fresh();
        self::assertSame('9.7',\App\Support\Exact\Decimal::represent($warehouse->fresh()->exactValue('stock')));
        $edit=array_replace($invoice,['id'=>$document->id,'series'=>$document->series,'number'=>$document->number,
            'received_retentions'=>[],'guarantee_fund'=>null]);
        foreach ([1,2] as $attempt) {
            $edit['items'][0]['record_id']=$document->fresh()->items->first()->id;
            // The editor does not need to send server-owned origin markers.
            unset($edit['items'][0]['item']['dispatch_inventory_quantity'],$edit['items'][0]['item']['dispatch_global_inventory_quantity']);
            $edit['items'][0]['item']['lots']=[['id'=>'9223372036854775807','has_sale'=>true]];
            $edit['items'][0]['item']['IdLoteSelected']='9223372036854775807';
            $edit['items'][0]['IdLoteSelected']='9223372036854775807';
            (new Facturalo())->forWebDocuments()->update($edit,$document->id);
            self::assertSame('9.7',\App\Support\Exact\Decimal::represent($warehouse->fresh()->exactValue('stock')));
            self::assertSame('9.7',\App\Support\Exact\Decimal::represent($item->fresh()->exactValue('stock')));
            self::assertSame($partial?'0.2':'0',\App\Support\Exact\Decimal::represent($document->fresh()->items->first()->item->dispatch_inventory_quantity));
            self::assertSame([],(array)$document->fresh()->items->first()->item->lots);
            self::assertNull($document->fresh()->items->first()->item->IdLoteSelected);
        }
        $other=\Modules\Inventory\Models\Warehouse::create(['establishment_id'=>$invoice['establishment_id'],'description'=>'Otro almacén de prueba']);
        $edit['items'][0]['record_id']=$document->fresh()->items->first()->id;$edit['items'][0]['warehouse_id']=$other->id;
        $before=[];foreach (['documents','document_items','items','item_warehouse','kardex','inventory_kardex'] as $table) $before[$table]=$db->table($table)->orderBy('id')->get()->toJson();
        try {(new Facturalo())->forWebDocuments()->update($edit,$document->id);self::fail('La partida heredada debe conservar su almacén.');}
        catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('items.warehouse_id',$error->errors());}
        foreach ($before as $table=>$rows) self::assertSame($rows,$db->table($table)->orderBy('id')->get()->toJson(),$table);
    }
}
