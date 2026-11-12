<?php

return [
    'url'     => env('CENTRIFUGO_URL', 'http://centrifugo:8000'),
    'api_key' => env('CENTRIFUGO_API_KEY', ''),
    'ws_url' => env('CENTRIFUGO_WS_URL'),
    'token_hmac_secret_key' => env('CENTRIFUGO_TOKEN_HMAC_SECRET_KEY', ''),
    'token_ttl' => 300,
];
