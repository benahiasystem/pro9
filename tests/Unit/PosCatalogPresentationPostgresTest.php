<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\{Item, ItemUnitType};
use App\Support\Exact\Decimal as D;
use Modules\Inventory\Models\ItemWarehouse;

/** @group postgres-integration */
final class PosCatalogPresentationPostgresTest extends \PHPUnit\Framework\TestCase
{
    private $fixture;
    private $db;
    private array $input;

    protected function setUp(): void
    {
        $this->fixture=new class extends FiscalEmissionSchemaTest {
            public function start(): array {parent::setUp();return (new \ReflectionMethod(parent::class,'bootHkaFixture'))->invoke($this,false);}
            public function stop(): void {parent::tearDown();}
        };
        [$this->db,,$this->input]=$this->fixture->start();
        $this->db->table('companies')->update(['fiscal_emission_mode'=>'free_form']);
        $this->db->table('configurations')->update(['auto_send_hka'=>false]);
        $this->input['payments']=[];$this->input['payment_condition_id']='02';
        $this->input['items'][0]['quantity']='3';$this->input['items'][0]['unit_price']='0.333333333333';
        $this->input['items'][0]['affectation_igv_type_id']='20';
        $this->input['items'][0]['price_input']=['amount'=>['n'=>'1','d'=>'3'],'currency_type_id'=>'VES','includes_iva'=>true];
        $item=Item::findOrFail($this->input['items'][0]['item_id']);$item->setExactValues(['stock'=>'100'])->save();
        ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',$this->input['items'][0]['warehouse_id'])
            ->firstOrFail()->setExactValues(['stock'=>'100'])->save();
    }

    protected function tearDown(): void
    {
        try {if ($this->fixture) $this->fixture->stop();} finally {parent::tearDown();}
    }

    private function stock(): string
    {
        $row=$this->input['items'][0];
        return D::represent(ItemWarehouse::where('item_id',$row['item_id'])->where('warehouse_id',$row['warehouse_id'])->firstOrFail()->exactValue('stock'));
    }

    private function presentation(): ItemUnitType
    {
        $unit=new ItemUnitType(['item_id'=>$this->input['items'][0]['item_id'],'description'=>'Presentación temporal',
            'unit_type_id'=>'UND','price_default'=>2]);
        $unit->setExactValues(['quantity_unit'=>D::of('1/3'),'price1'=>D::of('1/3'),'price2'=>D::of('2/3')])->save();
        return $unit;
    }

    public function test_mobile_invoice_catalog_formats_presentation_prices_and_quantities_exactly(): void
    {
        $unit = $this->presentation();
        $unit->setExactValues(['quantity_unit'=>'9007199254740993.125', 'price2'=>'9007199254740992.995'])->save();
        $label = \App\Models\Tenant\PriceLabel::firstOrCreate(['position'=>1], ['label'=>'Precio temporal', 'is_active'=>true]);
        $quote = new \App\Models\Tenant\ItemUnitTypePrice([
            'item_unit_type_id'=>$unit->id, 'price_label_id'=>$label->id, 'is_active'=>true,
        ]);
        $quote->setExactValues(['price'=>'9007199254740993.01'])->save();
        $item = Item::findOrFail($unit->item_id);
        $controller = new \App\Http\Controllers\Tenant\Api\MobileController();
        $this->db->table('configurations')->update(['decimal_quantity'=>3]);

        $request = \Illuminate\Http\Request::create('/api/document/search-items', 'GET', ['input'=>$item->internal_id]);
        foreach ([$controller->searchItems($request), $controller->tables()] as $index=>$response) {
            self::assertTrue($response['success']);
            $product = collect($response['data']['items'])->firstWhere('id',$item->id);
            self::assertNotNull($product);
            $presentation = collect($product['item_unit_types'])->firstWhere('id',$unit->id);
            self::assertNotNull($presentation);
            self::assertSame($index === 0 ? '9007199254740993.13' : '9007199254740993.125', $presentation['quantity_unit']);
            self::assertSame('9007199254740993.01', $presentation['price1']);
            self::assertSame('9007199254740993.00', $presentation['price2']);
            self::assertSame('0.00', $presentation['price3']);
        }
    }

