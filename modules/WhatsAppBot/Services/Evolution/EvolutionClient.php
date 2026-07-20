<?php

namespace Modules\WhatsAppBot\Services\Evolution;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de Evolution API.
 *
 * Por defecto habla con el bot-proxy de BuhoLa (https://bot-proxy.buho.xyz),
 * que expone una API mas amigable y oculta la APIkey master del servidor
 * Evolution compartido entre tenants.
 *
 * Si la instalacion trae su propio Evolution (deployments on-premise con
 * EVOLUTION_API_URL + EVOLUTION_API_KEY seteadas en env, ver config/evolution.php),
 * habla directo contra ese Evolution y deja de usar el proxy.
 *
 * Mantiene el nombre EvolutionClient por compatibilidad con el resto del
 * modulo; internamente decide a cual de los dos backends apuntar.
 */
class EvolutionClient
{
    private bool $direct;
    private string $baseUrl;
    private string $authHeader;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $directUrl = config('evolution.url');
        $directKey = config('evolution.apikey');

        if (!empty($directUrl) && !empty($directKey)) {
            $this->direct = true;
            $this->baseUrl = rtrim($directUrl, '/');
            $this->apiKey = $directKey;
            $this->timeout = (int) (config('evolution.timeout') ?? 30);

            return;
        }

        $url = config('buho_proxy.url');
        $clientId = config('buho_proxy.client_id');
        $clientSecret = config('buho_proxy.client_secret');

        if (empty($url) || empty($clientId) || empty($clientSecret)) {
            throw new RuntimeException('Proxy del bot no configurado. Revisa config/buho_proxy.php.');
        }

        $this->direct = false;
        $this->baseUrl = rtrim($url, '/');
        $this->authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
        $this->timeout = (int) (config('buho_proxy.timeout') ?? 30);
    }

    private function http(?int $timeout = null): PendingRequest
    {
        $headers = $this->direct
            ? ['apikey' => $this->apiKey]
            : ['Authorization' => $this->authHeader];

        $http = Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->acceptJson()
            ->timeout($timeout ?? $this->timeout);

        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }

        return $http;
    }

    /**
     * Crea la instancia. En modo proxy registra el webhook en el mismo paso
     * (el proxy se encarga de registrarlo contra si mismo y reenviar los
     * eventos al webhook_url del tenant). En modo directo crea la instancia
     * contra Evolution y, si se paso $webhookUrl, lo registra en un segundo
     * llamado real contra Evolution.
     */
    public function createInstance(string $instance, ?string $webhookUrl = null): array
    {
        if (!$this->direct) {
            $payload = ['instance_name' => $instance];
            if ($webhookUrl) {
                $payload['webhook_url'] = $webhookUrl;
            }

            $response = $this->http()->post('/api/bot/instances', $payload);

            if ($response->successful()) {
                return $response->json() ?: [];
            }

            $body = strtolower($response->body());
            if (str_contains($body, 'already exists') || str_contains($body, 'already in use')) {
                return ['ok' => true, 'already_exists' => true];
            }

            throw new RuntimeException("No se pudo crear la instancia. HTTP {$response->status()}: {$response->body()}");
        }

        // Evolution ha cambiado el shape aceptado por /instance/create entre
        // versiones; se prueban variantes en orden hasta que una funcione.
        $candidates = [
            ['instanceName' => $instance, 'qrcode' => true],
            ['instanceName' => $instance, 'qrcode' => true, 'token' => '', 'integration' => 'WHATSAPP-BAILEYS'],
            [
                'instanceName' => $instance,
                'token' => '',
                'qrcode' => true,
                'integration' => 'WHATSAPP-BAILEYS',
                'reject_call' => false,
                'groupsIgnore' => true,
                'alwaysOnline' => false,
                'readMessages' => false,
                'readStatus' => false,
                'syncFullHistory' => false,
            ],
        ];

        $lastError = null;
        $result = null;

        foreach ($candidates as $payload) {
            $response = $this->http()->post('/instance/create', $payload);

            if ($response->successful()) {
                $result = $response->json() ?: [];
                break;
            }

            $body = strtolower($response->body());
            if (str_contains($body, 'already exists') || str_contains($body, 'already in use')) {
                $result = ['ok' => true, 'already_exists' => true];
                break;
            }

            $lastError = "HTTP {$response->status()}: {$response->body()}";
            usleep(800000);
        }

        if ($result === null) {
            throw new RuntimeException('No se pudo crear la instancia en Evolution. ' . $lastError);
        }

        if ($webhookUrl) {
            $this->setWebhook($instance, $webhookUrl);
        }

        return $result;
    }

    public function connect(string $instance): array
    {
        $path = $this->direct
            ? "/instance/connect/{$instance}"
            : "/api/bot/instances/{$instance}/qr";

        return $this->http()->get($path)->json() ?: [];
    }

    public function connectionState(string $instance): array
    {
        $path = $this->direct
            ? "/instance/connectionState/{$instance}"
            : "/api/bot/instances/{$instance}/state";

        return $this->http()->get($path)->json() ?: [];
    }

    public function fetchInstance(string $instance): array
    {
        if ($this->direct) {
            return $this->http()->get('/instance/fetchInstances', ['instanceName' => $instance])->json() ?: [];
        }

        return $this->http()->get("/api/bot/instances/{$instance}/info")->json() ?: [];
    }

    /**
     * Actualiza el webhook del tenant. En modo proxy el webhook de Evolution
     * ya apunta al proxy desde la creacion de la instancia — esto solo
     * actualiza a donde reenvia el proxy internamente. En modo directo
     * registra el webhook real contra Evolution.
     */
    public function setWebhook(string $instance, string $url, array $events = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']): array
    {
        if ($this->direct) {
            return $this->http()->post("/webhook/set/{$instance}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $url,
                    'webhookByEvents' => false,
                    'webhookBase64' => false,
                    'events' => $events,
                ],
            ])->json() ?: [];
        }

        return $this->http()->put("/api/bot/instances/{$instance}/webhook", [
            'url' => $url,
        ])->json() ?: [];
    }

    public function sendPresence(string $instance, string $number, string $presence = 'composing', int $delay = 1200): array
    {
        $path = $this->direct
            ? "/chat/sendPresence/{$instance}"
            : "/api/bot/instances/{$instance}/presence";

        try {
            return $this->http(10)->post($path, [
                'number' => preg_replace('/\D/', '', $number),
                'presence' => $presence,
                'delay' => $delay,
            ])->json() ?: [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function sendText(string $instance, string $number, string $text): array
    {
        $path = $this->direct
            ? "/message/sendText/{$instance}"
            : "/api/bot/instances/{$instance}/messages/text";

        return $this->http()->post($path, [
            'number' => preg_replace('/\D/', '', $number),
            'text' => $text,
            'delay' => 0,
            'linkPreview' => true,
        ])->json() ?: [];
    }

    public function sendMedia(string $instance, string $number, array $media): array
    {
        $path = $this->direct
            ? "/message/sendMedia/{$instance}"
            : "/api/bot/instances/{$instance}/messages/media";

        return $this->http()->post($path, array_merge([
            'number' => preg_replace('/\D/', '', $number),
            'delay' => 0,
        ], $media))->json() ?: [];
    }

    public function deleteInstance(string $instance): array
    {
        $path = $this->direct
            ? "/instance/delete/{$instance}"
            : "/api/bot/instances/{$instance}";

        try {
            return $this->http()->delete($path)->json() ?: [];
        } catch (\Throwable $e) {
            if (!$this->direct) {
                return ['error' => $e->getMessage()];
            }

            try {
                return $this->http()->post($path)->json() ?: [];
            } catch (\Throwable $e2) {
                return ['error' => $e2->getMessage()];
            }
        }
    }

    public function restartInstance(string $instance): array
    {
        $path = $this->direct
            ? "/instance/restart/{$instance}"
            : "/api/bot/instances/{$instance}/restart";

        return $this->http()->post($path)->json() ?: [];
    }

    public static function extractQr(array $response): ?string
    {
        // El proxy ya normaliza el QR pero mantenemos los candidatos por
        // compat con el shape crudo de Evolution (modo directo).
        $candidates = [
            $response['qr'] ?? null,
            $response['qrcode']['base64'] ?? null,
            $response['base64'] ?? null,
            is_string($response['qrcode'] ?? null) ? $response['qrcode'] : null,
            $response['code'] ?? null,
            $response['qr_code'] ?? null,
            $response['image'] ?? null,
        ];

        foreach ($candidates as $qr) {
            if (!empty($qr) && is_string($qr)) {
                return str_starts_with($qr, 'data:image/') ? $qr : 'data:image/png;base64,' . $qr;
            }
        }

        return null;
    }

    public static function isConnected(array $response): bool
    {
        return ($response['state'] ?? null) === 'open'
            || ($response['status'] ?? null) === 'connected'
            || ($response['instance']['state'] ?? null) === 'open'
            || ($response['instance']['status'] ?? null) === 'connected'
            || ($response['connected'] ?? false) === true;
    }
}
