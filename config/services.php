<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    // ######## INICIO API BCV ########
    'bcv' => [
        'url' => env('BCV_API_URL', 'http://api-bcv:3000'),
        'token' => env('BCV_API_TOKEN'),
        'timeout' => (int) env('BCV_API_TIMEOUT', 15),
        'connect_timeout' => (int) env('BCV_API_CONNECT_TIMEOUT', 5),
    ],
    // ######## FIN API BCV ########

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    // ########## INICIO CAMBIO RIF SUPER ADMIN
    'super_admin_rif_lookup' => [
        'enabled' => env('SUPER_ADMIN_RIF_LOOKUP_ENABLED', false),
        'url' => env('SUPER_ADMIN_RIF_LOOKUP_URL', ''),
        'token' => env('SUPER_ADMIN_RIF_LOOKUP_TOKEN', ''),
        'timeout' => env('SUPER_ADMIN_RIF_LOOKUP_TIMEOUT', 10),
    ],
    // ######### FIN CAMBIO RIF SUPER ADMIN

];
