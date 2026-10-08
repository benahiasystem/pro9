<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** A single logical emission, claimed in the tenant DB before network I/O. */
final class HkaEmission
{
    public function send(Document $document): array
    {
        $attempt = null;
        $operationKey = null;
        try {
            if ($document->getConnection()->transactionLevel()) FiscalAmounts::error('emission', 'El envío HKA debe ejecutarse después de guardar la venta.');
            $document = $document->fresh();
            $emission = $document->emission;
            $operationKey = $emission->operation_key;
            $company = $this->company($document);
            if (in_array($emission->status, ['confirmed', 'rejected', 'cancelled'], true)) return DocumentEmissionView::forDocument($document);
            if (in_array($emission->status, ['pending', 'uncertain'], true)) {
                $this->query($document);
                $document = $document->fresh();
                if (empty($document->emission->response['retry_allowed'])) return DocumentEmissionView::forDocument($document);
            }
            $token = app(HkaTransport::class)->token($company);
            if ($document->emission->status === 'not_requested') (new HkaEmissionPreparation)->prepare($document, $operationKey);
            $attempt = $document->getConnection()->transaction(function () use ($document, $company, $operationKey) {
                Company::query()->lockForUpdate()->findOrFail($company->id);
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                $currentCompany = $this->company($locked);
                if ($this->context($currentCompany) !== $this->context($company)) FiscalAmounts::error('emission', 'La configuración cambió durante la autenticación. Intente de nuevo.');
                $emission = $locked->emission()->lockForUpdate()->firstOrFail();
                if ($emission->operation_key !== $operationKey) return null;
                $retry = $emission->status === 'uncertain' && !empty($emission->response['retry_allowed'])
                    && time() - ($emission->response['started_at'] ?? time()) >= 30;
                if ($emission->status !== 'prepared' && !$retry) return null;
                $context = $emission->response['context'] ?? null;
                $current = $this->context($company);
                if ($context !== null && $context !== $current) FiscalAmounts::error('emission', 'La configuración HKA cambió después de preparar esta operación.');
                $attempt = (string) Str::uuid();
                $emission->update(['status' => 'pending', 'response' => array_replace($emission->response ?? [], ['context' => $current,
                    'attempt_id' => $attempt, 'started_at' => time(), 'diagnostic' => 'Envío HKA en curso.',
                    'edit_history' => $emission->response['edit_history'] ?? []])]);
                return ['id' => $attempt, 'payload' => $emission->payload];
            });
            if (!$attempt) return DocumentEmissionView::forDocument($document->fresh());
            $identity = $attempt['payload']['documentoElectronico']['encabezado']['identificacionDocumento'];
            $response = app(HkaTransport::class)->emission($token, $attempt['payload']);
            $result = HkaResponse::interpret($response->status(), $response->json(), $identity);
            $this->finish($document, $attempt['id'], $result);
            if (($result['code'] ?? null) === '201') $this->query($document->fresh());
        } catch (\Throwable $exception) {
            $this->failure($document, $attempt['id'] ?? null, $exception, $operationKey);
        }
        return DocumentEmissionView::forDocument($document->fresh());
    }

    public function query(Document $document): array
    {
        $query = null;
        try {
            if ($document->getConnection()->transactionLevel()) FiscalAmounts::error('emission', 'La consulta HKA debe ejecutarse fuera de la transacción comercial.');
            $document = $document->fresh();
            $company = $this->company($document);
            $query = $document->getConnection()->transaction(function () use ($document, $company) {
                Company::query()->lockForUpdate()->findOrFail($company->id);
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                $emission = $locked->emission()->lockForUpdate()->firstOrFail();
                if (!in_array($emission->status, ['pending', 'uncertain', 'confirmed'], true) || !$emission->payload) return null;
                if ($emission->status === 'pending' && time() - ($emission->response['started_at'] ?? time()) < 40) return null;
                if (($emission->response['context'] ?? null) !== $this->context($company)) FiscalAmounts::error('emission', 'La configuración HKA no corresponde a la operación enviada.');
                $query = ['id' => $emission->response['attempt_id'] ?? null, 'payload' => $emission->payload];
                if ($emission->status === 'pending') $emission->update(['status' => 'uncertain']);
                return $query;
            });
            if ($query) {
                $identity = $query['payload']['documentoElectronico']['encabezado']['identificacionDocumento'];
                $token = app(HkaTransport::class)->token($company);
                $response = app(HkaTransport::class)->status($token, $identity);
                $result = HkaResponse::interpret($response->status(), $response->json(), $identity, true);
                $this->finish($document, $query['id'], $result);
            }
        } catch (\Throwable $exception) {
            $this->failure($document, $query['id'] ?? null, $exception);
        }
        return DocumentEmissionView::forDocument($document->fresh());
    }

