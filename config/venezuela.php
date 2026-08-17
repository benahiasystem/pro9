<?php

return [
    // ########### INICIO CAMBIO LOCALIZACIÓN VENEZUELA
    'country_id' => env('APP_COUNTRY_ID', 'VE'),
    'dial_code' => env('APP_DIAL_CODE', '+58'),

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
    // ########### FIN CAMBIO LOCALIZACIÓN VENEZUELA
];
