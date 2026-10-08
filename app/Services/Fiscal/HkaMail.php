<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Company, Document};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/** Email delivery is independent from the immutable fiscal emission. */
final class HkaMail
{
    public static function applies(Document $document): bool
    {
        return $document->document_type_id === '01' && $document->fiscal_emission_mode === 'digital';
    }

    /** Capture the automatic request with the sale, before its outer commit. */
    public function registerAutomatic(Document $document, bool $enabled): void
    {
        if (!$enabled || !self::applies($document)) return;
        $emission = $document->emission;
        if (!$emission || isset($emission->response['mail']['automatic'])) return;
        $email = $document->customer->email ?? null;
        $recipients = is_string($email) ? array_values(array_unique(array_map(
            fn ($value) => strtolower(trim($value)), preg_split('/[,;]/', $email)
        ))) : [];
        $valid = Validator::make(['email' => $email, 'recipients' => $recipients], [
            'email' => 'required|string|max:5080', 'recipients' => 'required|array|min:1|max:20',
            'recipients.*' => 'required|string|email:rfc|max:254',
        ])->passes();
        $mail = $emission->response['mail'] ?? [];
        $mail['automatic'] = ['request_id' => (string) Str::uuid(), 'recipients' => $valid ? $recipients : [],
            'status' => $valid ? 'waiting' : 'skipped',
            'message' => $valid ? 'El correo automático se enviará al confirmar la factura en HKA.'
                : 'Factura guardada. El cliente no tiene un correo válido para el envío automático.'];
        $emission->update(['response' => array_replace($emission->response ?? [], ['mail' => $mail])]);
    }

    /** A stable UUID prevents re-sending on repeated fiscal confirmations. */
    public function sendAutomatic(Document $document): array
    {
        $document = $document->fresh();
        $automatic = $document->emission->response['mail']['automatic'] ?? [];
        if (($automatic['status'] ?? null) !== 'waiting' || $document->emission->status !== 'confirmed') return self::view($document);
        try {
            return $this->send($document, $automatic['recipients'], $automatic['request_id'], false, true);
        } catch (\Throwable $exception) {
            $this->locked($document, function ($locked, $emission) use ($automatic) {
                $mail = $emission->response['mail'] ?? [];
                if (($mail['automatic']['request_id'] ?? null) !== $automatic['request_id']) return;
                if (($mail['automatic']['status'] ?? null) !== 'waiting') return;
                $mail['automatic']['status'] = 'failed';
                $mail['automatic']['message'] = 'Factura guardada. No se pudo preparar el correo automático HKA; revise el envío en el comprobante.';
                $emission->update(['response' => array_replace($emission->response ?? [], ['mail' => $mail])]);
            });
            return self::view($document->fresh());
        }
    }

    /** Compatibility with the automatic POST made by existing web bundles. */
    public static function automaticDelivery(Document $document, array $recipients): ?array
    {
        $automatic = $document->emission->response['mail']['automatic'] ?? [];
        if (!$recipients || ($automatic['recipients'] ?? []) !== $recipients) return null;
        return self::view($document);
    }

    public static function view(Document $document): array
    {
        if (!self::applies($document)) return ['provider' => 'local', 'status' => null, 'message' => null, 'can_send' => true, 'can_query' => false];
        $mail = $document->emission->response['mail'] ?? [];
        $attempt = $mail['attempts'][$mail['latest'] ?? ''] ?? [];
        $status = $attempt['status'] ?? null;
        $eligible = $document->fiscal_environment === 'demo' && optional($document->emission)->status === 'confirmed' && !$document->isVoidedOrRejected();
        $user = auth()->user();
        $scope = $user instanceof \App\Models\Tenant\User && ($user->type === 'admin' || (int) $user->establishment_id === (int) $document->establishment_id);
        return ['provider' => 'hka', 'status' => $status, 'request_id' => $attempt['id'] ?? null,
            'message' => !$eligible ? ($mail['automatic']['message'] ?? 'El correo HKA requiere una factura vigente confirmada en DEMO.')
                : ($attempt['message'] ?? $mail['automatic']['message'] ?? 'HKA enviará el PDF fiscal al correo indicado.'),
            'code' => $attempt['code'] ?? null, 'tracking' => $scope ? ($attempt['tracking'] ?? []) : [],
            'can_send' => $eligible && $scope && !in_array($status, ['pending', 'uncertain'], true),
            'can_query' => $eligible && $scope && in_array($status, ['pending', 'uncertain', 'accepted'], true)];
    }

