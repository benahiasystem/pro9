<?php
namespace Tests\Support;
use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\{Company,Person,DocumentReceivedRetention,ReceivedRetentionUpload};
use App\Services\Fiscal\{FiscalProfileService,IslrRetentionService,IvaRetentionService,HkaIslrRetention};
use Illuminate\Support\Facades\{Http,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
trait IslrRetentionPersistenceCases
{
    private function bootIslrFixture(): array
    {
        [$db,$app,$input]=$this->bootHkaFixture();
        config(['iva_retention.demo_net_collection_verified'=>true,'islr_retention.demo_net_collection_verified'=>true]);
        FiscalProfileService::store(Company::firstOrFail(),SpeProfiles::profile());
        // ISLR authority does not require SPE or authority to withhold IVA.
        FiscalProfileService::store(Person::findOrFail($input['customer_id']),SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true]));
        $input['payments'][0]['payment']=202;
        $input['payments'][0]['financial_intermediation']='financial_institution';
        $input['iva_retention_prevision']=['enabled'=>true];
        $input['islr_retention_prevision']=['enabled'=>true,'concept_id'=>'055','percentage'=>4,'subtrahend'=>2,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000',
            'fiscal_context'=>['concept_basis'=>'DEMO comprobante acreditado','evidence_reference'=>'DEMO soporte privado']];
        $state=(object)['remote'=>[],'calls'=>[],'mode'=>'normal'];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function($request)use($db,$state){
            self::assertSame(0,$db->transactionLevel());
            $state->calls[]=[$request->method(),$request->url(),$request->data()];
            if(str_ends_with($request->url(),'/Autenticacion'))return Http::response(['token'=>'private-fixture-token','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/DescargaArchivo'))return Http::response(['codigo'=>'203']);
            $islr=str_ends_with($request->url(),'/AplicarRetencionISLR');
            if($islr || str_ends_with($request->url(),'/AplicarRetencion')) {
                $data=$request->data();$key=($islr?'ISLR':'IVA').$data['numeroControl'];$field=$islr?'infoRetencionISLR':'infoRetencion';
                if($request->method()==='GET') {
                    if($state->mode==='unknown')return Http::response(['codigo'=>'203','mensaje'=>'unverified absence']);
                    return Http::response(['codigo'=>'200',$field=>$state->remote[$key] ?? null]);
                }
                if($state->mode==='rejected')return Http::response(['codigo'=>'203','validaciones'=>['bad voucher']]);
                if($request->method()==='DELETE')$state->remote[$key]=null;
                else $state->remote[$key]=array_intersect_key($data,array_flip(['serie','tipoDocumento','numeroDocumento','numeroControl']))+($islr?array_intersect_key($data['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido'])):['totalesRetencion'=>$data['totalRetencion']]);
                if($state->mode==='timeout')throw new \Illuminate\Http\Client\ConnectionException('private-fixture-token');
                return Http::response(['codigo'=>'200']);
            }
            $id=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
            return Http::response(['codigo'=>'200','resultado'=>$id+['numeroControl'=>'00-'.str_pad($id['numeroDocumento'],8,'0',STR_PAD_LEFT)]]);
        });
        return [$db,$app,$input,$state];
    }
    private function islrVoucher($doc,array $changes=[]):array
    {
        return array_replace(SpeProfiles::voucher($doc),['tax_kind'=>'ISLR','voucher_number'=>'ISLR recibido / 001','voucher_date'=>'2026-09-10','agent_id'=>$doc->customer_id,
            'concept_id'=>'055','base'=>200,'percentage'=>4,'subtrahend'=>2,'amount'=>6,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000'],$changes);
    }
    public function test_islr_coexists_with_iva_idempotence_replacement_cash_and_hka():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        foreach(['WEB','POS','VENDEYA','HOTEL','ORDER','ECOMMERCE','SUSCRIPTION','TECHNICAL_SERVICE'] as $source){
            $doc=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid(),'source_module'=>$source]))->getDocument()->fresh();
            self::assertEquals(30,$doc->balance);self::assertEquals(24,$doc->fiscal_data->iva_retention_prevision['amount']);self::assertEquals(6,$doc->fiscal_data->islr_retention_prevision['amount']);
            $iva=IvaRetentionService::store($doc,$this->ivaVoucher($doc));self::assertEquals(6,$doc->fresh()->balance);
            $voucher=$this->islrVoucher($doc);$row=IslrRetentionService::store($doc,$voucher);
            self::assertSame($row->id,IslrRetentionService::store($doc,$voucher)->id);self::assertEquals(0,$doc->fresh()->balance);
            self::assertSame('confirmed',$row->islr_operations()->first()->status);
            self::assertArrayNotHasKey('codigoOperacion',$row->islr_operations()->first()->payload['detallesRetencionISLR']);
            self::assertEquals(202,$doc->fresh()->payments->sum('payment'));self::assertCount(2,$doc->fresh()->received_retentions);
            try{IslrRetentionService::store($doc,array_replace($voucher,['voucher_number'=>'changed']));self::fail('Reused key');}catch(ValidationException $e){self::assertSame(409,$e->status);}
            try{IslrRetentionService::store($doc,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Second active ISLR');}catch(ValidationException $e){self::assertArrayHasKey('voucher_number',$e->errors());}
            $reverse=['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir comprobante ISLR'];IslrRetentionService::reverse($row,$reverse);IslrRetentionService::reverse($row,$reverse);
            self::assertEquals(6,$doc->fresh()->balance);self::assertSame('confirmed',$iva->operations()->first()->status);self::assertNotNull($state->remote['IVA'.$doc->control_number]);
            self::assertEquals(202,$doc->fresh()->payments->sum('payment'));self::assertEquals(6,$doc->fresh()->fiscal_data->islr_retention_prevision['amount']);
            $replacement=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('applied',$replacement->application_status);self::assertCount(3,$doc->fresh()->received_retentions);
            self::assertStringNotContainsString('private-fixture-token',json_encode(IslrRetentionService::view($doc->fresh())));
        }
    }
    public function test_islr_pending_support_reference_rollback_and_recovery():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        foreach([['concept_id'=>'001'],['voucher_number'=>str_repeat('X',51)],['amount'=>7],['base'=>201],['attachment'=>'received_retentions/other.pdf'],['fiscal_context'=>[]]] as $change){
            try{IslrRetentionService::store($doc,$this->islrVoucher($doc,$change));self::fail('Invalid voucher');}catch(ValidationException $e){self::assertSame(422,$e->status);}
        }
        $doc->emission()->update(['status'=>'pending']);$row=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc,['invoice_control'=>'wrong']));
        self::assertEquals(30,$doc->fresh()->balance);self::assertSame('pending_validation',$row->application_status);
        $doc->emission()->update(['status'=>'confirmed']);IslrRetentionService::resume($doc->fresh());self::assertSame('invalid',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
        $calls=count($state->calls);$db->beginTransaction();$rolled=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc));self::assertEquals(24,$doc->fresh()->balance);self::assertSame($calls,count($state->calls));$db->rollBack();self::assertNull(DocumentReceivedRetention::find($rolled->id));self::assertEquals(30,$doc->fresh()->balance);
        $state->mode='timeout';$row=IslrRetentionService::store($doc->fresh(),$this->islrVoucher($doc));self::assertSame('uncertain',$row->islr_operations()->first()->status);
        $state->mode='normal';app(HkaIslrRetention::class)->process($row,true);self::assertSame('confirmed',$row->islr_operations()->first()->status);
        $state->mode='unknown';IslrRetentionService::reverse($row,['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir']);self::assertSame('applied',$row->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
        $state->mode='normal';$row->islr_operations()->latest('id')->first()->update(['next_attempt_at'=>null]);app(HkaIslrRetention::class)->process($row);self::assertSame('reversed',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
    }
    public function test_islr_different_rates_declared_authority_and_net_capability():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        unset($input['iva_retention_prevision']);$input['currency_type_id']='USD';$input['exchange_rate_sale']='10.12345678';
        $input['payments'][0]['payment']=220;$input['islr_retention_prevision']['percentage']=4;
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $row=IslrRetentionService::store($doc,$this->islrVoucher($doc,['base'=>2000,'percentage'=>4,'subtrahend'=>20,'amount'=>60,'exchange_rate'=>'20.00000000']));
        self::assertSame('20.00000000',$row->exchange_rate);self::assertEquals(3,$row->applied_amount);self::assertSame('60.00',$row->islr_operations()->first()->payload['detallesRetencionISLR']['impuestoRetenido']);self::assertEquals(9,$doc->fresh()->balance);
        config(['islr_retention.demo_net_collection_verified'=>false]);$calls=count($state->calls);
        $blocked=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid()]))->getDocument()->fresh();self::assertSame('not_requested',$blocked->emission->status);self::assertSame($calls,count($state->calls));self::assertEquals(12,$blocked->balance);
        $profile=SpeProfiles::profile(['rif'=>'V-12345678-9','nature'=>'natural','personal_firm'=>true,'spe'=>false,'iva_agent_basis'=>'none']);FiscalProfileService::store(Person::findOrFail($input['customer_id']),$profile);
        $p=IslrRetentionService::proposal($input['customer_id'],'2026-09-10',200,'VES',$input['islr_retention_prevision']);self::assertTrue($p['eligible']);
    }
    public function test_islr_voucher_creation_upload_scope_and_activation_savepoint():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $token=(string)Str::uuid();$path='received_retentions/drafts/'.$token.'.pdf';Storage::disk('tenant')->put($path,'%PDF-private');
        ReceivedRetentionUpload::create(['token'=>$token,'user_id'=>auth()->id(),'path'=>$path,'expires_at'=>now()->addDay()]);
        $voucher=['tax_kind'=>'ISLR','operation_key'=>(string)Str::uuid(),'voucher_number'=>'Cliente-ISLR-01','voucher_date'=>'2026-09-10','agent_rif'=>'V-12345678-9','invoice_number'=>'FF01-00000100','invoice_control'=>'00-00000100','concept_id'=>'055','base'=>200,'percentage'=>4,'subtrahend'=>2,'amount'=>6,'currency_type_id'=>'VES','exchange_rate'=>'1.00000000','upload_id'=>$token,'fiscal_context'=>['concept_basis'=>'DEMO acreditación','evidence_reference'=>'DEMO soporte']];
        $input['received_retentions']=[$voucher];$db->beginTransaction();$calls=count($state->calls);
        $pending=(new Facturalo)->save($input)->getDocument();self::assertSame('pending_validation',$pending->received_retentions()->first()->application_status);self::assertEquals(30,$pending->fresh()->balance);self::assertSame($calls,count($state->calls));$db->rollBack();
        self::assertNull(ReceivedRetentionUpload::where('token',$token)->first()->document_id);self::assertTrue(Storage::disk('tenant')->exists($path));
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();$row=$doc->received_retentions->first();self::assertSame('applied',$row->application_status);self::assertEquals(24,$doc->balance);self::assertSame('confirmed',$row->islr_operations()->first()->status);
        $other=(new Facturalo)->save(array_replace($input,['external_id'=>(string)Str::uuid(),'received_retentions'=>[]]))->getDocument()->fresh();
        try{IslrRetentionService::store($other,array_replace($voucher,['operation_key'=>(string)Str::uuid()]));self::fail('Consumed token');}catch(ValidationException $e){self::assertArrayHasKey('upload_id',$e->errors());}
        auth()->user()->forceFill(['type'=>'seller','create_payment'=>false]);
        try{IslrRetentionService::store($other,$this->islrVoucher($other));self::fail('Unauthorized');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}
        auth()->user()->forceFill(['create_payment'=>true,'establishment_id'=>999]);
        try{IslrRetentionService::store($other,$this->islrVoucher($other));self::fail('Other establishment');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){self::assertSame(403,$e->getStatusCode());}
        auth()->user()->forceFill(['type'=>'admin','establishment_id'=>$doc->establishment_id]);
        $other->emission()->update(['status'=>'pending']);$bad=IslrRetentionService::store($other->fresh(),$this->islrVoucher($other));
        $payload=$other->fresh()->emission->payload;unset($payload['documentoElectronico']['encabezado']['identificacionDocumento']['moneda']);$other->emission()->update(['status'=>'confirmed','payload'=>$payload]);
        IslrRetentionService::resume($other->fresh());self::assertSame('invalid',$bad->fresh()->application_status);self::assertEquals(0,$bad->fresh()->applied_amount);self::assertEquals(30,$other->fresh()->balance);self::assertEquals(6,$other->fresh()->fiscal_data->islr_retention_prevision['amount']);self::assertSame(0,$bad->islr_operations()->count());
    }
    public function test_islr_hka_rejection_foreign_conflict_and_adopted_reversal():void
    {
        [$db,$app,$input,$state]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();$state->mode='rejected';
        $row=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('applied',$row->application_status);self::assertEquals(24,$doc->fresh()->balance);self::assertSame('rejected',$row->islr_operations()->first()->status);
        self::assertSame('203',$row->islr_operations()->first()->result['mutation_code']);
        $duplicate=$row->getAttributes();foreach(['id','active_iva_document_id','active_islr_document_id','active_voucher_number'] as $key)unset($duplicate[$key]);
        $duplicate['operation_key']=(string)Str::uuid();$duplicate['voucher_number']='Different active ISLR';
        try{$db->table('document_received_retentions')->insert($duplicate);self::fail('Missing generated ISLR unique constraint');}catch(\Illuminate\Database\QueryException $e){self::assertStringContainsString('received_islr_active_unique',$e->getMessage());}

        $state->mode='normal';IslrRetentionService::reverse($row,['operation_key'=>(string)Str::uuid(),'reason'=>'Corregir rechazo']);self::assertSame('reversed',$row->fresh()->application_status);self::assertEquals(30,$doc->fresh()->balance);
        $voucher=$this->islrVoucher($doc);$foreign=new DocumentReceivedRetention;$foreign->forceFill($voucher);$payload=\App\Services\Fiscal\HkaIslrRetentionPayload::build($doc,$foreign);
        $key='ISLR'.$doc->control_number;$state->remote[$key]=\App\Services\Fiscal\HkaIslrRetentionPayload::identity($payload)+array_intersect_key($payload['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido']));
        $adopted=IslrRetentionService::store($doc,$voucher);self::assertSame('confirmed',$adopted->islr_operations()->first()->status);self::assertSame(0,$adopted->islr_operations()->first()->attempts);
        IslrRetentionService::reverse($adopted,['operation_key'=>(string)Str::uuid(),'reason'=>'Retirar comprobante adoptado']);self::assertSame('reversed',$adopted->fresh()->application_status);self::assertNull($state->remote[$key]);
        $state->remote[$key]=['numeroCompRetencion'=>'external-other'];$calls=count($state->calls);
        $conflict=IslrRetentionService::store($doc,$this->islrVoucher($doc));self::assertSame('conflict',$conflict->islr_operations()->first()->status);self::assertSame(0,$conflict->islr_operations()->first()->attempts);self::assertSame('external-other',$state->remote[$key]['numeroCompRetencion']);
        IslrRetentionService::reverse($conflict,['operation_key'=>(string)Str::uuid(),'reason'=>'Resolver discrepancia externa']);self::assertSame('applied',$conflict->fresh()->application_status);self::assertEquals(24,$doc->fresh()->balance);
        self::assertSame('conflict',$conflict->islr_operations()->latest('id')->first()->status);self::assertEquals(202,$doc->fresh()->payments->sum('payment'));
    }
    public function test_islr_concurrent_registration_collection_and_reversal():void
    {
        [$db,$app,$input]=$this->bootIslrFixture();
        $doc=(new Facturalo)->save($input)->getDocument()->fresh();
        $doc->forceFill(['fiscal_emission_mode'=>'free_form'])->save();
        $voucher=$this->islrVoucher($doc);
        $result=$this->runFiscalWorkers($doc->id,'retention',[$voucher,$voucher]);
        self::assertCount(2,array_filter($result,fn($r)=>isset($r['id'])));self::assertSame($result[0]['id'],$result[1]['id']);self::assertEquals(24,$doc->fresh()->balance);
        $row=DocumentReceivedRetention::findOrFail($result[0]['id']);
        $reverse=['action'=>'reverse','retention_id'=>$row->id,'input'=>['operation_key'=>(string)Str::uuid(),'reason'=>'Corrección concurrente']];
        $result=$this->runFiscalWorkers($doc->id,'iva-mutations',[$reverse,$reverse]);self::assertCount(2,array_filter($result,fn($r)=>isset($r['id'])));self::assertEquals(30,$doc->fresh()->balance);
        $payment=['date_of_payment'=>'2026-09-10','payment_method_type_id'=>'01','original_amount'=>30,'currency_type_id'=>'VES','exchange_rate'=>1,'operation_key'=>(string)Str::uuid(),'financial_intermediation'=>'financial_institution'];
        $voucher=$this->islrVoucher($doc);
        $result=$this->runFiscalWorkers($doc->id,'iva-mutations',[['action'=>'register','input'=>$voucher],['action'=>'payment','input'=>$payment]]);
        self::assertCount(1,array_filter($result,fn($r)=>isset($r['id'])));self::assertGreaterThanOrEqual(0,$doc->fresh()->balance);
        self::assertEquals(232,$doc->fresh()->payments->sum('payment')+$doc->fresh()->received_retentions->sum('applied_amount')+$doc->fresh()->balance);
    }
}
