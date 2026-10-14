<?php
namespace App\Services\Fiscal;
use App\Models\Tenant\{Company,Dispatch,Document};
use Illuminate\Support\Str;

/** Local references persist independently of the external relationship journal. */
final class HkaDispatchRelations
{
    public static function attach(Document $invoice,array $input): void
    {
        $ids=[];if (!empty($input['dispatch_id'])) $ids[]=(int)$input['dispatch_id'];
        $related=$input['dispatches_relateds'] ?? [];
        if (is_string($related)) $related=json_decode($related,true) ?: [];
        foreach ($related as $reference) {
            $parsed=FiscalIdentity::parseNumberFull(is_array($reference) ? ($reference['number_full'] ?? '') : (string)$reference);
            if (!$parsed) FiscalAmounts::error('dispatches_relateds','La referencia de orden es inválida.');
            $ids[]=Dispatch::where('establishment_id',$invoice->establishment_id)->where('series',$parsed[0])->where('number',$parsed[1])->firstOrFail()->id;
        }
        foreach (array_unique($ids) as $id) {
            $d=Dispatch::lockForUpdate()->findOrFail($id);DeliveryOrderInput::authorize((int)$d->establishment_id);
            if ((int)$d->establishment_id!==(int)$invoice->establishment_id || (int)$d->customer_id!==(int)$invoice->customer_id || in_array($d->state_type_id,['09','11'],true) || ($d->reference_document_id && (int)$d->reference_document_id!==(int)$invoice->id)) FiscalAmounts::error('dispatches_relateds','La orden no puede asociarse a esta factura.');
            $d->reference_document_id=$invoice->id;$d->save();
        }
    }

    public static function resumeInvoice(Document $invoice): void
    {
        if (optional($invoice->emission)->status!=='confirmed') return;
        foreach (Dispatch::where('reference_document_id',$invoice->id)->get() as $order) { try { app(self::class)->sync($order,false); } catch (\Throwable $ignored) {} }
    }