    public function send(Document $document, array $recipients, string $requestId, bool $resend = false, bool $automatic = false): array
    {
        $this->outsideTransaction($document);
        $attempt = $this->locked($document, function ($locked, $emission, $company) use ($recipients, $requestId, $resend, $automatic) {
            $this->verify($locked, $company);
            $mail = $emission->response['mail'] ?? ['attempts' => []];
            if ($automatic && (($mail['automatic']['request_id'] ?? null) !== $requestId
                || ($mail['automatic']['recipients'] ?? []) !== $recipients
                || ($mail['automatic']['status'] ?? null) !== 'waiting')) return null;
            if ($automatic && !empty($mail['latest']) && $mail['latest'] !== $requestId) return null;
            if (isset($mail['attempts'][$requestId])) {
                if ($mail['attempts'][$requestId]['recipients'] !== $recipients) FiscalAmounts::error('customer_email', 'La solicitud ya corresponde a otros destinatarios.');
                return null;
            }
            $previous = $mail['attempts'][$mail['latest'] ?? ''] ?? [];
            if (in_array($previous['status'] ?? null, ['pending', 'uncertain'], true)) return null;
            if (($previous['status'] ?? null) === 'accepted' && !$resend) FiscalAmounts::error('customer_email', 'Use Enviar de nuevo para solicitar otro correo.');
            $identity = $this->identity($locked);
            $mail['latest'] = $requestId;
            $mail['attempts'][$requestId] = ['id' => $requestId, 'recipients' => $recipients,
                'status' => 'pending', 'started_at' => time(), 'baseline' => null,
                'message' => 'Solicitud de correo HKA en curso.'];
            if ($automatic) $mail['automatic']['status'] = 'requested';
            $emission->update(['response' => array_replace($emission->response ?? [], ['mail' => $mail])]);
            return ['identity' => $identity, 'company' => $company];
        });
        if (!$attempt) return self::view($document->fresh());
        $transmitting = false;
        try {
            $transport = app(HkaTransport::class);
            $token = $transport->token($attempt['company']);
            // Capture existing tracking IDs so an old email cannot reconcile a new request.
            $baseline = null;
            try {
                $response = $transport->mailTracking($token, $attempt['identity']);
                if ($response->successful() && (string) $response->json('codigo') === '200' && is_array($response->json('rastreos'))) {
                    $baseline = array_values(array_filter(array_map(fn ($row) => is_array($row) ? ($row['messageId'] ?? null) : null, $response->json('rastreos')), 'is_string'));
                }
            } catch (\Throwable $ignored) { /* A failed baseline leaves an uncertain send unreconciled. */ }
            $this->update($document, $requestId, ['baseline' => $baseline]);
            // Recheck issuer/credentials and immutable identity immediately before transmitting.
            $this->locked($document, function ($locked, $emission, $company) use ($attempt) {
                $this->verify($locked, $company);
                if ($this->identity($locked) !== $attempt['identity']) FiscalAmounts::error('customer_email', 'La identidad fiscal cambió durante la solicitud.');
            });
            $transmitting = true;
            $response = $transport->mail($token, array_replace($attempt['identity'], ['correos' => $recipients]));
            $result = self::interpret($response->status(), $response->json());
        } catch (\Throwable $exception) {
            $result = ['status' => $transmitting ? 'uncertain' : 'rejected', 'code' => null,
                'message' => $transmitting ? 'Factura guardada. No se pudo confirmar el correo; consulte su rastreo antes de repetir.' : 'Factura guardada. No se pudo autenticar o preparar el correo HKA.'];
        }
        try {
            $this->update($document, $requestId, $result);
        } catch (\Throwable $ignored) {
            return ['provider' => 'hka', 'status' => 'uncertain', 'request_id' => $requestId, 'code' => null, 'tracking' => [],
                'message' => 'Factura guardada. No se pudo guardar el resultado del correo; consulte su rastreo antes de repetir.',
                'can_send' => false, 'can_query' => true];
        }
        return self::view($document->fresh());
    }

