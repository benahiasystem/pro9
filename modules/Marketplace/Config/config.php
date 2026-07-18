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

    'disk' => env('MARKETPLACE_DISK', 'public'),

    'route_prefix' => env('MARKETPLACE_PREFIX', 'marketplace'),
];
