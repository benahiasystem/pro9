<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentEmission, DocumentNoteAdjustment, DocumentNoteRefund};
use App\Http\Controllers\Tenant\DocumentFiscalController;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Durable cancellation saga. HKA calls never hold commercial locks. */
final class NoteAnnulment
{
    public const ACTIVE = ['pending','uncertain','remote_confirmed'];

    public static function journal(Document $note): array { return $note->emission->response['annulment'] ?? []; }

    public static function busy(Document $document): bool
    {
        $invoiceId=FiscalNote::ordinary($document) ? $document->note->affected_document_id : $document->id;
        $ids=Document::whereHas('note',fn ($q)=>$q->where('affected_document_id',$invoiceId))->pluck('id');
        return DocumentEmission::whereIn('document_id',$ids)->whereIn('response->annulment->status',self::ACTIVE)->exists();
    }

    public static function assertNotBusy(Document $document): void
    {
        if (self::busy($document)) FiscalAmounts::error('annulment','Hay una anulación de nota pendiente. Concíliela y complete su reversión antes de cambiar cobros, notas o ajustes relacionados.');
    }

    public static function dependencies(Document $note): array
    {
        $reasons=[];
        if ($note->payments()->whereNull('reversed_at')->whereNull('receipt_parent_id')->exists()) $reasons[]='Revierta los cobros reales activos de la ND desde Pagos.';
        if (DocumentNoteRefund::where('document_id',$note->id)->whereNull('reversed_at')->exists()) $reasons[]='Regularice las devoluciones de dinero activas de esta NC.';
        if (DocumentNoteAdjustment::where('document_id',$note->id)->whereNull('reversed_at')->exists()) $reasons[]='Regularice los ajustes de retenciones, fondos o IGTF relacionados; no se revierten automáticamente.';
        return $reasons;
    }

    private static function localAllowed(Document $note): bool
    {
        $e=$note->emission;if (!$e) return false;
        return !$note->control_number && !$e->control_number && (
            (in_array($e->status,['not_requested','prepared'],true) && empty($e->response['attempt_id']))
            || ($e->status === 'uncertain' && !empty($e->response['retry_allowed'])));
    }

    public static function view(Document $note): array
    {
        if (!FiscalNote::digital($note) || $note->fiscal_environment !== 'demo' || !$note->emission) return ['available'=>false];
        $j=self::journal($note); $user=auth()->user();
        $allowed=$user instanceof \App\Models\Tenant\User && $user->type === 'admin';
        $reasons=self::dependencies($note);
        if (!$j && !$note->isVoidedOrRejected() && !self::localAllowed($note) && $note->emission->status !== 'confirmed') $reasons[]='Conciliar la emisión HKA es necesario antes de anular.';
        if ($note->emission->status === 'confirmed' && !config('hka_notes.demo_annulment_verified',false) && !config('hka_notes.contract_probe',false)) $reasons[]='La anulación HKA de notas espera aceptación verificable en DEMO.';
        $active=in_array($j['status'] ?? '',self::ACTIVE,true);
        if (!$active && self::busy($note)) $reasons[]='Hay otra anulación relacionada pendiente de completar.';
        return ['available'=>$allowed && !$note->isVoidedOrRejected(), 'status'=>$j['status'] ?? null,
            'diagnostic'=>$j['diagnostic'] ?? implode(' ',$reasons), 'dependencies'=>$reasons,
            'can_request'=>$allowed && !$note->isVoidedOrRejected() && !$active && !$reasons && ($j['status'] ?? '') !== 'completed',
            'can_query'=>$allowed && in_array($j['status'] ?? '',['pending','uncertain'],true) && ($j['remote'] ?? false),
            'can_complete'=>$allowed && ($j['status'] ?? '') === 'remote_confirmed',
            'remote'=>$j['remote'] ?? null, 'reason'=>$j['reason'] ?? null, 'actor_id'=>$j['actor_id'] ?? null,
            'requested_at'=>$j['requested_at'] ?? null, 'completed_at'=>$j['completed_at'] ?? null];
    }

