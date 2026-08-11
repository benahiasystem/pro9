<?php

namespace App\Http\Controllers\System\Api;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\WhatsAppBot\Services\Evolution\EvolutionClient;

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
     * sendText()/sendMedia() no lanzan excepcion en respuestas HTTP de error
     * (4xx/5xx) — solo devuelven el body parseado. Sin este chequeo se
     * reportaria "enviado" aunque Evolution lo haya rechazado.
     */
    private function deliveryError(array $response, string $context): ?array
    {
        $messageId = data_get($response, 'key.id') ?: data_get($response, 'data.key.id');
        if ($messageId) {
            return null;
        }

        Log::warning("[WhatsAppNotifyApi] {$context}: Evolution no confirmó el envío", ['response' => $response]);

        return [
            'success' => false,
            'message' => 'Evolution no confirmó el envío.',
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
            $response = (new EvolutionClient())->sendText($config->notify_wa_instance, $data['number'], $data['message']);
            if ($error = $this->deliveryError($response, 'text')) {
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
            $response = (new EvolutionClient())->sendMedia($config->notify_wa_instance, $data['number'], [
                'mediatype' => $data['media_type'],
                'mimetype' => $data['mimetype'] ?? '',
                'media' => $data['media'],
                'fileName' => $data['filename'] ?? '',
                'caption' => $data['caption'] ?? '',
            ]);
            if ($error = $this->deliveryError($response, 'media')) {
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
            $response = (new EvolutionClient())->sendMedia($config->notify_wa_instance, $data['number'], [
                'mediatype' => 'document',
                'mimetype' => 'application/pdf',
                'media' => $data['file'],
                'fileName' => $data['filename'],
                'caption' => $data['message'] ?? '',
            ]);
            if ($error = $this->deliveryError($response, 'pdf')) {
                return response()->json($error, 502);
            }
            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotifyApi] pdf falló', ['exception' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'No se pudo enviar: ' . $e->getMessage()], 500);
        }
    }
}