    public function test_pos_catalog_table_and_search_keep_price_projections_and_exact_sources(): void
    {
        $line = $this->input['items'][0];
        $item = Item::findOrFail($line['item_id']);
        $controller = new \App\Http\Controllers\Tenant\PosController();
        $request = \Illuminate\Http\Request::create('/pos/search_items', 'GET', ['input_item' => $item->internal_id]);
        $unit = $this->presentation();
        $factor = D::of('3000000000001/3000000000000');
        $unit->setExactValues(['quantity_unit' => $factor])->save();
        $label = \App\Models\Tenant\PriceLabel::firstOrCreate(['position' => 1], ['label' => 'Precio temporal', 'is_active' => true]);
        \App\Models\Tenant\ItemUnitTypePrice::create([
            'item_unit_type_id' => $unit->id, 'price_label_id' => $label->id, 'is_active' => true, 'price' => '2',
        ]);
        $catalogRow = $controller->search_items($request)['items']->sole('id', $item->id);
        $presentation = collect($catalogRow['item_unit_types'])->sole('id', $unit->id);
        self::assertSame('1', $presentation['quantity_unit']);
        self::assertTrue(D::readRequired($presentation, 'quantity_unit')->isEqualTo($factor));
        self::assertSame('2', $presentation['prices'][0]['price']);

        foreach (['1/3', '9007199254740993.01'] as $catalogPrice) {
            $item->setExactValues(['sale_unit_price' => D::of($catalogPrice)])->save();
            $table = $controller->table('items')->sole('id', $item->id);
            $search = $controller->search_items($request)['items']->sole('id', $item->id);
            foreach ([$table, $search] as $entry) {
                self::assertSame(D::represent($catalogPrice), $entry['sale_unit_price']);
                self::assertSame(D::represent($catalogPrice), $entry['aux_sale_unit_price']);
                self::assertSame(D::represent($catalogPrice), $entry['edit_sale_unit_price']);
                self::assertTrue(D::readRequired($entry, 'sale_unit_price')->isEqualTo($catalogPrice));
            }
        }

        (new \App\Models\Tenant\ItemWarehousePrice([
            'item_id' => $item->id, 'warehouse_id' => $line['warehouse_id'],
        ]))->setExactValues(['price' => D::of('2/3')])->save();
        $search = $controller->search_items($request)['items']->sole('id', $item->id);
        self::assertSame('0.666666666667', $search['sale_unit_price']);
        self::assertSame('9007199254740993.01', $search['aux_sale_unit_price']);
        self::assertSame('9007199254740993.01', $search['edit_sale_unit_price']);
        self::assertTrue(D::readRequired($search, 'sale_unit_price')->isEqualTo('2/3'));

        $invoiceReload = \Illuminate\Http\Request::create('/pos/table/items', 'GET', ['document_type_id' => '01']);
        $saleNoteReload = \Illuminate\Http\Request::create('/pos/table/items', 'GET', ['document_type_id' => '80']);
        $this->db->table('configurations')->update(['active_warehouse_prices' => false]);
        $disabled = $controller->table('items', $invoiceReload)->sole('id', $item->id);
        self::assertSame('9007199254740993.01', $disabled['sale_unit_price']);
        $this->db->table('configurations')->update(['active_warehouse_prices' => true]);
        $invoiceEntry = $controller->table('items', $invoiceReload)->sole('id', $item->id);
        self::assertSame('0.666666666667', $invoiceEntry['sale_unit_price']);
        self::assertTrue(D::readRequired($invoiceEntry, 'sale_unit_price')->isEqualTo('2/3'));
        $noteEntry = $controller->table('items', $saleNoteReload)->sole('id', $item->id);
        self::assertSame('9007199254740993.01', $noteEntry['sale_unit_price']);

        $input = $this->input;
        $input['items'][0]['unit_price'] = $invoiceEntry['sale_unit_price'];
        $input['items'][0]['price_input']['amount'] = D::fraction(D::readRequired($invoiceEntry, 'sale_unit_price'));
        $input['items'][0]['item']['presentation'] = ['id' => $unit->id, 'quantity_unit' => '1'];
        $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
        self::assertSame('VES', $invoice->currency_type_id);
        self::assertTrue($invoice->exactValue('total')->isEqualTo('2'));
        self::assertSame('96.999999999999', $this->stock());

        \App\Models\Tenant\ItemWarehousePrice::where('item_id', $item->id)->where('warehouse_id', $line['warehouse_id'])->delete();
        $fallback = $controller->table('items', $invoiceReload)->sole('id', $item->id);
        self::assertSame('9007199254740993.01', $fallback['sale_unit_price']);
    }