    private static function authorize(Document $note): void
    {
        DocumentFiscalController::authorizeDocument($note);
        abort_unless(auth()->user()->type === 'admin',403);
        if (!FiscalNote::digital($note) || $note->fiscal_environment !== 'demo') FiscalAmounts::error('note','Seleccione una NC o ND ordinaria digital DEMO.');
    }

    private function locked(Document $note, callable $action)
    {
        return $note->getConnection()->transaction(function () use ($note,$action) {
            $company=Company::query()->lockForUpdate()->firstOrFail();
            $invoice=Document::query()->lockForUpdate()->findOrFail($note->note->affected_document_id);
            $locked=Document::query()->lockForUpdate()->findOrFail($note->id);
            $emission=$locked->emission()->lockForUpdate()->firstOrFail();
            $locked->setRelation('emission',$emission);
            return $action($locked,$emission,$invoice,$company);
        });
    }

    private static function store(DocumentEmission $emission,array $journal): void
    {
        $emission->response=array_replace($emission->response ?? [],['annulment'=>$journal]);$emission->save();
        \App\Helpers\CacheHelper::flush(['document_list']);
    }

    private static function providerResult(int $http,$body): array
    {
        $code=is_array($body) ? ($body['codigo'] ?? null):null;
        $code=is_scalar($code) && preg_match('/\A[0-9]{3,4}\z/',(string)$code) ? (string)$code:null;
        $codes=[];
        foreach (is_array($body['validaciones'] ?? null) ? $body['validaciones']:[] as $validation) {
            if (is_string($validation) && preg_match_all('/\[([0-9]{4})\]/',$validation,$matches)) $codes=array_merge($codes,$matches[1]);
        }
        // Keep the provider's numeric evidence, never raw text that may expose private data.
        return ['http_status'=>$http,'code'=>$code,'validation_codes'=>array_values(array_unique($codes))];
    }

    private static function checkInventory(Document $note,bool $checkOnly): void
    {
        if ($note->document_type_id === '07') foreach ($note->items as $line) {
            FiscalNoteInventory::apply($line,-1,$checkOnly);
            if (!$checkOnly && $line->exactValue('inventory_quantity')->isPositive() && !empty($line->item->inventory_components)) {
                $ids=[$line->item_id];foreach ((array)$line->item->inventory_components as $component) $ids[]=((array)$component)['item_id'];
                $note->getConnection()->afterCommit(function() use($ids) {
                    try {
                        $provider=new \Modules\Inventory\Providers\InventoryKardexServiceProvider(app());
                        foreach(array_unique($ids) as $id) $provider->syncRestaurantStock($id);
                    } catch (\Throwable $ignored) { /* Auxiliary notification cannot undo committed inventory. */ }
                });
            }
        }
    }

