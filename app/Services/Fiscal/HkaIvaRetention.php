<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document, DocumentReceivedRetention, DocumentIvaRetentionOperation};
use Illuminate\Support\Str;

/** Durable, tenant-local outbox. Every mutation has a prior read and a subsequent reconciliation. */
final class HkaIvaRetention
{
    public static function enqueue(Document $document, DocumentReceivedRetention $retention, string $action, string $key): void
    {
        $other = DocumentIvaRetentionOperation::where('operation_key', $key)->first();
        if ($other) {
            if ((int)$other->retention_id !== (int)$retention->id || $other->action !== $action) IvaRetentionService::operationConflict('La clave HKA ya pertenece a otra operación.');
            return;
        }
        $previous = $retention->operations()->where('action','apply')->first();
        $payload = $action === 'remove' && $previous ? $previous->payload : HkaIvaRetentionPayload::build($document, $retention);
        $retention->operations()->create(['document_id'=>$document->id,'operation_key'=>$key,'action'=>$action,
            'payload'=>$payload,'context'=>self::context(Company::firstOrFail()),
            'next_attempt_at'=>$action === 'remove' && $previous && in_array($previous->status,['pending','uncertain'],true) && $previous->started_at ? $previous->started_at->copy()->addSeconds(90) : null,
            'actor_id'=>$action === 'remove' ? $retention->reversed_by : ($retention->fiscal_context['actor_id'] ?? auth()->id())]);
    }

    private static function context(Company $company): array
    {
        return ['issuer'=>$company->number,'environment'=>$company->fiscal_environment,'mode'=>$company->fiscal_emission_mode,
            'credentials_fingerprint'=>hash_hmac('sha256',(string)$company->getRawOriginal('fiscal_credentials'),(string)config('app.key'))];
    }

    public static function afterCommit(DocumentReceivedRetention $retention): void
    {
        $database = $retention->getConnection()->getDatabaseName();
        $id = $retention->id;
        $retention->getConnection()->afterCommit(static function () use ($database,$id) {
            if ((new Document)->getConnection()->getDatabaseName() !== $database) return;
            try { app(self::class)->process(DocumentReceivedRetention::findOrFail($id)); }
            catch (\Throwable $ignored) { /* Durable outbox is recovered by the tenant command. */ }
        });
    }

