<?php

namespace Modules\WhatsAppBot\Services\Webhook;

use App\Models\Tenant\Configuration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\WhatsAppBot\Models\BotMessage;
use Modules\WhatsAppBot\Services\BotOrchestrator;

/**
 * Lógica de ingesta de mensajes entrantes común a cualquier proveedor
 * (Evolution, WAHA): auth por token, dedupe, self-chat, persistencia de
 * BotMessage, lock por teléfono y despacho a BotOrchestrator. Cada
 * controller de proveedor (WebhookController para Evolution,
 * WahaWebhookController para WAHA) solo aporta el parseo específico del
 * shape de payload de su proveedor y delega el resto acá.
 */
class IncomingWebhookProcessor
{
    private const LOCK_TTL_SECONDS = 60;

    public function __construct(private BotOrchestrator $orchestrator)
    {
    }

    /**
     * Valida el token del webhook y el toggle global del bot. Devuelve una
     * respuesta JSON si hay que cortar el flujo acá, o null para continuar.
     */
    public function authorize(?string $storedToken, string $providedToken, bool $botEnabled): ?JsonResponse
    {
        if (!$storedToken || !hash_equals($storedToken, $providedToken)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        if (!$botEnabled) {
            return response()->json(['ok' => true, 'ignored' => 'bot_disabled']);
        }

        return null;
    }

    public function process(
        Configuration $configuration,
        ?string $phone,
        ?string $body,
        bool $fromMe,
        ?string $messageId,
        bool $isGroupOrBroadcast,
        array $rawPayload
    ): JsonResponse {
        if ($isGroupOrBroadcast) {
            return response()->json(['ok' => true, 'ignored' => 'group_or_broadcast']);
        }

        if ($messageId && BotMessage::where('message_id', $messageId)->exists()) {
            Log::info('[WhatsAppBot] Webhook duplicado ignorado', [
                'message_id' => $messageId,
                'phone' => $phone,
            ]);
            return response()->json(['ok' => true, 'deduped' => true]);
        }

        // Self-chat: el dueño habla consigo mismo en su propio numero conectado.
        // En ese caso procesamos como mensaje del dueño (autorizado via
        // evolution_owner_user_id). En cualquier otro fromMe (mensaje del dueño
        // a un cliente) lo ignoramos — no usamos eso como señal de nada.
        //
        // El webhook siempre llega al endpoint del bot (ver QrApiController::
        // buildWebhookUrl()), pero el numero conectado puede haber quedado
        // registrado del lado de QrApi si fue esa pestaña la que efectivamente
        // conecto la instancia/sesion (bot y QrApi independientes, sin compartir).
        // Por eso se compara contra cualquiera de los dos numeros conocidos.
        $connectedPhone = $configuration->evolution_connected_phone ?: $configuration->qr_api_connected_phone;
        $isSelfChat = $fromMe && $connectedPhone && $phone === $connectedPhone;

        if ($fromMe && !$isSelfChat) {
            return response()->json(['ok' => true, 'ignored' => 'from_me_to_other']);
        }

        BotMessage::create([
            'session_id' => null,
            'direction' => 'in',
            'phone' => $phone,
            'body' => $body,
            'message_id' => $messageId,
            'from_me' => $fromMe,
            'raw_payload' => $rawPayload,
        ]);

        Log::info('[WhatsAppBot] webhook received', [
            'phone' => $phone,
            'message_id' => $messageId,
            'self_chat' => $isSelfChat,
        ]);

        if (empty($phone) || empty($body)) {
            return response()->json(['ok' => true]);
        }

        $lock = Cache::lock("whatsapp-bot:phone:{$phone}", self::LOCK_TTL_SECONDS);

        if (!$lock->get()) {
            Log::warning('[WhatsAppBot] Otro mensaje del phone ya está siendo procesado, ignorado', [
                'phone' => $phone,
                'message_id' => $messageId,
            ]);
            return response()->json(['ok' => true, 'locked' => true]);
        }

        try {
            $this->orchestrator->handleIncoming($phone, $body, $isSelfChat);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppBot] Orchestrator falló', [
                'exception' => $e->getMessage(),
                'phone' => $phone,
            ]);
        } finally {
            $lock->release();
        }

        return response()->json(['ok' => true]);
    }
}
