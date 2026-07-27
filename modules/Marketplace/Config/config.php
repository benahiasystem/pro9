<?php

return [
    'name' => 'Marketplace',

    /*
    |--------------------------------------------------------------------------
    | Solo configuración env-level.
    |--------------------------------------------------------------------------
    | Todo lo demás (título, textos, límites, disponibilidad) vive en la tabla
    | marketplace_settings y se lee con Modules\Marketplace\Services\Settings.
    */

    /*
    | Interruptor MAESTRO del módulo, a nivel de .env. Es distinto de la clave
    | `is_enabled` de marketplace_settings (esa la maneja el admin y solo apaga
    | el marketplace para el público/app).
    |
    | Con este en false el módulo entero queda inactivo: no se registra ninguna
    | ruta (admin/api/web → 404) y el enlace del sidebar del sistema se oculta.
    | Default false: nace apagado; hay que encenderlo a propósito en el .env.
    */
    'enabled' => env('ENABLE_MARKETPLACE', false),

    'disk' => env('MARKETPLACE_DISK', 'public'),

    'route_prefix' => env('MARKETPLACE_PREFIX', 'marketplace'),
];
