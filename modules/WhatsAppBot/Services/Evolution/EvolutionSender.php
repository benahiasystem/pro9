<?php

namespace Modules\WhatsAppBot\Services\Evolution;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Configuration;
use Illuminate\Support\Facades\Log;
use Modules\WhatsAppBot\Models\BotMessage;
use Modules\WhatsAppBot\Services\WhatsAppProviderFactory;

class EvolutionSender
{
    use StorageDocument;

    /**
     * Triplete {instance, provider, server_key} de la conexión que
     * efectivamente usa el bot para enviar — la propia, o la de QrApi si
     * `evolution_use_qr_api_instance` está activo. Mismo criterio que
     * WhatsAppBotController::effectiveConnection().
     */
    private function effectiveConnection(Configuration $config): array
    {
        if ($config->evolution_use_qr_api_instance) {
            return [
                'instance' => $config->qr_api_instance,
                'provider' => $config->qr_api_provider ?: 'evolution',
                'server_key' => $config->qr_api_waha_server_key,
            ];
        }

        return [
            'instance' => $config->evolution_instance,
            'provider' => $config->evolution_provider ?: 'evolution',
            'server_key' => $config->evolution_waha_server_key,
        ];
    }

    public function sendTyping(string $toPhone, int $delay = 1200): void
    {
        $config = Configuration::first();
        if (!$config) {
            return;
        }

        $connection = $this->effectiveConnection($config);
        if (empty($connection['instance'])) {
            return;
        }

        try {
            WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key'])
                ->sendPresence($connection['instance'], $toPhone, 'composing', $delay);
        } catch (\Throwable $e) {
            // silent: presence is best-effort
        }
    }

    public function sendText(string $toPhone, string $text, ?int $sessionId = null): ?array
    {
        $config = Configuration::first();
        $connection = $config ? $this->effectiveConnection($config) : null;

        if (!$config || empty($connection['instance'])) {
            Log::error('[WhatsAppBot] EvolutionSender: tenant sin instance configurada');
            return null;
        }

        try {
            $data = WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key'])
                ->sendText($connection['instance'], $toPhone, $text);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppBot] Evolution sendText exception', [
                'exception' => $e->getMessage(),
                'to' => $toPhone,
            ]);
            return null;
        }

        $messageId = data_get($data, 'key.id');

        BotMessage::create([
            'session_id' => $sessionId,
            'direction' => 'out',
            'phone' => $toPhone,
            'body' => $text,
            'message_id' => $messageId,
            'from_me' => true,
            'raw_payload' => $data,
        ]);

        return $data;
    }

    public function sendDocumentPdf(string $toPhone, string $filenameBase, string $displayFilename, ?string $caption = null, ?int $sessionId = null): ?array
    {
        $config = Configuration::first();
        $connection = $config ? $this->effectiveConnection($config) : null;

        if (!$config || empty($connection['instance'])) {
            Log::error('[WhatsAppBot] EvolutionSender PDF: tenant sin instance configurada');
            return null;
        }

        try {
            $pdfBinary = $this->getStorage($filenameBase, 'pdf');
        } catch (\Throwable $e) {
            Log::error('[WhatsAppBot] No se pudo leer el PDF del storage', [
                'filename' => $filenameBase,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        try {
            $data = WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key'])
                ->sendMedia($connection['instance'], $toPhone, [
                    'mediatype' => 'document',
                    'mimetype' => 'application/pdf',
                    'media' => base64_encode($pdfBinary),
                    'fileName' => $displayFilename,
                    'caption' => $caption ?: $displayFilename,
                ]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppBot] Evolution sendMedia (PDF) exception', [
                'exception' => $e->getMessage(),
                'to' => $toPhone,
            ]);
            return null;
        }

        $messageId = data_get($data, 'key.id');

        BotMessage::create([
            'session_id' => $sessionId,
            'direction' => 'out',
            'phone' => $toPhone,
            'body' => '[PDF] ' . $displayFilename . ($caption ? ' — ' . $caption : ''),
            'message_id' => $messageId,
            'from_me' => true,
            'raw_payload' => $data,
        ]);

        return $data;
    }
}