    public function test_web_invoice_catalog_preserves_variation_price_and_stock_threshold(): void
    {
        $row = $this->input['items'][0];
        $parent = Item::findOrFail($row['item_id']);
        $variation = $parent->replicate();
        $variation->internal_id = 'INVOICE-VARIATION-EXACT';
        $variation->setExactValues([
            'sale_unit_price' => '9007199254740993.01',
            'stock_min' => '9007199254740992.99',
        ]);
        $parent->variations()->save($variation);
        (new ItemWarehouse(['item_id' => $variation->id, 'warehouse_id' => $row['warehouse_id']]))
            ->setExactValues(['stock' => '9007199254740993.00'])->save();

        $controller = new \Modules\Document\Http\Controllers\DocumentController();
        $response = $controller->searchItemById($parent->id);
        $result = $response['items']->sole('id', $parent->id);
        $option = collect($result['variations'])->sole('id', $variation->id);
        self::assertSame('9007199254740993.01', $option['sale_unit_price']);
        self::assertSame('9007199254740992.99', $option['stock_min']);
        self::assertTrue(D::of($option['stock'])->isGreaterThan(D::of($option['stock_min'])));

        $nonInvoice = $parent->loadCount('variations')->getDataToItemModal();
        $option = collect($nonInvoice['variations'])->sole('id', $variation->id);
        self::assertIsFloat($option['sale_unit_price']);
        self::assertIsFloat($option['stock_min']);
    }

    public function test_web_invoice_catalog_keeps_fractional_warehouse_prices_for_products_and_services(): void
    {
        $line = $this->input['items'][0];
        $product = Item::findOrFail($line['item_id']);
        $service = Item::where('unit_type_id', 'SERV')->where('active', true)->firstOrFail();
        $controller = new \Modules\Document\Http\Controllers\DocumentController();

        foreach ([$product, $service] as $item) {
            $price = new \App\Models\Tenant\ItemWarehousePrice([
                'item_id' => $item->id,
                'warehouse_id' => $line['warehouse_id'],
            ]);
            $price->setExactValues(['price' => D::of('1/3')])->save();

            $result = $controller->searchItemById($item->id)['items']->sole('id', $item->id);
            self::assertSame('0.333333333333', $result['sale_unit_price']);
            self::assertSame(['n' => '1', 'd' => '3'], $result['calculation_snapshot']['values']['sale_unit_price']);
        }
    }

