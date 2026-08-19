<?php

return [
    // ########### INICIO CONTRATO LOCALIZACIÓN VENEZUELA
    'country_id' => env('APP_COUNTRY_ID', 'VE'),
    'dial_code' => env('APP_DIAL_CODE', '+58'),

    'currency' => [
        'id' => env('APP_CURRENCY_ID', 'VES'),
        'symbol' => env('APP_CURRENCY_SYMBOL', 'Bs.'),
        'description' => env('APP_CURRENCY_DESCRIPTION', 'Bolívares'),
        'secondary_id' => env('APP_SECONDARY_CURRENCY_ID', 'USD'),
    ],

    // ########## INICIO CAMBIO AFECTACIÓN IVA
    'tax' => [
        'name' => 'IVA',
        'rate' => (float) env('APP_IVA_RATE', 0.16),
        'selectable_affectation_ids' => ['10', '20'],
    ],
    // ######### FIN CAMBIO AFECTACIÓN IVA

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
    // ########### FIN CONTRATO LOCALIZACIÓN VENEZUELA
];
