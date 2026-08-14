<?php

namespace Modules\WhatsAppBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use Modules\WhatsAppBot\Services\Webhook\IncomingWebhookProcessor;

/**
 * Ingesta de webhooks de WAHA. Solo hace el parseo específico del shape de
 * WAHA (payload.from/body/fromMe/id) — el resto (auth, dedupe, self-chat,
 * despacho al bot) vive en IncomingWebhookProcessor, compartido con
 * WebhookController (Evolution). Reusa el mismo evolution_webhook_token del
 * tenant: es un token de "canal", no de "proveedor" — se mantiene estable si
 * el tenant cambia de proveedor.
 */
class WahaWebhookController extends Controller
{
    public function __construct(private IncomingWebhookProcessor $processor)
    {
    }

    public function receive(Request $request, string $token)
    {
        $configuration = Configuration::first();

        $unauthorized = $this->processor->authorize(
            $configuration?->evolution_webhook_token,
            $token,
            (bool) $configuration?->whatsapp_bot_enabled
        );
        if ($unauthorized) {
            return $unauthorized;
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        if ($event !== 'message') {
            return response()->json(['ok' => true, 'ignored' => $event]);
        }

        $data = $payload['payload'] ?? [];
        $from = (string) ($data['from'] ?? '');
        $isGroupOrBroadcast = $from && (
            str_ends_with($from, '@g.us')
            || str_ends_with($from, '@broadcast')
            || str_ends_with($from, '@newsletter')
        );

        $phone = $from ? explode('@', $from)[0] : null;
        $fromMe = (bool) ($data['fromMe'] ?? false);
        $messageId = $data['id'] ?? null;
        $body = $data['body'] ?? null;

        return $this->processor->process($configuration, $phone, $body, $fromMe, $messageId, $isGroupOrBroadcast, $payload);
    }
}
