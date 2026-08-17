<?php

return [
    // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
    'country_id' => env('APP_COUNTRY_ID', 'VE'),
    'dial_code' => env('APP_DIAL_CODE', '+58'),

    'currency' => [
        'id' => env('APP_CURRENCY_ID', 'VES'),
        'symbol' => env('APP_CURRENCY_SYMBOL', 'Bs.'),
        'description' => env('APP_CURRENCY_DESCRIPTION', 'Bolívares'),
        'secondary_id' => env('APP_SECONDARY_CURRENCY_ID', 'USD'),
    ],

    'locations' => [
        'labels' => [
            'department' => 'Estado',
            'province' => 'Municipio',
            'district' => 'Parroquia',
        ],
        'default' => [
            'department_id' => '14',
            'province_id' => '0229',
            'district_id' => '000619',
        ],
    ],
    // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
];
