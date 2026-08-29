<?php

namespace App\Http\Controllers\System\Api;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\WhatsAppBot\Services\Contracts\WhatsAppProviderClientInterface;
use Modules\WhatsAppBot\Services\WhatsAppProviderFactory;

/**
 * API publica para que un servicio externo envie WhatsApp usando el numero
 * conectado al superadmin (ver App\Http\Controllers\System\WhatsAppNotifyController).
 * Autenticacion: header "Authorization: Bearer {notify_wa_api_token}".
 *
 * No requiere sesion ni dominio de tenant — es un endpoint global del
 * sistema, pensado para que se llame desde fuera de Pro9 (otro backend,
 * un script, un webhook, etc.).
 */
class WhatsAppNotifyApiController extends Controller
{
    private function authenticate(Request $request, Configuration $config): ?array
    {
        $token = $request->bearerToken();

        if (empty($config->notify_wa_api_token) || empty($token) || !hash_equals($config->notify_wa_api_token, $token)) {
            return ['success' => false, 'message' => 'Token inválido o ausente.'];
        }

        if (!$config->notify_wa_enabled || empty($config->notify_wa_instance) || $config->notify_wa_connection_state !== 'open') {
            return ['success' => false, 'message' => 'No hay ningún número conectado y habilitado para notificaciones.'];
        }

        return null;
    }

    /**
     * Cliente del proveedor congelado al conectar el numero (NULL en
     * notify_wa_provider = conexion previa a WAHA, o sea Evolution).
     */
    private function client(Configuration $config): WhatsAppProviderClientInterface
    {
        return WhatsAppProviderFactory::forProviderAndKey(
            $config->notify_wa_provider ?: 'evolution',
            $config->notify_wa_waha_server_key
        );
    }

    /**
     * sendText()/sendMedia() no lanzan excepcion en respuestas HTTP de error
     * (4xx/5xx) — solo devuelven el body parseado. Sin este chequeo se
     * reportaria "enviado" aunque el proveedor lo haya rechazado.
     */
    private function deliveryError(WhatsAppProviderClientInterface $client, array $response, string $context): ?array
    {
        if ($client::extractMessageId($response)) {
            return null;
        }

        Log::warning("[WhatsAppNotifyApi] {$context}: el proveedor no confirmó el envío", ['response' => $response]);

        return [
            'success' => false,
            'message' => 'El proveedor no confirmó el envío.',
            'data' => $response,
        ];
    }

    public function text(Request $request)
    {
        $config = Configuration::first();
        if ($error = $this->authenticate($request, $config)) {
            return response()->json($error, 401);
        }

        $data = $request->validate([
            'number' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $client = $this->client($config);
            $response = $client->sendText($config->notify_wa_instance, $data['number'], $data['message']);
            if ($error = $this->deliveryError($client, $response, 'text')) {
                return response()->json($error, 502);
            }
            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotifyApi] text falló', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'No se pudo enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Media generica (imagen, video, audio o documento) por URL o base64.
     */
    public function media(Request $request)
    {
        $config = Configuration::first();
        if ($error = $this->authenticate($request, $config)) {
            return response()->json($error, 401);
        }

        $data = $request->validate([
            'number' => 'required|string',
            'media' => 'required|string', // URL publica o base64
            'media_type' => 'required|in:image,video,audio,document',
            'mimetype' => 'nullable|string',
            'filename' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        try {
            $client = $this->client($config);
            $response = $client->sendMedia($config->notify_wa_instance, $data['number'], [
                'mediatype' => $data['media_type'],
                'mimetype' => $data['mimetype'] ?? '',
                'media' => $data['media'],
                'fileName' => $data['filename'] ?? '',
                'caption' => $data['caption'] ?? '',
            ]);
            if ($error = $this->deliveryError($client, $response, 'media')) {
                return response()->json($error, 502);
            }
            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotifyApi] media falló', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'No se pudo enviar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Atajo para el caso mas comun: mandar un PDF (comprobante, reporte, etc.)
     * en base64.
     */
    public function pdf(Request $request)
    {
        $config = Configuration::first();
        if ($error = $this->authenticate($request, $config)) {
            return response()->json($error, 401);
        }

        $data = $request->validate([
            'number' => 'required|string',
            'file' => 'required|string', // base64 del PDF
            'filename' => 'required|string',
            'message' => 'nullable|string',
        ]);

        try {
            $client = $this->client($config);
            $response = $client->sendMedia($config->notify_wa_instance, $data['number'], [
                'mediatype' => 'document',
                'mimetype' => 'application/pdf',
                'media' => $data['file'],
                'fileName' => $data['filename'],
                'caption' => $data['message'] ?? '',
            ]);
            if ($error = $this->deliveryError($client, $response, 'pdf')) {
                return response()->json($error, 502);
            }
            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotifyApi] pdf falló', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'No se pudo enviar: ' . $e->getMessage()], 500);
        }
    }
}