    public function request(Document $note,array $input): array
    {
        self::authorize($note);
        if ($note->getConnection()->transactionLevel()) FiscalAmounts::error('annulment','Solicite la anulación fuera de la transacción comercial.');
        $data=validator($input,['operation_key'=>'required|uuid','reason'=>'required|string|max:255'])->validate();
        $data['operation_key']=strtolower($data['operation_key']);
        $data['reason']=trim($data['reason']);if ($data['reason'] === '') FiscalAmounts::error('reason','Indique el motivo de anulación.');
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
        $claim=$this->locked($note,function ($n,$e,$invoice,$company) use ($data,$hash) {
            $old=self::journal($n);
            if (DocumentEmission::where('document_id','!=',$n->id)->where(function($q) use($data) { $q->where('response->annulment->operation_key',$data['operation_key'])->orWhereRaw("JSON_SEARCH(response, 'one', ?, NULL, '$.annulment.history[*].operation_key') IS NOT NULL",[$data['operation_key']]); })->exists()) abort(409,'La clave de anulación pertenece a otra nota.');
            if (($old['operation_key'] ?? null) === $data['operation_key']) {
                if ($old['request_hash'] !== $hash) abort(409,'La clave de anulación pertenece a otra solicitud.');
                return null;
            }
            foreach ($old['history'] ?? [] as $previous) if (($previous['operation_key'] ?? null) === $data['operation_key']) abort(409,'La clave corresponde a un intento anterior.');
            if ($n->isVoidedOrRejected() || in_array($old['status'] ?? '',self::ACTIVE,true) || ($old['status'] ?? '') === 'completed') FiscalAmounts::error('annulment','La nota ya está anulada o tiene una anulación en curso.');
            self::assertNotBusy($invoice);
            if ($reasons=self::dependencies($n)) FiscalAmounts::error('annulment',implode(' ',$reasons));
            $remote=!self::localAllowed($n);
            if ($remote && ($e->status !== 'confirmed' || !$n->control_number || $n->control_number !== $e->control_number)) FiscalAmounts::error('annulment','Conciliar la emisión HKA es necesario antes de anular.');
            if ($remote && !config('hka_notes.demo_annulment_verified',false) && !config('hka_notes.contract_probe',false)) FiscalAmounts::error('annulment','La anulación HKA espera aceptación verificable en DEMO.');
            if ($company->fiscal_emission_mode !== 'digital' || $company->fiscal_environment !== 'demo' || self::rif($company->number) !== self::rif($n->issuer['number'] ?? '')) FiscalAmounts::error('annulment','La configuración DEMO no corresponde al emisor de la nota.');
            if ($remote && isset($e->response['context']) && $e->response['context'] !== ['issuer'=>$company->number,'environment'=>$company->fiscal_environment,
                'credentials_fingerprint'=>hash_hmac('sha256',json_encode(HkaAuthentication::credentials($company)),(string)config('app.key'))]) FiscalAmounts::error('annulment','Las credenciales no corresponden a la operación fiscal original.');
            self::checkInventory($n,true);
            $identity=$remote ? $e->payload['documentoElectronico']['encabezado']['identificacionDocumento'] : [];
            if ($remote && (($identity['tipoDocumento'] ?? '') !== ($n->document_type_id === '07' ? '02':'03') || (string)($identity['numeroDocumento'] ?? '') !== (string)$n->number || ($identity['serie'] ?? null) !== $n->series)) FiscalAmounts::error('annulment','La identidad fiscal de la nota no coincide.');
            $now=now()->timezone('America/Caracas');
            $payload=['serie'=>$remote ? $identity['serie']:$n->series,'tipoDocumento'=>$n->document_type_id === '07' ? '02':'03',
                'numeroDocumento'=>(string)$n->number,'motivoAnulacion'=>$data['reason'],'fechaAnulacion'=>$now->format('d/m/Y'),'horaAnulacion'=>$now->format('h:i:s a')];
            $history=$old['history'] ?? [];if ($old) {unset($old['history']);$history[]=$old;}
            $j=['operation_key'=>$data['operation_key'],'request_hash'=>$hash,'attempt_id'=>(string)Str::uuid(),'actor_id'=>auth()->id(),
                'reason'=>$data['reason'],'requested_at'=>$now->toIso8601String(),'status'=>'pending','remote'=>$remote,'request'=>$payload,
                'state_before'=>$n->state_type_id,'history'=>$history,'diagnostic'=>$remote ? 'Anulación HKA en curso.':'Reversión comercial local en curso.'];
            self::store($e,$j);$n->state_type_id='13';$n->saveQuietly();
            return ['journal'=>$j,'company'=>$company];
        });
        if (!$claim) return DocumentEmissionView::forDocument($note->fresh());
        $j=$claim['journal'];
        if (!$j['remote']) { $this->finish($note,$j,'remote_confirmed','Anulación local acreditada sin transmisión HKA.');$this->complete($note);return DocumentEmissionView::forDocument($note->fresh()); }
        $transmitted=false;
        try {
            $transport=app(HkaTransport::class);$token=$transport->token($claim['company']);
            $ready=$this->locked($note,function($n,$e,$invoice,$company) use ($j,$claim) {
                return self::journal($n)['attempt_id'] === $j['attempt_id'] && self::journal($n)['status'] === 'pending'
                    && $company->fiscal_environment === 'demo' && $company->fiscal_emission_mode === 'digital'
                    && $company->number === $claim['company']->number && HkaAuthentication::credentials($company) === HkaAuthentication::credentials($claim['company']);
            });
            if (!$ready) FiscalAmounts::error('annulment','La configuración cambió antes de transmitir la anulación.');
            $transmitted=true;$response=$transport->annul($token,$j['request']);$body=$response->json();
            $result=self::providerResult($response->status(),$body);
            $validations=is_array($body) ? ($body['validaciones'] ?? []) : null;
            if ($response->successful() && is_array($body) && (string)($body['codigo'] ?? '') === '200' && $validations === []) {
                $this->finish($note,$j,'remote_confirmed','Anulación fiscal confirmada; reversión comercial pendiente.',$result);$this->complete($note);
            } elseif ($response->status() === 400 || $response->status() === 401 || (is_array($validations) && $validations && in_array((string)($body['codigo'] ?? ''),['400','401'],true))) {
                $diagnostic='HKA rechazó la anulación. La nota y sus efectos comerciales se conservan.';
                if ($result['code']) $diagnostic.=' Código HKA '.$result['code'].'.';
                if ($result['validation_codes']) $diagnostic.=' Validaciones: '.implode(', ',$result['validation_codes']).'.';
                $this->finish($note,$j,'rejected',$diagnostic,$result);
            } else $this->finish($note,$j,'uncertain','No se pudo acreditar la anulación. Consulte HKA antes de cualquier nuevo intento.',$result);
        } catch (\Throwable $exception) {
            $this->finish($note,$j,$transmitted ? 'uncertain':'rejected',$transmitted ? 'Resultado de anulación incierto. Consulte HKA; no se repite automáticamente.':'No se pudo autenticar la anulación. No se transmitió la solicitud.');
        }
        return DocumentEmissionView::forDocument($note->fresh());
    }

