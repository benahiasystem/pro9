<?php

namespace Modules\WhatsAppBot\Services;

use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\WahaServer;
use App\Models\Tenant\Configuration;
use Modules\WhatsAppBot\Services\Contracts\WhatsAppProviderClientInterface;
use Modules\WhatsAppBot\Services\Evolution\EvolutionClient;
use Modules\WhatsAppBot\Services\Exceptions\WahaServerUnavailableException;
use Modules\WhatsAppBot\Services\Waha\WahaClient;

/**
 * Resuelve qué cliente de proveedor WhatsApp usar. Reemplaza los
 * `new EvolutionClient()` dispersos en WhatsAppBotController, QrApiController
 * y EvolutionSender por llamadas a esta clase, que sabe leer el proveedor
 * (Evolution o WAHA) congelado por canal en Tenant\Configuration, o el
 * default de instalación en System\Configuration para conexiones nuevas.
 */
class WhatsAppProviderFactory
{
    /**
     * Resuelve el cliente para una conexión YA EXISTENTE de un canal
     * ('bot' o 'qr_api') de un tenant, leyendo su proveedor congelado.
     */
    public static function forChannel(Configuration $config, string $channel): WhatsAppProviderClientInterface
    {
        if ($channel === 'bot') {
            return self::forProviderAndKey($config->evolution_provider ?: 'evolution', $config->evolution_waha_server_key);
        }

        return self::forProviderAndKey($config->qr_api_provider ?: 'evolution', $config->qr_api_waha_server_key);
    }

    /**
     * Resuelve un cliente a partir de un proveedor + server_key explícitos
     * (usado también por effectiveConnection() cuando bot/qrapi comparten
     * instancia, para no depender de las columnas propias del canal que
     * está tomando prestada la conexión del otro).
     */
    public static function forProviderAndKey(string $provider, ?string $serverKey): WhatsAppProviderClientInterface
    {
        if ($provider !== 'waha') {
            return new EvolutionClient();
        }

        $server = self::resolveServer($serverKey);

        return new WahaClient($server->url, $server->api_key, $server->engine);
    }

    /**
     * Resuelve el proveedor/cliente a usar para una conexión NUEVA
     * (connect()), en base al default configurado por el superadmin.
     * Devuelve también el provider/server_key para que el controller los
     * persista junto con el nombre de instancia recién creada.
     *
     * @return array{provider: string, server_key: ?string, client: WhatsAppProviderClientInterface}
     */
    public static function forNewConnection(): array
    {
        $systemConfig = SystemConfiguration::first();
        $provider = $systemConfig?->whatsapp_provider ?: 'evolution';

        if ($provider !== 'waha') {
            return ['provider' => 'evolution', 'server_key' => null, 'client' => new EvolutionClient()];
        }

        $server = WahaServer::where('active', true)->where('is_default', true)->first();

        if (!$server) {
            throw new WahaServerUnavailableException(
                'El proveedor por defecto es WAHA pero no hay ningún servidor WAHA marcado como predeterminado. '
                . 'Un superadministrador debe configurarlo en Configuraciones → Servidores WAHA.'
            );
        }

        return [
            'provider' => 'waha',
            'server_key' => $server->key,
            'client' => new WahaClient($server->url, $server->api_key, $server->engine),
        ];
    }

    private static function resolveServer(?string $serverKey): WahaServer
    {
        $server = $serverKey ? WahaServer::where('key', $serverKey)->first() : null;

        if (!$server) {
            throw new WahaServerUnavailableException(
                "El servidor WAHA '{$serverKey}' ya no está registrado. Un superadministrador debe reasignar "
                . "este canal a otro servidor WAHA (ver \"Cambiar de servidor\" en esta pantalla) o contactar soporte."
            );
        }

        return $server;
    }
}
