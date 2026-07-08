<?php

namespace Modules\ExtraServices\Helpers;

use Modules\ExtraServices\Models\ExtraServices;

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
}