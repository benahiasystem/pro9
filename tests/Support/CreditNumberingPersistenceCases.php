<?php
namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Models\Tenant\Document;
use App\Services\Fiscal\{HkaEmission, HkaCreditNumbering, FiscalNote, NoteAnnulment};
use Illuminate\Support\Str;

// ######## INICIO CONFLICTO NUMERACION NC HKA ########
trait CreditNumberingPersistenceCases
{
    private function creditNumberingFixture(string &$mode, array &$calls): array
    {
        [$db,$app,$input,$invoice]=$this->noteFixture();
        $line=$invoice->items->first();$stock=$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock');
        $app->instance(\Illuminate\Http\Client\Factory::class,new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
        $identity=[];
        $app->make(\Illuminate\Http\Client\Factory::class)->fake(function($request) use ($db,&$mode,&$calls,&$identity) {
            self::assertSame(0,$db->transactionLevel(),'All HKA requests must remain outside locks');
            if(str_ends_with($request->url(),'/Autenticacion'))return \Illuminate\Support\Facades\Http::response(['token'=>'private-test-token','expiracion'=>date('c',time()+3600)]);
            if(str_ends_with($request->url(),'/DescargaArchivo'))return \Illuminate\Support\Facades\Http::response([],500);
            $calls[]=['url'=>$request->url(),'data'=>$request->data()];
            if(str_ends_with($request->url(),'/Emision')) {
                $identity=$request['documentoElectronico']['encabezado']['identificacionDocumento'];
                return \Illuminate\Support\Facades\Http::response($mode==='resend'
                    ? ['codigo'=>'200','resultado'=>$identity+['numeroControl'=>'00-00000999']]
                    : ['codigo'=>'201','mensaje'=>'private-test-token']);
            }
            self::assertStringEndsWith('/EstadoDocumento',$request->url(),'No annulment may be sent for another operation');
            if($mode==='http-failure')return \Illuminate\Support\Facades\Http::response([],500);
            if(isset($request['transaccionId'])) {
                if($mode==='original')return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','estado'=>$identity+['numeroControl'=>'00-00000999','estadoDocumento'=>'Procesado']]);
                if($mode==='own-annulled')return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','estado'=>$identity+['numeroControl'=>'00-00000999','estadoDocumento'=>'Anulada']]);
            } else {
                self::assertSame(['tipoDocumento'=>'02','serie'=>$identity['serie'],'numeroDocumento'=>$identity['numeroDocumento']],$request->data());
                if($mode==='timeout')throw new \Illuminate\Http\Client\ConnectionException('private-test-token');
                if($mode==='incomplete')return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','estado'=>['tipoDocumento'=>'02']]);
                if(in_array($mode,['conflict','active'],true))return \Illuminate\Support\Facades\Http::response(['codigo'=>'200','validaciones'=>[], 'estado'=>[
                    'tipoDocumento'=>'02','numeroDocumento'=>$identity['numeroDocumento'],'numeroControl'=>'00-00000712',
                    'transaccionId'=>'another-remote-operation','estadoDocumento'=>$mode==='active'?'Procesado':'Anulada']]);
            }
            return \Illuminate\Support\Facades\Http::response(['codigo'=>'203','mensaje'=>'Consulta no procesada','estado'=>null,
                'validaciones'=>['Documento no encontrado en nuestra base de datos']]);
        });
        $note=(new Facturalo)->forWebDocuments()->save($this->noteInput($input,$invoice,'07','07',[
            ['affected_document_item_id'=>$line->id,'quantity'=>1]]))->getDocument()->fresh();
        return [$db,$invoice,$note,$line,$stock];
    }

    public function test_note_numbering_conflict_preserves_effects_blocks_send_and_allows_only_verified_local_annulment(): void
    {
        $mode='conflict';$calls=[];
        [$db,$invoice,$note,$line,$stock]=$this->creditNumberingFixture($mode,$calls);
        $emission=$note->emission;$identity=$emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
        self::assertSame('uncertain',$emission->status); self::assertNull($note->control_number);
        self::assertSame('201',$emission->response['send_result']['code']);
        self::assertSame('203',$emission->response['query_result']['transaction']['code']);
        self::assertSame('200',$emission->response['query_result']['number']['code']);
        self::assertNotNull(HkaCreditNumbering::conflict($emission));
        self::assertTrue(HkaCreditNumbering::canCancelLocally($emission));
        $counts=[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('inventory_kardex')->count()];
        $key=$emission->operation_key;$payload=$emission->payload;
        $service=app(HkaEmission::class);$view=$service->send($note);
        self::assertTrue($view['numbering_conflict']);self::assertFalse($view['can_send']);
        self::assertSame('Conflicto de numeración',$view['description']);self::assertTrue($view['annulment']['can_request']);
        self::assertCount(3,$calls,'A conflict must not send or query implicitly on another send');
        foreach(['timeout','http-failure','absent'] as $mode){
            $view=$service->query($note->fresh());
            self::assertTrue($view['numbering_conflict']); self::assertFalse($view['can_send']);
            self::assertStringContainsString('00-00000712',$view['diagnostic']);
        }
        self::assertSame($key,$note->fresh()->emission->operation_key);self::assertSame($payload,$note->fresh()->emission->payload);
        self::assertSame($counts,[$db->table('documents')->count(),$db->table('document_payments')->count(),$db->table('inventory_kardex')->count()]);
        self::assertEquals($stock+1,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        self::assertEquals($note->total,FiscalNote::credits($invoice->fresh()));
        self::assertStringNotContainsString('private-test-token',json_encode($note->fresh()->emission->response));
        self::assertStringNotContainsString('another-remote-operation',json_encode($note->fresh()->emission->response));
        $mode='own-annulled';$view=$service->query($note->fresh());
        self::assertTrue($view['numbering_conflict']);self::assertFalse($view['annulment']['can_request']);
        self::assertFalse(HkaCreditNumbering::canCancelLocally($note->fresh()->emission));
        $mode='conflict';$service->query($note->fresh());
        $response=$note->fresh()->emission->response;$invalid=$response;$invalid['transaction_absence']['operation_key']='another-operation';
        $note->emission->update(['response'=>$invalid]);
        self::assertFalse(NoteAnnulment::view($note->fresh())['can_request']);
        $note->emission->update(['response'=>$response]);
        $adjustmentId=$db->table('document_note_adjustments')->insertGetId(['document_id'=>$note->id,'affected_document_id'=>$invoice->id,
            'kind'=>'fund','amount'=>1,'status'=>'applied','reason'=>'Existing adjustment','request'=>'{}','context'=>'{}',
            'operation_key'=>(string)Str::uuid(),'request_hash'=>str_repeat('0',64),'actor_id'=>auth()->id()]);
        self::assertFalse(NoteAnnulment::view($note->fresh())['can_request']);
        $db->table('document_note_adjustments')->where('id',$adjustmentId)->update(['reversed_at'=>now()]);
        $before=count($calls);
        $result=app(NoteAnnulment::class)->request($note->fresh(),['operation_key'=>(string)Str::uuid(),'reason'=>'Número ocupado en HKA por otra operación']);
        self::assertSame('completed',$result['annulment']['status']);self::assertFalse($result['annulment']['remote']);
        self::assertSame($before,count($calls));self::assertSame('11',$note->fresh()->state_type_id);
        self::assertEquals($stock,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
        self::assertEquals(0,FiscalNote::credits($invoice->fresh()));
        self::assertSame($identity,$note->fresh()->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento']);
    }

    public function test_note_numbering_absence_in_both_lookups_allows_one_same_identity_retry_after_thirty_seconds(): void
    {
        $mode='absent';$calls=[];[$db,$invoice,$note,$line,$stock]=$this->creditNumberingFixture($mode,$calls);
        self::assertTrue($note->emission->response['retry_allowed']);
        self::assertFalse(\App\Services\Fiscal\DocumentEmissionView::forDocument($note)['can_send']);
        $key=$note->emission->operation_key;$payload=$note->emission->payload;
        $response=$note->emission->response;$response['started_at']=time()-60;$note->emission->update(['response'=>$response]);
        $mode='resend';$view=app(HkaEmission::class)->send($note->fresh());
        self::assertSame('confirmed',$view['status']);self::assertFalse($view['numbering_conflict']);
        self::assertSame($key,$note->fresh()->emission->operation_key);self::assertSame($payload,$note->fresh()->emission->payload);
        self::assertCount(2,array_filter($calls,fn($r)=>str_ends_with($r['url'],'/Emision')));
        self::assertEquals($stock+1,$db->table('item_warehouse')->where('item_id',$line->item_id)->value('stock'));
    }

    public function test_note_numbering_incomplete_lookup_blocks_retry_without_claiming_a_conflict(): void
    {
        $mode='incomplete';$calls=[];[$db,$invoice,$note]=$this->creditNumberingFixture($mode,$calls);
        $view=\App\Services\Fiscal\DocumentEmissionView::forDocument($note);
        self::assertFalse($view['can_send']);self::assertFalse($view['numbering_conflict']);self::assertFalse($view['annulment']['can_request']);
        self::assertStringContainsString('No se pudo verificar',$view['diagnostic']);
        $mode='timeout';$view=app(HkaEmission::class)->query($note->fresh());
        self::assertFalse($view['can_send']);self::assertFalse($view['numbering_conflict']);self::assertFalse($view['annulment']['can_request']);
    }

    public function test_note_numbering_can_recover_the_original_operation_and_clear_a_verified_conflict(): void
    {
        $mode='active';$calls=[];[$db,$invoice,$note]=$this->creditNumberingFixture($mode,$calls);
        self::assertNotNull(HkaCreditNumbering::conflict($note->emission));
        self::assertStringContainsString('estado Procesado',$note->emission->response['diagnostic']);
        $mode='original';$view=app(HkaEmission::class)->query($note);
        self::assertSame('confirmed',$view['status']);self::assertFalse($view['numbering_conflict']);
        self::assertSame('00-00000999',$note->fresh()->control_number);
        self::assertNull($note->fresh()->emission->response['transaction_absence']);
        self::assertSame('201',$note->fresh()->emission->response['send_result']['code']);
    }
}
// ######## FIN CONFLICTO NUMERACION NC HKA ########
