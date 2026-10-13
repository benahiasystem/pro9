<?php
namespace Tests\Unit;
use App\Models\Tenant\{Document,DocumentEmission,DocumentReceivedRetention};
use App\Services\Fiscal\{HkaIslrRetention,HkaIslrRetentionPayload,HkaTransport,HkaIslrRetentionCapability};
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class HkaIslrRetentionTest extends TestCase
{
    private function payload():array
    {
        $doc=new Document;$doc->setRawAttributes(['control_number'=>'00-100','currency_type_id'=>'USD','exchange_rate_sale'=>'10.12345678']);
        $emission=new DocumentEmission;$emission->forceFill(['status'=>'confirmed','payload'=>['documentoElectronico'=>['encabezado'=>['identificacionDocumento'=>['tipoDocumento'=>'01','serie'=>'','numeroDocumento'=>'100','moneda'=>'VES']]]]]);$doc->setRelation('emission',$emission);
        $row=new DocumentReceivedRetention;$row->setRawAttributes(['voucher_number'=>'ISLR-text-50','voucher_date'=>'2026-10-12','currency_type_id'=>'VES','exchange_rate'=>'20.12345678','base'=>200,'percentage'=>4,'subtrahend'=>2,'amount'=>6,'agent_number'=>'V-12345678-9']);
        return HkaIslrRetentionPayload::build($doc,$row);
    }
    public function test_payload_and_transport_use_frozen_currency_and_json_get_delete():void
    {
        $payload=$this->payload();self::assertSame('6.00',$payload['detallesRetencionISLR']['impuestoRetenido']);self::assertArrayNotHasKey('codigoOperacion',$payload['detallesRetencionISLR']);self::assertArrayNotHasKey('base',$payload['detallesRetencionISLR']);
        Http::fake(['*'=>Http::response(['codigo'=>'200'])]);$transport=new HkaTransport;$id=HkaIslrRetentionPayload::identity($payload);
        $transport->applyIslrRetention('secret-test',$payload);$transport->queryIslrRetention('secret-test',$id);$transport->removeIslrRetention('secret-test',$id);
        foreach(['POST','GET','DELETE'] as $method)Http::assertSent(fn($req)=>$req->method()===$method && str_ends_with($req->url(),'/AplicarRetencionISLR') && $req->data()===($method==='POST'?$payload:$id) && $req->hasHeader('Content-Type','application/json'));
    }
    public function test_query_verifies_only_returned_fields_and_stops_at_conflicts():void
    {
        $payload=$this->payload();$remote=HkaIslrRetentionPayload::identity($payload)+array_intersect_key($payload['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido']));
        $method=new \ReflectionMethod(HkaIslrRetention::class,'interpretRead');$adapter=new HkaIslrRetention;
        $read=fn($body)=>$method->invoke($adapter,new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200,[],json_encode($body))),$payload);
        self::assertSame('match',$read(['codigo'=>'200','infoRetencionISLR'=>$remote]));self::assertSame('absent',$read(['codigo'=>'200','infoRetencionISLR'=>null]));
        self::assertSame('absent',$read(['codigo'=>'204','mensaje'=>'No se ha registrado aún ninguna Retención de ISLR a este documento','validaciones'=>[]]));
        self::assertSame('unknown',$read(['codigo'=>'204','mensaje'=>'different']));
        self::assertSame('unknown',$read(['codigo'=>'204','mensaje'=>'No se ha registrado aún ninguna Retención de ISLR a este documento','infoRetencionISLR'=>$remote]));
        self::assertSame('unknown',$read(['codigo'=>'200']));self::assertSame('unknown',$read(['codigo'=>'203','mensaje'=>'Retención no encontrada','validaciones'=>['No se ha aplicado retención previamente por lo que no se puede consultar nada']]));
        foreach(['numeroCompRetencion','fechaEmisionCR','numeroControl'] as $key)self::assertSame('conflict',$read(['codigo'=>'200','infoRetencionISLR'=>array_replace($remote,[$key=>'other'])]));
        unset($remote['impuestoRetenido']);self::assertSame('unknown',$read(['codigo'=>'200','infoRetencionISLR'=>$remote]));
        self::assertNotContains('porcentaje',HkaIslrRetentionPayload::VERIFIED_FIELDS);self::assertNotContains('rifAgenteRetencion',HkaIslrRetentionPayload::VERIFIED_FIELDS);
    }
    public function test_unverified_net_capability_requires_both_tax_acceptances():void
    {
        config(['islr_retention.demo_net_collection_verified'=>false]);$snapshot=['document_type_id'=>'01','total'=>116,'payments'=>[['payment'=>101]],'fiscal_data'=>['islr_retention_prevision'=>['amount'=>3]]];
        self::assertTrue(HkaIslrRetentionCapability::blockedSnapshot($snapshot));$snapshot['payments'][0]['payment']=116;self::assertFalse(HkaIslrRetentionCapability::blockedSnapshot($snapshot));
    }
    public function test_malformed_public_forecast_arrays_return_ordinary_validation():void
    {
        foreach ([['islr_retention_prevision'=>'invalid'],['received_retentions'=>'invalid'],['received_retentions'=>['invalid']]] as $input) {
            try { \App\Services\Fiscal\FiscalDocumentPersistence::applySettlements(new Document,$input);self::fail('Malformed public retention input'); }
            catch (\Illuminate\Validation\ValidationException $e) { self::assertSame(422,$e->status); }
        }
    }
}