    public function process(DocumentReceivedRetention $retention, bool $queryOnly = false): void
    {
        if ($retention->getConnection()->transactionLevel()) FiscalAmounts::error('retention', 'HKA debe ejecutarse después del commit comercial.');
        $attempt = null;
        $operation = null;
        try {
            $document = $retention->document->fresh();
            $company = Company::firstOrFail();
            if ($document->fiscal_emission_mode !== 'digital' || $document->fiscal_environment !== 'demo'
                || $company->fiscal_emission_mode !== 'digital' || $company->fiscal_environment !== 'demo') return;
            $operation = $retention->operations()->latest('id')->first();
            if ($operation && $operation->action === 'apply' && $document->isVoidedOrRejected()) {
                $operation->update(['status'=>'conflict','diagnostic'=>'La factura dejó de estar vigente; no se aplicó la retención en HKA.']);
                return;
            }
            if (!$operation || in_array($operation->status,['cancelled','removed'],true) || (!$queryOnly && $operation->status === 'conflict')) return;
            if (!$queryOnly && in_array($operation->status,['confirmed','rejected'],true)) return;
            if ($operation->action === 'remove') {
                $applying = $retention->operations()->where('action','apply')->first();
                if ($applying && in_array($applying->status,['pending','uncertain'],true) && $applying->started_at
                    && $applying->started_at->gt(now()->subSeconds(90))) return;
            }
            $normalizeRif = fn($value)=>preg_replace('/[^A-Z0-9]/','',strtoupper((string)$value));
            if (!$normalizeRif($document->issuer['number'] ?? '') || $normalizeRif($document->issuer['number'] ?? '') !== $normalizeRif($company->number)
                || $operation->context != self::context($company) || !IvaRetentionService::invoiceReady($document)) {
                $operation->update(['status'=>'conflict','diagnostic'=>'La identidad o conexión fiscal cambió; revise la retención.']);
                return;
            }
            // Authenticate before locking. Recheck configuration when claiming and when finishing.
            $token = app(HkaTransport::class)->token($company);
            $previousStatus = $operation->status;
            $attempt = $document->getConnection()->transaction(function () use ($document,$operation,$company,$queryOnly) {
                Company::query()->lockForUpdate()->findOrFail($company->id);
                $doc = Document::query()->lockForUpdate()->findOrFail($document->id);
                $row = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($operation->retention_id);
                $op = DocumentIvaRetentionOperation::query()->lockForUpdate()->findOrFail($operation->id);
                if ($op->context != self::context(Company::firstOrFail()) || !IvaRetentionService::invoiceReady($doc)) return null;
                if ((int)$row->operations()->max('id') !== (int)$op->id || ($row->reversal_key && $op->action === 'apply')) return null;
                if ($op->status === 'pending' && $op->started_at && $op->started_at->gt(now()->subSeconds(90))) return null;
                if (!$queryOnly && $op->next_attempt_at && $op->next_attempt_at->isFuture()) return null;
                if (!$queryOnly && !in_array($op->status,['waiting','uncertain','pending'],true)) return null;
                $attempt = (string)Str::uuid();
                $op->update(['status'=>'pending','attempt_id'=>$attempt,'started_at'=>now(),'diagnostic'=>'Conciliación IVA en curso.']);
                return $attempt;
            });
            if (!$attempt) return;
            $operation = $operation->fresh();
            $read = $this->read($token, $operation->payload);
            if ($read === 'match') {
                if ($operation->action === 'apply' || $queryOnly) {
                    $this->finish($operation,$attempt,$operation->action === 'apply' ? 'confirmed' : 'waiting','El comprobante está registrado en HKA.');
                    return;
                }
            } elseif ($read === 'absent') {
                if ($operation->action === 'remove') {
                    $this->finish($operation,$attempt,'removed','Ausencia de retención confirmada en HKA.');
                    return;
                }
                if ($queryOnly) {
                    $this->finish($operation,$attempt,in_array($previousStatus,['confirmed','conflict'],true) ? 'conflict' : ($previousStatus === 'rejected' ? 'rejected' : 'waiting'),'HKA no tiene esta retención; revise su estado local.');
                    return;
                }
            } else {
                $this->finish($operation,$attempt,$read === 'conflict' ? 'conflict' : ($queryOnly && in_array($previousStatus,['confirmed','rejected'],true) ? $previousStatus : 'uncertain'),
                    $read === 'conflict' ? 'HKA contiene otra retención o identidad; no se sobrescribió.' : 'No se pudo verificar la retención en HKA.');
                return;
            }
            $claimed = $operation->getConnection()->transaction(function () use ($operation,$attempt) {
                Company::query()->lockForUpdate()->firstOrFail();
                Document::query()->lockForUpdate()->findOrFail($operation->document_id);
                $row = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($operation->retention_id);
                $op = DocumentIvaRetentionOperation::query()->lockForUpdate()->findOrFail($operation->id);
                if ($op->attempt_id !== $attempt || $op->status !== 'pending' || $op->context != self::context(Company::firstOrFail())
                    || ($row->reversal_key && $op->action === 'apply')) return false;
                $op->increment('attempts');
                return true;
            });
            if (!$claimed) return;
            $response = $operation->action === 'apply'
                ? app(HkaTransport::class)->applyIvaRetention($token,$operation->payload)
                : app(HkaTransport::class)->removeIvaRetention($token,HkaIvaRetentionPayload::identity($operation->payload));
            $body = $response->json();
            if (in_array($response->status(),[400,422],true) || ($response->successful() && is_array($body) && in_array((string)($body['codigo'] ?? ''),['203','205','400'],true))) {
                $this->finish($operation,$attempt,'rejected','HKA rechazó la solicitud IVA; revise el comprobante y sus datos.');
                return;
            }
            $read = $this->read($token,$operation->payload);
            $success = ($operation->action === 'apply' && $read === 'match') || ($operation->action === 'remove' && $read === 'absent');
            $this->finish($operation,$attempt,$success ? ($operation->action === 'apply' ? 'confirmed' : 'removed') : ($read === 'conflict' ? 'conflict' : 'uncertain'),
                $success ? 'Retención IVA conciliada con HKA.' : ($read === 'conflict' ? 'La retención remota difiere; requiere revisión.' : 'El resultado IVA requiere consulta antes de otro intento.'));
        } catch (\Throwable $exception) {
            if ($operation && $attempt) $this->finish($operation,$attempt,'uncertain','No se pudo confirmar la operación IVA. La venta y aplicación local se conservan.');
            elseif ($operation) $operation->update(['failures'=>$operation->failures+1,'next_attempt_at'=>now()->addSeconds(min(3600,30 * (2 ** min(7,$operation->failures+1)))),'diagnostic'=>'No se pudo conectar con HKA. La operación queda pendiente.']);
        }
    }

