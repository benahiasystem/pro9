<?php

namespace App\Services\Fiscal;

use App\Http\Controllers\Tenant\DocumentFiscalController;
use App\Models\Tenant\{Company, Document, User};
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/** Remote downloads are independent from previews, email and fiscal mutations. */
final class HkaPdf
{
    public static function applies(Document $document): bool
    {
        return $document->document_type_id === '01' && $document->fiscal_emission_mode === 'digital';
    }

    public static function view(Document $document, bool $api = false): array
    {
        $remote = self::applies($document);
        $user = auth()->user();
        $scope = $user instanceof User && ($user->type === 'admin'
            || (int) $user->establishment_id === (int) $document->establishment_id);
        $reason = $remote ? self::unavailableReason($document) : null;
        if ($remote && !$scope) $reason = 'No tiene permiso para descargar esta factura.';
        $result = [];
        foreach (['a4', 'a5', 'ticket'] as $format) {
            $formatReason = $reason;
            if ($remote && $format === 'ticket' && $formatReason === null) {
                $formatReason = self::ticketUnavailableReason($document);
            }
            $result[$format] = [
                'provider' => $remote && $format !== 'ticket' ? 'hka' : 'local',
                'url' => url(($api ? '/api' : '')."/documents/{$document->external_id}/download-pdf/{$format}"),
                'available' => $scope && $formatReason === null,
                'message' => $formatReason,
            ];
        }
        return $result;
    }

    private static function unavailableReason(Document $document): ?string
    {
        if ($document->fiscal_environment !== 'demo') return 'La descarga HKA está habilitada únicamente en DEMO.';
        if ($document->isVoidedOrRejected()) return 'La factura no está vigente para descargar su PDF HKA.';
        if (optional($document->emission)->status !== 'confirmed') return 'Confirme la factura en HKA antes de descargar A4, A5 o 80MM.';
        if (!$document->control_number || $document->control_number !== $document->emission->control_number) {
            return 'La factura no tiene un control HKA confirmado y coherente.';
        }
        try {
            new \App\Services\FiscalControlNumber($document->control_number);
        } catch (\InvalidArgumentException $exception) {
            return 'La factura no tiene un control HKA válido.';
        }
        return null;
    }

    private static function ticketUnavailableReason(Document $document): ?string
    {
        $url = optional($document->emission)->consulta_url;
        if (!is_string($url) || strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return 'La factura no tiene un enlace de consulta HKA válido. Consulte el estado HKA y vuelva a intentar la descarga 80MM.';
        }
        return null;
    }

    public function download(Document $document, string $format): string
    {
        return $this->obtain($document, $format, true);
    }

    /** Only the already-authorized emission pipeline calls this after confirmation. */
    public function storeConfirmed(Document $document): void
    {
        $this->obtain($document->fresh(), 'a4', false);
    }

    /** Public UUID links can read a stored copy, but never initiate HKA requests. */
    public function stored(Document $document, string $format): string
    {
        $document = $document->fresh();
        if (!in_array($format, ['a4', 'a5'], true)) self::fail('El PDF solicitado requiere una sesión autorizada.');
        $this->verify($document, Company::firstOrFail());
        $pdf = app(HkaPdfStore::class)->read($document, $format);
        if ($pdf === null) self::fail('El PDF HKA aún no está disponible. Solicite su descarga desde una sesión autorizada.');
        return $pdf;
    }

