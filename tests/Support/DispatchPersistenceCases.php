<?php
namespace Tests\Support;
use App\CoreFacturalo\{Facturalo};
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\Models\Tenant\{Company,Dispatch,Establishment,PersonAddress};
use App\Services\Fiscal\{HkaDispatchEmission,HkaDispatchPdf,DeliveryOrderPersistence};
use Illuminate\Support\Str;
use Modules\Dispatch\Models\{Driver,Transport,Dispatcher};

trait DispatchPersistenceCases
{
    private function dispatchFixture(): array
    {
        [$db,$app,$invoice]=$this->bootHkaFixture();$calls=0;
        $driver=Driver::create(['name'=>'PRUEBA-OE-Conductor','identity_document_type_id'=>'1','number'=>'12340001','license'=>'Tercera','telephone'=>'04120000000','is_active'=>true]);
        $vehicle=Transport::create(['brand'=>'Prueba','model'=>'Camión','plate_number'=>'PRUEBA01','is_active'=>true,'tuc'=>'PRUEBA01']);
        $carrier=Dispatcher::create(['name'=>'PRUEBA-OE-Transportista','identity_document_type_id'=>'6','number'=>'000000004','address'=>'Caracas de prueba','is_active'=>true]);
        $customer=\App\Models\Tenant\Person::findOrFail($invoice['customer_id']);
        $customer->forceFill(['address'=>'Caracas de prueba','district_id'=>'000619','province_id'=>'0229','department_id'=>'14','enabled'=>true])->save();
        $address=PersonAddress::create(['person_id'=>$customer->id,'country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Llegada de prueba','has_consigned'=>false]);
        $raw=['creation_key'=>(string)Str::uuid(),'id'=>null,'document_type_id'=>'09','establishment_id'=>$invoice['establishment_id'],'series'=>'TT01','number'=>'#',
            'date_of_issue'=>date('Y-m-d'),'time_of_issue'=>'12:00:00','date_of_shipping'=>date('Y-m-d'),'customer_id'=>$customer->id,
            'transfer_reason_type_id'=>'24','transfer_reason_description'=>'Entrega sintética','transport_mode_type_id'=>'02','driver_id'=>$driver->id,'transport_id'=>$vehicle->id,'dispatcher_id'=>$carrier->id,
            'origin_address_id'=>0,'delivery_address_id'=>$address->id,'unit_type_id'=>'KG','total_weight'=>2,'packages_number'=>1,'observations'=>'Prueba','hka_data'=>[],
            'items'=>[['item_id'=>$invoice['items'][0]['item_id'],'quantity'=>2,'unit_price'=>116]],'reference_documents'=>[]];
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use($db,&$calls) {
            self::assertSame(0,$db->transactionLevel(),'HKA must run after the outer commit');
            if (str_ends_with($request->url(),'/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'private-test','expiracion'=>date('c',time()+3600)]);
            if (str_ends_with($request->url(),'/DescargaArchivo')) {$pdf=new \FPDF();$pdf->AddPage();$pdf->SetFont('Helvetica','',12);$pdf->Text(10,10,'Delivery HKA');return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','archivo'=>base64_encode($pdf->Output('S'))]);}
            $id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];$calls++;
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[],'resultado'=>$id+['numeroControl'=>'00-'.str_pad((string)$calls,8,'0',STR_PAD_LEFT),'urlConsulta'=>'https://example.test/guide']]);
        });
        return [$db,$app,$raw,$invoice];
    }

    public function test_dispatch_automatic_hka_idempotency_snapshots_pdf_and_outer_commit(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$d=null;
        $db->transaction(function() use($raw,&$d,$db) {
            $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
            self::assertSame('not_requested',$d->emission->status);
            self::assertNull($d->control_number);
        });
        $d=$d->fresh();self::assertSame('confirmed',$d->emission->status);self::assertSame('09',$d->document_type_id);
        $payload=$d->emission->payload['documentoElectronico'];self::assertSame('04',$payload['encabezado']['identificacionDocumento']['tipoDocumento']);
        self::assertSame('1',$payload['guiaDespacho']['esGuiaDespacho']);self::assertSame('0.0000',$payload['detallesItems'][0]['precioUnitario']);self::assertArrayNotHasKey('totales',$payload['encabezado']);
        \App\Models\Tenant\Configuration::first()->forceFill(['enabled_price_items_dispatch'=>true])->save();
        \App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id'])->forceFill(['currency_type_id'=>'VES'])->save();
        $priced=$d->emission->snapshot;$priced['items']=\App\Services\Fiscal\DeliveryOrderInput::resolve($raw)['items'];
        $pricedPayload=(new \App\Services\Fiscal\HkaDispatchPayloadBuilder)->build($priced,$d->emission->operation_key);
        self::assertSame('200.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['precioItem']);
        self::assertSame('32.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['valorIVA']);
        self::assertSame('232.00',$pricedPayload['documentoElectronico']['detallesItems'][0]['valorTotalItem']);
        self::assertSame('G',$pricedPayload['documentoElectronico']['detallesItems'][0]['codigoImpuesto']);
        \App\Models\Tenant\Configuration::first()->forceFill(['enabled_price_items_dispatch'=>false])->save();
        self::assertSame('C62',$payload['detallesItems'][0]['unidadMedida']);
        self::assertStringStartsWith('%PDF-',app(HkaDispatchPdf::class)->download($d));
        $key=$d->emission->operation_key;$again=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();
        self::assertSame($d->id,$again->id);self::assertSame($key,$again->emission->operation_key);self::assertSame(1,$db->table('dispatches')->count());
        self::assertSame(0,$db->table('inventory_kardex')->where('inventory_kardexable_type',Dispatch::class)->count());
        $bad=$raw;$bad['total_weight']=3;
        try {(new Facturalo)->save(DispatchInput::set($bad));self::fail('UUID collision must fail');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(409,$e->getStatusCode());}
        try {DeliveryOrderPersistence::assertEditable($d);self::fail('Confirmed orders are immutable');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('document',$e->errors());}
        Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->statement('DROP TABLE dispatch_emissions');$db->statement('ALTER TABLE dispatches DROP COLUMN creation_key, DROP COLUMN creation_hash, DROP COLUMN hka_data');
        $legacy=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertSame(2,$db->table('dispatches')->count());self::assertNull(HkaDispatchEmission::record($legacy));
    }

    public function test_dispatch_stock_uses_its_branch_and_edits_reverse_once(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $branch=Establishment::create(['description'=>'Other','country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'Other test','code'=>'0001','telephone'=>'04120000000']);
        $warehouse=\Modules\Inventory\Models\Warehouse::create(['establishment_id'=>$branch->id,'description'=>'Other']);
        $db->table('item_warehouse')->insert(['warehouse_id'=>$warehouse->id,'item_id'=>$raw['items'][0]['item_id'],'stock'=>20]);
        $series=\App\Models\Tenant\Series::create(['establishment_id'=>$branch->id,'document_type_id'=>'09','number'=>'TEST-OE']);
        $raw['establishment_id']=$branch->id;$raw['series']='TEST-OE';
        $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();self::assertEquals(18,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        self::assertEquals(10,$db->table('item_warehouse')->where('warehouse_id',1)->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $edit=$raw;$edit['id']=$d->id;$edit['items'][0]['quantity']=3;
        $d=(new Facturalo)->save(DispatchInput::set($edit))->getDocument()->fresh();self::assertEquals(17,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $d->getConnection()->transaction(function() use($d) {$d->state_type_id='11';$d->save();});self::assertEquals(20,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $d->getConnection()->transaction(function() use($d) {$d->save();});self::assertEquals(20,$db->table('item_warehouse')->where('warehouse_id',$warehouse->id)->value('stock'));
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);$item->forceFill(['lots_enabled'=>true,'series_enabled'=>true])->save();
        $lot=\Modules\Item\Models\ItemLotsGroup::create(['item_id'=>$item->id,'code'=>'PRUEBA-OE-Lote','quantity'=>20,'date_of_due'=>date('Y-m-d',time()+86400)]);
        $serials=[];
        for($i=1;$i<=3;$i++) $serials[]=\Modules\Item\Models\ItemLot::create(['item_id'=>$item->id,'warehouse_id'=>$warehouse->id,'series'=>'PRUEBA-OE-'.$i,'date'=>date('Y-m-d'),'item_loteable_type'=>\Modules\Inventory\Models\Inventory::class,'item_loteable_id'=>1,'has_sale'=>false])->toArray();
        $raw['creation_key']=(string)Str::uuid();$raw['items'][0]['IdLoteSelected']=$lot->id;$raw['items'][0]['lots']=array_slice($serials,0,2);
        $tracked=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertEquals(18,$lot->fresh()->quantity);self::assertSame(2,$db->table('item_lots')->where('has_sale',1)->count());
        $repeated=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();self::assertSame($tracked->id,$repeated->id);self::assertEquals(18,$lot->fresh()->quantity);
        $edit=$raw;$edit['id']=$tracked->id;$edit['items'][0]['quantity']=3;$edit['items'][0]['lots']=$serials;
        $tracked=(new Facturalo)->save(DispatchInput::set($edit))->getDocument();
        self::assertEquals(17,$lot->fresh()->quantity);self::assertSame(3,$db->table('item_lots')->where('has_sale',1)->count());
        $db->transaction(function() use($tracked){$tracked->state_type_id='11';$tracked->save();});
        self::assertEquals(20,$lot->fresh()->quantity);self::assertSame(0,$db->table('item_lots')->where('has_sale',1)->count());
        $bad=$raw;$bad['items'][0]['lots']=[$serials[0],$serials[0]];
        try {DispatchInput::set($bad);self::fail('Duplicate serial rejected');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
        $bad=$raw;$bad['items'][0]['quantity']=1.5;$bad['items'][0]['lots']=[$serials[0]];
        try {DispatchInput::set($bad);self::fail('Fractional serial quantity rejected');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
    }

    public function test_dispatch_timeout_and_absence_reconcile_same_operation(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$http=new \Illuminate\Http\Client\Factory;$app->instance(\Illuminate\Http\Client\Factory::class,$http);\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $http->fake(function($request) {if(str_ends_with($request->url(),'/Autenticacion')) return \Illuminate\Support\Facades\Http::response(['token'=>'private-test','expiracion'=>date('c',time()+3600)]);throw new \Illuminate\Http\Client\ConnectionException('Timeout');});
        $d=(new Facturalo)->save(DispatchInput::set($raw))->getDocument()->fresh();self::assertSame('uncertain',$d->emission->status);$key=$d->emission->operation_key;
        $e=$d->emission;$response=$e->response;$response['started_at']=time()-60;$e->update(['response'=>$response]);
        $http=new \Illuminate\Http\Client\Factory;$app->instance(\Illuminate\Http\Client\Factory::class,$http);\Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $http->fake(fn()=>\Illuminate\Support\Facades\Http::response(['codigo'=>'203','mensaje'=>'Consulta no procesada','validaciones'=>['Documento no encontrado en nuestra base de datos'],'estado'=>null]));
        $view=app(HkaDispatchEmission::class)->query($d);self::assertTrue($view['can_send'],json_encode($view));self::assertSame($key,$d->fresh()->emission->operation_key);self::assertSame(1,$db->table('dispatches')->count());
    }

    public function test_dispatch_server_rejects_foreign_addresses_and_invalid_weight_before_network(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();$bad=$raw;$bad['total_weight']=0;
        try {DispatchInput::set($bad);self::fail('Positive weight required');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('total_weight',$e->errors());}
        $bad=$raw;$bad['customer_id']=\App\Models\Tenant\Person::where('id','!=',$raw['customer_id'])->where('type','customers')->firstOrFail()->id;
        try {DispatchInput::set($bad);self::fail('Foreign address rejected');} catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e) {}
        $user=auth()->user();
        auth()->setUser(new \Illuminate\Auth\GenericUser(['id'=>999]));
        try {DispatchInput::set($raw);self::fail('Non-tenant principal rejected');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(403,$e->getStatusCode());}
        $seller=new class extends \App\Models\Tenant\User { public function searchModule($module){return true;} public function searchLevel($level){return true;} };
        $seller->forceFill(['id'=>$user->id,'type'=>'seller','establishment_id'=>$raw['establishment_id']+1]);auth()->setUser($seller);
        try {DispatchInput::set($raw);self::fail('Other branch rejected');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) {self::assertSame(403,$e->getStatusCode());}
        auth()->setUser($user);
        self::assertSame(0,$db->table('dispatches')->count());self::assertSame(1,$db->table('drivers')->count());
        $app->make(\Illuminate\Http\Client\Factory::class)->assertNothingSent();
    }
    public function test_dispatch_concurrent_creation_keeps_one_order_and_stock_effect_per_key(): void
    {
        [$db,$app,$raw]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $same=$this->runFiscalWorkers(1,'dispatch-create',[$raw,$raw]);
        self::assertSame($same[0]['id'],$same[1]['id']);self::assertSame(1,$db->table('dispatches')->count());
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $a=$raw;$a['creation_key']=(string)Str::uuid();$b=$raw;$b['creation_key']=(string)Str::uuid();
        $different=$this->runFiscalWorkers(1,'dispatch-create',[$a,$b]);self::assertNotSame($different[0]['id'],$different[1]['id']);
        self::assertNotSame($different[0]['number'],$different[1]['number']);self::assertSame(3,$db->table('dispatches')->count());
        self::assertEquals(4,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
    }

    public function test_dispatch_multi_order_invoice_moves_only_the_pending_quantities(): void
    {
        [$db,$app,$raw,$invoice]=$this->dispatchFixture();Company::first()->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>1]);
        $first=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $raw['creation_key']=(string)Str::uuid();$db->table('cat_transfer_reason_types')->where('id','24')->update(['discount_stock'=>0]);
        $second=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $invoice['dispatches_relateds']=[$first->number_full,$second->number_full];$invoice['items'][0]['quantity']=4;$invoice['payments']=[];
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals(6,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        self::assertSame($saved->id,(int)$first->fresh()->reference_document_id);self::assertSame($saved->id,(int)$second->fresh()->reference_document_id);
        self::assertSame('2',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        $db->transaction(function() use($saved){$saved->state_type_id='11';$saved->save();});
        self::assertEquals(8,$db->table('item_warehouse')->where('item_id',$raw['items'][0]['item_id'])->value('stock'));
        $item=\App\Models\Tenant\Item::findOrFail($raw['items'][0]['item_id']);$item->forceFill(['lots_enabled'=>true])->save();
        $lot=\Modules\Item\Models\ItemLotsGroup::create(['item_id'=>$item->id,'code'=>'PRUEBA-OE-Facturar','quantity'=>10,'date_of_due'=>date('Y-m-d',time()+86400)]);
        $raw['creation_key']=(string)Str::uuid();$raw['items'][0]['IdLoteSelected']=$lot->id;
        $third=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();$raw['creation_key']=(string)Str::uuid();
        $fourth=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatches_relateds']=[$third->number_full,$fourth->number_full];
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals(6,$lot->fresh()->quantity);self::assertSame('4',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        self::assertEquals($lot->id,$saved->items->first()->item->IdLoteSelected[0]->id);
        $db->transaction(function() use($saved){$saved->state_type_id='11';$saved->save();});
        self::assertEquals(10,$lot->fresh()->quantity);
        $item->forceFill(['lots_enabled'=>false])->save();unset($raw['items'][0]['IdLoteSelected']);
        $source=$db->transaction(function() use($invoice) {
            $order=\Modules\Order\Models\OrderNote::create(['user_id'=>auth()->id(),'external_id'=>(string)Str::uuid(),'establishment_id'=>$invoice['establishment_id'],'establishment'=>$invoice['establishment'],'state_type_id'=>'01','prefix'=>'PED','date_of_issue'=>date('Y-m-d'),'time_of_issue'=>'12:00:00','customer_id'=>$invoice['customer_id'],'customer'=>$invoice['customer'],'currency_type_id'=>'VES','exchange_rate_sale'=>1,'total'=>232]);
            $line=$invoice['items'][0];$line['quantity']=2;$order->items()->create($line);return $order;
        });
        $before=$db->table('item_warehouse')->where('item_id',$item->id)->value('stock');
        $raw['creation_key']=(string)Str::uuid();$raw['reference_order_note_id']=$source->id;
        $derived=(new Facturalo)->save(DispatchInput::set($raw))->getDocument();
        $invoice['dispatches_relateds']=[$derived->number_full];$invoice['items'][0]['quantity']=2;
        $saved=(new Facturalo)->forWebDocuments()->save($invoice)->getDocument();
        self::assertEquals($before,$db->table('item_warehouse')->where('item_id',$item->id)->value('stock'));self::assertSame('0',\App\Support\Exact\Decimal::represent($saved->items->first()->item->dispatch_inventory_quantity));
        $bad=$raw;$bad['items'][0]['quantity']=3;
        try {DispatchInput::set($bad);self::fail('Quantity exceeds source order');} catch(\Illuminate\Validation\ValidationException $e) {self::assertArrayHasKey('items',$e->errors());}
    }

}
