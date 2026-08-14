<?php

namespace Modules\QrApi\Http\Controllers;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\WhatsappMessageLog;
use App\Traits\LockedEmissionTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\WhatsAppBot\Services\Evolution\EvolutionClient;
use Modules\WhatsAppBot\Services\WhatsAppProviderFactory;

class QrApiController extends Controller
{
    use StorageDocument;
    use LockedEmissionTrait;

    private const NO_INSTANCE_MSG = 'QR Api no tiene una instancia conectada.';

    /**
     * QrApi es solo de salida — nunca tuvo (ni tiene) un endpoint propio que
     * reciba webhooks entrantes. Todo mensaje entrante siempre debe llegar
     * al bot (único consumidor de webhooks entrantes en el módulo), así que
     * la instancia/sesión que crea o renueva QrApi registra el webhook del
     * BOT, usando el token del bot (`evolution_webhook_token`) — incluso si
     * el bot todavía no tiene su propia conexión.
     */
    private function buildWebhookUrl(string $token, string $provider = 'evolution'): string
    {
        $override = env('WHATSAPP_WEBHOOK_BASE_URL');
        $base = $override ? rtrim($override, '/') : rtrim(url('/'), '/');
        $path = $provider === 'waha' ? 'waha-webhook' : 'webhook';
        return $base . '/api/whatsapp-bot/' . $path . '/' . $token;
    }

    /**
     * Triplete {instance, provider, server_key} de la conexión que
     * efectivamente usa QrApi — la propia, o la del bot si
     * `qr_api_use_bot_instance` está activo. Compartir instancia siempre
     * comparte el triplete completo, no solo el nombre, para que el cliente
     * correcto se resuelva sin importar en qué proveedor esté.
     */
    private function effectiveConnection(Configuration $config): array
    {
        if ($config->qr_api_use_bot_instance) {
            return [
                'instance' => $config->evolution_instance,
                'provider' => $config->evolution_provider ?: 'evolution',
                'server_key' => $config->evolution_waha_server_key,
            ];
        }

        return [
            'instance' => $config->qr_api_instance,
            'provider' => $config->qr_api_provider ?: 'evolution',
            'server_key' => $config->qr_api_waha_server_key,
        ];
    }

    public function getConfig()
    {
        $data = Configuration::first();
        $whatsapp_usage = $this->getWhatsappMessagesUsage();
        return [
            'qr_api_enable_ws' => (bool) ($data->qr_api_enable ?? false),
            'qr_api_use_bot_instance' => (bool) ($data->qr_api_use_bot_instance ?? false),
            'qr_api_pdf_format' => $data->qr_api_pdf_format ?? 'ticket',
            'qr_api_instance' => $data->qr_api_instance,
            'qr_api_instance_adopted' => (bool) ($data->qr_api_instance_adopted ?? false),
            'qr_api_connected_phone' => $data->qr_api_connected_phone,
            'qr_api_profile_name' => $data->qr_api_profile_name,
            'qr_api_connection_state' => $data->qr_api_connection_state ?? 'disconnected',
            'qr_api_provider' => $data->qr_api_provider ?: 'evolution',
            'qr_api_waha_server_key' => $data->qr_api_waha_server_key,
            'evolution_instance' => $data->evolution_instance,
            'evolution_instance_adopted' => (bool) ($data->evolution_instance_adopted ?? false),
            'evolution_connected_phone' => $data->evolution_connected_phone,
            'evolution_provider' => $data->evolution_provider ?: 'evolution',
            'evolution_waha_server_key' => $data->evolution_waha_server_key,
            'whatsapp_messages_used' => $whatsapp_usage['used'],
            'whatsapp_messages_limit' => $whatsapp_usage['limit'],
            'whatsapp_messages_unlimited' => $whatsapp_usage['unlimited'],
        ];
    }

