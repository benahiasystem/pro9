<?php

namespace Modules\ExtraServices\Helpers;

use Modules\ExtraServices\Models\ExtraServices;
use Modules\ExtraServices\Models\ClientUsageApidocs;

class ApidocsHelper
{
    /**
     * Verifica si apidocs está activo y disponible
     *
     * @return bool
     */
    public static function isActive(): bool
    {
        try {
            $extraService = ExtraServices::first();
            return $extraService ? (bool) $extraService->isActiveApidocs : false;
        } catch (\Exception $e) {
            \Log::error('Error al verificar el estado de apidocs: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si el servicio de consultas está activo y disponible
     *
     * @return bool
     */
    public static function canUseApidocs() : bool
    {
        return self::isActive();
    }

    /**
     * Host del panel del sistema (admin / reseller), considerando PREFIX_URL.
     *
     * Mismo criterio que usan las rutas del modulo y ApidocsService: si hay
     * prefijo el panel vive en {prefijo}.{APP_URL_BASE}.
     *
     * @return string
     */
    public static function getSystemHost(): string
    {
        $prefix = env('PREFIX_URL', null);
        $base = (string) config('tenant.app_url_base');

        return !empty($prefix) ? $prefix . '.' . $base : $base;
    }

    /**
     * Indica si la peticion viene del panel del admin / reseller y no de un tenant.
     *
     * @return bool
     */
    public static function isSystemRequest(): bool
    {
        $host = request()->getHost();

        // Se aceptan ambas formas: con prefijo y el dominio base pelado, para
        // no depender de como este configurado PREFIX_URL en cada instalacion.
        return $host === self::getSystemHost() || $host === (string) config('tenant.app_url_base');
    }

    /**
     * Obtiene el cliente actual del tenant
     *
     * @return \App\Models\System\Client|null
     */
    public static function getCurrentClient()
    {
        try {
            $current_url = request()->getHost();

            // Si es admin/system, no hay cliente
            if (self::isSystemRequest()) {
                return null;
            }

            $hostname = \Hyn\Tenancy\Models\Hostname::where('fqdn', $current_url)->first();
            if (!$hostname) {
                return null;
            }

            return \App\Models\System\Client::where('hostname_id', $hostname->id)->first();

        } catch (\Exception $e) {
            \Log::error('Error obteniendo cliente actual', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function incrementUsage()
    {
        try {
            // Consulta hecha desde el panel del admin / reseller: no hay cliente,
            // pero igual consume cuota del reseller, asi que se registra con
            // client_id null en lugar de descartarse.
            if (self::isSystemRequest()) {
                $usage = ClientUsageApidocs::incrementUsage(null);

                \Log::info('Contador apidocs incrementado (sistema)', [
                    'client_id' => null,
                    'month' => $usage->month,
                    'new_quantity' => $usage->quantity
                ]);

                return;
            }

            $client = self::getCurrentClient();

            if (!$client) {
                // Tenant sin cliente asociado: no se puede atribuir el consumo
                // a nadie y tampoco es del sistema, asi que no se registra.
                \Log::warning('Consulta apidocs sin cliente identificable, no se registra', [
                    'host' => request()->getHost(),
                ]);

                return;
            }

            $usage = ClientUsageApidocs::incrementUsage($client->id);

            \Log::info('Contador apidocs incrementado', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'month' => $usage->month,
                'new_quantity' => $usage->quantity
            ]);

        } catch (\Exception $e) {
            \Log::error('Error incrementando contador apidocs', ['error' => $e->getMessage()]);
        }
    }
}