    public function query(Document $document): array
    {
        $this->outsideTransaction($document);
        $attempt = $this->locked($document, function ($locked, $emission, $company) {
            $this->verify($locked, $company);
            $mail = $emission->response['mail'] ?? [];
            $entry = $mail['attempts'][$mail['latest'] ?? ''] ?? null;
            if (!$entry || !in_array($entry['status'], ['pending', 'uncertain', 'accepted'], true)) return null;
            if ($entry['status'] === 'pending') {
                if (time() - $entry['started_at'] < 50) return null;
                $entry['status'] = 'uncertain';
                $entry['message'] = 'Factura guardada. El intento interrumpido requiere verificar el rastreo.';
                $mail['attempts'][$entry['id']] = $entry;
                $emission->update(['response' => array_replace($emission->response, ['mail' => $mail])]);
            }
            return ['entry' => $entry, 'identity' => $this->identity($locked), 'company' => $company];
        });
        if (!$attempt) return self::view($document->fresh());
        try {
            $transport = app(HkaTransport::class);
            $response = $transport->mailTracking($transport->token($attempt['company']), $attempt['identity']);
            $entry = $attempt['entry'];
            if (!$response->successful() || (string) $response->json('codigo') !== '200' || !is_array($response->json('rastreos'))) throw new \RuntimeException('Unavailable tracking');
            $tracking = self::tracking($response->json('rastreos'), $entry);
            $result = ['tracking' => $tracking, 'checked_at' => time()];
            $found = array_column($tracking, 'recipient');
            if ((is_array($entry['baseline']) || $entry['status'] === 'accepted') && !array_diff($entry['recipients'], $found)) {
                $result['status'] = 'accepted';
                $result['message'] = 'HKA registra el correo solicitado. Consulte el estado de entrega indicado.';
            } else {
                $result['message'] = $entry['status'] === 'accepted' ? 'Solicitud aceptada por HKA. El rastreo aún no acredita la entrega.' : 'Factura guardada. El rastreo no permite confirmar este intento; no se reenviará automáticamente.';
            }
            $this->update($document, $entry['id'], $result);
        } catch (\Throwable $exception) {
            $this->update($document, $attempt['entry']['id'], ['message' => 'Factura guardada. No se pudo consultar el rastreo HKA; se conserva el estado del correo.']);
        }
        return self::view($document->fresh());
    }

    public static function interpret(int $httpStatus, $body): array
    {
        $value = is_array($body) ? ($body['codigo'] ?? null) : null;
        $code = (is_string($value) || is_int($value)) && preg_match('/^[0-9]{3,4}$/', (string) $value) ? (string) $value : null;
        if ($httpStatus >= 200 && $httpStatus < 300 && $code === '200' && empty($body['validaciones'])) {
            return ['status' => 'accepted', 'code' => $code, 'message' => 'Solicitud de correo aceptada por HKA.'];
        }
        if ($httpStatus >= 200 && $httpStatus < 300 && in_array($code, ['202', '203', '205', '400', '401'], true)) {
            return ['status' => 'rejected', 'code' => $code, 'message' => 'Factura guardada. HKA rechazó la solicitud de correo (código '.$code.').'];
        }
        return ['status' => 'uncertain', 'code' => $code, 'message' => 'Factura guardada. La respuesta no confirma el correo; consulte el rastreo antes de repetir.'];
    }