    private function read(string $token, array $payload): string
    {
        $response = app(HkaTransport::class)->queryIvaRetention($token,HkaIvaRetentionPayload::identity($payload));
        $body = $response->json();
        // DEMO verified 2026-10-12: absence is a business 203, not HTTP 404.
        if ($response->successful() && is_array($body) && (string)($body['codigo'] ?? '') === '203'
            && ($body['mensaje'] ?? '') === 'Retención no encontrada'
            && ($body['validaciones'] ?? []) === ['No se ha aplicado retención previamente por lo que no se puede consultar nada']) return 'absent';
        if (!$response->successful() || !is_array($body) || (string)($body['codigo'] ?? '') !== '200' || !empty($body['validaciones'])) return 'unknown';
        // Only an explicit empty result on a successful query accredits absence.
        if (!array_key_exists('infoRetencion',$body)) return 'unknown';
        if ($body['infoRetencion'] === null) return 'absent';
        $remote = $body['infoRetencion'];
        if (!is_array($remote)) return 'unknown';
        foreach (HkaIvaRetentionPayload::identity($payload) as $key=>$value) {
            if (array_key_exists($key,$remote) && (string)($remote[$key] ?? '') !== (string)$value) return 'conflict';
        }
        foreach (HkaIvaRetentionPayload::identity($payload) as $key=>$value) if (!array_key_exists($key,$remote)) return 'unknown';
        if (array_key_exists('totalesRetencion',$remote) && $remote['totalesRetencion'] === null) return 'absent';
        if (!is_array($remote['totalesRetencion'] ?? null)) return 'unknown';
        // A different voucher is a positive conflict even if the provider omits other totals.
        foreach (['numeroCompRetencion','fechaEmisionCR'] as $key) {
            if (isset($remote['totalesRetencion'][$key]) && (string)$remote['totalesRetencion'][$key] !== (string)$payload['totalRetencion'][$key]) return 'conflict';
        }
        foreach ($payload['totalRetencion'] as $key=>$value) if (!isset($remote['totalesRetencion'][$key])) return 'unknown';
        foreach (['totalBaseImponible','totalIVA','totalRetenido'] as $key) if (!is_numeric($remote['totalesRetencion'][$key])) return 'unknown';
        return HkaIvaRetentionPayload::matches($remote,$payload) ? 'match' : 'conflict';
    }

    private function finish(DocumentIvaRetentionOperation $operation, string $attempt, string $status, string $diagnostic): void
    {
        $operation->getConnection()->transaction(function () use ($operation,$attempt,$status,$diagnostic) {
            Company::query()->lockForUpdate()->firstOrFail();
            $doc = Document::query()->lockForUpdate()->findOrFail($operation->document_id);
            $row = DocumentReceivedRetention::query()->lockForUpdate()->findOrFail($operation->retention_id);
            $op = DocumentIvaRetentionOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($op->attempt_id !== $attempt || in_array($op->status,['cancelled','removed'],true)) return;
            if ($op->context != self::context(Company::firstOrFail())) { $status='conflict'; $diagnostic='La conexión fiscal cambió durante la operación.'; }
            $failures = in_array($status,['uncertain','waiting'],true) ? $op->failures + 1 : 0;
            $wait = min(3600,30 * (2 ** min(7,$failures)));
            $op->update(['status'=>$status,'failures'=>$failures,'diagnostic'=>$diagnostic,'next_attempt_at'=>in_array($status,['uncertain','waiting'],true) ? now()->addSeconds($wait) : null]);
            if ($status === 'removed') {
                $row->operations()->where('action','apply')->update(['status'=>'cancelled','attempt_id'=>null]);
                IvaRetentionService::finishReversal($doc,$row);
            }
        });
    }
}
