<?php

// ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########

namespace Modules\WhatsAppBot\Services\Waha;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\WhatsAppBot\Services\Contracts\WhatsAppProviderClientInterface;
use Modules\WhatsAppBot\Services\Exceptions\WahaVerifyOwnershipUnsupportedException;
use RuntimeException;

/**
 * Cliente de WAHA (https://waha.devlike.pro). A diferencia de EvolutionClient
 * no lee config()/env global: hay N servidores WAHA registrados por
 * superadmin (uno por motor — NOWEB/GOWS/WEBJS/WPP), así que quien instancia
 * este cliente (WhatsAppProviderFactory) resuelve url/apiKey/engine desde el
 * registro y los pasa explícitos.
 *
 * "Instancia" en el vocabulario de Evolution equivale a "sesión" en WAHA —
 * se mantiene el parámetro $instance en las firmas (viene de la interfaz
 * compartida) pero internamente es el nombre de la sesión de WAHA.
 */
class WahaClient implements WhatsAppProviderClientInterface
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $engine,
        private int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function http(?int $timeout = null): PendingRequest
    {
        $http = Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->acceptJson()
            ->timeout($timeout ?? $this->timeout);

        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }

        return $http;
    }

    private function toChatId(string $number): string
    {
        return preg_replace('/\D/', '', $number) . '@c.us';
    }

    public function createInstance(string $instance, ?string $webhookUrl = null): array
    {
        $config = array_filter([
            'webhooks' => $webhookUrl ? [['url' => $webhookUrl, 'events' => ['message']]] : null,
            'noweb' => strtoupper($this->engine) === 'NOWEB'
                ? ['store' => ['enabled' => true, 'fullSync' => false]]
                : null,
        ], static fn ($value) => $value !== null);

        $response = $this->http()->post('/api/sessions', [
            'name' => $instance,
            'start' => true,
            'config' => $config,
        ]);

        if ($response->successful()) {
            return $response->json() ?: [];
        }

        $body = strtolower($response->body());
        if (str_contains($body, 'already exists') || str_contains($body, 'already in use') || $response->status() === 422) {
            return ['ok' => true, 'already_exists' => true];
        }

        throw new RuntimeException("No se pudo crear la sesión en WAHA. HTTP {$response->status()}: {$response->body()}");
    }

    public function connect(string $instance): array
    {
        return $this->http()->get("/api/{$instance}/auth/qr")->json() ?: [];
    }

    public function connectionState(string $instance): array
    {
        $response = $this->http()->get("/api/sessions/{$instance}")->json() ?: [];
        $status = strtoupper((string) ($response['status'] ?? ''));

        $state = match (true) {
            $status === 'WORKING' => 'open',
            in_array($status, ['STARTING', 'SCAN_QR_CODE'], true) => 'connecting',
            default => 'close',
        };

        return ['state' => $state, 'waha_status' => $response['status'] ?? null];
    }

    public function fetchInstance(string $instance): array
    {
        $response = $this->http()->get("/api/sessions/{$instance}/me")->json() ?: [];

        return [
            'owner' => data_get($response, 'id'),
            'profileName' => data_get($response, 'pushname'),
            'token' => null,
        ];
    }

    /**
     * WAHA no tiene token por sesión (solo API key a nivel de servidor), así
     * que no hay forma segura de verificar propiedad como con Evolution.
     * Adoptar sesiones desde ChatBuho queda exclusivo de Evolution.
     */
    public function verifyOwnership(string $instance, string $expectedNumber, string $expectedToken): array
    {
        throw new WahaVerifyOwnershipUnsupportedException(
            'WAHA no soporta verificación de propiedad ni adopción de sesiones existentes.'
        );
    }

    public function setWebhook(string $instance, string $url, array $events = []): array
    {
        return $this->http()->put("/api/sessions/{$instance}", [
            'config' => [
                'webhooks' => [['url' => $url, 'events' => ['message']]],
            ],
        ])->json() ?: [];
    }

    public function sendPresence(string $instance, string $number, string $presence = 'composing', int $delay = 1200): array
    {
        try {
            return $this->http(10)->post('/api/startTyping', [
                'session' => $instance,
                'chatId' => $this->toChatId($number),
            ])->json() ?: [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function sendText(string $instance, string $number, string $text): array
    {
        return $this->http()->post('/api/sendText', [
            'session' => $instance,
            'chatId' => $this->toChatId($number),
            'text' => $text,
        ])->json() ?: [];
    }

    /**
     * Traduce el shape de Evolution ya usado por los call-sites existentes
     * (mediatype/mimetype/media(base64)/fileName/caption) al de
     * POST /api/sendFile de WAHA, sin que ningún call-site cambie.
     */
    public function sendMedia(string $instance, string $number, array $media): array
    {
        return $this->http()->post('/api/sendFile', [
            'session' => $instance,
            'chatId' => $this->toChatId($number),
            'file' => [
                'mimetype' => $media['mimetype'] ?? 'application/octet-stream',
                'filename' => $media['fileName'] ?? 'documento',
                'data' => $media['media'] ?? null,
            ],
            'caption' => $media['caption'] ?? null,
        ])->json() ?: [];
    }

    public function deleteInstance(string $instance): array
    {
        try {
            return $this->http()->delete("/api/sessions/{$instance}")->json() ?: [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function restartInstance(string $instance): array
    {
        return $this->http()->post("/api/sessions/{$instance}/restart")->json() ?: [];
    }

    public static function extractQr(array $response): ?string
    {
        $mimetype = $response['mimetype'] ?? 'image/png';
        $candidates = [
            $response['data'] ?? null,
            $response['qr'] ?? null,
            $response['base64'] ?? null,
        ];

        foreach ($candidates as $qr) {
            if (!empty($qr) && is_string($qr)) {
                return str_starts_with($qr, 'data:image/') ? $qr : "data:{$mimetype};base64,{$qr}";
            }
        }

        return null;
    }

    public static function isConnected(array $response): bool
    {
        return ($response['state'] ?? null) === 'open';
    }
}

// ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
