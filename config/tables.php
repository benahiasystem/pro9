<?php

return [
    'system' => [
        'state_types' => [
            '01' => 'Registrado',
            '03' => 'Enviado',
            '05' => 'Aceptado',
            '07' => 'Observado',
            '09' => 'Rechazado',
            '11' => 'Anulado',
            '13' => 'Anulando',// 'Anulación registrada',
            '15' => 'Anulando',// 'Anulación enviada',
        ],
        'soap_sends' => [
            '01' => 'Sunat',
            '02' => 'Ose',
            '04' => 'OseSendfact',
        ],
        'soap_types' => [
            '01' => 'Demo',
            '02' => 'Producción',
        ],
        'groups' => [
            '01' => 'F',
            '02' => 'B',
        ],
        'printing_formats' => [
            'a4' => 'A4',
            'ticket' => 'Ticket'
        ]
    ],
    'tenant' => [
        'document_types' => [
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            '01' => 'Factura de venta',
            '07' => 'Nota de crédito',
            '08' => 'Nota de débito',
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
        ]
    ],
];
