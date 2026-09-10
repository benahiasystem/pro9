<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS
    |--------------------------------------------------------------------------
    |
    | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
    | to accept any value.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        // PDFs de impresión consumidos por vendeya. El patrón sin prefijo es el
        // que matchea la ruta real (print/document/...): con Str::is, '**/...'
        // exige un segmento previo y nunca aplicaba.
        'print/*',
        '*/print/*',
        // Config runtime de las apps (mozo/vendeya) consumida desde otros
        // orígenes: dev de Vite y el escritorio Electron (origen app://).
        'config.json',
        'mozo/runtime-config',
        'vendeya/config.json',
        'vendeya/runtime-config',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,


];
