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
     * Obtiene el cliente actual del tenant
     *
     * @return \App\Models\System\Client|null
     */
    public static function getCurrentClient()
    {
        try {
            $current_url = request()->getHost();
            $app_url = config('tenant.app_url_base');

            // Si es admin/system, no hay cliente
            if ($current_url === $app_url) {
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
            $client = self::getCurrentClient();

            if (!$client) {
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