    public function sync(Dispatch $order,bool $authorize=true): array
    {
        if ($authorize) DeliveryOrderInput::authorize((int)$order->establishment_id);
        if ($order->getConnection()->transactionLevel()) FiscalAmounts::error('relations','Concílielas después del commit comercial.');
        $order=$order->fresh();$invoice=Document::find($order->reference_document_id);
        if (!$invoice || optional($invoice->emission)->status!=='confirmed' || optional($order->emission)->status!=='confirmed') return ['status'=>'waiting','description'=>'Espera la confirmación de la factura y de la orden.'];
        if ((int)$invoice->establishment_id!==(int)$order->establishment_id || (int)$invoice->customer_id!==(int)$order->customer_id || in_array($invoice->state_type_id,['09','11'],true)) FiscalAmounts::error('relations','La factura debe estar vigente y pertenecer al mismo cliente y sucursal.');
        $company=app(HkaDispatchEmission::class)->company($order);$context=HkaDispatchEmission::context($company);$e=$order->emission;$key=$e->operation_key;
        if ($context!==($e->response['context'] ?? null) || $invoice->fiscal_environment!=='demo' || $invoice->fiscal_emission_mode!=='digital') FiscalAmounts::error('relations','La configuración no corresponde a ambos documentos.');
        $identity=fn($p)=>array_intersect_key($p['documentoElectronico']['encabezado']['identificacionDocumento'],array_flip(['serie','tipoDocumento','numeroDocumento']));
        $source=$identity($e->payload);$target=$identity($invoice->emission->payload);$hash=hash('sha256',json_encode([$source,$target]));$journal=$e->response['relation'] ?? [];
        if (($journal['hash'] ?? null)===$hash && ($journal['status'] ?? null)==='confirmed') return ['status'=>'confirmed','description'=>'Relación confirmada en HKA.'];
        if (($journal['status'] ?? null)==='pending' && time()-($journal['started_at'] ?? time())<40) return ['status'=>'pending','description'=>'Relación HKA en curso.'];
        $transport=app(HkaTransport::class);$token=$transport->token($company);
        // Read first: a timeout must be reconciled before another mutation.
        $remote=$transport->dispatchRelations($token,$source);$found=$this->matches($remote->status(),$remote->json(),$target);
        $attempt=null;$state='uncertain';
        if ($found===true) $state='confirmed';
        elseif ($found===false) {
            $attempt=$order->getConnection()->transaction(function() use($order,$key,$context,$hash,$authorize) {
                $company=Company::lockForUpdate()->firstOrFail();$d=Dispatch::lockForUpdate()->findOrFail($order->id);if ($authorize) DeliveryOrderInput::authorize((int)$d->establishment_id);app(HkaDispatchEmission::class)->company($d);
                $e=$d->emission()->lockForUpdate()->firstOrFail();if ($e->operation_key!==$key || HkaDispatchEmission::context($company)!==$context) FiscalAmounts::error('relations','La operación cambió.');
                if (($e->response['relation']['status'] ?? '')==='pending' && time()-($e->response['relation']['started_at'] ?? time())<40) return null;
                $id=(string)Str::uuid();$response=$e->response;$response['relation']=['hash'=>$hash,'status'=>'pending','attempt_id'=>$id,'started_at'=>time()];$e->update(['response'=>$response]);return $id;
            });
            if (!$attempt) return ['status'=>'pending','description'=>'Relación HKA en curso.'];
            try {
                $response=$transport->relateDispatch($token,$source+['relacionados'=>[$target],'comentario'=>'Relación Pro9 Orden de entrega y Factura']);
                $body=$response->json();
                if ($response->successful() && (string)($body['codigo'] ?? '')==='200' && empty($body['validaciones'])) {
                    $check=$transport->dispatchRelations($token,$source);$state=$this->matches($check->status(),$check->json(),$target)===true ? 'confirmed':'uncertain';
                } elseif ($response->successful() && !empty($body['validaciones'])) $state='rejected';
            } catch (\Throwable $ignored) { $state='uncertain'; }
        }
        $view=['status'=>$state,'description'=>$state==='confirmed' ? 'Relación confirmada en HKA.':'La relación requiere conciliación.'];
        $order->getConnection()->transaction(function() use($order,$key,$hash,$attempt,$state,$view,$context) {
            $c=Company::lockForUpdate()->firstOrFail();$d=Dispatch::lockForUpdate()->findOrFail($order->id);app(HkaDispatchEmission::class)->company($d);$e=$d->emission()->lockForUpdate()->firstOrFail();
            if ($e->operation_key!==$key || HkaDispatchEmission::context($c)!==$context || ($attempt && ($e->response['relation']['attempt_id'] ?? null)!==$attempt)) return;
            $response=$e->response;$response['relation']=array_replace($response['relation'] ?? [],['hash'=>$hash,'status'=>$state,'checked_at'=>time()]);$response['relations_view']=$view;$e->update(['response'=>$response]);
        });
        return $view;
    }

    private function matches(int $http,$body,array $target): ?bool
    {
        if ($http<200 || $http>=300 || !is_array($body)) return null;
        // Exact absence envelope observed in DEMO on 2026-10-13.
        if ((string)($body['codigo'] ?? '')==='404' && ($body['mensaje'] ?? '')==='Relación no encontrada' && ($body['validaciones'] ?? null)===['Relación no encontrada en nuestra base de datos.']) return false;
        if (!empty($body['validaciones'])) return null;
        if ((string)($body['codigo'] ?? '')!=='200' || !is_array($body['relacionados'] ?? null)) return null;
        foreach ($body['relacionados'] as $row) if (is_array($row) && (string)($row['tipoDocumento'] ?? '')===$target['tipoDocumento'] && (string)($row['numeroDocumento'] ?? '')===$target['numeroDocumento'] && (string)($row['serie'] ?? '')===$target['serie']) return true;
        return false;
    }
}