    private function company(Document $document): Company
    {
        $company = Company::firstOrFail();
        if ($document->document_type_id !== '01' || $document->fiscal_emission_mode !== 'digital'
            || $document->fiscal_environment !== 'demo' || $company->fiscal_environment !== 'demo'
            || $company->fiscal_emission_mode !== 'digital' || $document->isVoidedOrRejected()) {
            FiscalAmounts::error('emission', 'El envío HKA está habilitado únicamente para facturas vigentes en Medios digitales y DEMO.');
        }
        $normalize = fn ($rif) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $rif));
        if (!$normalize($document->issuer['number'] ?? '') || $normalize($document->issuer['number'] ?? '') !== $normalize($company->number)) {
            FiscalAmounts::error('emission', 'El RIF del emisor conservado no corresponde a la empresa configurada.');
        }
        if (!HkaAuthentication::credentials($company)) FiscalAmounts::error('emission', 'Configure las credenciales HKA de este tenant antes de enviar.');
        if (Document::where('document_type_id', '01')->where('fiscal_environment', 'demo')->where('fiscal_emission_mode', 'digital')
            ->where('series', $document->series)->where('number', $document->number)->where('id', '!=', $document->id)->exists()) {
            FiscalAmounts::error('emission', 'La identidad serie/número es ambigua entre sucursales para HKA.');
        }
        return $company;
    }

    private function context(Company $company): array
    {
        return ['issuer' => $company->number, 'environment' => $company->fiscal_environment,
            'credentials_fingerprint' => hash_hmac('sha256', json_encode(HkaAuthentication::credentials($company)), (string) config('app.key'))];
    }

    private function finish(Document $document, ?string $attempt, array $result): void
    {
        $document->getConnection()->transaction(function () use ($document, $attempt, $result) {
            Company::query()->lockForUpdate()->firstOrFail();
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $emission = $locked->emission()->lockForUpdate()->firstOrFail();
            if (($emission->response['attempt_id'] ?? null) !== $attempt) return;
            if (!in_array($emission->status, ['pending', 'uncertain', 'confirmed'], true)) return;
            // A failed later consultation cannot undo a confirmed fiscal fact.
            if ($emission->status === 'confirmed' && $result['status'] !== 'confirmed') {
                $emission->update(['response' => array_replace($emission->response ?? [], ['diagnostic' => 'Factura confirmada. La última consulta no pudo verificarse.'])]);
                return;
            }
            if ($result['status'] === 'confirmed') {
                (new HkaEmissionPreparation)->setControl($locked, $result['control_number']);
                $emission->authorization = $result['authorization'] ?? $emission->authorization;
                $emission->consulta_url = $result['consulta_url'];
                $emission->assigned_at = $result['assigned_at'] ?? $emission->assigned_at;
                $emission->control_assigned_at = $result['control_assigned_at'] ?? $emission->control_assigned_at;
            }
            $emission->status = $result['status'];
            $emission->response = array_replace($emission->response ?? [], $result, ['checked_at' => time()]);
            $emission->save();
        });
        // Mail failures must never undo or downgrade a confirmed fiscal emission.
        try {
            app(HkaMail::class)->sendAutomatic($document);
        } catch (\Throwable $ignored) { /* The sale and its fiscal confirmation remain persisted. */ }
    }

    private function failure(Document $document, ?string $attempt, \Throwable $exception, ?string $operationKey = null): void
    {
        $diagnostic = $attempt ? 'No se pudo confirmar la emisión. Consulte HKA antes de reenviar.'
            : 'La venta está guardada. No se pudo preparar o conectar con HKA.';
        if (!$attempt && $exception instanceof ValidationException) {
            // These messages are produced locally; the HTTP adapter never propagates provider bodies.
            $diagnostic = implode(' ', array_merge(...array_values($exception->errors())));
        }
        try {
            $document->getConnection()->transaction(function () use ($document, $attempt, $diagnostic, $operationKey) {
                Company::query()->lockForUpdate()->firstOrFail();
                Document::query()->lockForUpdate()->findOrFail($document->id);
                $emission = $document->emission()->lockForUpdate()->firstOrFail();
                if ($operationKey && $emission->operation_key !== $operationKey) return;
                if ($attempt && ($emission->response['attempt_id'] ?? null) !== $attempt) return;
                if (!$attempt && !in_array($emission->status, ['not_requested', 'prepared'], true)) return;
                if ($attempt && $emission->status !== 'confirmed') $emission->status = 'uncertain';
                $emission->response = array_replace($emission->response ?? [], ['retry_allowed' => false,
                    'diagnostic' => mb_substr($diagnostic, 0, 1000)]);
                $emission->save();
            });
        } catch (\Throwable $ignored) {
            // Preserve the committed sale even if fiscal persistence is unavailable; pending remains reconcilable.
        }
    }
}