    private function finish(Document $note,array $attempt,string $status,string $diagnostic,array $result=[]): void
    {
        $this->locked($note,function ($n,$e) use ($attempt,$status,$diagnostic,$result) {
            $j=self::journal($n);
            if (($j['attempt_id'] ?? null) !== $attempt['attempt_id'] || !in_array($j['status'] ?? '',['pending','uncertain'],true)) return;
            $j['status']=$status;$j['diagnostic']=$diagnostic;$j['checked_at']=now()->toIso8601String();
            if ($result) $j['provider_result']=$result;
            if ($status === 'remote_confirmed') $e->status='cancelled';
            if ($status === 'rejected') {$n->state_type_id=$j['state_before'];$n->saveQuietly();}
            self::store($e,$j);
        });
    }

    public function complete(Document $note): array
    {
        self::authorize($note);
        try {
            $this->locked($note,function ($n,$e,$invoice) {
                $j=self::journal($n);if (($j['status'] ?? null) === 'completed') return;
                if (($j['status'] ?? null) !== 'remote_confirmed') FiscalAmounts::error('annulment','Confirme la anulación antes de completar la reversión comercial.');
                if ($reasons=self::dependencies($n)) FiscalAmounts::error('annulment',implode(' ',$reasons));
                self::checkInventory($n,false);
                $n->state_type_id='11';$n->saveQuietly();$e->status='cancelled';
                $j['status']='completed';$j['completed_at']=now()->toIso8601String();$j['diagnostic']=$j['remote'] ? 'Nota anulada en HKA; reversión comercial completada.':'Nota anulada localmente sin transmisión HKA.';
                self::store($e,$j);FiscalDocumentPersistence::updateSettlementState($invoice);
                // ######## INICIO CALCULOS EXACTOS PRO9 ########
                \App\Models\Tenant\CashDocumentCredit::where('document_id',$invoice->id)->update(['status'=>$invoice->fresh()->exactBalance()->isPositive() ? 'PENDING':'PROCESSED']);
                // ######## FIN CALCULOS EXACTOS PRO9 ########
            });
        } catch (\Throwable $exception) {
            $this->locked($note,function ($n,$e) use ($exception) {
                $j=self::journal($n);if (($j['status'] ?? '') !== 'remote_confirmed') throw $exception;
                $detail=$exception instanceof ValidationException ? implode(' ',array_merge(...array_values($exception->errors()))) : 'No se pudo aplicar la reversión comercial. Revise existencias y dependencias.';
                $j['diagnostic']='Anulación fiscal confirmada; reversión comercial pendiente. '.mb_substr($detail,0,800);self::store($e,$j);
            });
        }
        return DocumentEmissionView::forDocument($note->fresh());
    }

    private static function rif($value): string { return preg_replace('/[^A-Z0-9]/','',strtoupper((string)$value)); }