    private function obtain(Document $document, string $format, bool $authorize): string
    {
        $document = $document->fresh();
        if ($authorize) DocumentFiscalController::authorizeDocument($document);
        if (!in_array($format, ['a4', 'a5', 'ticket'], true)) self::fail('Formato de descarga inválido.');
        if ($document->getConnection()->transactionLevel()) self::fail('Descargue el PDF después de guardar la factura.');
        $company = Company::firstOrFail();
        $identity = $this->verify($document, $company);
        $operation = $document->emission->operation_key;
        if ($format === 'ticket') {
            if ($reason = self::ticketUnavailableReason($document)) self::fail($reason);
            try {
                return app(HkaTicketPdf::class)->render($document, $document->emission->consulta_url);
            } catch (\Throwable $exception) {
                self::fail('No se pudo generar el ticket 80MM con su QR HKA. Intente de nuevo; la venta sigue guardada.');
            }
        }
        $store = app(HkaPdfStore::class);
        if ($cached = $store->read($document, $format)) return $cached;
        if ($format === 'a5') {
            $source = $this->obtain($document, 'a4', $authorize);
            $pdf = self::toLandscapeA5($source);
            $current = $document->fresh();
            if ($authorize) DocumentFiscalController::authorizeDocument($current);
            if ($this->verify($current, Company::firstOrFail()) !== $identity || $current->emission->operation_key !== $operation) self::fail('La identidad fiscal cambió durante la conversión.');
            try { $store->write($current, 'a5', $pdf); }
            catch (\Throwable $exception) { self::fail('No se pudo guardar el PDF HKA. Intente de nuevo; la venta sigue guardada.'); }
            return $pdf;
        }
        try {
            $transport = app(HkaTransport::class);
            $token = $transport->token($company);
            // Authentication may take time: recheck context before retrieving private data.
            $current = $document->fresh();
            if ($authorize) DocumentFiscalController::authorizeDocument($current);
            if ($this->verify($current, Company::firstOrFail()) !== $identity || $current->emission->operation_key !== $operation) self::fail('La identidad fiscal cambió durante la descarga.');
            $response = $transport->downloadPdf($token, $identity);
            $pdf = self::decode($response->status(), $response->json());
            $current = $document->fresh();
            if ($authorize) DocumentFiscalController::authorizeDocument($current);
            if ($this->verify($current, Company::firstOrFail()) !== $identity || $current->emission->operation_key !== $operation) self::fail('La identidad fiscal cambió durante la descarga.');
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['pdf'])) throw $exception;
            self::fail('No se pudo autenticar la descarga HKA. Revise la conexión e intente de nuevo.');
        } catch (\Throwable $exception) {
            self::fail('No se pudo descargar el PDF de HKA. Intente de nuevo; la venta sigue guardada.');
        }
        try { $store->write($current, 'a4', $pdf); }
        catch (\Throwable $exception) { self::fail('No se pudo guardar el PDF HKA. Intente de nuevo; la venta sigue guardada.'); }
        return $pdf;
    }

    private function verify(Document $document, Company $company): array
    {
        if (!self::applies($document)) self::fail('Este documento no corresponde a una factura digital HKA.');
        if ($reason = self::unavailableReason($document)) self::fail($reason);
        $normalize = fn ($rif) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $rif));
        $credentials = HkaAuthentication::credentials($company);
        $context = ['issuer' => $company->number, 'environment' => $company->fiscal_environment,
            'credentials_fingerprint' => hash_hmac('sha256', json_encode($credentials), (string) config('app.key'))];
        if ($company->fiscal_emission_mode !== 'digital' || $company->fiscal_environment !== 'demo' || !$credentials
            || !$normalize($document->issuer['number'] ?? '')
            || $normalize($document->issuer['number'] ?? '') !== $normalize($company->number)
            || ($document->emission->response['context'] ?? null) !== $context) {
            self::fail('La descarga requiere la configuración HKA original de la factura confirmada.');
        }
        $frozen = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'] ?? [];
        if (($frozen['tipoDocumento'] ?? null) !== '01'
            || (string) ($frozen['numeroDocumento'] ?? '') !== (string) $document->number
            || (string) ($frozen['serie'] ?? '') !== (string) $document->series
            || !preg_match('/^[0-9]{1,19}$/', (string) ($frozen['numeroDocumento'] ?? ''))
            || strlen((string) ($frozen['serie'] ?? '')) > 20) {
            self::fail('La identidad fiscal conservada no corresponde a la factura.');
        }
        if (Document::where('document_type_id', '01')->where('fiscal_environment', 'demo')->where('fiscal_emission_mode', 'digital')
            ->where('series', $document->series)->where('number', $document->number)->where('id', '!=', $document->id)->exists()) {
            self::fail('La identidad fiscal es ambigua entre sucursales.');
        }
        return ['serie' => (string) ($frozen['serie'] ?? ''), 'tipoDocumento' => '01', 'numeroDocumento' => (string) $frozen['numeroDocumento']];
    }

    public static function decode(int $httpStatus, $body): string
    {
        if ($httpStatus < 200 || $httpStatus >= 300 || !is_array($body)
            || !in_array($body['codigo'] ?? null, [200, '200'], true) || !empty($body['validaciones'])
            || !is_string($body['archivo'] ?? null) || $body['archivo'] === '' || strlen($body['archivo']) > 40 * 1024 * 1024) {
            self::fail('HKA no devolvió un PDF válido. Intente de nuevo.');
        }
        $pdf = base64_decode($body['archivo'], true);
        return self::validatePdf($pdf);
    }

    public static function validatePdf($pdf): string
    {
        if (!is_string($pdf) || !preg_match('/^%PDF-1\.[0-9]/', $pdf) || !preg_match('/%%EOF\s*$/', $pdf)) {
            self::fail('HKA no devolvió un PDF válido. Intente de nuevo.');
        }
        try {
            $reader = new Fpdi();
            if ($reader->setSourceFile(StreamReader::createByString($pdf)) < 1) throw new \RuntimeException();
        } catch (\Throwable $exception) {
            self::fail('El PDF recibido de HKA no se puede procesar. Intente de nuevo.');
        }
        return $pdf;
    }

    public static function toLandscapeA5(string $source): string
    {
        try {
            $pdf = new HkaA5Pdf();
            $pdf->SetAutoPageBreak(false);
            $pages = $pdf->setSourceFile(StreamReader::createByString($source));
            if ($pages < 1) throw new \RuntimeException();
            for ($page = 1; $page <= $pages; $page++) {
                // MediaBox preserves the whole source page, including its margins.
                $template = $pdf->importPage($page, 'MediaBox');
                $size = $pdf->getTemplateSize($template);
                if ($size['width'] <= 0 || $size['height'] <= 0) throw new \RuntimeException();
                $pdf->normalizeText($template, 210, 148);
                $pdf->AddPage('L', [210, 148]);
                // Fit both axes to the sheet, without adding lateral letterbox margins.
                $pdf->useImportedPage($template, 0, 0, 210, 148);
            }
            return $pdf->Output('S');
        } catch (\Throwable $exception) {
            self::fail('No se pudo convertir el PDF HKA a A5 horizontal. Intente de nuevo.');
        }
    }

    private static function fail(string $message): void
    {
        throw ValidationException::withMessages(['pdf' => $message]);
    }
}
