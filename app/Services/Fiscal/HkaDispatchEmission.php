<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Dispatch};
use Illuminate\Support\Str;

final class HkaDispatchEmission
{
    public static function register(Dispatch $order,Company $company): void
    {
        if ($company->fiscal_emission_mode!=='digital') return;
        $snapshot=json_decode(json_encode($order->toArray(),JSON_THROW_ON_ERROR),true);
        $snapshot['issuer']=FiscalDocumentPersistence::issuer($company,(array)$order->establishment);
        $snapshot['fiscal_emission_mode']=$company->fiscal_emission_mode;
        $snapshot['reason_description']=$order->transfer_reason_type->description;
        $snapshot['catalogs']=[];
        foreach (['product_type'=>'cat_product_types','product_origin'=>'cat_product_origins','taxation_product'=>'cat_taxation_products'] as $key=>$table) if ($id=$order->hka_data[$key.'_id'] ?? null) $snapshot['catalogs'][$key]=$order->getConnection()->table($table)->where('id',$id)->value('description');
        if ($order->reference_document_id && $invoice=\App\Models\Tenant\Document::find($order->reference_document_id)) {
            if (optional($invoice->emission)->status==='confirmed') $snapshot['invoice_identity']=array_intersect_key($invoice->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'],array_flip(['tipoDocumento','serie','numeroDocumento']));
        }
        $emission=$order->emission;
        if ($emission && !in_array($emission->status,['not_requested','prepared','rejected'],true)) return;
        $registered=$order->emission()->updateOrCreate([],['fiscal_environment'=>$order->fiscal_environment,'operation_key'=>(string)Str::uuid(),'status'=>'not_requested','snapshot'=>$snapshot,'payload'=>null,'response'=>null,'contract_version'=>HkaPayloadBuilder::contractVersion()]);
        $order->setRelation('emission',$registered);
        if ($order->fiscal_environment!=='demo') return;
        $id=$order->id;$database=$order->getConnection()->getDatabaseName();
        $order->getConnection()->afterCommit(static function() use ($id,$database) {
            if ((new Dispatch)->getConnection()->getDatabaseName()!==$database) return;
            try { app(self::class)->send(Dispatch::findOrFail($id)); } catch (\Throwable $ignored) { /* Durable intent remains recoverable. */ }
        });
    }

    public static function record(Dispatch $order)
    {
        if ($order->relationLoaded('emission')) return $order->getRelation('emission');
        return $order->getConnection()->getSchemaBuilder()->hasTable('dispatch_emissions') ? $order->emission : null;
    }

    public static function view(Dispatch $order): array
    {
        $e=self::record($order);$status=$e->status ?? null;
        $enabled=$e && $e->fiscal_environment==='demo' && !in_array($order->state_type_id,['09','11'],true);
        $u=auth()->user();$scope=$u instanceof \App\Models\Tenant\User && ($u->type==='admin' || (int)$u->establishment_id===(int)$order->establishment_id);
        return ['status'=>$status,'description'=>[''=>'Local','not_requested'=>'Sin solicitar','prepared'=>'Preparado','pending'=>'Enviando','confirmed'=>'Confirmado','rejected'=>'Rechazado','uncertain'=>'Por conciliar','cancelled'=>'Cancelado'][$status ?? ''],
            'environment'=>$order->fiscal_environment,'control_number'=>$order->control_number,'code'=>$e->response['code'] ?? null,'validation_codes'=>$e->response['validation_codes'] ?? [],
            'diagnostic'=>$e->response['diagnostic'] ?? ($e && !$enabled ? 'La emisión está habilitada únicamente en DEMO.':null),
            'can_send'=>$enabled && $scope && $u->type==='admin' && (in_array($status,['not_requested','prepared'],true) || ($status==='uncertain' && !empty($e->response['retry_allowed']) && time()-($e->response['started_at'] ?? time())>=30)),
            'can_query'=>$enabled && $scope && in_array($status,['pending','uncertain','confirmed'],true),'relations'=>$e->response['relations_view'] ?? []];
    }

    public function send(Dispatch $order): array
    {
        $attempt=null;$key=null;
        try {
            $db=$order->getConnection();if ($db->transactionLevel()) FiscalAmounts::error('emission','El envío debe ejecutarse después del commit comercial.');
            $order=$order->fresh();$company=$this->company($order);$key=$order->emission->operation_key;
            if (in_array($order->emission->status,['pending','uncertain'],true)) { $this->query($order);$order=$order->fresh(); }
            if (in_array($order->emission->status,['confirmed','rejected','cancelled','pending'],true)) return self::view($order);
            if ($order->emission->status==='uncertain' && (empty($order->emission->response['retry_allowed']) || time()-($order->emission->response['started_at'] ?? time())<30)) return self::view($order);
            $context=self::context($company);$token=app(HkaTransport::class)->token($company);
            $attempt=$db->transaction(function() use($order,$key,$context) {
                $company=Company::lockForUpdate()->firstOrFail();$locked=Dispatch::lockForUpdate()->findOrFail($order->id);$this->company($locked);
                if (self::context($company)!==$context) FiscalAmounts::error('emission','La configuración HKA cambió durante la autenticación.');
                $e=$locked->emission()->lockForUpdate()->firstOrFail();
                if ($e->operation_key!==$key || !in_array($e->status,['not_requested','prepared','uncertain'],true)) return null;
                if ($e->status==='uncertain' && (empty($e->response['retry_allowed']) || time()-($e->response['started_at'] ?? time())<30)) return null;
                if (!empty($e->response['context']) && $e->response['context']!==$context) FiscalAmounts::error('emission','La conexión no corresponde a la operación conservada.');
                $payload=$e->payload ?: (new HkaDispatchPayloadBuilder)->build($e->snapshot,$e->operation_key);
                $id=(string)Str::uuid();$e->update(['status'=>'pending','payload'=>$payload,'response'=>array_replace($e->response ?? [],['attempt_id'=>$id,'context'=>$context,'started_at'=>time(),'diagnostic'=>'Envío HKA en curso.','retry_allowed'=>false])]);
                return ['id'=>$id,'payload'=>$payload];
            });
            if (!$attempt) return self::view($order->fresh());
            $response=app(HkaTransport::class)->emission($token,$attempt['payload']);
            $identity=$attempt['payload']['documentoElectronico']['encabezado']['identificacionDocumento'];
            $this->finish($order,$key,$attempt['id'],HkaResponse::interpret($response->status(),$response->json(),$identity));
        } catch (\Throwable $error) { $this->failure($order,$key,$attempt['id'] ?? null,$error); }
        return self::view($order->fresh());
    }

    public function query(Dispatch $order): array
    {
        $key=null;$attempt=null;
        try {
            if ($order->getConnection()->transactionLevel()) FiscalAmounts::error('emission','La consulta debe ejecutarse fuera de la transacción comercial.');
            $order=$order->fresh();$company=$this->company($order);$e=$order->emission;$key=$e->operation_key;$attempt=$e->response['attempt_id'] ?? null;
            if (!in_array($e->status,['pending','uncertain','confirmed'],true) || !$e->payload || ($e->status==='pending' && time()-($e->response['started_at'] ?? time())<40)) return self::view($order);
            if (self::context($company)!==($e->response['context'] ?? null)) FiscalAmounts::error('emission','La conexión no corresponde a la operación conservada.');
            $identity=$e->payload['documentoElectronico']['encabezado']['identificacionDocumento'];$transport=app(HkaTransport::class);
            $response=$transport->status($transport->token($company),$identity);
            $this->finish($order,$key,$attempt,HkaResponse::interpret($response->status(),$response->json(),$identity,true));
        } catch (\Throwable $error) { $this->failure($order,$key,$attempt,$error); }
        return self::view($order->fresh());
    }

    public function company(Dispatch $order): Company
    {
        $c=Company::firstOrFail();$e=$order->emission;
        if (!$e || $order->document_type_id!=='09' || $order->fiscal_environment!=='demo' || $c->fiscal_environment!=='demo' || $c->fiscal_emission_mode!=='digital' || ($e->snapshot['fiscal_emission_mode'] ?? null)!=='digital' || in_array($order->state_type_id,['09','11'],true)) FiscalAmounts::error('emission','La orden debe estar vigente en Medios digitales y DEMO.');
        if ((string)($e->snapshot['number'] ?? '')!==(string)$order->number || ($e->snapshot['series'] ?? null)!==$order->series || (int)($e->snapshot['establishment_id'] ?? 0)!==(int)$order->establishment_id) FiscalAmounts::error('emission','La identidad de la orden no corresponde a la operación conservada.');
        $rif=fn($s)=>preg_replace('/[^A-Z0-9]/','',strtoupper($s));
        if ($rif($e->snapshot['issuer']['number'] ?? '')!==$rif($c->number)) FiscalAmounts::error('emission','El emisor no corresponde al snapshot conservado.');
        if (Dispatch::where('fiscal_environment','demo')->where('series',$order->series)->where('number',$order->number)->where('id','!=',$order->id)->exists()) FiscalAmounts::error('emission','La identidad serie/número es ambigua entre sucursales para HKA.');
        return $c;
    }

    public static function context(Company $company): array
    {
        return ['issuer'=>$company->number,'environment'=>$company->fiscal_environment,'credentials_fingerprint'=>hash_hmac('sha256',json_encode(HkaAuthentication::credentials($company)),(string)config('app.key'))];
    }

    private function finish(Dispatch $order,string $key,?string $attempt,array $result): void
    {
        $order->getConnection()->transaction(function() use($order,$key,$attempt,$result) {
            Company::lockForUpdate()->firstOrFail();$d=Dispatch::lockForUpdate()->findOrFail($order->id);$this->company($d);$e=$d->emission()->lockForUpdate()->firstOrFail();
            if ($e->operation_key!==$key || ($e->response['attempt_id'] ?? null)!==$attempt || !in_array($e->status,['pending','uncertain','confirmed'],true)) return;
            if ($e->status==='confirmed' && $result['status']!=='confirmed') { $e->update(['response'=>array_replace($e->response,['diagnostic'=>'Orden confirmada. La última consulta no pudo verificarse.'])]);return; }
            if ($result['status']==='confirmed') {
                $control=$result['control_number'];
                if (($d->control_number && $d->control_number!==$control) || Dispatch::where('fiscal_environment','demo')->where('control_number',$control)->where('id','!=',$d->id)->exists() || \App\Models\Tenant\Document::where('fiscal_environment','demo')->where('control_number',$control)->exists()) FiscalAmounts::error('control_number','El control HKA ya pertenece a otro documento.');
                $d->forceFill(['control_number'=>$control])->save();$e->control_number=$control;$e->consulta_url=$result['consulta_url'];
            }
            $e->status=$result['status'];$e->response=array_replace($e->response ?? [],$result,['checked_at'=>time()]);$e->save();
        });
        if ($order->fresh()->emission->status==='confirmed') {
            try { app(HkaDispatchPdf::class)->download($order->fresh(),false); } catch (\Throwable $ignored) { /* Confirmation survives PDF errors. */ }
            try { app(HkaDispatchRelations::class)->sync($order->fresh(),false); } catch (\Throwable $ignored) { /* Relationships recover independently. */ }
        }
    }

    private function failure(Dispatch $order,?string $key,?string $attempt,\Throwable $error): void
    {
        try { $order->getConnection()->transaction(function() use($order,$key,$attempt,$error) {
            Company::lockForUpdate()->firstOrFail();Dispatch::lockForUpdate()->findOrFail($order->id);$e=$order->emission()->lockForUpdate()->firstOrFail();
            if ($key!==$e->operation_key || ($attempt && ($e->response['attempt_id'] ?? null)!==$attempt) || $e->status==='confirmed') return;
            $message=$attempt ? 'No se pudo confirmar la emisión. Consulte HKA antes de reenviar.':'Orden guardada. No se pudo preparar o conectar con HKA.';
            if (!$attempt && $error instanceof \Illuminate\Validation\ValidationException) $message=implode(' ',array_merge(...array_values($error->errors())));
            $e->update(['status'=>$attempt ? 'uncertain':$e->status,'response'=>array_replace($e->response ?? [],['diagnostic'=>mb_substr($message,0,1000),'retry_allowed'=>false])]);
        }); } catch (\Throwable $ignored) { /* A claim interrupted before completion remains queryable. */ }
    }
}
