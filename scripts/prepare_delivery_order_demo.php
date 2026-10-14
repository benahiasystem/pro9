<?php
/** Explicit BBC-only additive setup and synthetic data. Never resets a tenant or its catalogs. */
require dirname(__DIR__).'/vendor/autoload.php';
use App\Models\Tenant\{Company,Dispatch,Document,Establishment,Item,Person,PersonAddress,Series,User};
use App\CoreFacturalo\{Facturalo};
use App\CoreFacturalo\Requests\Inputs\DispatchInput;
use App\Services\Fiscal\{HkaTransport,HkaDispatchEmission,HkaDispatchPdf,HkaDispatchRelations};
use Modules\Dispatch\Models\{Driver,Transport,Dispatcher,OriginAddress};
use Modules\Inventory\Models\{Warehouse,Inventory};
use Illuminate\Support\Str;

$fixtureLock=fopen(sys_get_temp_dir().'/pro9-delivery-bbc-fixture.lock','c');
if (!flock($fixtureLock,LOCK_EX|LOCK_NB)) exit("Otra preparación de BBC está en ejecución. Reintente al terminar.\n");
$root=dirname(__DIR__);$host=$argv[1] ?? ''; $mode=$argv[2] ?? '';
if ($host!=='bbc.localhost' || !in_array($mode,['prepare','emit','documents','audit'],true)) exit("Usage: php scripts/prepare_delivery_order_demo.php bbc.localhost prepare|emit|documents|audit [case]\n");
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$hostname=Hyn\Tenancy\Models\Hostname::where('fqdn',$host)->firstOrFail();app(Hyn\Tenancy\Environment::class)->tenant($hostname->website);
$db=Illuminate\Support\Facades\DB::connection('tenant');Illuminate\Support\Facades\DB::setDefaultConnection('tenant');
$company=Company::firstOrFail();if ($company->fiscal_environment!=='demo' || $company->fiscal_emission_mode!=='digital') exit("BBC must remain digital DEMO.\n");
auth()->setUser(User::where('type','admin')->firstOrFail());
$file=$root.'/informes/evidencias/ordenes_entrega_bbc_manifest.json';
$manifest=is_file($file) ? json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR) : ['host'=>$host,'synthetic'=>true,'prefix'=>'PRUEBA-OE-','date'=>date('Y-m-d'),'created'=>[],'orders'=>[],'results'=>[]];
$save=static function() use (&$manifest,$file) {
    foreach ($manifest['orders'] as $case) { $id=Dispatch::where('creation_key',$case['creation_key'])->value('id');if ($id) $manifest['created']['dispatches'][]=$id; }
    $manifest['created']['dispatches']=array_values(array_unique($manifest['created']['dispatches'] ?? []));
    file_put_contents($file,json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));};
$tag=static function($kind,$model) use (&$manifest) {$manifest['created'][$kind][]=$model->id;$manifest['created'][$kind]=array_values(array_unique($manifest['created'][$kind]));return $model;};

