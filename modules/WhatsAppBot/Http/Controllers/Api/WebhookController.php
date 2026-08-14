<?php

namespace Modules\WhatsAppBot\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use Modules\WhatsAppBot\Services\Webhook\IncomingWebhookProcessor;

/**
 * Ingesta de webhooks de Evolution API (Baileys). Solo hace el parseo
 * específico del shape de Evolution — el resto (auth, dedupe, self-chat,
 * despacho al bot) vive en IncomingWebhookProcessor, compartido con
 * WahaWebhookController.
 */
class WebhookController extends Controller
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

        if (!in_array($event, ['messages.upsert', 'send.message'], true)) {
            return response()->json(['ok' => true, 'ignored' => $event]);
        }

        $data = $payload['data'] ?? [];
        $remoteJid = data_get($data, 'key.remoteJid', '');
        $isGroupOrBroadcast = $remoteJid && (str_ends_with($remoteJid, '@g.us') || str_ends_with($remoteJid, '@broadcast'));

        // Direccionamiento LID: el remitente llega como <id>@lid sin numero real.
        // El proxy bot-proxy-v2 inyecta key.senderPn con el numero real.
        $senderPn = data_get($data, 'key.senderPn', '');
        $remoteJidAlt = data_get($data, 'key.remoteJidAlt', '');
        $phoneJid = str_ends_with($remoteJid, '@lid') ? ($senderPn ?: $remoteJidAlt) : $remoteJid;

        $phone = $phoneJid ? explode('@', $phoneJid)[0] : null;
        $fromMe = (bool) data_get($data, 'key.fromMe', false);
        $messageId = data_get($data, 'key.id');
        $body = data_get($data, 'message.conversation')
            ?? data_get($data, 'message.extendedTextMessage.text')
            ?? null;

        return $this->processor->process($configuration, $phone, $body, $fromMe, $messageId, $isGroupOrBroadcast, $payload);
    }
}
