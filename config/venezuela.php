<?php

return [
    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    'country_id' => env('APP_COUNTRY_ID', 'VE'),

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
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
];