    public static function tracking(array $rows, array $entry): array
    {
        if (!is_array($entry['baseline'] ?? null) && ($entry['status'] ?? null) !== 'accepted') return [];
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $recipient = strtolower(trim((string) ($row['correo'] ?? '')));
            $id = $row['messageId'] ?? null;
            if (!in_array($recipient, $entry['recipients'], true) || !is_string($id) || !$id || in_array($id, $entry['baseline'] ?? [], true)) continue;
            $date = self::trackingDate($row['fecha'] ?? null);
            // Tracking dates with ambiguous formats cannot prove which request created the email.
            if (!$date || $date < $entry['started_at']) continue;
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $description = ['mensaje de correo electrónico entregado exitosamente.' => 'Entregado', 'delivered' => 'Entregado', 'sent' => 'Enviado', 'queued' => 'En cola', 'processed' => 'Procesado',
                'deferred' => 'Diferido', 'bounce' => 'Rebotado', 'bounced' => 'Rebotado', 'dropped' => 'Descartado',
                'open' => 'Abierto', 'opened' => 'Abierto', 'click' => 'Enlace visitado'][$status] ?? 'Estado de entrega no reconocido';
            $result[] = ['recipient' => $recipient, 'description' => $description, 'date' => gmdate('c', $date)];
        }
        return array_slice($result, 0, 100);
    }

    private static function trackingDate($value): ?int
    {
        if (!is_string($value)) return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,7})?(?:Z|[+-]\d{2}:\d{2})?$/', $value)) return null;
        try {
            $value = preg_replace('/(\.\d{6})\d/', '$1', $value);
            return \Carbon\CarbonImmutable::parse($value, 'America/Caracas')->getTimestamp();
        } catch (\Throwable $ignored) { return null; }
    }

    private function identity(Document $document): array
    {
        $frozen = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'] ?? [];
        if (($frozen['tipoDocumento'] ?? null) !== '01' || (string) ($frozen['numeroDocumento'] ?? '') !== (string) $document->number
            || (string) ($frozen['serie'] ?? '') !== (string) $document->series || !preg_match('/^[0-9]{1,19}$/', (string) ($frozen['numeroDocumento'] ?? ''))) {
            FiscalAmounts::error('customer_email', 'La identidad fiscal conservada no corresponde a la factura.');
        }
        return ['serie' => (string) ($frozen['serie'] ?? ''), 'tipoDocumento' => '01', 'numeroDocumento' => (string) $frozen['numeroDocumento']];
    }

    private function verify(Document $document, Company $company): void
    {
        $normalize = fn ($rif) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $rif));
        $context = ['issuer' => $company->number, 'environment' => $company->fiscal_environment,
            'credentials_fingerprint' => hash_hmac('sha256', json_encode(HkaAuthentication::credentials($company)), (string) config('app.key'))];
        if (!self::applies($document) || $document->fiscal_environment !== 'demo' || $company->fiscal_environment !== 'demo'
            || $company->fiscal_emission_mode !== 'digital' || $document->emission->status !== 'confirmed' || !$document->control_number || $document->control_number !== $document->emission->control_number || $document->isVoidedOrRejected()
            || !$normalize($document->issuer['number'] ?? '') || $normalize($document->issuer['number'] ?? '') !== $normalize($company->number)
            || !HkaAuthentication::credentials($company) || ($document->emission->response['context'] ?? null) !== $context) {
            FiscalAmounts::error('customer_email', 'El correo requiere una factura confirmada en DEMO y su configuración HKA original.');
        }
        if (Document::where('document_type_id', '01')->where('fiscal_environment', 'demo')->where('fiscal_emission_mode', 'digital')
            ->where('series', $document->series)->where('number', $document->number)->where('id', '!=', $document->id)->exists()) {
            FiscalAmounts::error('customer_email', 'La identidad fiscal es ambigua entre sucursales.');
        }
        $this->identity($document);
    }

    private function outsideTransaction(Document $document): void
    {
        if ($document->getConnection()->transactionLevel()) FiscalAmounts::error('customer_email', 'El correo debe enviarse después de guardar la factura.');
    }

    private function locked(Document $document, callable $callback)
    {
        return $document->getConnection()->transaction(function () use ($document, $callback) {
            $company = Company::query()->lockForUpdate()->firstOrFail();
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $emission = $locked->emission()->lockForUpdate()->firstOrFail();
            $locked->setRelation('emission', $emission);
            return $callback($locked, $emission, $company);
        });
    }

    private function update(Document $document, string $id, array $result): void
    {
        $this->locked($document, function ($locked, $emission) use ($id, $result) {
            $mail = $emission->response['mail'] ?? [];
            if (!isset($mail['attempts'][$id])) return;
            // A late response cannot overwrite a newer reconciliation of an uncertain attempt.
            if (($mail['attempts'][$id]['status'] ?? null) === 'accepted' && isset($result['status']) && $result['status'] !== 'accepted') unset($result['status'], $result['message'], $result['code']);
            $mail['attempts'][$id] = array_replace($mail['attempts'][$id], $result);
            $emission->update(['response' => array_replace($emission->response ?? [], ['mail' => $mail])]);
        });
    }
}