    public function query(Document $note): array
    {
        self::authorize($note);$j=self::journal($note);
        if (!($j['remote'] ?? false) || !in_array($j['status'] ?? '',['pending','uncertain'],true)) return DocumentEmissionView::forDocument($note);
        try {
            if ($note->getConnection()->transactionLevel()) FiscalAmounts::error('annulment','La consulta debe ejecutarse fuera de la transacción comercial.');
            $company=Company::firstOrFail();
            if ($company->fiscal_environment !== 'demo' || $company->fiscal_emission_mode !== 'digital' || self::rif($company->number) !== self::rif($note->issuer['number'] ?? '')) FiscalAmounts::error('annulment','La configuración no corresponde al emisor.');
            if (isset($note->emission->response['context']) && $note->emission->response['context'] !== ['issuer'=>$company->number,'environment'=>$company->fiscal_environment,'credentials_fingerprint'=>hash_hmac('sha256',json_encode(HkaAuthentication::credentials($company)),(string)config('app.key'))]) FiscalAmounts::error('annulment','Las credenciales no corresponden a la operación original.');
            $transport=app(HkaTransport::class);$token=$transport->token($company);$page=1;$pages=1;
            do {
                $response=$transport->annulledDocuments($token,['serie'=>$j['request']['serie'],'tipoDocumento'=>$j['request']['tipoDocumento'],
                    'fechaInicio'=>$j['request']['fechaAnulacion'],'fechaFin'=>now()->timezone('America/Caracas')->format('d/m/Y'),'numPagina'=>$page]);
                $body=$response->json();
                if (!$response->successful() || !is_array($body) || (string)($body['codigo'] ?? '') !== '200' || !empty($body['validaciones']) || !is_array($body['documentos'] ?? null)) break;
                $pages=filter_var($body['totalPaginas'] ?? null,FILTER_VALIDATE_INT);$actual=filter_var($body['numeroPagina'] ?? null,FILTER_VALIDATE_INT);
                if ($pages === false || $pages < 0 || $pages > 10000 || $actual !== $page) break;
                foreach ($body['documentos'] as $row) {
                    if (self::rif($row['rif'] ?? '') === self::rif($note->issuer['number'] ?? '') && ($row['serie'] ?? '') === $j['request']['serie']
                        && ($row['tipoDocumento'] ?? '') === $j['request']['tipoDocumento'] && (string)($row['numeroDocumento'] ?? '') === $j['request']['numeroDocumento']
                        && ($row['numeroControl'] ?? '') === $note->control_number && in_array(strtoupper($row['estadoDocumento'] ?? ''),['ANULADO','ANULADA'],true)) {
                        $this->finish($note,$j,'remote_confirmed','Anulación acreditada por consulta HKA; reversión comercial pendiente.');return $this->complete($note);
                    }
                }
                $page++;
            } while ($page <= $pages);
            // DEMO currently returns ListadoDocumentos 201 even for cancelled notes.
            // EstadoDocumento has independently verified Anulada and the exact operation UUID.
            $identity=$note->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'];
            $response=$transport->status($token,$identity);$body=$response->json();$state=$body['estado'] ?? null;
            if ($response->successful() && (string)($body['codigo'] ?? '') === '200' && empty($body['validaciones']) && is_array($state)
                && strtoupper($state['estadoDocumento'] ?? '') === 'ANULADA' && ($state['transaccionId'] ?? '') === $identity['transaccionId']
                && ($state['tipoDocumento'] ?? '') === $identity['tipoDocumento'] && (string)($state['numeroDocumento'] ?? '') === $identity['numeroDocumento']
                && ($state['numeroControl'] ?? '') === $note->control_number) {
                $this->finish($note,$j,'remote_confirmed','Anulación acreditada por EstadoDocumento HKA; reversión comercial pendiente.');return $this->complete($note);
            }

        } catch (\Throwable $ignored) { /* Empty, contradictory or unavailable lists never prove absence. */ }
        $this->finish($note,$j,'uncertain','La consulta no acredita la anulación. Se conservan los efectos comerciales y no se reenvía la solicitud.');
        return DocumentEmissionView::forDocument($note->fresh());
    }
}