    public function test_invoice_accepts_a_quantity_calculated_from_the_entered_amount_and_fractional_price(): void
    {
        $amount = '123456789.123456789012';
        $price = D::of('0.987654321019');
        $quantity = D::of($amount)->dividedBy($price)->simplified();
        $row = $this->input['items'][0];
        $item = Item::findOrFail($row['item_id']);
        $item->setExactValues(['stock'=>'200000000'])->save();
        $warehouse = ItemWarehouse::where('item_id',$item->id)->where('warehouse_id',$row['warehouse_id'])->firstOrFail();
        $warehouse->setExactValues(['stock'=>'200000000'])->save();
        $this->input['items'][0] = array_replace($row, [
            'quantity'=>D::represent($quantity), 'unit_price'=>D::represent($price),
            'quantity_input'=>['amount'=>$amount, 'unit_price'=>D::fraction($price)],
            'price_input'=>['amount'=>D::fraction($price), 'currency_type_id'=>'VES', 'includes_iva'=>true],
        ]);

        $invoice = (new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        self::assertTrue($invoice->exactValue('total')->isEqualTo($amount));
        self::assertTrue($invoice->items()->firstOrFail()->exactValue('quantity')->isEqualTo($quantity));
        self::assertTrue($warehouse->fresh()->exactValue('stock')->isEqualTo(D::of('200000000')->minus($quantity)));
    }

    public function test_invoice_and_credit_freeze_the_server_presentation_fraction_through_edit_and_catalog_removal(): void
    {
        $unit=$this->presentation();
        $this->input['items'][0]['item']['presentation']=['id'=>$unit->id,'quantity_unit'=>'999','quantity_unit_exact'=>['n'=>'999','d'=>'1']];
        $lot=$this->db->table('item_lots_group')->insertGetId(['item_id'=>$unit->item_id,'code'=>'POS-THIRD','quantity'=>'10','date_of_due'=>'2027-11-30']);
        $this->input['items'][0]['item']['IdLoteSelected']=[['id'=>$lot,'compromise_quantity'=>'3']];
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $line=$invoice->items()->firstOrFail();
        self::assertSame('1',(string)$invoice->exactValue('total'));
        self::assertSame('99',$this->stock());self::assertSame('99',(string)Item::findOrFail($unit->item_id)->exactValue('stock'));
        self::assertSame('9',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        self::assertSame('1/3',(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($line->item));
        self::assertSame(['n'=>'1','d'=>'3'],(array)$line->item->inventory_components[0]->quantity_exact);
        $unit->setExactValues(['quantity_unit'=>'9'])->save();
        $edited=$this->input;$edited['items'][0]['record_id']=$line->id;
        $edited=array_replace($edited,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,'received_retentions'=>[],'guarantee_fund'=>null]);
        (new Facturalo())->forWebDocuments()->update($edited,$invoice->id);
        self::assertSame('99',$this->stock());self::assertSame('99',(string)Item::findOrFail($unit->item_id)->exactValue('stock'));
        self::assertSame('9',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        self::assertSame('1/3',(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($invoice->fresh()->items->first()->item));
        $unit->delete();
        $noteInput=(new \ReflectionMethod($this->fixture,'noteInput'))->invoke($this->fixture,$this->input,$invoice->fresh(),'07','01',[]);
        $note=(new Facturalo())->forWebDocuments()->save($noteInput)->getDocument()->fresh();
        self::assertSame('1',(string)$note->exactValue('total'));
        self::assertSame('100',$this->stock());self::assertSame('100',(string)Item::findOrFail($unit->item_id)->exactValue('stock'));
        self::assertSame('10',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        self::assertSame('1/3',(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($note->items->first()->item));
    }

    public function test_foreign_and_malformed_presentation_ids_fail_without_invoice_or_stock_writes(): void
    {
        $unit=$this->presentation();$other=Item::findOrFail($unit->item_id)->replicate();
        $other->internal_id='POS-FOREIGN';$other->description='Otro producto temporal';$other->save();
        $foreign=ItemUnitType::create(['item_id'=>$other->id,'description'=>'Ajena','unit_type_id'=>'UND','quantity_unit'=>'6']);
        $tables=['documents','document_items','inventory_kardex','kardex'];$before=[];
        foreach ($tables as $table) $before[$table]=$this->db->table($table)->count();
        foreach ([$foreign->id,(string)$unit->id.'.9',(string)$unit->id.'e0','9223372036854775808',true] as $id) {
            $case=$this->input;$case['external_id']=(string)\Illuminate\Support\Str::uuid();
            $case['items'][0]['item']['presentation']=['id'=>$id,'quantity_unit'=>'1'];
            try {(new Facturalo())->forWebDocuments()->save($case);self::fail('La presentación debe corresponder al producto.');}
            catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('items.presentation',$error->errors());}
            foreach ($before as $table=>$count) self::assertSame($count,$this->db->table($table)->count(),$table);
            self::assertSame('100',$this->stock());self::assertSame(0,$this->db->transactionLevel());
        }
    }

    public function test_a_scalar_presentation_without_an_original_fraction_remains_a_finite_decimal(): void
    {
        $this->input['items'][0]['item']['presentation']=['quantity_unit'=>'0.333333333333','quantity_unit_exact'=>['n'=>'1','d'=>'3'],
            'calculation_snapshot'=>D::snapshot(['quantity_unit'=>D::of('1/7')])];
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        self::assertSame('99.000000000001',$this->stock());
        self::assertSame('333333333333/1000000000000',(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($invoice->items->first()->item));
    }

    public function test_invoice_applies_each_distinct_scalar_presentation_to_physical_stock(): void
    {
        $first = $this->input['items'][0];
        $first['quantity'] = '1';
        $first['item']['presentation'] = ['quantity_unit' => '1'];
        $second = $first;
        $second['item']['presentation']['quantity_unit'] = '2';
        $this->input['items'] = [$first, $second];

        $invoice = (new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $lines = $invoice->items()->orderBy('id')->get();

        self::assertCount(2, $lines);
        self::assertSame('2/3', (string) $invoice->exactValue('total'));
        self::assertSame(['1', '2'], $lines->map(
            static fn ($line) => (string) \App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($line->item)
        )->all());
        self::assertSame('97', $this->stock());
        self::assertSame('97', D::represent(Item::findOrFail($first['item_id'])->exactValue('stock')));
    }

    public function test_invoice_keeps_distinct_catalog_presentations_with_adjacent_bigint_ids(): void
    {
        $ids = ['9007199254740992', '9007199254740993'];
        $line = $this->input['items'][0];
        $lines = [];
        foreach ($ids as $index => $id) {
            $unit = new ItemUnitType([
                'item_id' => $line['item_id'], 'description' => 'Caja temporal', 'unit_type_id' => 'UND',
            ]);
            $unit->forceFill(['id' => $id])->setExactValues(['quantity_unit' => '2'])->save();
            $row = $line;
            $row['quantity'] = $index === 0 ? '2' : '1';
            $row['item']['presentation'] = ['id' => $id, 'quantity_unit' => '2'];
            $lines[] = $row;
        }

        $invoice = (new Facturalo())->forWebDocuments()->save(array_replace($this->input, ['items' => $lines]))
            ->getDocument()->fresh();
        self::assertSame('VES', $invoice->currency_type_id);
        self::assertTrue($invoice->exactValue('total')->isEqualTo('1'));
        self::assertSame($ids, $invoice->items()->orderBy('id')->get()->map(fn ($row) => (string) $row->item->presentation->id)->all());
        self::assertSame('94', $this->stock());
    }

    public function test_editing_a_scalar_presentation_changes_its_explicit_factor(): void
    {
        $this->input['items'][0]['item']['presentation']=['quantity_unit'=>'6'];
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        self::assertSame('82',$this->stock());
        $edit=array_replace($this->input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,'received_retentions'=>[],'guarantee_fund'=>null]);
        $edit['items'][0]['record_id']=$invoice->items->first()->id;$edit['items'][0]['item']['presentation']=['quantity_unit'=>'3'];
        (new Facturalo())->forWebDocuments()->update($edit,$invoice->id);
        self::assertSame('91',$this->stock());
        self::assertSame('3',(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($invoice->fresh()->items->first()->item));
    }

    public function test_edit_preserves_a_pack_after_its_catalog_type_changes(): void {$this->assertOriginalProductType(true);}
    public function test_edit_preserves_an_individual_after_its_catalog_type_changes(): void {$this->assertOriginalProductType(false);}

    public function test_new_edited_line_cannot_forge_an_inherited_zero_inventory_movement(): void
    {
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $row=$this->input['items'][0];$row['record_id']=$invoice->items->first()->id;
        $extra=$this->input['items'][0];
        foreach (['dispatch_inventory_quantity','dispatch_global_inventory_quantity'] as $field) $extra['item'][$field]=['n'=>'0','d'=>'1'];
        $extra['item']['order_inventory_inherited']=true;
        $edit=array_replace($this->input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,
            'received_retentions'=>[],'guarantee_fund'=>null,'items'=>[$row,$extra]]);
        (new Facturalo())->forWebDocuments()->update($edit,$invoice->id);
        self::assertSame('94',$this->stock());
        self::assertSame('94',D::represent(Item::findOrFail($row['item_id'])->exactValue('stock')));
        foreach ($invoice->fresh()->items as $line) {
            self::assertFalse(isset($line->item->dispatch_inventory_quantity));
            self::assertFalse(isset($line->item->dispatch_global_inventory_quantity));
            self::assertFalse(isset($line->item->order_inventory_inherited));
        }
    }

    public function test_edit_cannot_invent_document_origins_that_suppress_inventory(): void
    {
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $fields=['order_note_id','dispatch_id','sale_notes_relateds','dispatches_relateds'];$before=$invoice->only($fields);
        $edit=array_replace($this->input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,
            'received_retentions'=>[],'guarantee_fund'=>null,'order_note_id'=>9223372036854775807,'dispatch_id'=>9223372036854775807,
            'sale_notes_relateds'=>['NV01-999999'],'dispatches_relateds'=>['TT01-999999']]);
        $edit['items'][0]['record_id']=$invoice->items->first()->id;
        (new Facturalo())->forWebDocuments()->update($edit,$invoice->id);
        self::assertSame($before,$invoice->fresh()->only($fields));
        self::assertSame('97',$this->stock());
        self::assertSame('97',D::represent(Item::findOrFail($edit['items'][0]['item_id'])->exactValue('stock')));
    }

    public function test_invoice_rejects_a_sold_serial_and_a_serial_from_another_warehouse_without_writes(): void
    {
        $itemId=$this->input['items'][0]['item_id'];$warehouseId=$this->input['items'][0]['warehouse_id'];
        $other=\Modules\Inventory\Models\Warehouse::create(['establishment_id'=>$this->input['establishment_id'],'description'=>'Almacén de serial temporal']);
        foreach ([[$warehouseId,true],[$other->id,false]] as [$location,$sold]) {
            $serial=\Modules\Item\Models\ItemLot::create(['item_id'=>$itemId,'warehouse_id'=>$location,
                'series'=>'POS-SERIAL-'.$location,'date'=>'2026-09-10','has_sale'=>$sold,'item_loteable_type'=>Item::class,'item_loteable_id'=>$itemId]);
            $case=$this->input;$case['external_id']=(string)\Illuminate\Support\Str::uuid();
            $case['items'][0]['item']['lots']=[['id'=>$serial->id,'has_sale'=>true]];
            $before=[];foreach (['documents','document_items','items','item_warehouse','item_lots','kardex','inventory_kardex'] as $table) {
                $before[$table]=$this->db->table($table)->orderBy('id')->get()->toJson();
            }
            try {(new Facturalo())->forWebDocuments()->save($case);self::fail('El serial debe estar disponible en el almacén de la factura.');}
            catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('items.serial_ids',$error->errors());}
            foreach ($before as $table=>$rows) self::assertSame($rows,$this->db->table($table)->orderBy('id')->get()->toJson(),$table);
            self::assertSame(0,$this->db->transactionLevel());
        }
    }

    public function test_invoice_rejects_noncanonical_lot_keys_without_moving_stock(): void
    {
        $itemId = $this->input['items'][0]['item_id'];
        $lot = \Modules\Item\Models\ItemLotsGroup::create([
            'item_id' => $itemId, 'code' => 'LOT-IDENTITY', 'quantity' => '10', 'date_of_due' => '2027-11-30',
        ]);

        foreach ([(string) $lot->id.'x', '0'.$lot->id, '9223372036854775808'] as $invalid) {
            foreach ([$invalid, [['id' => $invalid, 'compromise_quantity' => '3']]] as $selection) {
                $input = $this->input;
                $input['items'][0]['item']['IdLoteSelected'] = $selection;
                try {
                    (new Facturalo())->forWebDocuments()->save($input);
                    self::fail('La factura debe rechazar la clave de lote no canónica.');
                } catch (\Illuminate\Validation\ValidationException $error) {
                    self::assertArrayHasKey('items.IdLoteSelected', $error->errors());
                }
                self::assertSame('10', D::represent($lot->fresh()->exactValue('quantity')));
                self::assertSame('100', $this->stock());
                self::assertSame(0, $this->db->table('documents')->count());
                self::assertSame(0, $this->db->table('inventory_kardex')->count());
            }
        }

        $input = $this->input;
        $input['items'][0]['item']['IdLoteSelected'] = [['id' => (string) $lot->id, 'compromise_quantity' => '3']];
        (new Facturalo())->forWebDocuments()->save($input);
        self::assertSame('7', D::represent($lot->fresh()->exactValue('quantity')));
        self::assertSame('97', $this->stock());
    }

    public function test_failed_recreation_rolls_back_global_warehouse_lot_serial_and_movement_reversals(): void
    {
        $unit=$this->presentation();$this->input['items'][0]['item']['presentation']=['id'=>$unit->id,'quantity_unit'=>'0.333333333333'];
        $lot=$this->db->table('item_lots_group')->insertGetId(['item_id'=>$unit->item_id,'code'=>'POS-ROLLBACK','quantity'=>'10','date_of_due'=>'2027-11-30']);
        $serial=\Modules\Item\Models\ItemLot::create(['item_id'=>$unit->item_id,'warehouse_id'=>$this->input['items'][0]['warehouse_id'],
            'series'=>'POS-ROLLBACK-SERIAL','date'=>'2026-09-10','has_sale'=>false,
            'item_loteable_type'=>Item::class,'item_loteable_id'=>$unit->item_id]);
        $this->input['items'][0]['item']['IdLoteSelected']=[['id'=>$lot,'compromise_quantity'=>'3']];
        $this->input['items'][0]['item']['lots']=[['id'=>$serial->id,'has_sale'=>true]];
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $line=$invoice->items->first();self::assertTrue($serial->fresh()->has_sale);
        $before=[];
        foreach (['documents','document_items','items','item_warehouse','item_lots_group','item_lots','kardex','inventory_kardex'] as $table) {
            $before[$table]=$this->db->table($table)->orderBy('id')->get()->toJson();
        }
        $edit=array_replace($this->input,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,'received_retentions'=>[],'guarantee_fund'=>null]);
        $edit['items'][0]['record_id']=$line->id;$edit['items'][0]['item']['presentation']['id']=9223372036854775807;
        try {(new Facturalo())->forWebDocuments()->update($edit,$invoice->id);self::fail('La nueva presentación no existe.');}
        catch (\Illuminate\Validation\ValidationException $error) {self::assertArrayHasKey('items.presentation',$error->errors());}
        foreach ($before as $table=>$rows) self::assertSame($rows,$this->db->table($table)->orderBy('id')->get()->toJson(),$table);
        self::assertSame(0,$this->db->transactionLevel());
        $edit['items'][0]['item']['presentation']['id']=$unit->id;
        foreach ([1,2] as $attempt) {
            $edit['items'][0]['record_id']=$invoice->fresh()->items->first()->id;
            (new Facturalo())->forWebDocuments()->update($edit,$invoice->id);
            self::assertSame('99',$this->stock());self::assertTrue($serial->fresh()->has_sale);
            self::assertSame('9',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        }
    }

    /** @dataProvider historicalPresentationSources */
    public function test_historical_invoice_credit_preserves_its_original_presentation_and_lot_factor(bool $hasSnapshot): void
    {
        $unit=$this->presentation();$factor=$hasSnapshot ? D::of('1/3') : D::of('0.333333333333');
        $this->input['items'][0]['item']['presentation']=$hasSnapshot
            ? ['id'=>$unit->id,'quantity_unit'=>'0.333333333333'] : ['quantity_unit'=>'0.333333333333'];
        $lot=$this->db->table('item_lots_group')->insertGetId(['item_id'=>$unit->item_id,'code'=>'HISTORICAL-FACTOR','quantity'=>'10','date_of_due'=>'2027-11-30']);
        $this->input['items'][0]['item']['IdLoteSelected']=[['id'=>$lot,'compromise_quantity'=>'3']];
        $invoice=(new Facturalo())->forWebDocuments()->save($this->input)->getDocument()->fresh();
        $line=$invoice->items()->firstOrFail();$legacy=json_decode(json_encode($line->item,JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
        unset($legacy['inventory_components'],$legacy['presentation']['quantity_unit_exact']);
        if (!$hasSnapshot) unset($legacy['presentation']['calculation_snapshot']);
        $this->db->table('document_items')->where('id',$line->id)->update(['item'=>json_encode($legacy,JSON_THROW_ON_ERROR)]);
        $unit->delete();
        self::assertSame((string)$factor,(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($line->fresh()->item));
        $noteInput=(new \ReflectionMethod($this->fixture,'noteInput'))->invoke($this->fixture,$this->input,$invoice->fresh(),'07','01',[]);
        $note=(new Facturalo())->forWebDocuments()->save($noteInput)->getDocument()->fresh();
        self::assertSame(D::fraction($factor),(array)$note->items->first()->item->inventory_components[0]->quantity_exact);
        self::assertSame('100',$this->stock());
        self::assertSame('100',(string)Item::findOrFail($line->item_id)->exactValue('stock'));
        self::assertSame('10',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        self::assertSame('1',(string)$note->exactValue('total'));
    }

    public static function historicalPresentationSources(): array
    {
        return ['stored original snapshot'=>[true],'finite historical value without a snapshot'=>[false]];
    }

    /** @dataProvider packPresentationSources */
    public function test_pack_presentation_metadata_is_normalized_before_lot_inventory(bool $fromCatalog): void
    {
        $product=Item::findOrFail($this->input['items'][0]['item_id']);$product->is_set=true;$product->save();
        $component=$product->replicate();$component->internal_id='PACK-TRUST-COMPONENT';$component->description='Componente temporal';$component->is_set=false;
        $component->setExactValues(['stock'=>'100'])->save();$warehouse=$this->input['items'][0]['warehouse_id'];
        ItemWarehouse::create(['item_id'=>$component->id,'warehouse_id'=>$warehouse,'stock'=>'100']);
        $product->sets()->create(['individual_item_id'=>$component->id,'quantity'=>'2']);
        $case=$this->input;$case['items'][0]['item']=$product->fresh()->toArray();
        $presentation=['quantity_unit'=>$fromCatalog ? '999' : '1','quantity_unit_exact'=>['n'=>'1','d'=>'7'],
            'calculation_snapshot'=>D::snapshot(['quantity_unit'=>D::of('1/13')])];
        if ($fromCatalog) {$unit=$this->presentation();$presentation['id']=$unit->id;}
        $case['items'][0]['item']['presentation']=$presentation;
        $lot=$this->db->table('item_lots_group')->insertGetId(['item_id'=>$product->id,'code'=>'PACK-TRUST-LOT','quantity'=>'10','date_of_due'=>'2027-11-30']);
        $case['items'][0]['item']['IdLoteSelected']=[['id'=>$lot,'compromise_quantity'=>'3']];
        $invoice=(new Facturalo())->forWebDocuments()->save($case)->getDocument()->fresh();
        $expected=$fromCatalog ? D::of('1/3') : D::of(1);$line=$invoice->items->first();
        self::assertSame(D::fraction($expected),(array)$line->item->presentation->quantity_unit_exact);
        self::assertSame((string)$expected,(string)\App\Services\Fiscal\FiscalNoteInventory::presentationQuantity($line->item));
        self::assertSame($fromCatalog ? '9' : '7',(string)\Modules\Item\Models\ItemLotsGroup::findOrFail($lot)->exactValue('quantity'));
        // Preserve the existing component rule; this test does not redefine pack units.
        self::assertSame('100',(string)$component->fresh()->exactValue('stock'));
        self::assertSame('97',(string)$product->fresh()->exactValue('stock'));
        self::assertSame('94',(string)ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock'));
    }

    public static function packPresentationSources(): array
    {
        return ['catalog original'=>[true],'finite legacy input'=>[false]];
    }

    private function assertOriginalProductType(bool $isSet): void
    {
        $product=Item::findOrFail($this->input['items'][0]['item_id']);$product->is_set=$isSet;$product->save();
        $component=$product->replicate();$component->internal_id='POS-COMPONENT';$component->description='Componente temporal';$component->is_set=false;
        $component->setExactValues(['stock'=>'100'])->save();
        $warehouse=$this->input['items'][0]['warehouse_id'];
        ItemWarehouse::create(['item_id'=>$component->id,'warehouse_id'=>$warehouse,'stock'=>'100']);
        $product->sets()->create(['individual_item_id'=>$component->id,'quantity'=>'2']);
        $case=$this->input;$case['items'][0]['item']=$product->fresh()->toArray();
        $invoice=(new Facturalo())->forWebDocuments()->save($case)->getDocument()->fresh();
        $stock=fn()=>[$this->stock(),D::represent(ItemWarehouse::where('item_id',$component->id)->where('warehouse_id',$warehouse)->firstOrFail()->exactValue('stock')),
            D::represent($product->fresh()->exactValue('stock')),D::represent($component->fresh()->exactValue('stock'))];
        $before=$stock();
        $product->is_set=!$isSet;$product->save();
        $edit=array_replace($case,['id'=>$invoice->id,'series'=>$invoice->series,'number'=>$invoice->number,'received_retentions'=>[],'guarantee_fund'=>null]);
        $edit['items'][0]['record_id']=$invoice->items->first()->id;$edit['items'][0]['_invoice_is_set']=!$isSet;
        (new Facturalo())->forWebDocuments()->update($edit,$invoice->id);
        self::assertSame($before,$stock());self::assertSame($isSet,$invoice->fresh()->items->first()->item->is_set);
    }
}