    public function updateConfig(Request $request)
    {
        $request->validate([
            'qr_api_enable_ws' => 'required|boolean',
            'qr_api_use_bot_instance' => 'sometimes|boolean',
            'qr_api_pdf_format' => 'sometimes|in:a4,ticket',
        ]);

        $config = Configuration::first();
        $config->qr_api_enable = (bool) $request->qr_api_enable_ws;

        if ($request->has('qr_api_pdf_format')) {
            $config->qr_api_pdf_format = $request->qr_api_pdf_format;
        }

        if ($request->has('qr_api_use_bot_instance')) {
            $newValue = (bool) $request->qr_api_use_bot_instance;

            // Si está activando "usar el mismo del bot" y hay una instancia propia,
            // la desconectamos para no dejar instancias zombie. Si esa instancia
            // fue adoptada (compartida con ChatBuho), NO se elimina — solo se
            // desvincula localmente.
            if ($newValue && !$config->qr_api_use_bot_instance && !empty($config->qr_api_instance)) {
                if (!$config->qr_api_instance_adopted) {
                    try {
                        WhatsAppProviderFactory::forChannel($config, 'qr_api')->deleteInstance($config->qr_api_instance);
                    } catch (\Throwable $e) {
                        Log::warning('[QrApi] No se pudo borrar instancia previa al cambiar a modo bot', [
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
                $config->qr_api_instance = null;
                $config->qr_api_instance_adopted = false;
                $config->qr_api_provider = 'evolution';
                $config->qr_api_waha_server_key = null;
                $config->qr_api_connected_phone = null;
                $config->qr_api_profile_name = null;
                $config->qr_api_connection_state = 'disconnected';
                $config->qr_api_connected_at = null;
            }

            $config->qr_api_use_bot_instance = $newValue;
        }

        $config->save();

        return ['success' => true, 'message' => 'Configuración actualizada.'];
    }

    public function connect(Request $request)
    {
        $data = $request->validate([
            'instance_name' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{3,40}$/'],
        ]);

        $instance = $data['instance_name'];

        $config = Configuration::first();
        if (empty($config->evolution_webhook_token)) {
            $config->evolution_webhook_token = Str::random(40);
        }

        try {
            $resolved = WhatsAppProviderFactory::forNewConnection();
            $webhookUrl = $this->buildWebhookUrl($config->evolution_webhook_token, $resolved['provider']);
            $resolved['client']->createInstance($instance, $webhookUrl);
        } catch (\Throwable $e) {
            Log::error('[QrApi] No se pudo crear instancia', [
                'instance' => $instance,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'No se pudo iniciar la conexión: ' . $e->getMessage()];
        }

        $config->qr_api_instance = $instance;
        $config->qr_api_instance_adopted = false;
        $config->qr_api_provider = $resolved['provider'];
        $config->qr_api_waha_server_key = $resolved['server_key'];
        $config->qr_api_connected_phone = null;
        $config->qr_api_profile_name = null;
        $config->qr_api_connection_state = 'connecting';
        $config->qr_api_use_bot_instance = false;
        $config->qr_api_enable = true;
        $config->save();

        return [
            'success' => true,
            'instance_name' => $instance,
            'provider' => $resolved['provider'],
            'waha_server_key' => $resolved['server_key'],
            'message' => 'Instancia creada. Escanea el código QR para vincular el WhatsApp.',
        ];
    }

    /**
     * Adopta una instancia que ya existe y ya está conectada (ej. porque se
     * conectó primero desde Chatwoot) en vez de crear una nueva. No llama a
     * createInstance() — solo verifica propiedad y registra el webhook.
     * Exclusivo de Evolution (ver WhatsAppBotController::linkExisting()).
     */
    public function linkExisting(Request $request)
    {
        $data = $request->validate([
            'instance_name' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{3,40}$/'],
            'phone_number' => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'token' => ['required', 'string'],
        ]);

        $instance = $data['instance_name'];

        $client = new EvolutionClient();
        $verification = $client->verifyOwnership($instance, $data['phone_number'], $data['token']);

        if (!$verification['success']) {
            return ['success' => false, 'message' => $verification['message']];
        }

        $config = Configuration::first();
        if (empty($config->evolution_webhook_token)) {
            $config->evolution_webhook_token = Str::random(40);
        }

        try {
            $webhookUrl = $this->buildWebhookUrl($config->evolution_webhook_token, 'evolution');
            $client->setWebhook($instance, $webhookUrl);
        } catch (\Throwable $e) {
            Log::error('[QrApi] linkExisting: no se pudo registrar webhook', [
                'instance' => $instance,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'No se pudo vincular la instancia: ' . $e->getMessage()];
        }

        $config->qr_api_instance = $instance;
        $config->qr_api_instance_adopted = true;
        $config->qr_api_provider = 'evolution';
        $config->qr_api_waha_server_key = null;
        $config->qr_api_connected_phone = $verification['connected_phone'];
        $config->qr_api_connection_state = 'open';
        $config->qr_api_connected_at = now();
        $config->qr_api_use_bot_instance = false;
        $config->qr_api_enable = true;
        $config->save();

        return [
            'success' => true,
            'instance_name' => $instance,
            'message' => 'Instancia vinculada con éxito.',
        ];
    }

    public function qr()
    {
        $config = Configuration::first();
        $connection = $this->effectiveConnection($config);
        if (empty($connection['instance'])) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            $client = WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key']);
            $response = $client->connect($connection['instance']);
            $qr = $client::extractQr($response);
            return [
                'success' => $qr !== null,
                'qr' => $qr,
                'message' => $qr ? null : 'El proveedor no devolvió un QR válido.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al obtener QR: ' . $e->getMessage()];
        }
    }

    public function state()
    {
        $config = Configuration::first();
        $connection = $this->effectiveConnection($config);
        $instance = $connection['instance'];
        if (empty($instance)) {
            return ['success' => false, 'connected' => false, 'state' => 'disconnected'];
        }

        try {
            $client = WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key']);
            $response = $client->connectionState($instance);
            $connected = $client::isConnected($response);
            $state = data_get($response, 'instance.state') ?: data_get($response, 'state') ?: 'unknown';

            // Si esta instancia es propia (no compartida con el bot), guardamos los datos.
            if (!$config->qr_api_use_bot_instance) {
                if ($connected && $config->qr_api_connection_state !== 'open') {
                    $config->qr_api_connection_state = 'open';
                    $config->qr_api_connected_at = now();
                }
                if ($connected && (empty($config->qr_api_connected_phone) || empty($config->qr_api_profile_name) || empty($config->qr_api_instance_token))) {
                    try {
                        $info = $client->fetchInstance($instance);
                        $first = is_array($info) && isset($info[0]) ? $info[0] : $info;
                        $owner = data_get($first, 'instance.owner') ?: data_get($first, 'owner') ?: data_get($first, 'ownerJid');
                        $profileName = data_get($first, 'instance.profileName') ?: data_get($first, 'profileName');
                        $token = data_get($first, 'instance.token') ?: data_get($first, 'token');
                        if ($owner) {
                            $config->qr_api_connected_phone = preg_replace('/\D/', '', explode('@', (string) $owner)[0]);
                        }
                        if ($profileName) {
                            $config->qr_api_profile_name = $profileName;
                        }
                        if ($token) {
                            $config->qr_api_instance_token = $token;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[QrApi] fetchInstance fallo', ['exception' => $e->getMessage()]);
                    }
                }
                if (!$connected && $config->qr_api_connection_state !== $state) {
                    $config->qr_api_connection_state = $state;
                }
                $config->save();
            }

            $instanceAdopted = $config->qr_api_use_bot_instance
                ? (bool) $config->evolution_instance_adopted
                : (bool) $config->qr_api_instance_adopted;
            // El token solo se expone cuando la instancia es propia de QrApi
            // (no compartida con el bot, no adoptada) — es el que hay que
            // copiar hacia ChatBuho para que ellos puedan adoptarla ahí.
            // Nunca aplica para WAHA (no tiene token por sesión).
            $instanceToken = (!$config->qr_api_use_bot_instance && !$instanceAdopted)
                ? $config->qr_api_instance_token
                : null;

            return [
                'success' => true,
                'connected' => $connected,
                'state' => $state,
                'instance_name' => $instance,
                'instance_adopted' => $instanceAdopted,
                'connected_phone' => $config->qr_api_use_bot_instance
                    ? $config->evolution_connected_phone
                    : $config->qr_api_connected_phone,
                'profile_name' => $config->qr_api_use_bot_instance
                    ? $config->evolution_profile_name
                    : $config->qr_api_profile_name,
                'instance_token' => $instanceToken,
                'provider' => $connection['provider'],
                'waha_server_key' => $connection['server_key'],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al consultar estado: ' . $e->getMessage()];
        }
    }

    /**
     * Desconecta QrApi de su instancia. Si la instancia fue adoptada (vino de
     * ChatBuho, ver linkExisting()), NO se elimina — solo se desvincula
     * localmente, para no romper la conexión de ChatBuho.
     */
    public function disconnect()
    {
        $config = Configuration::first();
        if (empty($config->qr_api_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        $wasAdopted = (bool) $config->qr_api_instance_adopted;

        if (!$wasAdopted) {
            try {
                WhatsAppProviderFactory::forChannel($config, 'qr_api')->deleteInstance($config->qr_api_instance);
            } catch (\Throwable $e) {
                Log::warning('[QrApi] deleteInstance fallo (continuo limpiando local)', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $config->qr_api_instance = null;
        $config->qr_api_instance_adopted = false;
        $config->qr_api_provider = 'evolution';
        $config->qr_api_waha_server_key = null;
        $config->qr_api_connected_phone = null;
        $config->qr_api_profile_name = null;
        $config->qr_api_connection_state = 'disconnected';
        $config->qr_api_connected_at = null;
        $config->qr_api_enable = false;
        $config->save();

        return [
            'success' => true,
            'message' => $wasAdopted
                ? 'Instancia desvinculada de QrApi. Sigue conectada en ChatBuho.'
                : 'Número desconectado.',
        ];
    }

    public function restart()
    {
        $config = Configuration::first();
        $connection = $this->effectiveConnection($config);
        if (empty($connection['instance'])) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key'])
                ->restartInstance($connection['instance']);
            return ['success' => true, 'message' => 'Instancia reiniciada.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo reiniciar: ' . $e->getMessage()];
        }
    }

    /**
     * Renueva la instancia/sesión (borra + crea de nuevo, hay que reescanear
     * el QR). Si se manda `waha_server_key` y la conexión actual ya está en
     * WAHA, en vez de recrear en el mismo servidor mueve la sesión al
     * servidor destino — mecanismo de "cambiar de motor" si uno falla.
     */
    public function renew(Request $request)
    {
        $data = $request->validate([
            'waha_server_key' => ['sometimes', 'nullable', 'string'],
        ]);
        $targetServerKey = $data['waha_server_key'] ?? null;

        $config = Configuration::first();
        if (empty($config->qr_api_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        if ($config->qr_api_instance_adopted) {
            return [
                'success' => false,
                'message' => 'Esta instancia está vinculada desde ChatBuho — renovarla la eliminaría también ahí. Desvincúlala primero o renuévala desde ChatBuho.',
            ];
        }

        if ($targetServerKey && $config->qr_api_provider !== 'waha') {
            return ['success' => false, 'message' => 'Solo se puede cambiar de servidor si la conexión actual ya está en WAHA.'];
        }

        $instance = $config->qr_api_instance;

        try {
            WhatsAppProviderFactory::forProviderAndKey($config->qr_api_provider ?: 'evolution', $config->qr_api_waha_server_key)
                ->deleteInstance($instance);
        } catch (\Throwable $e) {
            Log::warning('[QrApi] renew: deleteInstance fallo', ['exception' => $e->getMessage()]);
        }

        sleep(2);

        $newProvider = $targetServerKey ? 'waha' : ($config->qr_api_provider ?: 'evolution');
        $newServerKey = $targetServerKey ?: $config->qr_api_waha_server_key;

        if (empty($config->evolution_webhook_token)) {
            $config->evolution_webhook_token = Str::random(40);
        }

        try {
            $webhookUrl = $this->buildWebhookUrl($config->evolution_webhook_token, $newProvider);
            WhatsAppProviderFactory::forProviderAndKey($newProvider, $newServerKey)->createInstance($instance, $webhookUrl);
        } catch (\Throwable $e) {
            Log::error('[QrApi] renew: no se pudo recrear instancia', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => 'No se pudo renovar la instancia: ' . $e->getMessage()];
        }

        $config->qr_api_provider = $newProvider;
        $config->qr_api_waha_server_key = $newServerKey;
        $config->qr_api_connected_phone = null;
        $config->qr_api_profile_name = null;
        $config->qr_api_connection_state = 'connecting';
        $config->qr_api_connected_at = null;
        $config->save();

        return [
            'success' => true,
            'message' => $targetServerKey
                ? 'Conexión movida al nuevo servidor. Escanea el nuevo QR para reconectar.'
                : 'Instancia renovada. Escanea el nuevo QR para reconectar.',
        ];
    }

    public function encodeBase64(Request $request)
    {
        $request->validate([
            'filename_only' => 'required',
            'extension_only' => 'required',
        ]);

        $filename = $request->filename_only;
        $content_file = $this->getStorage($filename, 'pdf');
        return base64_encode($content_file);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'file' => 'required',
            'number' => 'required',
            'message' => 'required',
            'filename' => 'required',
            'link' => 'sometimes|nullable|string',
        ]);

        $exceed_limit = $this->exceedLimitWhatsappMessages();
        if ($exceed_limit['success']) {
            return response()->json([
                'success' => false,
                'message' => $exceed_limit['message'],
            ], 422);
        }

        $config = Configuration::first();

        if(!$config->qr_api_enable) {
            return response()->json([
                'success' => false,
                'message' => 'La opción de enviar mensajes no está habilitada'
            ], 422);
        }

        $connection = $this->effectiveConnection($config);
        $instance = $connection['instance'];

        if (empty($instance)) {
            return response()->json([
                'success' => false,
                'message' => 'QR Api no tiene un número conectado. Configúralo en Configuración → QR Api.',
            ], 422);
        }

        $client = WhatsAppProviderFactory::forProviderAndKey($connection['provider'], $connection['server_key']);

        $mediaResponse = null;
        $mediaOk = false;
        try {
            $mediaResponse = $client->sendMedia($instance, $request->number, [
                'mediatype' => 'document',
                'mimetype' => 'application/pdf',
                'media' => $request->file,
                'fileName' => $request->filename,
                'caption' => $request->message,
            ]);
            $mediaOk = true;
        } catch (\Throwable $e) {
            Log::error('[QrApi] sendMessage sendMedia fallo', ['exception' => $e->getMessage()]);
        }

        $textOk = false;
        if ($request->filled('link')) {
            try {
                $text = $request->message . ': puede revisarlo en el siguiente enlace ' . $request->link;
                $client->sendText($instance, $request->number, $text);
                $textOk = true;
            } catch (\Throwable $e) {
                Log::error('[QrApi] sendMessage sendText fallo', ['exception' => $e->getMessage()]);
            }
        }

        if ($mediaOk || $textOk) {
            WhatsappMessageLog::create([
                'phone' => $request->number,
                'filename' => $request->filename,
                'status' => 'sent',
            ]);

            return response()->json([
                'success' => true,
                'data' => $mediaResponse,
                'media_sent' => $mediaOk,
                'link_sent' => $textOk,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo enviar el comprobante por WhatsApp.',
        ], 500);
    }
}
