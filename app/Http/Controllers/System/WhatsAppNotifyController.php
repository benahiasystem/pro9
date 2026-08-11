<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\WhatsAppBot\Services\Evolution\EvolutionClient;

/**
 * Numero de WhatsApp conectado al superadmin (reseller), usado unicamente
 * para ENVIAR notificaciones salientes (recordatorios de pago a los
 * tenants) via Evolution. A diferencia del bot de tenant, no procesa
 * mensajes entrantes: no registra webhook ni necesita instancia adoptada
 * o compartida.
 */
class WhatsAppNotifyController extends Controller
{
    private const NO_INSTANCE_MSG = 'No hay ningún número conectado.';

    public function connect(Request $request)
    {
        $data = $request->validate([
            'instance_name' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{3,40}$/'],
        ]);

        $instance = $data['instance_name'];
        $config = Configuration::first();

        try {
            (new EvolutionClient())->createInstance($instance);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotify] No se pudo crear instancia', [
                'instance' => $instance,
                'exception' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'No se pudo iniciar la conexión: ' . $e->getMessage(),
            ];
        }

        $config->notify_wa_instance = $instance;
        $config->notify_wa_connected_phone = null;
        $config->notify_wa_profile_name = null;
        $config->notify_wa_connection_state = 'connecting';
        $config->notify_wa_connected_at = null;
        $config->notify_wa_enabled = true;
        if (empty($config->notify_wa_api_token)) {
            $config->notify_wa_api_token = Str::random(48);
        }
        $config->save();

        return [
            'success' => true,
            'instance_name' => $instance,
            'message' => 'Instancia creada. Escanea el código QR para vincular el número.',
        ];
    }

    public function qr()
    {
        $config = Configuration::first();
        if (empty($config->notify_wa_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            $response = (new EvolutionClient())->connect($config->notify_wa_instance);
            $qr = EvolutionClient::extractQr($response);

            return [
                'success' => $qr !== null,
                'qr' => $qr,
                'message' => $qr ? null : 'Evolution no devolvió un QR válido.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al obtener QR: ' . $e->getMessage()];
        }
    }

    public function state()
    {
        $config = Configuration::first();
        if (empty($config->notify_wa_instance)) {
            return ['success' => false, 'connected' => false, 'state' => 'disconnected'];
        }

        try {
            $client = new EvolutionClient();
            $response = $client->connectionState($config->notify_wa_instance);
            $connected = EvolutionClient::isConnected($response);
            $state = data_get($response, 'instance.state') ?: data_get($response, 'state') ?: 'unknown';

            if ($connected) {
                if ($config->notify_wa_connection_state !== 'open') {
                    $config->notify_wa_connection_state = 'open';
                    $config->notify_wa_connected_at = now();
                }

                if (empty($config->notify_wa_connected_phone) || empty($config->notify_wa_profile_name)) {
                    try {
                        $info = $client->fetchInstance($config->notify_wa_instance);
                        $first = is_array($info) && isset($info[0]) ? $info[0] : $info;
                        $owner = data_get($first, 'instance.owner')
                            ?: data_get($first, 'owner')
                            ?: data_get($first, 'ownerJid');
                        $profileName = data_get($first, 'instance.profileName') ?: data_get($first, 'profileName');

                        if ($owner) {
                            $config->notify_wa_connected_phone = preg_replace('/\D/', '', explode('@', (string) $owner)[0]);
                        }
                        if ($profileName) {
                            $config->notify_wa_profile_name = $profileName;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[WhatsAppNotify] No se pudo obtener fetchInstance', ['exception' => $e->getMessage()]);
                    }
                }

                $config->save();
            } elseif ($config->notify_wa_connection_state !== $state) {
                $config->notify_wa_connection_state = $state;
                $config->save();
            }

            return [
                'success' => true,
                'connected' => $connected,
                'state' => $state,
                'instance_name' => $config->notify_wa_instance,
                'connected_phone' => $config->notify_wa_connected_phone,
                'profile_name' => $config->notify_wa_profile_name,
                'api_token' => $config->notify_wa_api_token,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al consultar estado: ' . $e->getMessage()];
        }
    }

    /**
     * Regenera el token con el que servicios externos se autentican contra
     * la API publica de envio (texto/media/pdf). Invalida el anterior.
     */
    public function regenerateToken()
    {
        $config = Configuration::first();
        $config->notify_wa_api_token = Str::random(48);
        $config->save();

        return [
            'success' => true,
            'api_token' => $config->notify_wa_api_token,
            'message' => 'Token regenerado. El anterior dejó de funcionar.',
        ];
    }

    public function disconnect()
    {
        $config = Configuration::first();
        if (empty($config->notify_wa_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            (new EvolutionClient())->deleteInstance($config->notify_wa_instance);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppNotify] deleteInstance falló (continúo limpiando local)', [
                'exception' => $e->getMessage(),
            ]);
        }

        $config->notify_wa_instance = null;
        $config->notify_wa_connected_phone = null;
        $config->notify_wa_profile_name = null;
        $config->notify_wa_connection_state = 'disconnected';
        $config->notify_wa_connected_at = null;
        $config->notify_wa_enabled = false;
        $config->save();

        return ['success' => true, 'message' => 'Número desconectado.'];
    }

    public function restart()
    {
        $config = Configuration::first();
        if (empty($config->notify_wa_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            (new EvolutionClient())->restartInstance($config->notify_wa_instance);
            return ['success' => true, 'message' => 'Instancia reiniciada.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo reiniciar: ' . $e->getMessage()];
        }
    }

    public function renew()
    {
        $config = Configuration::first();
        if (empty($config->notify_wa_instance)) {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        $instance = $config->notify_wa_instance;
        $client = new EvolutionClient();

        try {
            $client->deleteInstance($instance);
        } catch (\Throwable $e) {
            Log::warning('[WhatsAppNotify] renew: deleteInstance falló', ['exception' => $e->getMessage()]);
        }

        sleep(2);

        try {
            $client->createInstance($instance);
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotify] renew: no se pudo recrear instancia', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => 'No se pudo renovar la instancia: ' . $e->getMessage()];
        }

        $config->notify_wa_connected_phone = null;
        $config->notify_wa_profile_name = null;
        $config->notify_wa_connection_state = 'connecting';
        $config->notify_wa_connected_at = null;
        $config->save();

        return [
            'success' => true,
            'message' => 'Instancia renovada. Escanea el nuevo QR para reconectar.',
        ];
    }

    /**
     * Envio manual desde el panel (mensaje de prueba o puntual a un
     * destinatario). Autenticado por sesion de admin, no por el token de la
     * API publica. Acepta texto y/o un PDF adjunto (nada de otros formatos
     * de media, a diferencia de la API publica).
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'message' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:150', 'required_with:file'],
        ]);

        if (empty($data['message']) && empty($data['file'])) {
            return ['success' => false, 'message' => 'Escribe un mensaje o adjunta un PDF.'];
        }

        $config = Configuration::first();
        if (empty($config->notify_wa_instance) || $config->notify_wa_connection_state !== 'open') {
            return ['success' => false, 'message' => self::NO_INSTANCE_MSG];
        }

        try {
            $client = new EvolutionClient();

            if (!empty($data['file'])) {
                $response = $client->sendMedia($config->notify_wa_instance, $data['number'], [
                    'mediatype' => 'document',
                    'mimetype' => 'application/pdf',
                    'media' => $data['file'],
                    'fileName' => $data['filename'],
                    // Nunca mandar null: algunas versiones del wrapper rechazan
                    // (o descartan silenciosamente) el envio si "caption" llega
                    // como null en vez de string vacio.
                    'caption' => $data['message'] ?? '',
                ]);
            } else {
                $response = $client->sendText($config->notify_wa_instance, $data['number'], $data['message']);
            }

            // sendMedia()/sendText() no lanzan excepcion en respuestas HTTP de
            // error (4xx/5xx) — solo devuelven el body parseado. Si Evolution
            // rechazo el envio, la respuesta no trae id de mensaje; sin este
            // chequeo el controlador reportaria "enviado" aunque no llegara.
            $messageId = data_get($response, 'key.id') ?: data_get($response, 'data.key.id');
            if (!$messageId) {
                Log::warning('[WhatsAppNotify] send: Evolution no confirmó el envío', [
                    'has_file' => !empty($data['file']),
                    'response' => $response,
                ]);
                return [
                    'success' => false,
                    'message' => 'Evolution no confirmó el envío. Respuesta: ' . json_encode($response),
                ];
            }

            return ['success' => true, 'message' => 'Mensaje enviado.', 'data' => $response];
        } catch (\Throwable $e) {
            Log::error('[WhatsAppNotify] send falló', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => 'No se pudo enviar: ' . $e->getMessage()];
        }
    }

    public function toggleEnabled(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $config = Configuration::first();
        $config->notify_wa_enabled = (bool) $request->enabled;
        $config->save();

        return [
            'success' => true,
            'enabled' => (bool) $config->notify_wa_enabled,
            'message' => $config->notify_wa_enabled
                ? 'Se usará este número para enviar notificaciones.'
                : 'Este número dejó de usarse para notificaciones (sigue conectado).',
        ];
    }
}