if ($mode==='audit') {
    $guides=Dispatch::where('observations','like','PRUEBA-OE%')->get();
    foreach ($guides as $guide) {
        $tag('dispatches',$guide);
        if (!empty($guide->reference_document_id)) $manifest['relationships'][$guide->id]=app(HkaDispatchRelations::class)->sync($guide);
        if ($guide->id===($manifest['browser_initial']['id'] ?? null)) {
            $pdf=app(HkaDispatchPdf::class)->download($guide);$manifest['browser_final']=['id'=>$guide->id,'number'=>$guide->number_full,'status'=>$guide->emission->status,'code'=>$guide->emission->response['code'] ?? null,
                'control'=>$guide->control_number,'pdf_bytes'=>strlen($pdf),'pdf_sha256'=>hash('sha256',$pdf),'query_ui'=>true,'download_ui'=>true];
        }
    }
    foreach (['inventories'=>['item_id',$manifest['created']['items']],'item_warehouse'=>['item_id',$manifest['created']['items']],
        'dispatch_items'=>['dispatch_id',$manifest['created']['dispatches']],'dispatch_emissions'=>['dispatch_id',$manifest['created']['dispatches']],
        'document_items'=>['document_id',$manifest['created']['documents']],'document_emissions'=>['document_id',$manifest['created']['documents']],
        'quotation_items'=>['quotation_id',$manifest['created']['quotations']],'order_note_items'=>['order_note_id',$manifest['created']['order_notes']],'sale_note_items'=>['sale_note_id',$manifest['created']['sale_notes']]] as $table=>[$field,$ids]) {
        $manifest['created'][$table]=array_values(array_unique(array_merge($manifest['created'][$table] ?? [],$db->table($table)->whereIn($field,$ids)->pluck('id')->all())));
    }
    $transport=app(HkaTransport::class);$token=$transport->token($company);
    foreach ($manifest['invoices'] as $currency=>$result) {
        $invoice=Document::findOrFail($result['id']);$identity=array_intersect_key($invoice->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'],array_flip(['serie','tipoDocumento','numeroDocumento']));
        $response=$transport->dispatchRelations($token,$identity);
        $manifest['reverse_relationships'][$currency]=['http'=>$response->status(),'code'=>$response->json('codigo'),'related'=>$response->json('relacionados')];
        $manifest['invoice_mail'][$currency]=['automatic_status'=>$invoice->emission->response['mail']['automatic']['status'] ?? null];
    }
    $manifest['audit']=['checked_at'=>date('c'),'orders'=>$guides->count(),'statuses'=>$guides->groupBy(fn($d)=>$d->emission->status)->map->count()->all(),
        'dispatch_stock_flags'=>$guides->pluck('discount_stock')->unique()->values()->all(),'motifs_stock_flags'=>$db->table('cat_transfer_reason_types')->whereIn('id',['04','21','22','23','24'])->pluck('discount_stock','id')->all()];
    $save();echo json_encode($manifest['audit']),PHP_EOL;exit;
}

if ($mode==='prepare') {
    foreach (['creation_key'=>'char(36) NULL','creation_hash'=>'char(64) NULL','hka_data'=>'json NULL'] as $column=>$definition) if (!$db->getSchemaBuilder()->hasColumn('dispatches',$column)) $db->statement('ALTER TABLE `dispatches` ADD `'.$column.'` '.$definition);
    if (!$db->select("SHOW INDEX FROM dispatches WHERE Key_name='dispatches_creation_unique'")) $db->statement('ALTER TABLE dispatches ADD UNIQUE KEY dispatches_creation_unique (creation_key)');
    if (!$db->getSchemaBuilder()->hasTable('dispatch_emissions')) {
        (require $root.'/database/migrations/tenant/2026_08_17_000342_create_dispatch_emissions_table.php')->up();
        $db->statement('ALTER TABLE dispatch_emissions ADD CONSTRAINT dispatch_emissions_dispatch_foreign FOREIGN KEY (dispatch_id) REFERENCES dispatches(id)');
        $db->statement('ALTER TABLE dispatch_emissions ADD CONSTRAINT dispatch_emissions_environment_foreign FOREIGN KEY (fiscal_environment) REFERENCES fiscal_environments(id)');
    }
    if (!$db->table('migrations')->where('migration','2026_08_17_000342_create_dispatch_emissions_table')->exists()) $db->table('migrations')->insert(['migration'=>'2026_08_17_000342_create_dispatch_emissions_table','batch'=>(int)$db->table('migrations')->max('batch')+1]);
    $main=Establishment::findOrFail(auth()->user()->establishment_id);
    $branch=$tag('establishments',Establishment::firstOrCreate(['description'=>'PRUEBA-OE-Sucursal'],['country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'PRUEBA-OE Avenida de prueba Maracaibo','code'=>'0099','telephone'=>'04120000000']));
    $warehouses=[];foreach ([$main,$branch] as $b) $warehouses[$b->id]=Warehouse::firstOrCreate(['establishment_id'=>$b->id],['description'=>'PRUEBA-OE-Almacén']);
    $tag('warehouses',$warehouses[$branch->id]);
    $origin=$tag('origin_addresses',OriginAddress::firstOrCreate(['establishment_id'=>$main->id,'address'=>'PRUEBA-OE Origen adicional'],['location_id'=>['14','0229','000619'],'is_active'=>true]));
    $customers=[];
    foreach (['6'=>'000000004','1'=>'12340001','E'=>'12340002'] as $type=>$number) {
        $c=$tag('persons',Person::firstOrCreate(['type'=>'customers','identity_document_type_id'=>$type,'number'=>$number],['name'=>'PRUEBA-OE-Cliente '.$type,'country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','address'=>'PRUEBA-OE Dirección cliente '.$type,'email'=>'prueba-oe@example.test','telephone'=>'04120000000','enabled'=>true,'nationality_id'=>$type==='E' ? 'CO':null]));
        // Do not reuse a non-synthetic identity if it was already registered.
        if (!str_starts_with($c->name,'PRUEBA-OE-')) throw new RuntimeException('Synthetic customer identity collision');
        foreach (['Principal','Adicional'] as $name) $tag('person_addresses',PersonAddress::firstOrCreate(['person_id'=>$c->id,'address'=>'PRUEBA-OE '.$name.' '.$type],['country_id'=>'VE','department_id'=>'14','province_id'=>'0229','district_id'=>'000619','has_consigned'=>false]));
        $customers[$type]=$c;
    }
    $drivers=[];$vehicles=[];$carriers=[];
    foreach ([1,2] as $n) {
        $drivers[]=$tag('drivers',Driver::firstOrCreate(['number'=>'1234900'.$n],['identity_document_type_id'=>$n===1 ? '1':'E','name'=>'PRUEBA-OE-Conductor '.$n,'license'=>'Tercera','telephone'=>'0412000000'.$n,'is_active'=>true]));
        $vehicles[]=$tag('transports',Transport::firstOrCreate(['plate_number'=>'PRUEBA0'.$n],['brand'=>'Prueba','model'=>'Camión '.$n,'tuc'=>'PRUEBAOE'.$n,'is_active'=>true]));
        $carriers[]=$tag('dispatchers',Dispatcher::firstOrCreate(['number'=>'00000001'.$n],['identity_document_type_id'=>'6','name'=>'PRUEBA-OE-Transportista '.$n,'address'=>'PRUEBA-OE Domicilio transportista '.$n,'is_active'=>true]));
    }
    $template=Item::where('item_type_id','01')->firstOrFail();$items=[];
    foreach (['UND'=>['UND',false,false],'KG'=>['KG',false,false],'LOTE'=>['UND',true,false],'SERIAL'=>['UND',false,true]] as $name=>[$unit,$lot,$serial]) {
        $item=Item::where('internal_id','PRUEBA-OE-'.$name)->first();
        if (!$item) {
            $data=$template->only($template->getFillable());unset($data['id'],$data['warehouse_id']);
            $data=array_replace($data,['internal_id'=>'PRUEBA-OE-'.$name,'description'=>'PRUEBA-OE-Bien '.$name,'unit_type_id'=>$unit,'stock'=>0,'currency_type_id'=>'VES','sale_unit_price'=>116,'purchase_unit_price'=>50,'has_igv'=>true,'sale_affectation_igv_type_id'=>'10','purchase_affectation_igv_type_id'=>'10','lots_enabled'=>$lot,'series_enabled'=>$serial,'is_set'=>false,'attributes'=>[]]);
            $item=Item::create($data);
        }
        $tag('items',$item);$items[$name]=$item;
        foreach ($warehouses as $warehouse) {
            $label='PRUEBA-OE Existencias '.$name.' '.$warehouse->id;
            $stock=Inventory::where('description',$label)->first();
            if (!$stock) $stock=Inventory::create(['type'=>1,'description'=>$label,'item_id'=>$item->id,'warehouse_id'=>$warehouse->id,'quantity'=>100,'date_of_issue'=>$manifest['date']]);
            $tag('inventory',$stock);
        }
    }
    $lot=$tag('item_lots_group',\Modules\Item\Models\ItemLotsGroup::firstOrCreate(['item_id'=>$items['LOTE']->id,'code'=>'PRUEBA-OE-LOTE'],['quantity'=>100,'date_of_due'=>'2027-10-13']));
    $serials=[];foreach ([1,2,3] as $n) $serials[]=$tag('item_lots',\Modules\Item\Models\ItemLot::firstOrCreate(['item_id'=>$items['SERIAL']->id,'series'=>'PRUEBA-OE-SERIAL-'.$n],['warehouse_id'=>$warehouses[$main->id]->id,'date'=>$manifest['date'],'has_sale'=>false,'state'=>'01','item_loteable_type'=>Inventory::class,'item_loteable_id'=>$manifest['created']['inventory'][0]]));
    $token=app(HkaTransport::class)->token($company);
    $numerations=Illuminate\Support\Facades\Http::acceptJson()->asJson()->withToken($token)->timeout(10)->connectTimeout(5)->withOptions(['verify'=>true,'allow_redirects'=>false])->post('https://demoemisionv2.thefactoryhka.com.ve/api/ConsultaNumeraciones',['tipoDocumento'=>'04','serie'=>'']);
    $manifest['numeration_preflight']=['http'=>$numerations->status(),'code'=>$numerations->json('codigo'),'matches'=>count($numerations->json('numeraciones') ?? [])];unset($token,$numerations);
    foreach ([$main,$branch] as $i=>$b) {
        $series=$tag('series',Series::firstOrCreate(['establishment_id'=>$b->id,'document_type_id'=>'09','number'=>''],['dedicated'=>false,'contingency'=>false]));
        $db->table('series_configurations')->updateOrInsert(['series_id'=>$series->id],['document_type_id'=>'09','series'=>'','number'=>$db->table('series_configurations')->where('series_id',$series->id)->value('number') ?? (1600000000+$i*100000)]);
        $tag('series',Series::firstOrCreate(['establishment_id'=>$b->id,'document_type_id'=>'09','number'=>'PRUEBA-OE-'.($i+1)],['dedicated'=>false,'contingency'=>false]));
    }
    $address=PersonAddress::where('person_id',$customers['1']->id)->orderBy('id')->first();
    $base=['creation_key'=>(string)Str::uuid(),'transport_mode_type_id'=>'02','document_type_id'=>'09','establishment_id'=>$main->id,'series'=>'','number'=>'#','date_of_issue'=>$manifest['date'],'time_of_issue'=>'12:00:00','date_of_shipping'=>$manifest['date'],'customer_id'=>$customers['1']->id,'unit_type_id'=>'KG','total_weight'=>2,'packages_number'=>1,'origin_address_id'=>$origin->id,'delivery_address_id'=>$address->id,'driver_id'=>$drivers[0]->id,'transport_id'=>$vehicles[0]->id,'dispatcher_id'=>$carriers[0]->id,'has_transport_driver_01'=>true,'items'=>[['item_id'=>$items['UND']->id,'quantity'=>2]],'observations'=>'PRUEBA-OE','reference_documents'=>[],'hka_data'=>['service_description'=>'PRUEBA-OE Reparación sintética']];
    if (!$manifest['orders']) {
        foreach (['04','21','22','23','24'] as $reason) foreach (['01','02'] as $transportMode) {
            $case=$base;$case['creation_key']=(string)Str::uuid();$case['transfer_reason_type_id']=$reason;$case['transport_mode_type_id']=$transportMode;
            $case['transfer_reason_description']=$reason==='24' ? 'PRUEBA-OE Entrega sintética':null;
            if ($reason==='04') {$case['customer_id']=null;$case['delivery_address_id']=1;}
            $manifest['orders'][$reason.'-'.$transportMode]=$case;
        }
        $fraction=$base;$fraction['creation_key']=(string)Str::uuid();$fraction['transfer_reason_type_id']='24';$fraction['transfer_reason_description']='PRUEBA-OE Fraccionario';$fraction['items']=[['item_id'=>$items['KG']->id,'quantity'=>1.25]];$manifest['orders']['fraction']=$fraction;
        $batch=$fraction;$batch['creation_key']=(string)Str::uuid();$batch['items']=[['item_id'=>$items['LOTE']->id,'quantity'=>2,'IdLoteSelected'=>$lot->id]];$manifest['orders']['lot']=$batch;
        $serial=$fraction;$serial['creation_key']=(string)Str::uuid();$serial['items']=[['item_id'=>$items['SERIAL']->id,'quantity'=>2,'lots'=>array_map(fn($s)=>['id'=>$s->id],array_slice($serials,0,2))]];$manifest['orders']['serial']=$serial;
        $other=$fraction;$other['creation_key']=(string)Str::uuid();$other['establishment_id']=$branch->id;$manifest['orders']['other-branch']=$other;
    }
    foreach ($manifest['orders'] as &$case) if (!Dispatch::where('creation_key',$case['creation_key'])->exists()) { $case['origin_address_id']=(int)$case['establishment_id']===(int)$main->id ? $origin->id:0;$case['transport_mode_type_id']=$case['transport_mode_type_id'] ?? '02'; }unset($case);
    foreach (['6','E'] as $type) {
        $name='customer-'.$type;
        if (!isset($manifest['orders'][$name])) { $case=$base;$case['creation_key']=(string)Str::uuid();$case['transport_mode_type_id']='02';$case['transfer_reason_type_id']='24';$case['transfer_reason_description']='PRUEBA-OE Identidad '.$type;$case['customer_id']=$customers[$type]->id;$case['delivery_address_id']=PersonAddress::where('person_id',$customers[$type]->id)->orderBy('id')->value('id');$manifest['orders'][$name]=$case; }
    }
    if (!isset($manifest['orders']['public-carrier-only'])) { $case=$base;$case['creation_key']=(string)Str::uuid();$case['transport_mode_type_id']='01';$case['has_transport_driver_01']=false;$case['transfer_reason_type_id']='24';$case['transfer_reason_description']='PRUEBA-OE Transportista público';$manifest['orders']['public-carrier-only']=$case; }
    $manifest['base']=$base;$manifest['invoice_item_id']=$items['UND']->id;$manifest['customer_id']=$customers['1']->id;$manifest['main_establishment_id']=$main->id;
    $save();echo json_encode(['prepared'=>true,'counts'=>array_map('count',$manifest['created']),'orders'=>count($manifest['orders'])]),PHP_EOL;
    exit;
}

if ($mode==='documents') {
    $customer=Person::findOrFail($manifest['customer_id']);$branch=Establishment::findOrFail($manifest['main_establishment_id']);
    $establishment=App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput::set($branch->id);
    $buyer=App\CoreFacturalo\Requests\Inputs\Common\PersonInput::set($customer->id);
    $item=Item::findOrFail($manifest['invoice_item_id']);
    $line=static function($item,$quantity) {
        $value=round($quantity*100,2);$iva=round($value*0.16,2);
        return ['item_id'=>$item->id,'item'=>array_replace($item->toArray(),['is_set'=>false]),'quantity'=>$quantity,'unit_value'=>100,'unit_price'=>116,
            'price_type_id'=>'01','affectation_igv_type_id'=>'10','additional_information'=>'PRUEBA-OE','total_base_igv'=>$value,'percentage_igv'=>16,'total_igv'=>$iva,'total_taxes'=>$iva,'total_value'=>$value,'total'=>$value+$iva];
    };
    $common=['user_id'=>auth()->id(),'external_id'=>(string)Str::uuid(),'establishment_id'=>$branch->id,'establishment'=>$establishment,'fiscal_environment'=>'demo','state_type_id'=>'01','prefix'=>'COT',
        'date_of_issue'=>$manifest['date'],'time_of_issue'=>'12:00:00','customer_id'=>$customer->id,'customer'=>$buyer,'currency_type_id'=>'VES','exchange_rate_sale'=>1,
        'total_taxed'=>200,'total_igv'=>32,'total_taxes'=>32,'total_value'=>200,'total'=>232,'description'=>'PRUEBA-OE Documento relacionado','observation'=>'PRUEBA-OE Documento relacionado'];
    foreach (['quotations'=>App\Models\Tenant\Quotation::class,'order_notes'=>Modules\Order\Models\OrderNote::class,'sale_notes'=>App\Models\Tenant\SaleNote::class] as $kind=>$class) {
        $id=$manifest['created'][$kind][0] ?? null;
        if (!$id) {
            $data=$common;$data['external_id']=(string)Str::uuid();
            if ($kind==='order_notes') $data['prefix']='PED';
            if ($kind==='sale_notes') {
                $data['prefix']='NV';$data['series']='NV01';$data['number']='#';$data['state_type_id']='05';
                Series::firstOrCreate(['establishment_id'=>$branch->id,'document_type_id'=>'80','number'=>'NV01']);
            }
            $record=$db->transaction(function() use($class,$data,$line,$item) { $record=$class::create($data);$record->items()->create($line($item,2));return $record; });
            $tag($kind,$record);$save();
        }
    }
    foreach (['VES'=>['24-02'],'USD'=>['fraction','lot']] as $currency=>$names) {
        $key=$manifest['invoice_keys'][$currency] ?? (string)Str::uuid();$manifest['invoice_keys'][$currency]=$key;$save();
        $invoice=Document::where('external_id',$key)->first();
        if (!$invoice) {
            $rows=[];$references=[];
            foreach ($names as $name) {
                $guide=Dispatch::where('creation_key',$manifest['orders'][$name]['creation_key'])->firstOrFail();$references[]=$guide->number_full;
                foreach ($guide->items as $detail) $rows[]=$line(Item::findOrFail($detail->item_id),$detail->quantity);
            }
            $subtotal=array_sum(array_column($rows,'total_value'));$iva=array_sum(array_column($rows,'total_igv'));$total=$subtotal+$iva;
            $bank=App\Models\Tenant\BankAccount::firstOrCreate(['description'=>'PRUEBA-OE Cobros '.$currency],['bank_id'=>1,'number'=>'PRUEBA-OE-'.$currency,'currency_type_id'=>$currency,'establishment_id'=>$branch->id]);$tag('bank_accounts',$bank);
            Series::firstOrCreate(['establishment_id'=>$branch->id,'document_type_id'=>'01','number'=>'']);
            $input=array_replace($common,['type'=>'invoice','external_id'=>$key,'document_type_id'=>'01','group_id'=>'01','series'=>'','number'=>'#','currency_type_id'=>$currency,'exchange_rate_sale'=>$currency==='USD' ? '50.12345678':'1',
                'total_taxed'=>$subtotal,'total_igv'=>$iva,'total_taxes'=>$iva,'total_value'=>$subtotal,'total'=>$total,'additional_information'=>'PRUEBA-OE Factura '.$currency,'items'=>$rows,
                'payments'=>[['operation_key'=>(string)Str::uuid(),'payment_destination_id'=>$bank->id,'date_of_payment'=>$manifest['date'],'payment_method_type_id'=>'01','payment'=>$total,'reference'=>'PRUEBA-OE Cobro sintético']],
                'payment_condition_id'=>'01','fee'=>[],'hotel'=>null,'transport'=>null,'invoice'=>['date_of_due'=>$manifest['date'],'operation_type_id'=>'0101'],'dispatches_relateds'=>$references]);
            $invoice=(new Facturalo)->forWebDocuments()->save($input)->getDocument()->fresh();
        }
        $tag('documents',$invoice);$manifest['invoices'][$currency]=['id'=>$invoice->id,'number'=>$invoice->number_full,'status'=>$invoice->emission->status,'code'=>$invoice->emission->response['code'] ?? null,'control'=>$invoice->control_number];
        foreach (Dispatch::where('reference_document_id',$invoice->id)->get() as $guide) $manifest['relationships'][$guide->id]=app(HkaDispatchRelations::class)->sync($guide);
        $save();echo json_encode(['currency'=>$currency]+$manifest['invoices'][$currency]),PHP_EOL;
    }
    foreach (['quotation'=>['reference_quotation_id',$manifest['created']['quotations'][0]],'order-note'=>['reference_order_note_id',$manifest['created']['order_notes'][0]],'sale-note'=>['reference_sale_note_id',$manifest['created']['sale_notes'][0]],
        'invoice-VES'=>['reference_document_id',$manifest['invoices']['VES']['id']],'invoice-USD'=>['reference_document_id',$manifest['invoices']['USD']['id']]] as $name=>[$field,$id]) {
        $caseName='derived-'.$name;
        if (!isset($manifest['orders'][$caseName])) {
            $case=$manifest['base'];$case['creation_key']=(string)Str::uuid();$case['transfer_reason_type_id']='24';$case['transfer_reason_description']='PRUEBA-OE Derivada '.$name;$case[$field]=$id;
            if ($name==='invoice-USD') {$case['items']=[];foreach (Document::findOrFail($id)->items as $detail) $case['items'][]=['item_id'=>$detail->item_id,'quantity'=>$detail->quantity,'IdLoteSelected'=>Item::findOrFail($detail->item_id)->lots_enabled ? $manifest['created']['item_lots_group'][0]:null];}
            $manifest['orders'][$caseName]=$case;
        }
    }
    // Reconcile only the synthetic invoice made before the lot-conversion fix, once.
    $batchGuide=Dispatch::where('creation_key',$manifest['orders']['lot']['creation_key'])->firstOrFail();
    $batchSource=$batchGuide->items->first();$fixtureInvoice=Document::where('external_id',$manifest['invoice_keys']['USD'])->firstOrFail();
    $batchLine=$fixtureInvoice->items->firstWhere('item_id',$batchSource->item_id);
    if ($batchLine && empty($batchLine->item->IdLoteSelected) && !empty($batchSource->item->IdLoteSelected) && !$batchGuide->discountsPhysicalStock()) {
        $manifest['inventory_fixture_reconciliation']=$db->transaction(function() use($batchLine,$batchSource) {
            $line=\App\Models\Tenant\DocumentItem::lockForUpdate()->findOrFail($batchLine->id);$snapshot=(array)$line->item;
            if (!empty($snapshot['IdLoteSelected'])) return ['already_reconciled'=>true];
            $lot=\Modules\Item\Models\ItemLotsGroup::where('item_id',$line->item_id)->lockForUpdate()->findOrFail($batchSource->item->IdLoteSelected);
            $amount=(float)($snapshot['dispatch_inventory_quantity'] ?? -$line->document->inventory_kardex()->where('item_id',$line->item_id)->sum('quantity'));
            if ($amount!== (float)$line->quantity || $lot->quantity<$amount) throw new RuntimeException('Synthetic lot reconciliation requires the conserved movement quantity');
            $lot->quantity-=$amount;$lot->save();$snapshot['dispatch_inventory_quantity']=$amount;$snapshot['IdLoteSelected']=[['id'=>$lot->id,'compromise_quantity'=>$amount]];$line->forceFill(['item'=>$snapshot])->saveQuietly();
            return ['document_id'=>$line->document_id,'document_item_id'=>$line->id,'lot_id'=>$lot->id,'quantity'=>$amount,'stock_movement_repeated'=>false,'fiscal_fields_changed'=>false];
        });
    }
    $save();
    exit;
}

foreach ($manifest['orders'] as $name=>$raw) {
    if (isset($argv[3]) && $name!==$argv[3]) continue;
    $existing=Dispatch::where('creation_key',$raw['creation_key'])->first();
    $d=$existing ?: (new Facturalo)->save(DispatchInput::set($raw))->getDocument();
    if (!$existing) (new Facturalo)->createPdf($d,'dispatch','a4');
    $d=$d->fresh();$result=['id'=>$d->id,'number_full'=>$d->number_full,'status'=>$d->emission->status,'code'=>$d->emission->response['code'] ?? null,'control'=>$d->control_number];
    if ($d->emission->status==='confirmed') {
        $view=app(HkaDispatchEmission::class)->query($d);$pdf=app(HkaDispatchPdf::class)->download($d);
        $result+=['queried_status'=>$view['status'],'pdf_bytes'=>strlen($pdf),'pdf_sha256'=>hash('sha256',$pdf)];
    }
    $manifest['results'][$name]=$result;$tag('dispatches',$d);$save();echo json_encode(['case'=>$name]+$result),PHP_EOL;
}
