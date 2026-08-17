<?php

/**
 * Datos iniciales consolidados desde las migraciones tenant históricas.
 * Generado de forma determinista; no editar manualmente.
 */

return array (
  'source' => 'migraciones tenant históricas',
  'tables' => 
  array (
    'app_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'show_image_item' => 1,
          'print_format_pdf' => 'ticket',
          'card_color' => 'multicolored',
          'theme_color' => 'blue',
          'header_waves' => 0,
          'direct_send_documents_whatsapp' => 0,
          'primary_color' => '#020F3C',
          'direct_print' => 0,
          'app_mode' => 'default',
          'created_at' => '2026-08-17 14:14:53',
          'updated_at' => '2026-08-17 14:14:53',
        ),
      ),
    ),
    'app_modules' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'value' => 'invoice',
          'description' => 'Factura electrónica',
          'order_menu' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'value' => 'invoice-ticket',
          'description' => 'Boleta electrónica',
          'order_menu' => 2,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'value' => 'sale-note',
          'description' => 'Nota de venta',
          'order_menu' => 3,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'value' => 'order-note',
          'description' => 'Pedido',
          'order_menu' => 4,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        4 => 
        array (
          'id' => 5,
          'value' => 'purchase',
          'description' => 'Compra',
          'order_menu' => 5,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        5 => 
        array (
          'id' => 6,
          'value' => 'documents',
          'description' => 'Lista de comprobantes',
          'order_menu' => 6,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        6 => 
        array (
          'id' => 7,
          'value' => 'report-sales',
          'description' => 'Reportes',
          'order_menu' => 7,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        7 => 
        array (
          'id' => 8,
          'value' => 'validate-document',
          'description' => 'Validar cpe',
          'order_menu' => 8,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        8 => 
        array (
          'id' => 9,
          'value' => 'customers',
          'description' => 'Clientes',
          'order_menu' => 9,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        9 => 
        array (
          'id' => 10,
          'value' => 'items',
          'description' => 'Productos',
          'order_menu' => 10,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        10 => 
        array (
          'id' => 11,
          'value' => 'cash',
          'description' => 'Caja',
          'order_menu' => 11,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        11 => 
        array (
          'id' => 12,
          'value' => 'configuration',
          'description' => 'Configuración',
          'order_menu' => 12,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        12 => 
        array (
          'id' => 13,
          'value' => 'quotation',
          'description' => 'Cotización',
          'order_menu' => 13,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        13 => 
        array (
          'id' => 14,
          'value' => 'dispatches',
          'description' => 'G.R. Remitente',
          'order_menu' => 14,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        14 => 
        array (
          'id' => 15,
          'value' => 'carrier_dispatches',
          'description' => 'G.R. Transportista',
          'order_menu' => 15,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'bank_loan_reasons' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Varios',
        ),
      ),
    ),
    'bank_loan_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Prestamo',
          'created_at' => '2026-08-17 14:14:44',
          'updated_at' => '2026-08-17 14:14:44',
        ),
      ),
    ),
    'banks' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'BANCO SCOTIABANK',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'BANCO DE CREDITO DEL PERU',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'BANCO DE COMERCIO',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        3 => 
        array (
          'id' => 4,
          'description' => 'BANCO PICHINCHA',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        4 => 
        array (
          'id' => 5,
          'description' => 'BBVA CONTINENTAL',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        5 => 
        array (
          'id' => 6,
          'description' => 'INTERBANK',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
      ),
    ),
    'business_turns' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'value' => 'hotel',
          'name' => 'Hoteles',
          'active' => 0,
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
        ),
        1 => 
        array (
          'id' => 2,
          'value' => 'transport',
          'name' => 'Empresa de transporte de pasajeros',
          'active' => 0,
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
        ),
        2 => 
        array (
          'id' => 3,
          'value' => 'restaurant',
          'name' => 'Restaurantes',
          'active' => 0,
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
        ),
        3 => 
        array (
          'id' => 4,
          'value' => 'tap',
          'name' => 'Grifos',
          'active' => 0,
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
        ),
        4 => 
        array (
          'id' => 5,
          'value' => 'pharmacy',
          'name' => 'Farmacia',
          'active' => 0,
          'created_at' => '2026-08-17 14:15:21',
          'updated_at' => '2026-08-17 14:15:21',
        ),
      ),
    ),
    'card_brands' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Visa',
          'active' => 1,
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Mastercard',
          'active' => 1,
        ),
      ),
    ),
    'cat_accounting_ledger_code_account' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'code_account' => '1',
          'name' => 'Activos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'code_account' => '1.1',
          'name' => 'Activos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'code_account' => '1.1.1',
          'name' => 'Efectivo y equivalentes de efectivo',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'code_account' => '1.1.1.1',
          'name' => 'Caja',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        4 => 
        array (
          'id' => 5,
          'code_account' => '1.1.1.2',
          'name' => 'Bancos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        5 => 
        array (
          'id' => 6,
          'code_account' => '1.1.2',
          'name' => 'Deudores comerciales y otras cuentas por cobrar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        6 => 
        array (
          'id' => 7,
          'code_account' => '1.1.2.0',
          'name' => 'Activos por impuestos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        7 => 
        array (
          'id' => 8,
          'code_account' => '1.1.2.1',
          'name' => 'Otras cuentas por cobrar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        8 => 
        array (
          'id' => 9,
          'code_account' => '1.1.3',
          'name' => 'Inventarios',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        9 => 
        array (
          'id' => 10,
          'code_account' => '1.1.4',
          'name' => 'Inversiones a corto plazo',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        10 => 
        array (
          'id' => 11,
          'code_account' => '1.1.5',
          'name' => 'Otros activos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        11 => 
        array (
          'id' => 12,
          'code_account' => '1.2',
          'name' => 'Activos no corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        12 => 
        array (
          'id' => 13,
          'code_account' => '1.2.1',
          'name' => 'Propiedad, planta y equipo (Activos fijos)',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        13 => 
        array (
          'id' => 14,
          'code_account' => '1.2.2',
          'name' => 'Otros Activos no corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        14 => 
        array (
          'id' => 15,
          'code_account' => '2',
          'name' => 'Pasivos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        15 => 
        array (
          'id' => 16,
          'code_account' => '2.1',
          'name' => 'Pasivos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        16 => 
        array (
          'id' => 17,
          'code_account' => '2.1.1',
          'name' => 'Cuentas por pagar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        17 => 
        array (
          'id' => 18,
          'code_account' => '2.1.1.1',
          'name' => 'Otras cuentas por pagar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        18 => 
        array (
          'id' => 19,
          'code_account' => '2.1.2',
          'name' => 'Provisiones',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        19 => 
        array (
          'id' => 20,
          'code_account' => '2.1.3',
          'name' => 'Obligaciones laborales y de seguridad social',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        20 => 
        array (
          'id' => 21,
          'code_account' => '2.1.4',
          'name' => 'Pasivos por impuestos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        21 => 
        array (
          'id' => 22,
          'code_account' => '2.1.4.1',
          'name' => 'Impuestos por pagar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        22 => 
        array (
          'id' => 23,
          'code_account' => '2.1.4.2',
          'name' => 'Retenciones por pagar',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        23 => 
        array (
          'id' => 24,
          'code_account' => '2.1.5',
          'name' => 'Cuentas por pagar con costo financiero',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        24 => 
        array (
          'id' => 25,
          'code_account' => '2.1.6',
          'name' => 'Obligaciones financieras',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        25 => 
        array (
          'id' => 26,
          'code_account' => '2.1.6.1',
          'name' => 'Tarjetas de crédito',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        26 => 
        array (
          'id' => 27,
          'code_account' => '2.1.7',
          'name' => 'Otros pasivos corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        27 => 
        array (
          'id' => 28,
          'code_account' => '2.2',
          'name' => 'Pasivos no corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        28 => 
        array (
          'id' => 29,
          'code_account' => '2.2.1',
          'name' => 'Préstamos a largo plazo',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        29 => 
        array (
          'id' => 30,
          'code_account' => '2.2.2',
          'name' => 'Otros pasivos no corrientes',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        30 => 
        array (
          'id' => 31,
          'code_account' => '3',
          'name' => 'Patrimonio',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        31 => 
        array (
          'id' => 32,
          'code_account' => '3.1',
          'name' => 'Capital social',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        32 => 
        array (
          'id' => 33,
          'code_account' => '3.2',
          'name' => 'Ganancias acumuladas',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        33 => 
        array (
          'id' => 34,
          'code_account' => '3.3',
          'name' => 'Ajustes por saldos iniciales',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        34 => 
        array (
          'id' => 35,
          'code_account' => '3.3.1',
          'name' => 'Ajustes iniciales en bancos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        35 => 
        array (
          'id' => 36,
          'code_account' => '3.3.2',
          'name' => 'Ajustes iniciales en inventario',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        36 => 
        array (
          'id' => 37,
          'code_account' => '4',
          'name' => 'Ingresos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        37 => 
        array (
          'id' => 38,
          'code_account' => '4.1',
          'name' => 'Ingresos de actividades ordinarias',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        38 => 
        array (
          'id' => 39,
          'code_account' => '4.2',
          'name' => 'Otros Ingresos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        39 => 
        array (
          'id' => 40,
          'code_account' => '4.2.1',
          'name' => 'Otros ingresos diversos',
          'disabled' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'cat_address_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Punto de venta',
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Producción',
        ),
        2 => 
        array (
          'id' => '03',
          'description' => 'Extracción',
        ),
        3 => 
        array (
          'id' => '04',
          'description' => 'Explotación',
        ),
        4 => 
        array (
          'id' => '05',
          'description' => 'Otros',
        ),
      ),
    ),
    'cat_affectation_igv_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '10',
          'active' => 1,
          'exportation' => 0,
          'free' => 0,
          'description' => 'Gravado - Operación Onerosa',
        ),
        1 => 
        array (
          'id' => '11',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Retiro por premio',
        ),
        2 => 
        array (
          'id' => '12',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Retiro por donación',
        ),
        3 => 
        array (
          'id' => '13',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Retiro',
        ),
        4 => 
        array (
          'id' => '14',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Retiro por publicidad',
        ),
        5 => 
        array (
          'id' => '15',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Bonificaciones',
        ),
        6 => 
        array (
          'id' => '16',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – Retiro por entrega a trabajadores',
        ),
        7 => 
        array (
          'id' => '17',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Gravado – IVAP',
        ),
        8 => 
        array (
          'id' => '20',
          'active' => 0,
          'exportation' => 0,
          'free' => 0,
          'description' => 'Exonerado - Operación Onerosa',
        ),
        9 => 
        array (
          'id' => '21',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Exonerado – Transferencia Gratuita',
        ),
        10 => 
        array (
          'id' => '30',
          'active' => 1,
          'exportation' => 0,
          'free' => 0,
          'description' => 'Inafecto - Operación Onerosa',
        ),
        11 => 
        array (
          'id' => '31',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto – Retiro por Bonificación',
        ),
        12 => 
        array (
          'id' => '32',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto – Retiro',
        ),
        13 => 
        array (
          'id' => '33',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto – Retiro por Muestras Médicas',
        ),
        14 => 
        array (
          'id' => '34',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto - Retiro por Convenio Colectivo',
        ),
        15 => 
        array (
          'id' => '35',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto – Retiro por premio',
        ),
        16 => 
        array (
          'id' => '36',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto - Retiro por publicidad',
        ),
        17 => 
        array (
          'id' => '37',
          'active' => 0,
          'exportation' => 0,
          'free' => 1,
          'description' => 'Inafecto - Transferencia gratuita',
        ),
        18 => 
        array (
          'id' => '40',
          'active' => 0,
          'exportation' => 1,
          'free' => 0,
          'description' => 'Exportación de bienes o servicios',
        ),
      ),
    ),
    'cat_attribute_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '3001',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Matrícula de la embarcación',
        ),
        1 => 
        array (
          'id' => '3002',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Nombre de la embarcación',
        ),
        2 => 
        array (
          'id' => '3003',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Tipo de especie vendida',
        ),
        3 => 
        array (
          'id' => '3004',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Lugar de descarga',
        ),
        4 => 
        array (
          'id' => '3005',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Fecha de descarga',
        ),
        5 => 
        array (
          'id' => '3006',
          'active' => 0,
          'description' => 'Detracciones: Recursos Hidrobiológicos-Cantidad de especie vendida',
        ),
        6 => 
        array (
          'id' => '3050',
          'active' => 0,
          'description' => 'Transportre Terreste - Número de asiento',
        ),
        7 => 
        array (
          'id' => '3051',
          'active' => 0,
          'description' => 'Transporte Terrestre - Información de manifiesto de pasajeros',
        ),
        8 => 
        array (
          'id' => '3052',
          'active' => 0,
          'description' => 'Transporte Terrestre - Número de documento de identidad del pasajero',
        ),
        9 => 
        array (
          'id' => '3053',
          'active' => 0,
          'description' => 'Transporte Terrestre - Tipo de documento de identidad del pasajero',
        ),
        10 => 
        array (
          'id' => '3054',
          'active' => 0,
          'description' => 'Transporte Terrestre - Nombres y apellidos del pasajero',
        ),
        11 => 
        array (
          'id' => '3055',
          'active' => 0,
          'description' => 'Transporte Terrestre - Ciudad o lugar de destino - Ubigeo',
        ),
        12 => 
        array (
          'id' => '3056',
          'active' => 0,
          'description' => 'Transporte Terrestre - Ciudad o lugar de destino - Dirección detallada',
        ),
        13 => 
        array (
          'id' => '3057',
          'active' => 0,
          'description' => 'Transporte Terrestre - Ciudad o lugar de origen - Ubigeo',
        ),
        14 => 
        array (
          'id' => '3058',
          'active' => 0,
          'description' => 'Transporte Terrestre - Ciudad o lugar de origen - Dirección detallada',
        ),
        15 => 
        array (
          'id' => '3059',
          'active' => 0,
          'description' => 'Transporte Terrestre - Fecha de inicio programado',
        ),
        16 => 
        array (
          'id' => '3060',
          'active' => 0,
          'description' => 'Transporte Terrestre - Hora de inicio programado',
        ),
        17 => 
        array (
          'id' => '4000',
          'active' => 0,
          'description' => 'Beneficio Hospedajes-Paquete turístico: Código de país de emisión del pasaporte',
        ),
        18 => 
        array (
          'id' => '4001',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Código de país de residencia del sujeto no domiciliado',
        ),
        19 => 
        array (
          'id' => '4002',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Fecha de ingreso al país',
        ),
        20 => 
        array (
          'id' => '4003',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Fecha de Ingreso al Establecimiento',
        ),
        21 => 
        array (
          'id' => '4004',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Fecha de Salida del Establecimiento',
        ),
        22 => 
        array (
          'id' => '4005',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Número de Días de Permanencia',
        ),
        23 => 
        array (
          'id' => '4006',
          'active' => 0,
          'description' => 'Beneficio Hospedajes: Fecha de Consumo',
        ),
        24 => 
        array (
          'id' => '4007',
          'active' => 0,
          'description' => 'Beneficio Hospedajes-Paquete turístico: Nombres y apellidos del huesped',
        ),
        25 => 
        array (
          'id' => '4008',
          'active' => 0,
          'description' => 'Beneficio Hospedajes-Paquete turístico: Tipo de documento de identidad del huesped',
        ),
        26 => 
        array (
          'id' => '4009',
          'active' => 0,
          'description' => 'Beneficio Hospedajes-Paquete turístico: Número de documento de identidad del huesped',
        ),
        27 => 
        array (
          'id' => '4030',
          'active' => 0,
          'description' => 'Carta Porte Aéreo:  Lugar de origen - Código de ubigeo',
        ),
        28 => 
        array (
          'id' => '4031',
          'active' => 0,
          'description' => 'Carta Porte Aéreo:  Lugar de origen - Dirección detallada',
        ),
        29 => 
        array (
          'id' => '4032',
          'active' => 0,
          'description' => 'Carta Porte Aéreo:  Lugar de destino - Código de ubigeo',
        ),
        30 => 
        array (
          'id' => '4033',
          'active' => 0,
          'description' => 'Carta Porte Aéreo:  Lugar de destino - Dirección detallada',
        ),
        31 => 
        array (
          'id' => '4040',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Pasajero - Apellidos y Nombres',
        ),
        32 => 
        array (
          'id' => '4041',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Pasajero - Tipo de documento de identidad',
        ),
        33 => 
        array (
          'id' => '4042',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Ciudad o lugar de origen - Código de ubigeo',
        ),
        34 => 
        array (
          'id' => '4043',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Ciudad o lugar de origen - Dirección detallada',
        ),
        35 => 
        array (
          'id' => '4044',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Ciudad o lugar de destino - Código de ubigeo',
        ),
        36 => 
        array (
          'id' => '4045',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Ciudad o lugar de destino - Dirección detallada',
        ),
        37 => 
        array (
          'id' => '4046',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte:Número de asiento',
        ),
        38 => 
        array (
          'id' => '4047',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Hora programada de inicio de viaje',
        ),
        39 => 
        array (
          'id' => '4048',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Servicio transporte: Fecha programada de inicio de viaje',
        ),
        40 => 
        array (
          'id' => '4049',
          'active' => 0,
          'description' => 'BVME transporte ferroviario: Pasajero - Número de documento de identidad',
        ),
        41 => 
        array (
          'id' => '4060',
          'active' => 0,
          'description' => 'Regalía Petrolera: Decreto Supremo de aprobación del contrato',
        ),
        42 => 
        array (
          'id' => '4061',
          'active' => 0,
          'description' => 'Regalía Petrolera: Area de contrato (Lote)',
        ),
        43 => 
        array (
          'id' => '4062',
          'active' => 0,
          'description' => 'Regalía Petrolera: Periodo de pago - Fecha de inicio',
        ),
        44 => 
        array (
          'id' => '4063',
          'active' => 0,
          'description' => 'Regalía Petrolera: Periodo de pago - Fecha de fin',
        ),
        45 => 
        array (
          'id' => '4064',
          'active' => 0,
          'description' => 'Regalía Petrolera: Fecha de Pago',
        ),
        46 => 
        array (
          'id' => '5000',
          'active' => 0,
          'description' => 'Proveedores Estado: Número de Expediente',
        ),
        47 => 
        array (
          'id' => '5001',
          'active' => 0,
          'description' => 'Proveedores Estado: Código de Unidad Ejecutora',
        ),
        48 => 
        array (
          'id' => '5002',
          'active' => 0,
          'description' => 'Proveedores Estado: N° de Proceso de Selección',
        ),
        49 => 
        array (
          'id' => '5003',
          'active' => 0,
          'description' => 'Proveedores Estado: N° de Contrato',
        ),
        50 => 
        array (
          'id' => '5010',
          'active' => 1,
          'description' => 'Numero de Placa',
        ),
        51 => 
        array (
          'id' => '5011',
          'active' => 1,
          'description' => 'Categoria',
        ),
        52 => 
        array (
          'id' => '5012',
          'active' => 1,
          'description' => 'Marca',
        ),
        53 => 
        array (
          'id' => '5013',
          'active' => 1,
          'description' => 'Modelo',
        ),
        54 => 
        array (
          'id' => '5014',
          'active' => 1,
          'description' => 'Color',
        ),
        55 => 
        array (
          'id' => '5015',
          'active' => 1,
          'description' => 'Motor',
        ),
        56 => 
        array (
          'id' => '5016',
          'active' => 1,
          'description' => 'Combustible',
        ),
        57 => 
        array (
          'id' => '5017',
          'active' => 1,
          'description' => 'Form. Rodante',
        ),
        58 => 
        array (
          'id' => '5018',
          'active' => 1,
          'description' => 'VIN',
        ),
        59 => 
        array (
          'id' => '5019',
          'active' => 1,
          'description' => 'Serie/Chasis',
        ),
        60 => 
        array (
          'id' => '5020',
          'active' => 1,
          'description' => 'Año fabricacion',
        ),
        61 => 
        array (
          'id' => '5021',
          'active' => 1,
          'description' => 'Año modelo',
        ),
        62 => 
        array (
          'id' => '5022',
          'active' => 1,
          'description' => 'Version',
        ),
        63 => 
        array (
          'id' => '5023',
          'active' => 1,
          'description' => 'Ejes',
        ),
        64 => 
        array (
          'id' => '5024',
          'active' => 1,
          'description' => 'Asientos',
        ),
        65 => 
        array (
          'id' => '5025',
          'active' => 1,
          'description' => 'Pasajeros',
        ),
        66 => 
        array (
          'id' => '5026',
          'active' => 1,
          'description' => 'Ruedas',
        ),
        67 => 
        array (
          'id' => '5027',
          'active' => 1,
          'description' => 'Carroceria',
        ),
        68 => 
        array (
          'id' => '5028',
          'active' => 1,
          'description' => 'Potencia',
        ),
        69 => 
        array (
          'id' => '5029',
          'active' => 1,
          'description' => 'Cilindros',
        ),
        70 => 
        array (
          'id' => '5030',
          'active' => 1,
          'description' => 'Ciliindrada',
        ),
        71 => 
        array (
          'id' => '5031',
          'active' => 1,
          'description' => 'Peso Bruto',
        ),
        72 => 
        array (
          'id' => '5032',
          'active' => 1,
          'description' => 'Peso Neto',
        ),
        73 => 
        array (
          'id' => '5033',
          'active' => 1,
          'description' => 'Carga Util',
        ),
        74 => 
        array (
          'id' => '5034',
          'active' => 1,
          'description' => 'Longitud',
        ),
        75 => 
        array (
          'id' => '5035',
          'active' => 1,
          'description' => 'Altura',
        ),
        76 => 
        array (
          'id' => '5036',
          'active' => 1,
          'description' => 'Ancho',
        ),
        77 => 
        array (
          'id' => '6000',
          'active' => 0,
          'description' => 'Comercialización de Oro:  Código Unico Concesión Minera',
        ),
        78 => 
        array (
          'id' => '6001',
          'active' => 0,
          'description' => 'Comercialización de Oro:  N° declaración compromiso',
        ),
        79 => 
        array (
          'id' => '6002',
          'active' => 0,
          'description' => 'Comercialización de Oro:  N° Reg. Especial .Comerci. Oro',
        ),
        80 => 
        array (
          'id' => '6003',
          'active' => 0,
          'description' => 'Comercialización de Oro:  N° Resolución que autoriza Planta de Beneficio',
        ),
        81 => 
        array (
          'id' => '6004',
          'active' => 0,
          'description' => 'Comercialización de Oro: Ley Mineral (% concent. oro)',
        ),
        82 => 
        array (
          'id' => '7000',
          'active' => 0,
          'description' => 'Gastos Art. 37 Renta:  Número de Placa',
        ),
        83 => 
        array (
          'id' => '7001',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Tipo de préstamo',
        ),
        84 => 
        array (
          'id' => '7002',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Indicador de Primera Vivienda',
        ),
        85 => 
        array (
          'id' => '7003',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Partida Registral',
        ),
        86 => 
        array (
          'id' => '7004',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Número de contrato',
        ),
        87 => 
        array (
          'id' => '7005',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Fecha de otorgamiento del crédito',
        ),
        88 => 
        array (
          'id' => '7006',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Código de ubigeo',
        ),
        89 => 
        array (
          'id' => '7007',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Dirección completa',
        ),
        90 => 
        array (
          'id' => '7008',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Urbanización',
        ),
        91 => 
        array (
          'id' => '7009',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Provincia',
        ),
        92 => 
        array (
          'id' => '7010',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Distrito',
        ),
        93 => 
        array (
          'id' => '7011',
          'active' => 0,
          'description' => 'Créditos Hipotecarios: Dirección del predio - Departamento',
        ),
        94 => 
        array (
          'id' => '7020',
          'active' => 0,
          'description' => 'Partida Arancelaria',
        ),
      ),
    ),
    'cat_charge_discount_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '00',
          'active' => 1,
          'base' => 1,
          'level' => 'item',
          'type' => 'discount',
          'description' => 'Descuentos que afectan la base imponible del IGV/IVAP',
        ),
        1 => 
        array (
          'id' => '01',
          'active' => 1,
          'base' => 0,
          'level' => 'item',
          'type' => 'discount',
          'description' => 'Descuentos que no afectan la base imponible del IGV/IVAP',
        ),
        2 => 
        array (
          'id' => '02',
          'active' => 1,
          'base' => 1,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales que afectan la base imponible del IGV/IVAP',
        ),
        3 => 
        array (
          'id' => '03',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales que no afectan la base imponible del IGV/IVAP',
        ),
        4 => 
        array (
          'id' => '04',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales por anticipos gravados que afectan la base imponible del IGV/IVAP',
        ),
        5 => 
        array (
          'id' => '05',
          'active' => 0,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales por anticipos exonerados',
        ),
        6 => 
        array (
          'id' => '06',
          'active' => 0,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales por anticipos inafectos',
        ),
        7 => 
        array (
          'id' => '45',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'FISE',
        ),
        8 => 
        array (
          'id' => '46',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Recargo al consumo y/o propinas',
        ),
        9 => 
        array (
          'id' => '47',
          'active' => 1,
          'base' => 1,
          'level' => 'item',
          'type' => 'charge',
          'description' => 'Cargos que afectan la base imponible del IGV/IVAP',
        ),
        10 => 
        array (
          'id' => '48',
          'active' => 1,
          'base' => 0,
          'level' => 'item',
          'type' => 'charge',
          'description' => 'Cargos que no afectan la base imponible del IGV/IVAP',
        ),
        11 => 
        array (
          'id' => '49',
          'active' => 1,
          'base' => 1,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Cargos globales que afectan la base imponible del IGV/IVAP',
        ),
        12 => 
        array (
          'id' => '50',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Cargos globales que no afectan la base imponible del IGV/IVAP',
        ),
        13 => 
        array (
          'id' => '51',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Percepción venta interna',
        ),
        14 => 
        array (
          'id' => '52',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Percepción a la adquisición de combustible',
        ),
        15 => 
        array (
          'id' => '53',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Percepción realizada al agente de percepción con tasa especial',
        ),
        16 => 
        array (
          'id' => '62',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Retención del IGV',
        ),
      ),
    ),
    'cat_currency_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 'PEN',
          'active' => 1,
          'symbol' => 'S/',
          'description' => 'Soles',
        ),
        1 => 
        array (
          'id' => 'USD',
          'active' => 0,
          'symbol' => '$',
          'description' => 'Dólares Americanos',
        ),
      ),
    ),
    'cat_document_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'short' => 'FT',
          'description' => 'FACTURA ELECTRÓNICA',
          'is_sunat' => 1,
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'short' => NULL,
          'description' => 'RECIBO POR HONORARIOS',
          'is_sunat' => 1,
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'short' => 'BV',
          'description' => 'BOLETA DE VENTA ELECTRÓNICA',
          'is_sunat' => 1,
        ),
        3 => 
        array (
          'id' => '04',
          'active' => 1,
          'short' => NULL,
          'description' => 'LIQUIDACIÓN DE COMPRA',
          'is_sunat' => 1,
        ),
        4 => 
        array (
          'id' => '07',
          'active' => 1,
          'short' => 'NC',
          'description' => 'NOTA DE CRÉDITO',
          'is_sunat' => 1,
        ),
        5 => 
        array (
          'id' => '08',
          'active' => 1,
          'short' => 'ND',
          'description' => 'NOTA DE DÉBITO',
          'is_sunat' => 1,
        ),
        6 => 
        array (
          'id' => '09',
          'active' => 1,
          'short' => NULL,
          'description' => 'GUIA DE REMISIÓN REMITENTE',
          'is_sunat' => 1,
        ),
        7 => 
        array (
          'id' => '14',
          'active' => 1,
          'short' => NULL,
          'description' => 'SERVICIOS PÚBLICOS',
          'is_sunat' => 1,
        ),
        8 => 
        array (
          'id' => '20',
          'active' => 1,
          'short' => NULL,
          'description' => 'COMPROBANTE DE RETENCIÓN ELECTRÓNICA',
          'is_sunat' => 1,
        ),
        9 => 
        array (
          'id' => '31',
          'active' => 1,
          'short' => NULL,
          'description' => 'GUÍA DE REMISIÓN TRANSPORTISTA',
          'is_sunat' => 1,
        ),
        10 => 
        array (
          'id' => '40',
          'active' => 1,
          'short' => NULL,
          'description' => 'COMPROBANTE DE PERCEPCIÓN ELECTRÓNICA',
          'is_sunat' => 1,
        ),
        11 => 
        array (
          'id' => '71',
          'active' => 0,
          'short' => NULL,
          'description' => 'Guia de remisión remitente complementaria',
          'is_sunat' => 1,
        ),
        12 => 
        array (
          'id' => '72',
          'active' => 0,
          'short' => NULL,
          'description' => 'Guia de remisión transportista complementaria',
          'is_sunat' => 1,
        ),
        13 => 
        array (
          'id' => '80',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE VENTA',
          'is_sunat' => 1,
        ),
        14 => 
        array (
          'id' => 'GU75',
          'active' => 1,
          'short' => NULL,
          'description' => 'GUÍA',
          'is_sunat' => 1,
        ),
        15 => 
        array (
          'id' => 'NE76',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE ENTRADA',
          'is_sunat' => 1,
        ),
        16 => 
        array (
          'id' => 'U2',
          'active' => 1,
          'short' => NULL,
          'description' => 'Guía de Ingreso Almacén',
          'is_sunat' => 1,
        ),
        17 => 
        array (
          'id' => 'U3',
          'active' => 1,
          'short' => NULL,
          'description' => 'Guía de Salida Almacén',
          'is_sunat' => 1,
        ),
        18 => 
        array (
          'id' => 'U4',
          'active' => 1,
          'short' => NULL,
          'description' => 'Guía de Transferencia Almacén',
          'is_sunat' => 1,
        ),
      ),
    ),
    'cat_identity_document_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '0',
          'active' => 1,
          'description' => 'Doc.trib.no.dom.sin.ruc',
        ),
        1 => 
        array (
          'id' => '1',
          'active' => 1,
          'description' => 'DNI',
        ),
        2 => 
        array (
          'id' => '4',
          'active' => 1,
          'description' => 'CE',
        ),
        3 => 
        array (
          'id' => '6',
          'active' => 1,
          'description' => 'RUC',
        ),
        4 => 
        array (
          'id' => '7',
          'active' => 1,
          'description' => 'Pasaporte',
        ),
        5 => 
        array (
          'id' => 'A',
          'active' => 0,
          'description' => 'Ced. Diplomática de identidad',
        ),
        6 => 
        array (
          'id' => 'B',
          'active' => 0,
          'description' => 'Documento identidad país residencia-no.d',
        ),
        7 => 
        array (
          'id' => 'C',
          'active' => 0,
          'description' => 'Tax Identification Number - TIN – Doc Trib PP.NN',
        ),
        8 => 
        array (
          'id' => 'D',
          'active' => 0,
          'description' => 'Identification Number - IN – Doc Trib PP. JJ',
        ),
        9 => 
        array (
          'id' => 'E',
          'active' => 0,
          'description' => 'TAM- Tarjeta Andina de Migración',
        ),
      ),
    ),
    'cat_legend_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '1000',
          'active' => 1,
          'description' => 'Monto en Letras',
        ),
        1 => 
        array (
          'id' => '1002',
          'active' => 1,
          'description' => 'TRANSFERENCIA GRATUITA DE UN BIEN Y/O SERVICIO PRESTADO GRATUITAMENTE',
        ),
        2 => 
        array (
          'id' => '2000',
          'active' => 1,
          'description' => 'COMPROBANTE DE PERCEPCIÓN',
        ),
        3 => 
        array (
          'id' => '2001',
          'active' => 1,
          'description' => 'BIENES TRANSFERIDOS EN LA AMAZONÍA REGIÓN SELVA PARA SER CONSUMIDOS EN LA MISMA',
        ),
        4 => 
        array (
          'id' => '2002',
          'active' => 1,
          'description' => 'SERVICIOS PRESTADOS EN LA AMAZONÍA  REGIÓN SELVA PARA SER CONSUMIDOS EN LA MISMA',
        ),
        5 => 
        array (
          'id' => '2003',
          'active' => 1,
          'description' => 'CONTRATOS DE CONSTRUCCIÓN EJECUTADOS  EN LA AMAZONÍA REGIÓN SELVA',
        ),
        6 => 
        array (
          'id' => '2004',
          'active' => 1,
          'description' => 'Agencia de Viaje - Paquete turístico',
        ),
        7 => 
        array (
          'id' => '2005',
          'active' => 1,
          'description' => 'Venta realizada por emisor itinerante',
        ),
        8 => 
        array (
          'id' => '2006',
          'active' => 1,
          'description' => 'Operación sujeta a detracción',
        ),
        9 => 
        array (
          'id' => '2007',
          'active' => 1,
          'description' => 'Operación sujeta al IVAP',
        ),
        10 => 
        array (
          'id' => '2008',
          'active' => 1,
          'description' => 'VENTA EXONERADA DEL IGV-ISC-IPM. PROHIBIDA LA VENTA FUERA DE LA ZONA COMERCIAL DE TACNA',
        ),
        11 => 
        array (
          'id' => '2009',
          'active' => 1,
          'description' => 'PRIMERA VENTA DE MERCANCÍA IDENTIFICABLE ENTRE USUARIOS DE LA ZONA COMERCIAL',
        ),
        12 => 
        array (
          'id' => '2010',
          'active' => 1,
          'description' => 'Restitucion Simplificado de Derechos Arancelarios',
        ),
      ),
    ),
    'cat_note_credit_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Anulación de la operación',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Anulación por error en el RUC',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Corrección por error en la descripción',
        ),
        3 => 
        array (
          'id' => '04',
          'active' => 1,
          'description' => 'Descuento global',
        ),
        4 => 
        array (
          'id' => '05',
          'active' => 1,
          'description' => 'Descuento por ítem',
        ),
        5 => 
        array (
          'id' => '06',
          'active' => 1,
          'description' => 'Devolución total',
        ),
        6 => 
        array (
          'id' => '07',
          'active' => 1,
          'description' => 'Devolución por ítem',
        ),
        7 => 
        array (
          'id' => '08',
          'active' => 1,
          'description' => 'Bonificación',
        ),
        8 => 
        array (
          'id' => '09',
          'active' => 1,
          'description' => 'Disminución en el valor',
        ),
        9 => 
        array (
          'id' => '10',
          'active' => 1,
          'description' => 'Otros Conceptos',
        ),
        10 => 
        array (
          'id' => '11',
          'active' => 1,
          'description' => 'Ajustes de operaciones de exportación',
        ),
        11 => 
        array (
          'id' => '12',
          'active' => 1,
          'description' => 'Ajustes afectos al IVAP',
        ),
        12 => 
        array (
          'id' => '13',
          'active' => 1,
          'description' => 'Ajustes – montos y/o fechas de pago',
        ),
      ),
    ),
    'cat_note_debit_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Intereses por mora',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Aumento en el valor',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Otros conceptos',
        ),
        3 => 
        array (
          'id' => '10',
          'active' => 1,
          'description' => 'Ajustes de operaciones de exportación',
        ),
        4 => 
        array (
          'id' => '11',
          'active' => 1,
          'description' => 'Ajustes afectos al IVAP',
        ),
        5 => 
        array (
          'id' => '13',
          'active' => 1,
          'description' => 'Penalidades',
        ),
      ),
    ),
    'cat_operation_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '0101',
          'active' => 1,
          'exportation' => 0,
          'description' => 'Venta interna',
        ),
        1 => 
        array (
          'id' => '0101_itinerant',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Venta Interna - Itinerante',
        ),
        2 => 
        array (
          'id' => '0112',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Venta Interna - Sustenta Gastos Deducibles Persona Natural',
        ),
        3 => 
        array (
          'id' => '0113',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Venta Interna - NRUS',
        ),
        4 => 
        array (
          'id' => '0200',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Bienes',
        ),
        5 => 
        array (
          'id' => '0201',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Prestación servicios realizados íntegramente en el país',
        ),
        6 => 
        array (
          'id' => '0202',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Prestación de servicios de hospedaje No Domiciliado',
        ),
        7 => 
        array (
          'id' => '0203',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Transporte de navieras',
        ),
        8 => 
        array (
          'id' => '0204',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Servicios a naves y aeronaves de bandera extranjera',
        ),
        9 => 
        array (
          'id' => '0205',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios - Servicios que conformen un Paquete Turístico',
        ),
        10 => 
        array (
          'id' => '0206',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Servicios complementarios al transporte de carga',
        ),
        11 => 
        array (
          'id' => '0207',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Suministro de energía eléctrica a favor de sujetos domiciliados en ZED',
        ),
        12 => 
        array (
          'id' => '0208',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Servicios – Prestación servicios realizados parcialmente en el extranjero',
        ),
        13 => 
        array (
          'id' => '0301',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operaciones con Carta de porte aéreo (emitidas en el ámbito nacional)',
        ),
        14 => 
        array (
          'id' => '0302',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operaciones de Transporte ferroviario de pasajeros',
        ),
        15 => 
        array (
          'id' => '0303',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operaciones de Pago de regalía petrolera',
        ),
        16 => 
        array (
          'id' => '0401',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Ventas no domiciliados que no califican como exportación',
        ),
        17 => 
        array (
          'id' => '0501',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Compra interna',
        ),
        18 => 
        array (
          'id' => '1001',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operación Sujeta a Detracción',
        ),
        19 => 
        array (
          'id' => '1002',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operación Sujeta a Detracción- Recursos Hidrobiológicos',
        ),
        20 => 
        array (
          'id' => '1003',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operación Sujeta a Detracción- Servicios de Transporte Pasajeros',
        ),
        21 => 
        array (
          'id' => '1004',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operación Sujeta a Detracción- Servicios de Transporte Carga',
        ),
        22 => 
        array (
          'id' => '2001',
          'active' => 0,
          'exportation' => 0,
          'description' => 'Operación Sujeta a Percepción',
        ),
      ),
    ),
    'cat_other_tax_concept_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '1000',
          'active' => 1,
          'description' => 'Total valor de venta - operaciones exportadas',
        ),
        1 => 
        array (
          'id' => '1001',
          'active' => 1,
          'description' => 'Total valor de venta - operaciones gravadas',
        ),
        2 => 
        array (
          'id' => '1002',
          'active' => 1,
          'description' => 'Total valor de venta - operaciones inafectas',
        ),
        3 => 
        array (
          'id' => '1003',
          'active' => 1,
          'description' => 'Total valor de venta - operaciones exoneradas',
        ),
        4 => 
        array (
          'id' => '1004',
          'active' => 1,
          'description' => 'Total valor de venta – Operaciones gratuitas',
        ),
        5 => 
        array (
          'id' => '1005',
          'active' => 1,
          'description' => 'Sub total de venta',
        ),
        6 => 
        array (
          'id' => '2001',
          'active' => 1,
          'description' => 'Percepciones',
        ),
        7 => 
        array (
          'id' => '2002',
          'active' => 1,
          'description' => 'Retenciones',
        ),
        8 => 
        array (
          'id' => '2003',
          'active' => 1,
          'description' => 'Detracciones',
        ),
        9 => 
        array (
          'id' => '2004',
          'active' => 1,
          'description' => 'Bonificaciones',
        ),
        10 => 
        array (
          'id' => '2005',
          'active' => 1,
          'description' => 'Total descuentos',
        ),
        11 => 
        array (
          'id' => '3001',
          'active' => 1,
          'description' => 'FISE (Ley 29852) Fondo Inclusión Social Energético',
        ),
      ),
    ),
    'cat_payment_method_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '001',
          'active' => 1,
          'description' => 'Depósito en cuenta',
        ),
        1 => 
        array (
          'id' => '002',
          'active' => 1,
          'description' => 'Giro',
        ),
        2 => 
        array (
          'id' => '003',
          'active' => 1,
          'description' => 'Transferencia de fondos',
        ),
        3 => 
        array (
          'id' => '004',
          'active' => 1,
          'description' => 'Orden de pago',
        ),
        4 => 
        array (
          'id' => '005',
          'active' => 1,
          'description' => 'Tarjeta de débito',
        ),
        5 => 
        array (
          'id' => '006',
          'active' => 1,
          'description' => 'Tarjeta de crédito emitida en el país por una empresa del sistema financiero',
        ),
        6 => 
        array (
          'id' => '007',
          'active' => 1,
          'description' => 'Cheques con la cláusula de "NO NEGOCIABLE", "INTRANSFERIBLES", "NO A LA ORDEN" u otra equivalente, a que se refiere el inciso g) del artículo 5° de la ley',
        ),
        7 => 
        array (
          'id' => '008',
          'active' => 1,
          'description' => 'Efectivo, por operaciones en las que no existe obligación de utilizar medio de pago',
        ),
        8 => 
        array (
          'id' => '009',
          'active' => 1,
          'description' => 'Efectivo, en los demás casos',
        ),
        9 => 
        array (
          'id' => '010',
          'active' => 1,
          'description' => 'Medios de pago usados en comercio exterior',
        ),
        10 => 
        array (
          'id' => '011',
          'active' => 1,
          'description' => 'Documentos emitidos por las EDPYMES y las cooperativas de ahorro y crédito no autorizadas a captar depósitos del público',
        ),
        11 => 
        array (
          'id' => '012',
          'active' => 1,
          'description' => 'Tarjeta de crédito emitida en el país o en el exterior por una empresa no perteneciente al sistema financiero, cuyo objeto principal sea la emisión y administración de tarjetas de crédito',
        ),
        12 => 
        array (
          'id' => '013',
          'active' => 1,
          'description' => 'Tarjetas de crédito emitidas en el exterior por empresas bancarias o financieras no domiciliadas',
        ),
        13 => 
        array (
          'id' => '101',
          'active' => 1,
          'description' => 'Transferencias – Comercio exterior',
        ),
        14 => 
        array (
          'id' => '102',
          'active' => 1,
          'description' => 'Cheques bancarios - Comercio exterior',
        ),
        15 => 
        array (
          'id' => '103',
          'active' => 1,
          'description' => 'Orden de pago simple - Comercio exterior',
        ),
        16 => 
        array (
          'id' => '104',
          'active' => 1,
          'description' => 'Orden de pago documentario - Comercio exterior',
        ),
        17 => 
        array (
          'id' => '105',
          'active' => 1,
          'description' => 'Remesa simple - Comercio exterior',
        ),
        18 => 
        array (
          'id' => '106',
          'active' => 1,
          'description' => 'Remesa documentaria - Comercio exterior',
        ),
        19 => 
        array (
          'id' => '107',
          'active' => 1,
          'description' => 'Carta de crédito simple - Comercio exterior',
        ),
        20 => 
        array (
          'id' => '108',
          'active' => 1,
          'description' => 'Carta de crédito documentario - Comercio exterior',
        ),
        21 => 
        array (
          'id' => '999',
          'active' => 1,
          'description' => 'Otros medios de pago',
        ),
      ),
    ),
    'cat_perception_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'percentage' => '2.00',
          'description' => 'Percepción Venta Interna',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'percentage' => '1.00',
          'description' => 'Percepción a la adquisición de combustible',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'percentage' => '0.50',
          'description' => 'Percepción realizada al agente de percepción con tasa especial',
        ),
      ),
    ),
    'cat_periods' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'period' => 'M',
          'name' => 'Mensual',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'period' => 'Y',
          'name' => 'Anual',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'period' => 'D',
          'name' => 'Diario',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'period' => 'W',
          'name' => 'Semanal',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        4 => 
        array (
          'id' => 5,
          'period' => 'Q',
          'name' => 'Quincenal',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        5 => 
        array (
          'id' => 6,
          'period' => 'B',
          'name' => 'Bimestral',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        6 => 
        array (
          'id' => 7,
          'period' => 'T',
          'name' => 'Trimestral',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        7 => 
        array (
          'id' => 8,
          'period' => 'S',
          'name' => 'Semestral',
          'active' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'cat_price_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Precio unitario (incluye el IGV)',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Valor referencial unitario en operaciones no onerosas',
        ),
      ),
    ),
    'cat_related_documents_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Numeración DAM',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Número de orden de entrega',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Número SCOP',
        ),
        3 => 
        array (
          'id' => '04',
          'active' => 1,
          'description' => 'Número de manifiesto de carga',
        ),
        4 => 
        array (
          'id' => '05',
          'active' => 1,
          'description' => 'Número de constancia de detracción',
        ),
        5 => 
        array (
          'id' => '06',
          'active' => 1,
          'description' => 'Otros',
        ),
      ),
    ),
    'cat_related_tax_document_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Factura – emitida para corregir error en el RUC',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Factura – emitida por anticipos',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Boleta de Venta – emitida por anticipos',
        ),
        3 => 
        array (
          'id' => '04',
          'active' => 1,
          'description' => 'Ticket de Salida - ENAPU',
        ),
        4 => 
        array (
          'id' => '05',
          'active' => 1,
          'description' => 'Código SCOP',
        ),
        5 => 
        array (
          'id' => '99',
          'active' => 1,
          'description' => 'Otros',
        ),
      ),
    ),
    'cat_retention_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'percentage' => '3.00',
          'description' => 'Tasa 3%',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'percentage' => '6.00',
          'description' => 'Tasa 6%',
        ),
      ),
    ),
    'cat_summary_status_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '1',
          'active' => 1,
          'description' => 'Adicionar',
        ),
        1 => 
        array (
          'id' => '2',
          'active' => 1,
          'description' => 'Modificar',
        ),
        2 => 
        array (
          'id' => '3',
          'active' => 1,
          'description' => 'Anulado',
        ),
      ),
    ),
    'cat_system_isc_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Sistema al valor',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Aplicación del Monto Fijo',
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Sistema de Precios de Venta al Público',
        ),
      ),
    ),
    'cat_transfer_reason_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Venta',
          'discount_stock' => 0,
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Compra',
          'discount_stock' => 0,
        ),
        2 => 
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Venta con entrega a terceros',
          'discount_stock' => 0,
        ),
        3 => 
        array (
          'id' => '04',
          'active' => 1,
          'description' => 'Traslado entre establecimientos de la misma empresa',
          'discount_stock' => 0,
        ),
        4 => 
        array (
          'id' => '05',
          'active' => 1,
          'description' => 'Consignación',
          'discount_stock' => 0,
        ),
        5 => 
        array (
          'id' => '06',
          'active' => 1,
          'description' => 'Devolución',
          'discount_stock' => 0,
        ),
        6 => 
        array (
          'id' => '07',
          'active' => 1,
          'description' => 'Recojo de bienes transformados',
          'discount_stock' => 0,
        ),
        7 => 
        array (
          'id' => '08',
          'active' => 1,
          'description' => 'Importación',
          'discount_stock' => 0,
        ),
        8 => 
        array (
          'id' => '09',
          'active' => 1,
          'description' => 'Exportación',
          'discount_stock' => 0,
        ),
        9 => 
        array (
          'id' => '13',
          'active' => 1,
          'description' => 'Otros no comprendido en ningún código del presente catálogo',
          'discount_stock' => 0,
        ),
        10 => 
        array (
          'id' => '14',
          'active' => 1,
          'description' => 'Venta sujeta a confirmación del comprador',
          'discount_stock' => 0,
        ),
        11 => 
        array (
          'id' => '17',
          'active' => 1,
          'description' => 'Traslado de bienes para transformación',
          'discount_stock' => 0,
        ),
        12 => 
        array (
          'id' => '18',
          'active' => 1,
          'description' => 'Traslado emisor itinerante de comprobantes de pago Aquí no se está considerando el traslado a zona primaria.',
          'discount_stock' => 0,
        ),
        13 => 
        array (
          'id' => '19',
          'active' => 0,
          'description' => 'Traslado a zona primaria',
          'discount_stock' => 0,
        ),
      ),
    ),
    'cat_transport_mode_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Transporte público',
        ),
        1 => 
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Transporte privado',
        ),
      ),
    ),
    'cat_unit_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 'AV',
          'active' => 0,
          'symbol' => 'CAPS',
          'description' => 'Cápsula',
        ),
        1 => 
        array (
          'id' => 'BE',
          'active' => 0,
          'symbol' => 'FARD',
          'description' => 'Fardo',
        ),
        2 => 
        array (
          'id' => 'BG',
          'active' => 1,
          'symbol' => 'BOLS',
          'description' => 'Bolsa',
        ),
        3 => 
        array (
          'id' => 'BJ',
          'active' => 0,
          'symbol' => 'BALD',
          'description' => 'Balde',
        ),
        4 => 
        array (
          'id' => 'BLL',
          'active' => 0,
          'symbol' => 'BRL',
          'description' => 'Barril',
        ),
        5 => 
        array (
          'id' => 'BO',
          'active' => 0,
          'symbol' => 'BOT',
          'description' => 'Botellas',
        ),
        6 => 
        array (
          'id' => 'BT',
          'active' => 0,
          'symbol' => 'TORN',
          'description' => 'Tornillo',
        ),
        7 => 
        array (
          'id' => 'BX',
          'active' => 1,
          'symbol' => 'CAJ',
          'description' => 'Caja',
        ),
        8 => 
        array (
          'id' => 'C62',
          'active' => 0,
          'symbol' => 'PZ',
          'description' => 'Piezas',
        ),
        9 => 
        array (
          'id' => 'CA',
          'active' => 0,
          'symbol' => 'LT',
          'description' => 'Latas',
        ),
        10 => 
        array (
          'id' => 'CEN',
          'active' => 0,
          'symbol' => 'CTO',
          'description' => 'Centenar o ciento',
        ),
        11 => 
        array (
          'id' => 'CH',
          'active' => 0,
          'symbol' => 'ENV',
          'description' => 'Envase',
        ),
        12 => 
        array (
          'id' => 'CMK',
          'active' => 0,
          'symbol' => 'CM2',
          'description' => 'Centímetro cuadrado',
        ),
        13 => 
        array (
          'id' => 'CMQ',
          'active' => 0,
          'symbol' => 'CM3',
          'description' => 'Centímetro cúbico',
        ),
        14 => 
        array (
          'id' => 'CMT',
          'active' => 0,
          'symbol' => 'CM',
          'description' => 'Centímetro',
        ),
        15 => 
        array (
          'id' => 'CT',
          'active' => 0,
          'symbol' => 'CTON',
          'description' => 'Cartón',
        ),
        16 => 
        array (
          'id' => 'CY',
          'active' => 0,
          'symbol' => 'CIL',
          'description' => 'Cilindro',
        ),
        17 => 
        array (
          'id' => 'DZN',
          'active' => 0,
          'symbol' => 'DOC',
          'description' => 'Docena',
        ),
        18 => 
        array (
          'id' => 'DZP',
          'active' => 0,
          'symbol' => 'DOC2',
          'description' => 'Docena de paquetes',
        ),
        19 => 
        array (
          'id' => 'FOT',
          'active' => 0,
          'symbol' => 'PIE',
          'description' => 'Pies',
        ),
        20 => 
        array (
          'id' => 'FTK',
          'active' => 0,
          'symbol' => 'PIE2',
          'description' => 'Pies cuadrados',
        ),
        21 => 
        array (
          'id' => 'FTQ',
          'active' => 0,
          'symbol' => 'PIE3',
          'description' => 'Pies cúbicos',
        ),
        22 => 
        array (
          'id' => 'GLI',
          'active' => 0,
          'symbol' => 'GL',
          'description' => 'Galón inglés',
        ),
        23 => 
        array (
          'id' => 'GLL',
          'active' => 0,
          'symbol' => 'GL',
          'description' => 'Galones',
        ),
        24 => 
        array (
          'id' => 'GRM',
          'active' => 0,
          'symbol' => 'GR',
          'description' => 'Gramos',
        ),
        25 => 
        array (
          'id' => 'HD',
          'active' => 0,
          'symbol' => '1/2 DOC',
          'description' => 'Media docena',
        ),
        26 => 
        array (
          'id' => 'HT',
          'active' => 0,
          'symbol' => '1/2 H',
          'description' => 'Media hora',
        ),
        27 => 
        array (
          'id' => 'HUR',
          'active' => 0,
          'symbol' => 'HR',
          'description' => 'Hora',
        ),
        28 => 
        array (
          'id' => 'INH',
          'active' => 0,
          'symbol' => 'INCH',
          'description' => 'Pulgadas',
        ),
        29 => 
        array (
          'id' => 'JG',
          'active' => 0,
          'symbol' => 'JARR',
          'description' => 'Jarra',
        ),
        30 => 
        array (
          'id' => 'JR',
          'active' => 0,
          'symbol' => 'FCO',
          'description' => 'Frasco',
        ),
        31 => 
        array (
          'id' => 'KGM',
          'active' => 1,
          'symbol' => 'KG',
          'description' => 'Kilos',
        ),
        32 => 
        array (
          'id' => 'KT',
          'active' => 0,
          'symbol' => 'KIT',
          'description' => 'Kit',
        ),
        33 => 
        array (
          'id' => 'KTM',
          'active' => 0,
          'symbol' => 'KM',
          'description' => 'Kilómetro',
        ),
        34 => 
        array (
          'id' => 'KWH',
          'active' => 0,
          'symbol' => 'KWxH',
          'description' => 'Kilovatio hora',
        ),
        35 => 
        array (
          'id' => 'LBR',
          'active' => 0,
          'symbol' => 'LB',
          'description' => 'Libras',
        ),
        36 => 
        array (
          'id' => 'LEF',
          'active' => 0,
          'symbol' => 'HOJA',
          'description' => 'Hoja',
        ),
        37 => 
        array (
          'id' => 'LTR',
          'active' => 1,
          'symbol' => 'LT',
          'description' => 'Litros',
        ),
        38 => 
        array (
          'id' => 'MGM',
          'active' => 0,
          'symbol' => 'MG',
          'description' => 'Miligramos',
        ),
        39 => 
        array (
          'id' => 'MIL',
          'active' => 0,
          'symbol' => 'MIL',
          'description' => 'Millar',
        ),
        40 => 
        array (
          'id' => 'MLT',
          'active' => 0,
          'symbol' => 'ML',
          'description' => 'Mililitro',
        ),
        41 => 
        array (
          'id' => 'MMK',
          'active' => 0,
          'symbol' => 'MM2',
          'description' => 'Milímetro cuadrado',
        ),
        42 => 
        array (
          'id' => 'MMQ',
          'active' => 0,
          'symbol' => 'MM3',
          'description' => 'Milímetro cúbico',
        ),
        43 => 
        array (
          'id' => 'MMT',
          'active' => 0,
          'symbol' => 'MM',
          'description' => 'Milímetro',
        ),
        44 => 
        array (
          'id' => 'MTK',
          'active' => 0,
          'symbol' => 'M2',
          'description' => 'Metro cuadrado',
        ),
        45 => 
        array (
          'id' => 'MTQ',
          'active' => 0,
          'symbol' => 'M3',
          'description' => 'Metro cúbico',
        ),
        46 => 
        array (
          'id' => 'MTR',
          'active' => 1,
          'symbol' => 'M',
          'description' => 'Metros',
        ),
        47 => 
        array (
          'id' => 'MWH',
          'active' => 0,
          'symbol' => 'MWxH',
          'description' => 'Megavatio hora',
        ),
        48 => 
        array (
          'id' => 'NIU',
          'active' => 1,
          'symbol' => 'UND',
          'description' => 'Unidades',
        ),
        49 => 
        array (
          'id' => 'ONZ',
          'active' => 0,
          'symbol' => 'ONZ',
          'description' => 'Onzas',
        ),
        50 => 
        array (
          'id' => 'PF',
          'active' => 0,
          'symbol' => 'PAL',
          'description' => 'Paletas',
        ),
        51 => 
        array (
          'id' => 'PG',
          'active' => 0,
          'symbol' => 'PLAC',
          'description' => 'Placas',
        ),
        52 => 
        array (
          'id' => 'PK',
          'active' => 1,
          'symbol' => 'PQT',
          'description' => 'Paquete',
        ),
        53 => 
        array (
          'id' => 'PR',
          'active' => 0,
          'symbol' => 'PAR',
          'description' => 'Par',
        ),
        54 => 
        array (
          'id' => 'QD',
          'active' => 0,
          'symbol' => '1/4 DOC',
          'description' => 'Cuarto de docena',
        ),
        55 => 
        array (
          'id' => 'RD',
          'active' => 0,
          'symbol' => 'VAR',
          'description' => 'Varilla',
        ),
        56 => 
        array (
          'id' => 'RL',
          'active' => 0,
          'symbol' => 'CRR',
          'description' => 'Carrete',
        ),
        57 => 
        array (
          'id' => 'RM',
          'active' => 0,
          'symbol' => 'RESM',
          'description' => 'Resma',
        ),
        58 => 
        array (
          'id' => 'SA',
          'active' => 0,
          'symbol' => 'SCO',
          'description' => 'Saco',
        ),
        59 => 
        array (
          'id' => 'SEC',
          'active' => 0,
          'symbol' => 'SEG',
          'description' => 'Segundo',
        ),
        60 => 
        array (
          'id' => 'SET',
          'active' => 0,
          'symbol' => 'JGO',
          'description' => 'Juego',
        ),
        61 => 
        array (
          'id' => 'ST',
          'active' => 0,
          'symbol' => 'PLGO',
          'description' => 'Pliego',
        ),
        62 => 
        array (
          'id' => 'TNE',
          'active' => 0,
          'symbol' => 'TNL',
          'description' => 'Toneladas',
        ),
        63 => 
        array (
          'id' => 'TU',
          'active' => 0,
          'symbol' => 'TB',
          'description' => 'Tubos',
        ),
        64 => 
        array (
          'id' => 'U2',
          'active' => 0,
          'symbol' => 'BLIST',
          'description' => 'Tableta o blister',
        ),
        65 => 
        array (
          'id' => 'UM',
          'active' => 0,
          'symbol' => 'MILL',
          'description' => 'Millón',
        ),
        66 => 
        array (
          'id' => 'YRD',
          'active' => 0,
          'symbol' => 'YD',
          'description' => 'Yardas',
        ),
        67 => 
        array (
          'id' => 'ZZ',
          'active' => 1,
          'symbol' => 'SERV',
          'description' => 'Servicio',
        ),
      ),
    ),
    'client_error_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 'data_entry',
          'name' => 'Errores de ingreso de datos',
        ),
        1 => 
        array (
          'id' => 'token_creation',
          'name' => 'Errores en la creación del token de tarjeta',
        ),
      ),
    ),
    'company_accounts' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'subtotal_pen' => '70111',
          'total_pen' => '12121',
          'igv_pen' => '40111',
          'subtotal_usd' => '70111',
          'total_usd' => '12122',
          'igv_usd' => '40111',
          'exonerated' => NULL,
          'unaffected' => NULL,
        ),
      ),
    ),
    'configuration_ecommerce' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'information_contact_name' => 'Admin',
          'information_contact_email' => 'admin@mail.com',
          'information_contact_phone' => '01 505-5555',
          'information_contact_address' => NULL,
          'phone_whatsapp' => NULL,
          'script_paypal' => NULL,
          'logo' => NULL,
          'link_youtube' => NULL,
          'link_twitter' => NULL,
          'link_tiktok' => NULL,
          'link_instagram' => NULL,
          'color_ecommerce' => NULL,
          'link_facebook' => NULL,
          'tag_support' => NULL,
          'tag_dollar' => NULL,
          'tag_shipping' => NULL,
          'token_public_culqui' => NULL,
          'token_private_culqui' => NULL,
          'title_one_customised_link' => NULL,
          'title_two_customised_link' => NULL,
          'title_three_customised_link' => NULL,
          'customised_link_one' => NULL,
          'customised_link_two' => NULL,
          'customised_link_three' => NULL,
          'preferences' => '{"show_stock": 0, "show_description": 1, "full_width_banner": 0, "only_available_products": 0}',
          'publicidad_activa' => 0,
          'publicidad_texto' => NULL,
          'publicidad_color_fondo' => '#000000',
          'publicidad_link' => NULL,
          'terms_conditions' => NULL,
          'privacy_policy' => NULL,
          'about_us' => NULL,
          'delivery_no_coverage_message' => NULL,
          'enable_electronic_documents' => 0,
          'enable_store_pickup' => 0,
          'enable_yape' => 0,
          'enable_transfer' => 0,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'configuration_taps' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'save_plates_client' => 0,
          'created_at' => '2026-08-17 14:15:21',
          'updated_at' => '2026-08-17 14:15:21',
        ),
      ),
    ),
    'contract_state_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Registrado',
        ),
        1 => 
        array (
          'id' => '05',
          'description' => 'Entregado',
        ),
        2 => 
        array (
          'id' => '09',
          'description' => 'Rechazado',
        ),
        3 => 
        array (
          'id' => '11',
          'description' => 'Anulado',
        ),
      ),
    ),
    'countries' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 'AD',
          'description' => 'ANDORRA',
          'active' => 1,
        ),
        1 => 
        array (
          'id' => 'AE',
          'description' => 'UNITED ARAB EMIRATES',
          'active' => 1,
        ),
        2 => 
        array (
          'id' => 'AF',
          'description' => 'AFGHANISTAN',
          'active' => 1,
        ),
        3 => 
        array (
          'id' => 'AG',
          'description' => 'ANTIGUA AND BARBUDA',
          'active' => 1,
        ),
        4 => 
        array (
          'id' => 'AI',
          'description' => 'ANGUILLA',
          'active' => 1,
        ),
        5 => 
        array (
          'id' => 'AL',
          'description' => 'ALBANIA',
          'active' => 1,
        ),
        6 => 
        array (
          'id' => 'AM',
          'description' => 'ARMENIA',
          'active' => 1,
        ),
        7 => 
        array (
          'id' => 'AN',
          'description' => 'NETHERLANDS ANTILLES',
          'active' => 1,
        ),
        8 => 
        array (
          'id' => 'AO',
          'description' => 'ANGOLA',
          'active' => 1,
        ),
        9 => 
        array (
          'id' => 'AQ',
          'description' => 'ANTARCTICA',
          'active' => 1,
        ),
        10 => 
        array (
          'id' => 'AR',
          'description' => 'ARGENTINA',
          'active' => 1,
        ),
        11 => 
        array (
          'id' => 'AS',
          'description' => 'AMERICAN SAMOA',
          'active' => 1,
        ),
        12 => 
        array (
          'id' => 'AT',
          'description' => 'AUSTRIA',
          'active' => 1,
        ),
        13 => 
        array (
          'id' => 'AU',
          'description' => 'AUSTRALIA',
          'active' => 1,
        ),
        14 => 
        array (
          'id' => 'AW',
          'description' => 'ARUBA',
          'active' => 1,
        ),
        15 => 
        array (
          'id' => 'AX',
          'description' => 'AALAND ISLANDS',
          'active' => 1,
        ),
        16 => 
        array (
          'id' => 'AZ',
          'description' => 'AZERBAIJAN',
          'active' => 1,
        ),
        17 => 
        array (
          'id' => 'BA',
          'description' => 'BOSNIA AND HERZEGOWINA',
          'active' => 1,
        ),
        18 => 
        array (
          'id' => 'BB',
          'description' => 'BARBADOS',
          'active' => 1,
        ),
        19 => 
        array (
          'id' => 'BD',
          'description' => 'BANGLADESH',
          'active' => 1,
        ),
        20 => 
        array (
          'id' => 'BE',
          'description' => 'BELGIUM',
          'active' => 1,
        ),
        21 => 
        array (
          'id' => 'BF',
          'description' => 'BURKINA FASO',
          'active' => 1,
        ),
        22 => 
        array (
          'id' => 'BG',
          'description' => 'BULGARIA',
          'active' => 1,
        ),
        23 => 
        array (
          'id' => 'BH',
          'description' => 'BAHRAIN',
          'active' => 1,
        ),
        24 => 
        array (
          'id' => 'BI',
          'description' => 'BURUNDI',
          'active' => 1,
        ),
        25 => 
        array (
          'id' => 'BJ',
          'description' => 'BENIN',
          'active' => 1,
        ),
        26 => 
        array (
          'id' => 'BM',
          'description' => 'BERMUDA',
          'active' => 1,
        ),
        27 => 
        array (
          'id' => 'BN',
          'description' => 'BRUNEI DARUSSALAM',
          'active' => 1,
        ),
        28 => 
        array (
          'id' => 'BO',
          'description' => 'BOLIVIA',
          'active' => 1,
        ),
        29 => 
        array (
          'id' => 'BR',
          'description' => 'BRAZIL',
          'active' => 1,
        ),
        30 => 
        array (
          'id' => 'BS',
          'description' => 'BAHAMAS',
          'active' => 1,
        ),
        31 => 
        array (
          'id' => 'BT',
          'description' => 'BHUTAN',
          'active' => 1,
        ),
        32 => 
        array (
          'id' => 'BV',
          'description' => 'BOUVET ISLAND',
          'active' => 1,
        ),
        33 => 
        array (
          'id' => 'BW',
          'description' => 'BOTSWANA',
          'active' => 1,
        ),
        34 => 
        array (
          'id' => 'BY',
          'description' => 'BELARUS',
          'active' => 1,
        ),
        35 => 
        array (
          'id' => 'BZ',
          'description' => 'BELIZE',
          'active' => 1,
        ),
        36 => 
        array (
          'id' => 'CA',
          'description' => 'CANADA',
          'active' => 1,
        ),
        37 => 
        array (
          'id' => 'CC',
          'description' => 'COCOS (KEELING) ISLANDS',
          'active' => 1,
        ),
        38 => 
        array (
          'id' => 'CD',
          'description' => 'CONGO, Democratic Republic of (was Zaire)',
          'active' => 1,
        ),
        39 => 
        array (
          'id' => 'CF',
          'description' => 'CENTRAL AFRICAN REPUBLIC',
          'active' => 1,
        ),
        40 => 
        array (
          'id' => 'CG',
          'description' => 'CONGO, Republic of',
          'active' => 1,
        ),
        41 => 
        array (
          'id' => 'CH',
          'description' => 'SWITZERLAND',
          'active' => 1,
        ),
        42 => 
        array (
          'id' => 'CI',
          'description' => 'COTE D`IVOIRE',
          'active' => 1,
        ),
        43 => 
        array (
          'id' => 'CK',
          'description' => 'COOK ISLANDS',
          'active' => 1,
        ),
        44 => 
        array (
          'id' => 'CL',
          'description' => 'CHILE',
          'active' => 1,
        ),
        45 => 
        array (
          'id' => 'CM',
          'description' => 'CAMEROON',
          'active' => 1,
        ),
        46 => 
        array (
          'id' => 'CN',
          'description' => 'CHINA',
          'active' => 1,
        ),
        47 => 
        array (
          'id' => 'CO',
          'description' => 'COLOMBIA',
          'active' => 1,
        ),
        48 => 
        array (
          'id' => 'CR',
          'description' => 'COSTA RICA',
          'active' => 1,
        ),
        49 => 
        array (
          'id' => 'CS',
          'description' => 'SERBIA AND MONTENEGRO',
          'active' => 1,
        ),
        50 => 
        array (
          'id' => 'CU',
          'description' => 'CUBA',
          'active' => 1,
        ),
        51 => 
        array (
          'id' => 'CV',
          'description' => 'CAPE VERDE',
          'active' => 1,
        ),
        52 => 
        array (
          'id' => 'CX',
          'description' => 'CHRISTMAS ISLAND',
          'active' => 1,
        ),
        53 => 
        array (
          'id' => 'CY',
          'description' => 'CYPRUS',
          'active' => 1,
        ),
        54 => 
        array (
          'id' => 'CZ',
          'description' => 'CZECH REPUBLIC',
          'active' => 1,
        ),
        55 => 
        array (
          'id' => 'DE',
          'description' => 'GERMANY',
          'active' => 1,
        ),
        56 => 
        array (
          'id' => 'DJ',
          'description' => 'DJIBOUTI',
          'active' => 1,
        ),
        57 => 
        array (
          'id' => 'DK',
          'description' => 'DENMARK',
          'active' => 1,
        ),
        58 => 
        array (
          'id' => 'DM',
          'description' => 'DOMINICA',
          'active' => 1,
        ),
        59 => 
        array (
          'id' => 'DO',
          'description' => 'DOMINICAN REPUBLIC',
          'active' => 1,
        ),
        60 => 
        array (
          'id' => 'DZ',
          'description' => 'ALGERIA',
          'active' => 1,
        ),
        61 => 
        array (
          'id' => 'EC',
          'description' => 'ECUADOR',
          'active' => 1,
        ),
        62 => 
        array (
          'id' => 'EE',
          'description' => 'ESTONIA',
          'active' => 1,
        ),
        63 => 
        array (
          'id' => 'EG',
          'description' => 'EGYPT',
          'active' => 1,
        ),
        64 => 
        array (
          'id' => 'EH',
          'description' => 'WESTERN SAHARA',
          'active' => 1,
        ),
        65 => 
        array (
          'id' => 'ER',
          'description' => 'ERITREA',
          'active' => 1,
        ),
        66 => 
        array (
          'id' => 'ES',
          'description' => 'SPAIN',
          'active' => 1,
        ),
        67 => 
        array (
          'id' => 'ET',
          'description' => 'ETHIOPIA',
          'active' => 1,
        ),
        68 => 
        array (
          'id' => 'FI',
          'description' => 'FINLAND',
          'active' => 1,
        ),
        69 => 
        array (
          'id' => 'FJ',
          'description' => 'FIJI',
          'active' => 1,
        ),
        70 => 
        array (
          'id' => 'FK',
          'description' => 'FALKLAND ISLANDS (MALVINAS)',
          'active' => 1,
        ),
        71 => 
        array (
          'id' => 'FM',
          'description' => 'MICRONESIA, FEDERATED STATES OF',
          'active' => 1,
        ),
        72 => 
        array (
          'id' => 'FO',
          'description' => 'FAROE ISLANDS',
          'active' => 1,
        ),
        73 => 
        array (
          'id' => 'FR',
          'description' => 'FRANCE',
          'active' => 1,
        ),
        74 => 
        array (
          'id' => 'GA',
          'description' => 'GABON',
          'active' => 1,
        ),
        75 => 
        array (
          'id' => 'GB',
          'description' => 'UNITED KINGDOM',
          'active' => 1,
        ),
        76 => 
        array (
          'id' => 'GD',
          'description' => 'GRENADA',
          'active' => 1,
        ),
        77 => 
        array (
          'id' => 'GE',
          'description' => 'GEORGIA',
          'active' => 1,
        ),
        78 => 
        array (
          'id' => 'GF',
          'description' => 'FRENCH GUIANA',
          'active' => 1,
        ),
        79 => 
        array (
          'id' => 'GH',
          'description' => 'GHANA',
          'active' => 1,
        ),
        80 => 
        array (
          'id' => 'GI',
          'description' => 'GIBRALTAR',
          'active' => 1,
        ),
        81 => 
        array (
          'id' => 'GL',
          'description' => 'GREENLAND',
          'active' => 1,
        ),
        82 => 
        array (
          'id' => 'GM',
          'description' => 'GAMBIA',
          'active' => 1,
        ),
        83 => 
        array (
          'id' => 'GN',
          'description' => 'GUINEA',
          'active' => 1,
        ),
        84 => 
        array (
          'id' => 'GP',
          'description' => 'GUADELOUPE',
          'active' => 1,
        ),
        85 => 
        array (
          'id' => 'GQ',
          'description' => 'EQUATORIAL GUINEA',
          'active' => 1,
        ),
        86 => 
        array (
          'id' => 'GR',
          'description' => 'GREECE',
          'active' => 1,
        ),
        87 => 
        array (
          'id' => 'GS',
          'description' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS',
          'active' => 1,
        ),
        88 => 
        array (
          'id' => 'GT',
          'description' => 'GUATEMALA',
          'active' => 1,
        ),
        89 => 
        array (
          'id' => 'GU',
          'description' => 'GUAM',
          'active' => 1,
        ),
        90 => 
        array (
          'id' => 'GW',
          'description' => 'GUINEA-BISSAU',
          'active' => 1,
        ),
        91 => 
        array (
          'id' => 'GY',
          'description' => 'GUYANA',
          'active' => 1,
        ),
        92 => 
        array (
          'id' => 'HK',
          'description' => 'HONG KONG',
          'active' => 1,
        ),
        93 => 
        array (
          'id' => 'HM',
          'description' => 'HEARD AND MC DONALD ISLANDS',
          'active' => 1,
        ),
        94 => 
        array (
          'id' => 'HN',
          'description' => 'HONDURAS',
          'active' => 1,
        ),
        95 => 
        array (
          'id' => 'HR',
          'description' => 'CROATIA (local name: Hrvatska)',
          'active' => 1,
        ),
        96 => 
        array (
          'id' => 'HT',
          'description' => 'HAITI',
          'active' => 1,
        ),
        97 => 
        array (
          'id' => 'HU',
          'description' => 'HUNGARY',
          'active' => 1,
        ),
        98 => 
        array (
          'id' => 'ID',
          'description' => 'INDONESIA',
          'active' => 1,
        ),
        99 => 
        array (
          'id' => 'IE',
          'description' => 'IRELAND',
          'active' => 1,
        ),
        100 => 
        array (
          'id' => 'IL',
          'description' => 'ISRAEL',
          'active' => 1,
        ),
        101 => 
        array (
          'id' => 'IN',
          'description' => 'INDIA',
          'active' => 1,
        ),
        102 => 
        array (
          'id' => 'IO',
          'description' => 'BRITISH INDIAN OCEAN TERRITORY',
          'active' => 1,
        ),
        103 => 
        array (
          'id' => 'IQ',
          'description' => 'IRAQ',
          'active' => 1,
        ),
        104 => 
        array (
          'id' => 'IR',
          'description' => 'IRAN (ISLAMIC REPUBLIC OF)',
          'active' => 1,
        ),
        105 => 
        array (
          'id' => 'IS',
          'description' => 'ICELAND',
          'active' => 1,
        ),
        106 => 
        array (
          'id' => 'IT',
          'description' => 'ITALY',
          'active' => 1,
        ),
        107 => 
        array (
          'id' => 'JM',
          'description' => 'JAMAICA',
          'active' => 1,
        ),
        108 => 
        array (
          'id' => 'JO',
          'description' => 'JORDAN',
          'active' => 1,
        ),
        109 => 
        array (
          'id' => 'JP',
          'description' => 'JAPAN',
          'active' => 1,
        ),
        110 => 
        array (
          'id' => 'KE',
          'description' => 'KENYA',
          'active' => 1,
        ),
        111 => 
        array (
          'id' => 'KG',
          'description' => 'KYRGYZSTAN',
          'active' => 1,
        ),
        112 => 
        array (
          'id' => 'KH',
          'description' => 'CAMBODIA',
          'active' => 1,
        ),
        113 => 
        array (
          'id' => 'KI',
          'description' => 'KIRIBATI',
          'active' => 1,
        ),
        114 => 
        array (
          'id' => 'KM',
          'description' => 'COMOROS',
          'active' => 1,
        ),
        115 => 
        array (
          'id' => 'KN',
          'description' => 'SAINT KITTS AND NEVIS',
          'active' => 1,
        ),
        116 => 
        array (
          'id' => 'KP',
          'description' => 'KOREA, DEMOCRATIC PEOPLE`S REPUBLIC OF',
          'active' => 1,
        ),
        117 => 
        array (
          'id' => 'KR',
          'description' => 'KOREA, REPUBLIC OF',
          'active' => 1,
        ),
        118 => 
        array (
          'id' => 'KW',
          'description' => 'KUWAIT',
          'active' => 1,
        ),
        119 => 
        array (
          'id' => 'KY',
          'description' => 'CAYMAN ISLANDS',
          'active' => 1,
        ),
        120 => 
        array (
          'id' => 'KZ',
          'description' => 'KAZAKHSTAN',
          'active' => 1,
        ),
        121 => 
        array (
          'id' => 'LA',
          'description' => 'LAO PEOPLE`S DEMOCRATIC REPUBLIC',
          'active' => 1,
        ),
        122 => 
        array (
          'id' => 'LB',
          'description' => 'LEBANON',
          'active' => 1,
        ),
        123 => 
        array (
          'id' => 'LC',
          'description' => 'SAINT LUCIA',
          'active' => 1,
        ),
        124 => 
        array (
          'id' => 'LI',
          'description' => 'LIECHTENSTEIN',
          'active' => 1,
        ),
        125 => 
        array (
          'id' => 'LK',
          'description' => 'SRI LANKA',
          'active' => 1,
        ),
        126 => 
        array (
          'id' => 'LR',
          'description' => 'LIBERIA',
          'active' => 1,
        ),
        127 => 
        array (
          'id' => 'LS',
          'description' => 'LESOTHO',
          'active' => 1,
        ),
        128 => 
        array (
          'id' => 'LT',
          'description' => 'LITHUANIA',
          'active' => 1,
        ),
        129 => 
        array (
          'id' => 'LU',
          'description' => 'LUXEMBOURG',
          'active' => 1,
        ),
        130 => 
        array (
          'id' => 'LV',
          'description' => 'LATVIA',
          'active' => 1,
        ),
        131 => 
        array (
          'id' => 'LY',
          'description' => 'LIBYAN ARAB JAMAHIRIYA',
          'active' => 1,
        ),
        132 => 
        array (
          'id' => 'MA',
          'description' => 'MOROCCO',
          'active' => 1,
        ),
        133 => 
        array (
          'id' => 'MC',
          'description' => 'MONACO',
          'active' => 1,
        ),
        134 => 
        array (
          'id' => 'MD',
          'description' => 'MOLDOVA, REPUBLIC OF',
          'active' => 1,
        ),
        135 => 
        array (
          'id' => 'MG',
          'description' => 'MADAGASCAR',
          'active' => 1,
        ),
        136 => 
        array (
          'id' => 'MH',
          'description' => 'MARSHALL ISLANDS',
          'active' => 1,
        ),
        137 => 
        array (
          'id' => 'MK',
          'description' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF',
          'active' => 1,
        ),
        138 => 
        array (
          'id' => 'ML',
          'description' => 'MALI',
          'active' => 1,
        ),
        139 => 
        array (
          'id' => 'MM',
          'description' => 'MYANMAR',
          'active' => 1,
        ),
        140 => 
        array (
          'id' => 'MN',
          'description' => 'MONGOLIA',
          'active' => 1,
        ),
        141 => 
        array (
          'id' => 'MO',
          'description' => 'MACAU',
          'active' => 1,
        ),
        142 => 
        array (
          'id' => 'MP',
          'description' => 'NORTHERN MARIANA ISLANDS',
          'active' => 1,
        ),
        143 => 
        array (
          'id' => 'MQ',
          'description' => 'MARTINIQUE',
          'active' => 1,
        ),
        144 => 
        array (
          'id' => 'MR',
          'description' => 'MAURITANIA',
          'active' => 1,
        ),
        145 => 
        array (
          'id' => 'MS',
          'description' => 'MONTSERRAT',
          'active' => 1,
        ),
        146 => 
        array (
          'id' => 'MT',
          'description' => 'MALTA',
          'active' => 1,
        ),
        147 => 
        array (
          'id' => 'MU',
          'description' => 'MAURITIUS',
          'active' => 1,
        ),
        148 => 
        array (
          'id' => 'MV',
          'description' => 'MALDIVES',
          'active' => 1,
        ),
        149 => 
        array (
          'id' => 'MW',
          'description' => 'MALAWI',
          'active' => 1,
        ),
        150 => 
        array (
          'id' => 'MX',
          'description' => 'MEXICO',
          'active' => 1,
        ),
        151 => 
        array (
          'id' => 'MY',
          'description' => 'MALAYSIA',
          'active' => 1,
        ),
        152 => 
        array (
          'id' => 'MZ',
          'description' => 'MOZAMBIQUE',
          'active' => 1,
        ),
        153 => 
        array (
          'id' => 'NA',
          'description' => 'NAMIBIA',
          'active' => 1,
        ),
        154 => 
        array (
          'id' => 'NC',
          'description' => 'NEW CALEDONIA',
          'active' => 1,
        ),
        155 => 
        array (
          'id' => 'NE',
          'description' => 'NIGER',
          'active' => 1,
        ),
        156 => 
        array (
          'id' => 'NF',
          'description' => 'NORFOLK ISLAND',
          'active' => 1,
        ),
        157 => 
        array (
          'id' => 'NG',
          'description' => 'NIGERIA',
          'active' => 1,
        ),
        158 => 
        array (
          'id' => 'NI',
          'description' => 'NICARAGUA',
          'active' => 1,
        ),
        159 => 
        array (
          'id' => 'NL',
          'description' => 'NETHERLANDS',
          'active' => 1,
        ),
        160 => 
        array (
          'id' => 'NO',
          'description' => 'NORWAY',
          'active' => 1,
        ),
        161 => 
        array (
          'id' => 'NP',
          'description' => 'NEPAL',
          'active' => 1,
        ),
        162 => 
        array (
          'id' => 'NR',
          'description' => 'NAURU',
          'active' => 1,
        ),
        163 => 
        array (
          'id' => 'NU',
          'description' => 'NIUE',
          'active' => 1,
        ),
        164 => 
        array (
          'id' => 'NZ',
          'description' => 'NEW ZEALAND',
          'active' => 1,
        ),
        165 => 
        array (
          'id' => 'OM',
          'description' => 'OMAN',
          'active' => 1,
        ),
        166 => 
        array (
          'id' => 'PA',
          'description' => 'PANAMA',
          'active' => 1,
        ),
        167 => 
        array (
          'id' => 'PE',
          'description' => 'PERU',
          'active' => 1,
        ),
        168 => 
        array (
          'id' => 'PF',
          'description' => 'FRENCH POLYNESIA',
          'active' => 1,
        ),
        169 => 
        array (
          'id' => 'PG',
          'description' => 'PAPUA NEW GUINEA',
          'active' => 1,
        ),
        170 => 
        array (
          'id' => 'PH',
          'description' => 'PHILIPPINES',
          'active' => 1,
        ),
        171 => 
        array (
          'id' => 'PK',
          'description' => 'PAKISTAN',
          'active' => 1,
        ),
        172 => 
        array (
          'id' => 'PL',
          'description' => 'POLAND',
          'active' => 1,
        ),
        173 => 
        array (
          'id' => 'PM',
          'description' => 'SAINT PIERRE AND MIQUELON',
          'active' => 1,
        ),
        174 => 
        array (
          'id' => 'PN',
          'description' => 'PITCAIRN',
          'active' => 1,
        ),
        175 => 
        array (
          'id' => 'PR',
          'description' => 'PUERTO RICO',
          'active' => 1,
        ),
        176 => 
        array (
          'id' => 'PS',
          'description' => 'PALESTINIAN TERRITORY, Occupied',
          'active' => 1,
        ),
        177 => 
        array (
          'id' => 'PT',
          'description' => 'PORTUGAL',
          'active' => 1,
        ),
        178 => 
        array (
          'id' => 'PW',
          'description' => 'PALAU',
          'active' => 1,
        ),
        179 => 
        array (
          'id' => 'PY',
          'description' => 'PARAGUAY',
          'active' => 1,
        ),
        180 => 
        array (
          'id' => 'QA',
          'description' => 'QATAR',
          'active' => 1,
        ),
        181 => 
        array (
          'id' => 'RE',
          'description' => 'REUNION',
          'active' => 1,
        ),
        182 => 
        array (
          'id' => 'RO',
          'description' => 'ROMANIA',
          'active' => 1,
        ),
        183 => 
        array (
          'id' => 'RU',
          'description' => 'RUSSIAN FEDERATION',
          'active' => 1,
        ),
        184 => 
        array (
          'id' => 'RW',
          'description' => 'RWANDA',
          'active' => 1,
        ),
        185 => 
        array (
          'id' => 'SA',
          'description' => 'SAUDI ARABIA',
          'active' => 1,
        ),
        186 => 
        array (
          'id' => 'SB',
          'description' => 'SOLOMON ISLANDS',
          'active' => 1,
        ),
        187 => 
        array (
          'id' => 'SC',
          'description' => 'SEYCHELLES',
          'active' => 1,
        ),
        188 => 
        array (
          'id' => 'SD',
          'description' => 'SUDAN',
          'active' => 1,
        ),
        189 => 
        array (
          'id' => 'SE',
          'description' => 'SWEDEN',
          'active' => 1,
        ),
        190 => 
        array (
          'id' => 'SG',
          'description' => 'SINGAPORE',
          'active' => 1,
        ),
        191 => 
        array (
          'id' => 'SH',
          'description' => 'SAINT HELENA',
          'active' => 1,
        ),
        192 => 
        array (
          'id' => 'SI',
          'description' => 'SLOVENIA',
          'active' => 1,
        ),
        193 => 
        array (
          'id' => 'SJ',
          'description' => 'SVALBARD AND JAN MAYEN ISLANDS',
          'active' => 1,
        ),
        194 => 
        array (
          'id' => 'SK',
          'description' => 'SLOVAKIA',
          'active' => 1,
        ),
        195 => 
        array (
          'id' => 'SL',
          'description' => 'SIERRA LEONE',
          'active' => 1,
        ),
        196 => 
        array (
          'id' => 'SM',
          'description' => 'SAN MARINO',
          'active' => 1,
        ),
        197 => 
        array (
          'id' => 'SN',
          'description' => 'SENEGAL',
          'active' => 1,
        ),
        198 => 
        array (
          'id' => 'SO',
          'description' => 'SOMALIA',
          'active' => 1,
        ),
        199 => 
        array (
          'id' => 'SR',
          'description' => 'SURINAME',
          'active' => 1,
        ),
        200 => 
        array (
          'id' => 'ST',
          'description' => 'SAO TOME AND PRINCIPE',
          'active' => 1,
        ),
        201 => 
        array (
          'id' => 'SV',
          'description' => 'EL SALVADOR',
          'active' => 1,
        ),
        202 => 
        array (
          'id' => 'SY',
          'description' => 'SYRIAN ARAB REPUBLIC',
          'active' => 1,
        ),
        203 => 
        array (
          'id' => 'SZ',
          'description' => 'SWAZILAND',
          'active' => 1,
        ),
        204 => 
        array (
          'id' => 'TC',
          'description' => 'TURKS AND CAICOS ISLANDS',
          'active' => 1,
        ),
        205 => 
        array (
          'id' => 'TD',
          'description' => 'CHAD',
          'active' => 1,
        ),
        206 => 
        array (
          'id' => 'TF',
          'description' => 'FRENCH SOUTHERN TERRITORIES',
          'active' => 1,
        ),
        207 => 
        array (
          'id' => 'TG',
          'description' => 'TOGO',
          'active' => 1,
        ),
        208 => 
        array (
          'id' => 'TH',
          'description' => 'THAILAND',
          'active' => 1,
        ),
        209 => 
        array (
          'id' => 'TJ',
          'description' => 'TAJIKISTAN',
          'active' => 1,
        ),
        210 => 
        array (
          'id' => 'TK',
          'description' => 'TOKELAU',
          'active' => 1,
        ),
        211 => 
        array (
          'id' => 'TL',
          'description' => 'TIMOR-LESTE',
          'active' => 1,
        ),
        212 => 
        array (
          'id' => 'TM',
          'description' => 'TURKMENISTAN',
          'active' => 1,
        ),
        213 => 
        array (
          'id' => 'TN',
          'description' => 'TUNISIA',
          'active' => 1,
        ),
        214 => 
        array (
          'id' => 'TO',
          'description' => 'TONGA',
          'active' => 1,
        ),
        215 => 
        array (
          'id' => 'TR',
          'description' => 'TURKEY',
          'active' => 1,
        ),
        216 => 
        array (
          'id' => 'TT',
          'description' => 'TRINIDAD AND TOBAGO',
          'active' => 1,
        ),
        217 => 
        array (
          'id' => 'TV',
          'description' => 'TUVALU',
          'active' => 1,
        ),
        218 => 
        array (
          'id' => 'TW',
          'description' => 'TAIWAN',
          'active' => 1,
        ),
        219 => 
        array (
          'id' => 'TZ',
          'description' => 'TANZANIA, UNITED REPUBLIC OF',
          'active' => 1,
        ),
        220 => 
        array (
          'id' => 'UA',
          'description' => 'UKRAINE',
          'active' => 1,
        ),
        221 => 
        array (
          'id' => 'UG',
          'description' => 'UGANDA',
          'active' => 1,
        ),
        222 => 
        array (
          'id' => 'UM',
          'description' => 'UNITED STATES MINOR OUTLYING ISLANDS',
          'active' => 1,
        ),
        223 => 
        array (
          'id' => 'US',
          'description' => 'UNITED STATES',
          'active' => 1,
        ),
        224 => 
        array (
          'id' => 'UY',
          'description' => 'URUGUAY',
          'active' => 1,
        ),
        225 => 
        array (
          'id' => 'UZ',
          'description' => 'UZBEKISTAN',
          'active' => 1,
        ),
        226 => 
        array (
          'id' => 'VA',
          'description' => 'VATICAN CITY STATE (HOLY SEE)',
          'active' => 1,
        ),
        227 => 
        array (
          'id' => 'VC',
          'description' => 'SAINT VINCENT AND THE GRENADINES',
          'active' => 1,
        ),
        228 => 
        array (
          'id' => 'VE',
          'description' => 'VENEZUELA',
          'active' => 1,
        ),
        229 => 
        array (
          'id' => 'VG',
          'description' => 'VIRGIN ISLANDS (BRITISH)',
          'active' => 1,
        ),
        230 => 
        array (
          'id' => 'VI',
          'description' => 'VIRGIN ISLANDS (U.S.)',
          'active' => 1,
        ),
        231 => 
        array (
          'id' => 'VN',
          'description' => 'VIET NAM',
          'active' => 1,
        ),
        232 => 
        array (
          'id' => 'VU',
          'description' => 'VANUATU',
          'active' => 1,
        ),
        233 => 
        array (
          'id' => 'WF',
          'description' => 'WALLIS AND FUTUNA ISLANDS',
          'active' => 1,
        ),
        234 => 
        array (
          'id' => 'WS',
          'description' => 'SAMOA',
          'active' => 1,
        ),
        235 => 
        array (
          'id' => 'YE',
          'description' => 'YEMEN',
          'active' => 1,
        ),
        236 => 
        array (
          'id' => 'YT',
          'description' => 'MAYOTTE',
          'active' => 1,
        ),
        237 => 
        array (
          'id' => 'ZA',
          'description' => 'SOUTH AFRICA',
          'active' => 1,
        ),
        238 => 
        array (
          'id' => 'ZM',
          'description' => 'ZAMBIA',
          'active' => 1,
        ),
        239 => 
        array (
          'id' => 'ZW',
          'description' => 'ZIMBABWE',
          'active' => 1,
        ),
      ),
    ),
    'departments' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'AMAZONAS',
          'active' => 1,
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'ÁNCASH',
          'active' => 1,
        ),
        2 => 
        array (
          'id' => '03',
          'description' => 'APURIMAC',
          'active' => 1,
        ),
        3 => 
        array (
          'id' => '04',
          'description' => 'AREQUIPA',
          'active' => 1,
        ),
        4 => 
        array (
          'id' => '05',
          'description' => 'AYACUCHO',
          'active' => 1,
        ),
        5 => 
        array (
          'id' => '06',
          'description' => 'CAJAMARCA',
          'active' => 1,
        ),
        6 => 
        array (
          'id' => '07',
          'description' => 'CALLAO',
          'active' => 1,
        ),
        7 => 
        array (
          'id' => '08',
          'description' => 'CUSCO',
          'active' => 1,
        ),
        8 => 
        array (
          'id' => '09',
          'description' => 'HUANCAVELICA',
          'active' => 1,
        ),
        9 => 
        array (
          'id' => '10',
          'description' => 'HUÁNUCO',
          'active' => 1,
        ),
        10 => 
        array (
          'id' => '11',
          'description' => 'ICA',
          'active' => 1,
        ),
        11 => 
        array (
          'id' => '12',
          'description' => 'JUNÍN',
          'active' => 1,
        ),
        12 => 
        array (
          'id' => '13',
          'description' => 'LA LIBERTAD',
          'active' => 1,
        ),
        13 => 
        array (
          'id' => '14',
          'description' => 'LAMBAYEQUE',
          'active' => 1,
        ),
        14 => 
        array (
          'id' => '15',
          'description' => 'LIMA',
          'active' => 1,
        ),
        15 => 
        array (
          'id' => '16',
          'description' => 'LORETO',
          'active' => 1,
        ),
        16 => 
        array (
          'id' => '17',
          'description' => 'MADRE DE DIOS',
          'active' => 1,
        ),
        17 => 
        array (
          'id' => '18',
          'description' => 'MOQUEGUA',
          'active' => 1,
        ),
        18 => 
        array (
          'id' => '19',
          'description' => 'PASCO',
          'active' => 1,
        ),
        19 => 
        array (
          'id' => '20',
          'description' => 'PIURA',
          'active' => 1,
        ),
        20 => 
        array (
          'id' => '21',
          'description' => 'PUNO',
          'active' => 1,
        ),
        21 => 
        array (
          'id' => '22',
          'description' => 'SAN MARTIN',
          'active' => 1,
        ),
        22 => 
        array (
          'id' => '23',
          'description' => 'TACNA',
          'active' => 1,
        ),
        23 => 
        array (
          'id' => '24',
          'description' => 'TUMBES',
          'active' => 1,
        ),
        24 => 
        array (
          'id' => '25',
          'description' => 'UCAYALI',
          'active' => 1,
        ),
      ),
    ),
    'devolution_reasons' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Productos vencidos',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Productos dañados',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'Productos con errores de Fábrica',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'documentary_guides_number_status' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'En Calificación',
          'color' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Concluidos',
          'color' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Observados',
          'color' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'Archivados',
          'color' => NULL,
        ),
      ),
    ),
    'documentary_offices' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Caja',
          'description' => '',
          'active' => 1,
          'created_at' => '2026-08-17 14:14:38',
          'updated_at' => '2026-08-17 14:14:38',
          'days' => 0,
          'default' => 0,
          'color' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Procesos',
          'description' => '',
          'active' => 1,
          'created_at' => '2026-08-17 14:14:38',
          'updated_at' => '2026-08-17 14:14:38',
          'days' => 0,
          'default' => 0,
          'color' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Seguimiento',
          'description' => '',
          'active' => 1,
          'created_at' => '2026-08-17 14:14:38',
          'updated_at' => '2026-08-17 14:14:38',
          'days' => 0,
          'default' => 0,
          'color' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'Validacion',
          'description' => '',
          'active' => 1,
          'created_at' => '2026-08-17 14:14:38',
          'updated_at' => '2026-08-17 14:14:38',
          'days' => 0,
          'default' => 0,
          'color' => NULL,
        ),
      ),
    ),
    'expense_method_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'CAJA GENERAL',
          'has_card' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Tarjeta de crédito',
          'has_card' => 1,
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'Tarjeta de débito',
          'has_card' => 1,
        ),
      ),
    ),
    'expense_reasons' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Varios',
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Representación de la organización',
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'Trabajo de campo',
        ),
      ),
    ),
    'expense_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'PLANILLA',
          'created_at' => '2026-08-17 14:14:12',
          'updated_at' => '2026-08-17 14:14:12',
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'RECIBO POR HONORARIO',
          'created_at' => '2026-08-17 14:14:12',
          'updated_at' => '2026-08-17 14:14:12',
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'SERVICIO PÚBLICO',
          'created_at' => '2026-08-17 14:14:12',
          'updated_at' => '2026-08-17 14:14:12',
        ),
        3 => 
        array (
          'id' => 4,
          'description' => 'OTROS',
          'created_at' => '2026-08-17 14:14:12',
          'updated_at' => '2026-08-17 14:14:12',
        ),
      ),
    ),
    'general_payment_conditions' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'name' => 'Contado',
        ),
        1 => 
        array (
          'id' => '02',
          'name' => 'Crédito',
        ),
        2 => 
        array (
          'id' => '03',
          'name' => 'Crédito con cuotas',
        ),
      ),
    ),
    'groups' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Facturas',
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Boletas',
        ),
      ),
    ),
    'income_reasons' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Varios',
        ),
      ),
    ),
    'income_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'INGRESOS FINANCIEROS',
          'created_at' => '2026-08-17 14:14:28',
          'updated_at' => '2026-08-17 14:14:28',
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'PRESTAMOS',
          'created_at' => '2026-08-17 14:14:28',
          'updated_at' => '2026-08-17 14:14:28',
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'OTROS',
          'created_at' => '2026-08-17 14:14:28',
          'updated_at' => '2026-08-17 14:14:28',
        ),
      ),
    ),
    'inventory_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'stock_control' => 0,
          'validate_stock_add_item' => 0,
          'inventory_review' => 0,
          'created_at' => NULL,
          'updated_at' => NULL,
          'generate_internal_id' => 1,
        ),
      ),
    ),
    'inventory_transactions' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'name' => 'Venta nacional',
          'type' => 'output',
        ),
        1 => 
        array (
          'id' => '02',
          'name' => 'Compra nacional',
          'type' => 'input',
        ),
        2 => 
        array (
          'id' => '03',
          'name' => 'Consignación recibida',
          'type' => 'input',
        ),
        3 => 
        array (
          'id' => '04',
          'name' => 'Consignación entregada',
          'type' => 'output',
        ),
        4 => 
        array (
          'id' => '05',
          'name' => 'Devolución recibida',
          'type' => 'input',
        ),
        5 => 
        array (
          'id' => '06',
          'name' => 'Devolución entregada',
          'type' => 'output',
        ),
        6 => 
        array (
          'id' => '07',
          'name' => 'Bonificación',
          'type' => 'output',
        ),
        7 => 
        array (
          'id' => '08',
          'name' => 'Premio',
          'type' => 'output',
        ),
        8 => 
        array (
          'id' => '09',
          'name' => 'Donación',
          'type' => 'output',
        ),
        9 => 
        array (
          'id' => '10',
          'name' => 'Salida a producción',
          'type' => 'output',
        ),
        10 => 
        array (
          'id' => '100',
          'name' => 'Ingreso insumos por molino',
          'type' => 'input',
        ),
        11 => 
        array (
          'id' => '101',
          'name' => 'Salida por insumo',
          'type' => 'output',
        ),
        12 => 
        array (
          'id' => '102',
          'name' => 'Entrada por importacion masiva (xlsx)',
          'type' => 'input',
        ),
        13 => 
        array (
          'id' => '11',
          'name' => 'Salida por transferencia entre almacenes',
          'type' => 'output',
        ),
        14 => 
        array (
          'id' => '12',
          'name' => 'Retiro',
          'type' => 'output',
        ),
        15 => 
        array (
          'id' => '13',
          'name' => 'Mermas',
          'type' => 'output',
        ),
        16 => 
        array (
          'id' => '14',
          'name' => 'Desmedros',
          'type' => 'output',
        ),
        17 => 
        array (
          'id' => '15',
          'name' => 'Destrucción',
          'type' => 'output',
        ),
        18 => 
        array (
          'id' => '16',
          'name' => 'Inventario inicial',
          'type' => 'input',
        ),
        19 => 
        array (
          'id' => '17',
          'name' => 'Exportación',
          'type' => 'output',
        ),
        20 => 
        array (
          'id' => '18',
          'name' => 'Entrada de importación',
          'type' => 'input',
        ),
        21 => 
        array (
          'id' => '19',
          'name' => 'Ingreso de producción',
          'type' => 'input',
        ),
        22 => 
        array (
          'id' => '20',
          'name' => 'Entrada por devolución de producción',
          'type' => 'input',
        ),
        23 => 
        array (
          'id' => '21',
          'name' => 'Entrada por transferencia entre almacenes',
          'type' => 'input',
        ),
        24 => 
        array (
          'id' => '22',
          'name' => 'Entrada por identificación erronea',
          'type' => 'input',
        ),
        25 => 
        array (
          'id' => '23',
          'name' => 'Salida por identificación erronea',
          'type' => 'output',
        ),
        26 => 
        array (
          'id' => '24',
          'name' => 'Entrada por devolución del cliente',
          'type' => 'input',
        ),
        27 => 
        array (
          'id' => '25',
          'name' => 'Salida por devolución al proveedor',
          'type' => 'output',
        ),
        28 => 
        array (
          'id' => '26',
          'name' => 'Entrada para servicio de producción',
          'type' => 'input',
        ),
        29 => 
        array (
          'id' => '27',
          'name' => 'Salida por servicio de producción',
          'type' => 'output',
        ),
        30 => 
        array (
          'id' => '28',
          'name' => 'Ajuste por diferencia de inventario',
          'type' => 'output',
        ),
        31 => 
        array (
          'id' => '29',
          'name' => 'Entrada de bienes en prestamo',
          'type' => 'input',
        ),
        32 => 
        array (
          'id' => '30',
          'name' => 'Salida de bienes en prestamo',
          'type' => 'output',
        ),
        33 => 
        array (
          'id' => '31',
          'name' => 'Entrada de bienes en custodia',
          'type' => 'input',
        ),
        34 => 
        array (
          'id' => '32',
          'name' => 'Salida de bienes en custodia',
          'type' => 'output',
        ),
        35 => 
        array (
          'id' => '33',
          'name' => 'Muestras médicas',
          'type' => 'output',
        ),
        36 => 
        array (
          'id' => '34',
          'name' => 'Publicidad',
          'type' => 'output',
        ),
        37 => 
        array (
          'id' => '35',
          'name' => 'Gastos de representación',
          'type' => 'output',
        ),
        38 => 
        array (
          'id' => '36',
          'name' => 'Retiro para entrega a trabajadores',
          'type' => 'output',
        ),
        39 => 
        array (
          'id' => '37',
          'name' => 'Retiro por convenio colectivo',
          'type' => 'output',
        ),
        40 => 
        array (
          'id' => '38',
          'name' => 'Retiro por sustitución de bien siniestrado',
          'type' => 'output',
        ),
        41 => 
        array (
          'id' => '50',
          'name' => 'Ingreso temporal',
          'type' => 'input',
        ),
        42 => 
        array (
          'id' => '51',
          'name' => 'Salida temporal',
          'type' => 'output',
        ),
        43 => 
        array (
          'id' => '52',
          'name' => 'Ingreso por transformación',
          'type' => 'input',
        ),
        44 => 
        array (
          'id' => '53',
          'name' => 'Salida para servicios terceros',
          'type' => 'output',
        ),
        45 => 
        array (
          'id' => '54',
          'name' => 'Ingreso de producción',
          'type' => 'input',
        ),
        46 => 
        array (
          'id' => '55',
          'name' => 'Entrada de importación',
          'type' => 'input',
        ),
        47 => 
        array (
          'id' => '56',
          'name' => 'Salida por conversión de medida',
          'type' => 'output',
        ),
        48 => 
        array (
          'id' => '57',
          'name' => 'Entrada por conversión de medida',
          'type' => 'input',
        ),
        49 => 
        array (
          'id' => '91',
          'name' => 'Ingreso por transformación',
          'type' => 'input',
        ),
        50 => 
        array (
          'id' => '93',
          'name' => 'Ingreso temporal',
          'type' => 'input',
        ),
        51 => 
        array (
          'id' => '96',
          'name' => 'Entrada por conversión de medida',
          'type' => 'input',
        ),
        52 => 
        array (
          'id' => '99',
          'name' => 'Otros',
          'type' => 'input',
        ),
      ),
    ),
    'item_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Producto',
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Servicio',
        ),
      ),
    ),
    'module_level_user' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'module_level_id' => 76,
          'user_id' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'module_level_id' => 77,
          'user_id' => 0,
        ),
        2 => 
        array (
          'id' => 3,
          'module_level_id' => 78,
          'user_id' => 0,
        ),
      ),
    ),
    'modules' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'value' => 'documents',
          'description' => 'Ventas',
          'order_menu' => 3,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'value' => 'purchases',
          'description' => 'Compras',
          'order_menu' => 4,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'value' => 'advanced',
          'description' => 'Documentos Avanzados',
          'order_menu' => 11,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'value' => 'reports',
          'description' => 'Reportes',
          'order_menu' => 13,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        4 => 
        array (
          'id' => 5,
          'value' => 'configuration',
          'description' => 'Configuration',
          'order_menu' => 25,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        5 => 
        array (
          'id' => 7,
          'value' => 'dashboard',
          'description' => 'Dashboard',
          'order_menu' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        6 => 
        array (
          'id' => 8,
          'value' => 'inventory',
          'description' => 'Inventario',
          'order_menu' => 7,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        7 => 
        array (
          'id' => 9,
          'value' => 'accounting',
          'description' => 'Contabilidad',
          'order_menu' => 12,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        8 => 
        array (
          'id' => 10,
          'value' => 'ecommerce',
          'description' => 'Ecommerce',
          'order_menu' => 14,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        9 => 
        array (
          'id' => 11,
          'value' => 'cuenta',
          'description' => 'Cuenta',
          'order_menu' => 22,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        10 => 
        array (
          'id' => 12,
          'value' => 'finance',
          'description' => 'Finanzas',
          'order_menu' => 8,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        11 => 
        array (
          'id' => 14,
          'value' => 'establishments',
          'description' => 'Usuarios/Locales & Series',
          'order_menu' => 23,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        12 => 
        array (
          'id' => 15,
          'value' => 'hotels',
          'description' => 'Hoteles',
          'order_menu' => 17,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        13 => 
        array (
          'id' => 16,
          'value' => 'documentary-procedure',
          'description' => 'Trámite documentario',
          'order_menu' => 20,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        14 => 
        array (
          'id' => 17,
          'value' => 'items',
          'description' => 'Productos/Servicios',
          'order_menu' => 6,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        15 => 
        array (
          'id' => 18,
          'value' => 'persons',
          'description' => 'Clientes',
          'order_menu' => 5,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        16 => 
        array (
          'id' => 19,
          'value' => 'digemid',
          'description' => 'Farmacia',
          'order_menu' => 16,
          'created_at' => '2026-08-17 14:14:37',
          'updated_at' => '2026-08-17 14:14:37',
        ),
        17 => 
        array (
          'id' => 20,
          'value' => 'apps',
          'description' => 'Apps',
          'order_menu' => 24,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        18 => 
        array (
          'id' => 21,
          'value' => 'suscription_app',
          'description' => 'Suscriptiones',
          'order_menu' => 19,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        19 => 
        array (
          'id' => 22,
          'value' => 'production_app',
          'description' => 'Producción',
          'order_menu' => 21,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        20 => 
        array (
          'id' => 23,
          'value' => 'restaurant_app',
          'description' => 'Restaurante',
          'order_menu' => 15,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        21 => 
        array (
          'id' => 24,
          'value' => 'full_suscription_app',
          'description' => 'Suscripción Servicios SAAS',
          'order_menu' => 18,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        22 => 
        array (
          'id' => 26,
          'value' => 'app_2_generator',
          'description' => 'Generador APP 2.0',
          'order_menu' => 26,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        23 => 
        array (
          'id' => 50,
          'value' => 'preventa',
          'description' => 'PreVenta',
          'order_menu' => 2,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        24 => 
        array (
          'id' => 51,
          'value' => 'guia',
          'description' => 'Guías de Remisión',
          'order_menu' => 9,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        25 => 
        array (
          'id' => 52,
          'value' => 'comprobante',
          'description' => 'Comprobantes Pendientes',
          'order_menu' => 10,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        26 => 
        array (
          'id' => 53,
          'value' => 'claims_book',
          'description' => 'Libro de Reclamaciones',
          'order_menu' => 25,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        27 => 
        array (
          'id' => 54,
          'value' => 'webhooks',
          'description' => 'Webhooks',
          'order_menu' => 26,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'offline_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'is_client' => 0,
          'token_server' => NULL,
          'url_server' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'payment_conditions' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'name' => 'Contado',
          'days' => 0,
          'is_locked' => 1,
          'is_active' => 1,
        ),
        1 => 
        array (
          'id' => '02',
          'name' => 'Crédito',
          'days' => 0,
          'is_locked' => 1,
          'is_active' => 1,
        ),
      ),
    ),
    'payment_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'enabled_yape' => 0,
          'qrcode_yape' => NULL,
          'name_yape' => NULL,
          'telephone_yape' => NULL,
          'enabled_mp' => 0,
          'access_token_mp' => NULL,
          'public_key_mp' => NULL,
          'publickey_culqi' => NULL,
          'privatekey_culqi' => NULL,
          'enabled_culqi' => 0,
          'enabled_izipay' => 0,
          'default_payment_for_payment_links' => NULL,
          'idrsa_culqi' => NULL,
          'rsa_culqi' => NULL,
          'username_izipay' => NULL,
          'password_izipay' => NULL,
          'publickey_izipay' => NULL,
          'sha256key_izipay' => NULL,
          'created_at' => '2026-08-17 14:14:51',
          'updated_at' => '2026-08-17 14:14:51',
        ),
      ),
    ),
    'payment_link_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Yape',
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Mercado Pago',
        ),
      ),
    ),
    'payment_method_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Efectivo',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Tarjeta de crédito',
          'has_card' => 1,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        2 => 
        array (
          'id' => '03',
          'description' => 'Tarjeta de débito',
          'has_card' => 1,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        3 => 
        array (
          'id' => '04',
          'description' => 'Transferencia',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 0,
          'is_active' => 1,
        ),
        4 => 
        array (
          'id' => '05',
          'description' => 'Factura a 30 días',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => 30,
          'is_credit' => 1,
          'is_cash' => 0,
          'is_active' => 1,
        ),
        5 => 
        array (
          'id' => '06',
          'description' => 'Tarjeta crédito visa',
          'has_card' => 1,
          'charge' => '3.68',
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        6 => 
        array (
          'id' => '07',
          'description' => 'Contado contraentrega',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 0,
          'is_active' => 1,
        ),
        7 => 
        array (
          'id' => '08',
          'description' => 'A 30 días',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => 30,
          'is_credit' => 1,
          'is_cash' => 0,
          'is_active' => 1,
        ),
        8 => 
        array (
          'id' => '09',
          'description' => 'Crédito',
          'has_card' => 1,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 1,
          'is_cash' => 0,
          'is_active' => 1,
        ),
      ),
    ),
    'price_labels' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'position' => 1,
          'label' => 'Precio 1',
          'is_active' => 1,
          'is_default' => 0,
          'created_at' => '2026-08-17 14:15:24',
          'updated_at' => '2026-08-17 14:15:24',
        ),
        1 => 
        array (
          'id' => 2,
          'position' => 2,
          'label' => 'Precio 2',
          'is_active' => 1,
          'is_default' => 0,
          'created_at' => '2026-08-17 14:15:24',
          'updated_at' => '2026-08-17 14:15:24',
        ),
        2 => 
        array (
          'id' => 3,
          'position' => 3,
          'label' => 'Precio 3',
          'is_active' => 1,
          'is_default' => 0,
          'created_at' => '2026-08-17 14:15:24',
          'updated_at' => '2026-08-17 14:15:24',
        ),
      ),
    ),
    'pse_providers' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'contaweb',
          'description' => 'ContaWeb A&M',
          'active' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'gior',
          'description' => 'Gior Technology',
          'active' => 1,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'qpse',
          'description' => 'QPSE',
          'active' => 1,
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'sendfact',
          'description' => 'SendFact',
          'active' => 1,
        ),
        4 => 
        array (
          'id' => 5,
          'name' => 'validapse',
          'description' => 'Validapse',
          'active' => 1,
        ),
      ),
    ),
    'report_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'route_name' => 'tenant.reports.general_items.index',
          'route_path' => 'reports/general-items',
          'name' => 'Ventas - Reporte general de productos',
          'convert_pen' => 0,
          'created_at' => '2026-08-17 14:14:49',
          'updated_at' => '2026-08-17 14:14:49',
        ),
        1 => 
        array (
          'id' => 2,
          'route_name' => 'tenant.reports.purchases.general_items.index',
          'route_path' => 'reports/purchases/general_items',
          'name' => 'Compras - Reporte general de productos',
          'convert_pen' => 0,
          'created_at' => '2026-08-17 14:14:49',
          'updated_at' => '2026-08-17 14:14:49',
        ),
        2 => 
        array (
          'id' => 3,
          'route_name' => 'tenant.reports.purchases.index',
          'route_path' => 'reports/purchases',
          'name' => 'Compras - Compras totales',
          'convert_pen' => 0,
          'created_at' => '2026-08-17 14:14:49',
          'updated_at' => '2026-08-17 14:14:49',
        ),
      ),
    ),
    'restaurant_configurations' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'menu_pos' => 1,
          'menu_order' => 1,
          'menu_tables' => 1,
          'first_menu' => 'POS',
          'tables_quantity' => 15,
          'menu_bar' => 1,
          'menu_kitchen' => 1,
          'items_maintenance' => 0,
          'enabled_environment_1' => 1,
          'enabled_environment_2' => 0,
          'tables_quantity_environment_2' => 5,
          'enabled_environment_3' => 0,
          'tables_quantity_environment_3' => 5,
          'enabled_environment_4' => 0,
          'tables_quantity_environment_4' => 5,
          'enabled_send_command' => 0,
          'enabled_print_command' => 1,
          'enabled_print_group_commands' => 0,
          'enabled_printsend_command' => 0,
          'enabled_command_waiter' => 0,
          'enabled_pos_waiter' => 0,
          'enabled_close_table' => 1,
          'enabled_close_table_mozo' => 0,
          'enabled_server_print' => 0,
          'replace_template_mozo' => 0,
          'printer_enabled' => 0,
          'printer_host' => NULL,
          'printer_status' => NULL,
          'printer_public_ip' => NULL,
          'print_local_enabled' => 0,
          'print_destination' => 1,
          'printer_name_comanda' => NULL,
          'printer_name_documents' => NULL,
          'printer_name_precuenta' => NULL,
          'printer_areas_enabled' => 0,
          'printer_per_area_enabled' => 0,
        ),
      ),
    ),
    'restaurant_roles' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'code' => 'MOZO',
          'name' => 'Mozo',
          'description' => 'Usuario que genera pedidos en mesas',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'code' => 'CAJA',
          'name' => 'Caja',
          'description' => 'Usuario que genera pago de pedidos',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'code' => 'ADM',
          'name' => 'Administrador',
          'description' => 'Usuario con permisos totales',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 6,
          'code' => 'KITBAR',
          'name' => 'Cocina/Bar',
          'description' => 'Usuario con acceso a cocina y bar',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'restaurant_table_envs' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Delivery',
          'active' => 0,
          'tables_quantity' => 0,
          'is_delivery' => 1,
          'is_takeaway' => 0,
          'can_edit' => 0,
          'can_deactivate' => 1,
          'can_delete' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Para Llevar',
          'active' => 0,
          'tables_quantity' => 0,
          'is_delivery' => 0,
          'is_takeaway' => 1,
          'can_edit' => 0,
          'can_deactivate' => 1,
          'can_delete' => 0,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Ambiente 1',
          'active' => 1,
          'tables_quantity' => 25,
          'is_delivery' => 0,
          'is_takeaway' => 0,
          'can_edit' => 1,
          'can_deactivate' => 0,
          'can_delete' => 0,
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'Ambiente 2',
          'active' => 0,
          'tables_quantity' => 25,
          'is_delivery' => 0,
          'is_takeaway' => 0,
          'can_edit' => 1,
          'can_deactivate' => 1,
          'can_delete' => 1,
        ),
      ),
    ),
    'skins' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Default',
          'filename' => 'default.css',
          'status' => 1,
          'is_system' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Light',
          'filename' => 'light.css',
          'status' => 1,
          'is_system' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Black',
          'filename' => 'black.css',
          'status' => 1,
          'is_system' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'Modern',
          'filename' => 'modern.css',
          'status' => 1,
          'is_system' => 1,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'soap_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Demo',
        ),
        1 => 
        array (
          'id' => '02',
          'description' => 'Producción',
        ),
        2 => 
        array (
          'id' => '03',
          'description' => 'Interno',
        ),
      ),
    ),
    'state_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '01',
          'description' => 'Registrado',
        ),
        1 => 
        array (
          'id' => '03',
          'description' => 'Enviado',
        ),
        2 => 
        array (
          'id' => '05',
          'description' => 'Aceptado',
        ),
        3 => 
        array (
          'id' => '07',
          'description' => 'Observado',
        ),
        4 => 
        array (
          'id' => '09',
          'description' => 'Rechazado',
        ),
        5 => 
        array (
          'id' => '11',
          'description' => 'Anulado',
        ),
        6 => 
        array (
          'id' => '13',
          'description' => 'Por anular',
        ),
      ),
    ),
    'status_claims' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Registrado',
          'color' => '#409EFF',
          'sort_order' => 0,
          'is_initial' => 1,
          'is_final' => 0,
          'action_send_email' => 0,
          'assigned_user_id' => NULL,
          'created_at' => '2026-08-17 14:15:25',
          'updated_at' => '2026-08-17 14:15:25',
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Leido',
          'color' => '#67C23A',
          'sort_order' => 1,
          'is_initial' => 0,
          'is_final' => 0,
          'action_send_email' => 0,
          'assigned_user_id' => NULL,
          'created_at' => '2026-08-17 14:15:25',
          'updated_at' => '2026-08-17 14:15:25',
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'Cerrado',
          'color' => '#F56C6C',
          'sort_order' => 3,
          'is_initial' => 0,
          'is_final' => 1,
          'action_send_email' => 1,
          'assigned_user_id' => NULL,
          'created_at' => '2026-08-17 14:15:25',
          'updated_at' => '2026-08-17 14:15:25',
        ),
        3 => 
        array (
          'id' => 4,
          'description' => 'Borrador',
          'color' => '#E6A23C',
          'sort_order' => 2,
          'is_initial' => 0,
          'is_final' => 0,
          'action_send_email' => 0,
          'assigned_user_id' => NULL,
          'created_at' => '2026-08-17 14:15:26',
          'updated_at' => '2026-08-17 14:15:26',
        ),
      ),
    ),
    'status_orders' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Pago sin verificar',
          'color' => '#909399',
          'sort_order' => 0,
          'is_initial' => 1,
          'is_final' => 0,
          'is_payment_status' => 1,
          'is_order_status' => 0,
          'is_shipping_status' => 0,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-08-17 14:14:29',
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Pago verificado',
          'color' => '#409EFF',
          'sort_order' => 1,
          'is_initial' => 0,
          'is_final' => 0,
          'is_payment_status' => 1,
          'is_order_status' => 0,
          'is_shipping_status' => 0,
          'action_generate_document' => 1,
          'action_discount_stock' => 0,
          'action_mark_payment' => 1,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-08-17 14:14:29',
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'description' => 'Despachado',
          'color' => '#E6A23C',
          'sort_order' => 2,
          'is_initial' => 0,
          'is_final' => 0,
          'is_payment_status' => 0,
          'is_order_status' => 1,
          'is_shipping_status' => 0,
          'action_generate_document' => 0,
          'action_discount_stock' => 1,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 1,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-08-17 14:14:29',
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'description' => 'Confirmado por el cliente',
          'color' => '#67C23A',
          'sort_order' => 3,
          'is_initial' => 0,
          'is_final' => 0,
          'is_payment_status' => 0,
          'is_order_status' => 1,
          'is_shipping_status' => 0,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-08-17 14:14:29',
          'updated_at' => NULL,
        ),
      ),
    ),
    'suscription_grade' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => '1er',
        ),
        1 => 
        array (
          'id' => 2,
          'name' => '2do',
        ),
        2 => 
        array (
          'id' => 3,
          'name' => '3ro',
        ),
        3 => 
        array (
          'id' => 4,
          'name' => '4to',
        ),
        4 => 
        array (
          'id' => 5,
          'name' => '5to',
        ),
        5 => 
        array (
          'id' => 6,
          'name' => '6to',
        ),
        6 => 
        array (
          'id' => 7,
          'name' => '7mo',
        ),
        7 => 
        array (
          'id' => 8,
          'name' => '8vo',
        ),
      ),
    ),
    'suscription_plans' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'cat_period_id' => 1,
          'name' => 'Matricula Escolar',
          'description' => 'Demostración de matricula escolar',
          'total' => 1.0,
          'currency_type_id' => 'PEN',
          'payment_method_type_id' => '01',
          'quantity_period' => 12,
          'unlimited' => 0,
          'trial_days' => NULL,
          'status' => 1,
          'exchange_rate_sale' => 0.0,
          'total_prepayment' => 0.0,
          'total_charge' => 0.0,
          'total_discount' => 0.0,
          'total_exportation' => 0.0,
          'total_free' => 0.0,
          'total_taxed' => 0.0,
          'total_unaffected' => 0.0,
          'total_exonerated' => 0.0,
          'total_igv' => 0.0,
          'total_igv_free' => 0.0,
          'total_base_isc' => 0.0,
          'total_isc' => 0.0,
          'total_base_other_taxes' => 0.0,
          'total_other_taxes' => 0.0,
          'total_taxes' => 0.0,
          'total_value' => 0.0,
          'charges' => NULL,
          'attributes' => NULL,
          'discounts' => NULL,
          'prepayments' => NULL,
          'related' => NULL,
          'perception' => NULL,
          'detraction' => NULL,
          'legends' => NULL,
          'terms_condition' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'suscription_section' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'A',
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'B',
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'C',
        ),
        3 => 
        array (
          'id' => 4,
          'name' => 'D',
        ),
        4 => 
        array (
          'id' => 5,
          'name' => 'E',
        ),
        5 => 
        array (
          'id' => 6,
          'name' => 'F',
        ),
        6 => 
        array (
          'id' => 7,
          'name' => 'G',
        ),
        7 => 
        array (
          'id' => 8,
          'name' => 'H',
        ),
      ),
    ),
    'system_activity_log_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 'companies_certificate',
          'description' => 'Actualización del campo certificado en configuración de empresa',
        ),
        1 => 
        array (
          'id' => 'companies_name',
          'description' => 'Actualización del campo nombre en configuración de empresa',
        ),
        2 => 
        array (
          'id' => 'companies_number',
          'description' => 'Actualización del campo número en configuración de empresa',
        ),
        3 => 
        array (
          'id' => 'companies_soap_password',
          'description' => 'Actualización del campo SOAP Contraseña en configuración de empresa',
        ),
        4 => 
        array (
          'id' => 'companies_soap_send_id',
          'description' => 'Actualización del campo SOAP envío en configuración de empresa',
        ),
        5 => 
        array (
          'id' => 'companies_soap_type_id',
          'description' => 'Actualización del campo SOAP tipo en configuración de empresa',
        ),
        6 => 
        array (
          'id' => 'companies_soap_url',
          'description' => 'Actualización del campo SOAP url envío en configuración de empresa',
        ),
        7 => 
        array (
          'id' => 'companies_soap_username',
          'description' => 'Actualización del campo SOAP Usuario en configuración de empresa',
        ),
        8 => 
        array (
          'id' => 'failed',
          'description' => 'Error de inicio de sesión',
        ),
        9 => 
        array (
          'id' => 'level_module_access_error',
          'description' => 'Error de acceso al submódulo (no tiene permiso)',
        ),
        10 => 
        array (
          'id' => 'login',
          'description' => 'Iniciar sesión',
        ),
        11 => 
        array (
          'id' => 'login_lockout',
          'description' => 'Bloqueo de usuario por exceder límite de intentos permitidos al iniciar sesión',
        ),
        12 => 
        array (
          'id' => 'logout',
          'description' => 'Cerrar sesión',
        ),
        13 => 
        array (
          'id' => 'module_access_error',
          'description' => 'Error de acceso al módulo (no tiene permiso)',
        ),
      ),
    ),
    'transaction_states' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '00',
          'name' => 'Rechazado (Error desconocido)',
          'success' => 0,
          'status' => 'other',
          'status_detail' => 'other',
          'original_message' => 'Error desconocido',
          'user_message' => 'Lo sentimos, ocurrió un error inesperado.',
        ),
        1 => 
        array (
          'id' => '01',
          'name' => 'Aceptado',
          'success' => 1,
          'status' => 'approved',
          'status_detail' => 'accredited',
          'original_message' => '¡Listo! Se acreditó tu pago. En tu resumen verás el cargo de amount como statement_descriptor.',
          'user_message' => '¡Listo! Se acreditó tu pago. En tu resumen verás el cargo del pago.',
        ),
        2 => 
        array (
          'id' => '02',
          'name' => 'En proceso',
          'success' => 1,
          'status' => 'in_process',
          'status_detail' => 'pending_contingency',
          'original_message' => 'Estamos procesando tu pago. No te preocupes, menos de 2 días hábiles te avisaremos por e-mail si se acreditó.',
          'user_message' => 'Estamos procesando tu pago. No te preocupes, en menos de 2 días hábiles te avisaremos por e-mail si se acreditó.',
        ),
        3 => 
        array (
          'id' => '03',
          'name' => 'En proceso',
          'success' => 1,
          'status' => 'in_process',
          'status_detail' => 'pending_review_manual',
          'original_message' => 'Estamos procesando tu pago. No te preocupes, menos de 2 días hábiles te avisaremos por e-mail si se acreditó o si necesitamos más información.',
          'user_message' => 'Estamos procesando tu pago. No te preocupes, en menos de 2 días hábiles te avisaremos por e-mail si se acreditó o si necesitamos más información.',
        ),
        4 => 
        array (
          'id' => '04',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_bad_filled_card_number',
          'original_message' => 'Revisa el número de tarjeta.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. Revisa el número de tarjeta.',
        ),
        5 => 
        array (
          'id' => '05',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_bad_filled_date',
          'original_message' => 'Revisa la fecha de vencimiento.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. Revisa la fecha de vencimiento.',
        ),
        6 => 
        array (
          'id' => '06',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_bad_filled_other',
          'original_message' => 'Revisa los datos.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. Revisa los datos.',
        ),
        7 => 
        array (
          'id' => '07',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_bad_filled_security_code',
          'original_message' => 'Revisa el código de seguridad de la tarjeta.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. Revisa el código de seguridad de la tarjeta.',
        ),
        8 => 
        array (
          'id' => '08',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_blacklist',
          'original_message' => 'No pudimos procesar tu pago.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. No pudimos procesar tu pago.',
        ),
        9 => 
        array (
          'id' => '09',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_call_for_authorize',
          'original_message' => 'Debes autorizar ante payment_method_id el pago de amount.',
          'user_message' => 'Debes autorizar ante el medio de pago, el pago a realizar.',
        ),
        10 => 
        array (
          'id' => '10',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_card_disabled',
          'original_message' => 'Llama a payment_method_id para activar tu tarjeta o usa otro medio de pago. El teléfono está al dorso de tu tarjeta.',
          'user_message' => 'Llama a la entidad para activar tu tarjeta o usa otro medio de pago. El teléfono está al dorso de tu tarjeta.',
        ),
        11 => 
        array (
          'id' => '11',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_card_error',
          'original_message' => 'No pudimos procesar tu pago.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. No pudimos procesar tu pago.',
        ),
        12 => 
        array (
          'id' => '12',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_duplicated_payment',
          'original_message' => 'Ya hiciste un pago por ese valor. Si necesitas volver a pagar usa otra tarjeta u otro medio de pago.',
          'user_message' => 'Ya hiciste un pago por ese valor. Si necesitas volver a pagar usa otra tarjeta u otro medio de pago.',
        ),
        13 => 
        array (
          'id' => '13',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_high_risk',
          'original_message' => 'Tu pago fue rechazado. Elige otro de los medios de pago, te recomendamos con medios en efectivo.',
          'user_message' => 'Tu pago fue rechazado. Elige otro de los medios de pago, te recomendamos con medios en efectivo.',
        ),
        14 => 
        array (
          'id' => '14',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_insufficient_amount',
          'original_message' => 'Tu payment_method_id no tiene fondos suficientes.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. Tu medio de pago no tiene fondos suficientes.',
        ),
        15 => 
        array (
          'id' => '15',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_invalid_installments',
          'original_message' => 'payment_method_id no procesa pagos en installments cuotas.',
          'user_message' => 'Para el medio de pago ingresado, no se puede procesar los pagos en la cantidad de cuotas seleccionadas.',
        ),
        16 => 
        array (
          'id' => '16',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_max_attempts',
          'original_message' => 'Llegaste al límite de intentos permitidos. Elige otra tarjeta u otro medio de pago.',
          'user_message' => 'Llegaste al límite de intentos permitidos. Elige otra tarjeta u otro medio de pago.',
        ),
        17 => 
        array (
          'id' => '17',
          'name' => 'Rechazado',
          'success' => 0,
          'status' => 'rejected',
          'status_detail' => 'cc_rejected_other_reason',
          'original_message' => 'payment_method_id no procesó el pago.',
          'user_message' => 'Lo sentimos, ocurrió un inconveniente. El pago no pudo ser procesado por el medio usado',
        ),
      ),
    ),
    'web_platforms' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => 'Saga Falabella',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Mercado Libre ',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'name' => 'Linio',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'cat_detraction_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '001',
          'active' => 1,
          'description' => 'Azúcar y melaza de caña',
          'percentage' => '10.00',
          'operation_type_id' => '1001',
        ),
        1 => 
        array (
          'id' => '003',
          'active' => 1,
          'description' => 'Alcohol etílico',
          'percentage' => '10.00',
          'operation_type_id' => '1001',
        ),
        2 => 
        array (
          'id' => '005',
          'active' => 1,
          'description' => 'Maíz amarillo duro',
          'percentage' => '4.00',
          'operation_type_id' => '1001',
        ),
        3 => 
        array (
          'id' => '008',
          'active' => 1,
          'description' => 'Madera',
          'percentage' => '4.00',
          'operation_type_id' => '1001',
        ),
        4 => 
        array (
          'id' => '016',
          'active' => 1,
          'description' => 'Aceite de pescado',
          'percentage' => '10.00',
          'operation_type_id' => '1001',
        ),
        5 => 
        array (
          'id' => '019',
          'active' => 1,
          'description' => 'Arrendamiento de bienes',
          'percentage' => '10.00',
          'operation_type_id' => '1001',
        ),
        6 => 
        array (
          'id' => '020',
          'active' => 1,
          'description' => 'Mantenimiento y reparación de bienes muebles',
          'percentage' => '12.00',
          'operation_type_id' => '1001',
        ),
        7 => 
        array (
          'id' => '022',
          'active' => 1,
          'description' => 'Otros servicios empresariales',
          'percentage' => '12.00',
          'operation_type_id' => '1001',
        ),
        8 => 
        array (
          'id' => '023',
          'active' => 1,
          'description' => 'Leche',
          'percentage' => '4.00',
          'operation_type_id' => '1001',
        ),
        9 => 
        array (
          'id' => '025',
          'active' => 1,
          'description' => 'Fabricación de bienes por encargo',
          'percentage' => '10.00',
          'operation_type_id' => '1001',
        ),
        10 => 
        array (
          'id' => '027',
          'active' => 1,
          'description' => 'Servicio de transporte de carga',
          'percentage' => '4.00',
          'operation_type_id' => '1004',
        ),
        11 => 
        array (
          'id' => '030',
          'active' => 1,
          'description' => 'Contratos de construcción',
          'percentage' => '4.00',
          'operation_type_id' => '1001',
        ),
      ),
    ),
    'client_errors' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'code' => '205',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter cardNumber can not be null/empty',
          'user_message' => 'Ingresa el número de tu tarjeta.',
        ),
        1 => 
        array (
          'id' => 2,
          'code' => '208',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter cardExpirationMonth can not be null/empty',
          'user_message' => 'Elige un mes.',
        ),
        2 => 
        array (
          'id' => 3,
          'code' => '209',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter cardExpirationYear can not be null/empty',
          'user_message' => 'Elige un año.',
        ),
        3 => 
        array (
          'id' => 4,
          'code' => '212',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter docType can not be null/empty',
          'user_message' => 'Ingresa tu tipo de documento.',
        ),
        4 => 
        array (
          'id' => 5,
          'code' => '213',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'The parameter cardholder.document.subtype can not be null or empty',
          'user_message' => 'Ingresa tu documento.',
        ),
        5 => 
        array (
          'id' => 6,
          'code' => '214',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter docNumber can not be null/empty',
          'user_message' => 'Ingresa tu documento.',
        ),
        6 => 
        array (
          'id' => 7,
          'code' => '220',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter cardIssuerId can not be null/empty',
          'user_message' => 'Ingresa tu banco.',
        ),
        7 => 
        array (
          'id' => 8,
          'code' => '221',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter cardholderName can not be null/empty',
          'user_message' => 'Ingresa el nombre y apellido.',
        ),
        8 => 
        array (
          'id' => 9,
          'code' => '224',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'parameter securityCode can not be null/empty',
          'user_message' => 'Ingresa el código de seguridad.',
        ),
        9 => 
        array (
          'id' => 10,
          'code' => 'E301',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter cardNumber',
          'user_message' => 'Ingresa un número de tarjeta válido.',
        ),
        10 => 
        array (
          'id' => 11,
          'code' => 'E302',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter securityCode',
          'user_message' => 'Revisa el código de seguridad.',
        ),
        11 => 
        array (
          'id' => 12,
          'code' => '316',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter cardholderName',
          'user_message' => 'Ingresa un nombre válido.',
        ),
        12 => 
        array (
          'id' => 13,
          'code' => '322',
          'client_error_type_id' => 'data_entry',
          'original_message' => '	invalid parameter docType',
          'user_message' => 'El tipo de documento es inválido.',
        ),
        13 => 
        array (
          'id' => 14,
          'code' => '323',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter cardholder.document.subtype',
          'user_message' => 'Revisa tu documento.',
        ),
        14 => 
        array (
          'id' => 15,
          'code' => '324',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter docNumber',
          'user_message' => 'El documento es inválido.',
        ),
        15 => 
        array (
          'id' => 16,
          'code' => '325',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter cardExpirationMonth',
          'user_message' => 'El mes es inválido',
        ),
        16 => 
        array (
          'id' => 17,
          'code' => '326',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'invalid parameter cardExpirationYear',
          'user_message' => 'El año es inválido',
        ),
        17 => 
        array (
          'id' => 18,
          'code' => 'default',
          'client_error_type_id' => 'data_entry',
          'original_message' => 'Otro código de error',
          'user_message' => 'Revisa los datos.',
        ),
        18 => 
        array (
          'id' => 19,
          'code' => '106',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Cannot operate between users from different countries',
          'user_message' => 'No puedes realizar pagos a otros países.',
        ),
        19 => 
        array (
          'id' => 20,
          'code' => '109',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Invalid number of shares for this payment_method_id',
          'user_message' => 'El medio de pago no procesa pagos en installments cuotas. Elige otra tarjeta u otro medio de pago.',
        ),
        20 => 
        array (
          'id' => 21,
          'code' => '126',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'The action requested is not valid for the current payment state',
          'user_message' => 'No pudimos procesar tu pago.',
        ),
        21 => 
        array (
          'id' => 22,
          'code' => '129',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Cannot pay this amount with this paymentMethod',
          'user_message' => 'El medio de pago no procesa pagos del monto seleccionado. Elige otra tarjeta u otro medio de pago.',
        ),
        22 => 
        array (
          'id' => 23,
          'code' => '145',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Invalid users involved',
          'user_message' => 'Una de las partes con la que intentas hacer el pago es de prueba y la otra es usuario real.',
        ),
        23 => 
        array (
          'id' => 24,
          'code' => '150',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'The payer_id cannot do payments currently',
          'user_message' => 'No puedes realizar pagos.',
        ),
        24 => 
        array (
          'id' => 25,
          'code' => '151',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'The payer_id cannot do payments with this payment_method_id',
          'user_message' => 'No puedes realizar pagos.',
        ),
        25 => 
        array (
          'id' => 26,
          'code' => '160',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Collector not allowed to operate',
          'user_message' => 'No pudimos procesar tu pago.',
        ),
        26 => 
        array (
          'id' => 27,
          'code' => '204',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Unavailable payment_method',
          'user_message' => 'El medio de pago no está disponible en este momento. Elige otra tarjeta u otro medio de pago.',
        ),
        27 => 
        array (
          'id' => 28,
          'code' => '801',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Already posted the same request in the last minute',
          'user_message' => 'Realizaste un pago similar hace instantes. Intenta de nuevo en unos minutos.',
        ),
        28 => 
        array (
          'id' => 29,
          'code' => 'default',
          'client_error_type_id' => 'token_creation',
          'original_message' => 'Otro código de error',
          'user_message' => 'No pudimos procesar tu pago.',
        ),
      ),
    ),
    'module_levels' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'value' => 'new_document',
          'description' => 'Nuevo comprobante',
          'module_id' => 1,
          'route_name' => 'tenant.documents.create',
          'route_path' => '/documents/create',
          'label_menu' => 'NC',
          'icon_id' => 'icon_001',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'value' => 'list_document',
          'description' => 'L. Comprobantes',
          'module_id' => 1,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        2 => 
        array (
          'id' => 3,
          'value' => 'document_not_sent',
          'description' => 'Doc. No enviados',
          'module_id' => 52,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        3 => 
        array (
          'id' => 4,
          'value' => 'document_contingengy',
          'description' => 'Doc. Contingencia',
          'module_id' => 3,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        4 => 
        array (
          'id' => 5,
          'value' => 'catalogs',
          'description' => 'Catálogos',
          'module_id' => 1,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        5 => 
        array (
          'id' => 6,
          'value' => 'summary_voided',
          'description' => 'Resúmenes y Anulaciones',
          'module_id' => 52,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        6 => 
        array (
          'id' => 7,
          'value' => 'quotations',
          'description' => 'Cotizaciones',
          'module_id' => 50,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        7 => 
        array (
          'id' => 8,
          'value' => 'sale_notes',
          'description' => 'Notas de Venta',
          'module_id' => 1,
          'route_name' => 'tenant.sale_notes.index',
          'route_path' => '/sale-notes',
          'label_menu' => 'NV',
          'icon_id' => 'icon_005',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        8 => 
        array (
          'id' => 9,
          'value' => 'incentives',
          'description' => 'Comisiones',
          'module_id' => 1,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        9 => 
        array (
          'id' => 10,
          'value' => 'sale-opportunity',
          'description' => 'Oportunidad de venta',
          'module_id' => 50,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        10 => 
        array (
          'id' => 11,
          'value' => 'contracts',
          'description' => 'Contratos',
          'module_id' => 50,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        11 => 
        array (
          'id' => 12,
          'value' => 'order-note',
          'description' => 'Pedidos',
          'module_id' => 50,
          'route_name' => 'tenant.order_notes.index',
          'route_path' => '/order-notes',
          'label_menu' => 'PED',
          'icon_id' => 'icon_006',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        12 => 
        array (
          'id' => 13,
          'value' => 'technical-service',
          'description' => 'Servicios de soporte técnico',
          'module_id' => 50,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        13 => 
        array (
          'id' => 14,
          'value' => 'regularize_shipping',
          'description' => 'CPE pendientes de rectificación',
          'module_id' => 52,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        14 => 
        array (
          'id' => 15,
          'value' => 'pos',
          'description' => 'Punto de venta',
          'module_id' => 1,
          'route_name' => 'tenant.pos.index',
          'route_path' => '/pos',
          'label_menu' => 'POS',
          'icon_id' => 'icon_002',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        15 => 
        array (
          'id' => 16,
          'value' => 'cash',
          'description' => 'Caja chica POS',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        16 => 
        array (
          'id' => 17,
          'value' => 'ecommerce',
          'description' => 'Ir a la tienda',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        17 => 
        array (
          'id' => 18,
          'value' => 'ecommerce_orders',
          'description' => 'Pedidos',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        18 => 
        array (
          'id' => 19,
          'value' => 'ecommerce_items',
          'description' => 'Productos tienda virtual',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        19 => 
        array (
          'id' => 20,
          'value' => 'ecommerce_tags',
          'description' => 'Etiquetas',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        20 => 
        array (
          'id' => 21,
          'value' => 'ecommerce_promotions',
          'description' => 'Promociones - Banners',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        21 => 
        array (
          'id' => 22,
          'value' => 'ecommerce_settings',
          'description' => 'Configuración',
          'module_id' => 10,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        22 => 
        array (
          'id' => 23,
          'value' => 'items',
          'description' => 'Productos',
          'module_id' => 17,
          'route_name' => 'tenant.items.index',
          'route_path' => '/items',
          'label_menu' => 'PRO',
          'icon_id' => 'icon_007',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        23 => 
        array (
          'id' => 24,
          'value' => 'items_packs',
          'description' => 'Packs',
          'module_id' => 17,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        24 => 
        array (
          'id' => 25,
          'value' => 'items_services',
          'description' => 'Servicios',
          'module_id' => 17,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        25 => 
        array (
          'id' => 26,
          'value' => 'items_categories',
          'description' => 'Categorías',
          'module_id' => 17,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        26 => 
        array (
          'id' => 27,
          'value' => 'items_brands',
          'description' => 'Marcas',
          'module_id' => 17,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        27 => 
        array (
          'id' => 28,
          'value' => 'items_lots',
          'description' => 'Series',
          'module_id' => 17,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        28 => 
        array (
          'id' => 29,
          'value' => 'clients',
          'description' => 'Clientes',
          'module_id' => 18,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        29 => 
        array (
          'id' => 30,
          'value' => 'clients_types',
          'description' => 'Tipos de clientes',
          'module_id' => 18,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        30 => 
        array (
          'id' => 31,
          'value' => 'purchases_create',
          'description' => 'Nueva Compra',
          'module_id' => 2,
          'route_name' => 'tenant.purchases.create',
          'route_path' => '/purchases/create',
          'label_menu' => 'NC',
          'icon_id' => 'icon_010',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        31 => 
        array (
          'id' => 32,
          'value' => 'purchases_list',
          'description' => 'Listado',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        32 => 
        array (
          'id' => 33,
          'value' => 'purchases_orders',
          'description' => 'Ordenes de compra',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        33 => 
        array (
          'id' => 34,
          'value' => 'purchases_expenses',
          'description' => 'Gastos diversos',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        34 => 
        array (
          'id' => 35,
          'value' => 'purchases_suppliers',
          'description' => 'Proveedores',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        35 => 
        array (
          'id' => 36,
          'value' => 'purchases_quotations',
          'description' => 'Solicitar cotización',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        36 => 
        array (
          'id' => 37,
          'value' => 'purchases_fixed_assets_items',
          'description' => 'Activos fijos - Ítems',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        37 => 
        array (
          'id' => 38,
          'value' => 'purchases_fixed_assets_purchases',
          'description' => 'Activos fijos - Compras',
          'module_id' => 2,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        38 => 
        array (
          'id' => 39,
          'value' => 'inventory',
          'description' => 'Movimientos',
          'module_id' => 8,
          'route_name' => 'inventory.index',
          'route_path' => '/inventory',
          'label_menu' => 'INV',
          'icon_id' => 'icon_008',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        39 => 
        array (
          'id' => 40,
          'value' => 'inventory_transfers',
          'description' => 'Traslados',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        40 => 
        array (
          'id' => 41,
          'value' => 'inventory_devolutions',
          'description' => 'Devoluciones',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        41 => 
        array (
          'id' => 42,
          'value' => 'inventory_report_kardex',
          'description' => 'Reporte kardex',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        42 => 
        array (
          'id' => 43,
          'value' => 'inventory_report',
          'description' => 'Reporte inventario',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        43 => 
        array (
          'id' => 44,
          'value' => 'inventory_report_valued_kardex',
          'description' => 'Kardex valorizado',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => '2026-08-17 14:15:00',
        ),
        44 => 
        array (
          'id' => 45,
          'value' => 'users',
          'description' => 'Usuarios',
          'module_id' => 14,
          'route_name' => 'tenant.users.index',
          'route_path' => '/users',
          'label_menu' => 'USR',
          'icon_id' => 'icon_009',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        45 => 
        array (
          'id' => 46,
          'value' => 'users_establishments',
          'description' => 'Establecimientos',
          'module_id' => 14,
          'route_name' => 'tenant.establishments.index',
          'route_path' => '/establishments',
          'label_menu' => 'ES',
          'icon_id' => 'icon_004',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        46 => 
        array (
          'id' => 47,
          'value' => 'advanced_retentions',
          'description' => 'Retenciones',
          'module_id' => 3,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        47 => 
        array (
          'id' => 49,
          'value' => 'advanced_perceptions',
          'description' => 'Percepciones',
          'module_id' => 3,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        48 => 
        array (
          'id' => 50,
          'value' => 'advanced_order_forms',
          'description' => 'Ordenes de pedido',
          'module_id' => 3,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        49 => 
        array (
          'id' => 51,
          'value' => 'account_report',
          'description' => 'Exportar reporte',
          'module_id' => 9,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        50 => 
        array (
          'id' => 52,
          'value' => 'account_formats',
          'description' => 'Exportar formatos',
          'module_id' => 9,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        51 => 
        array (
          'id' => 53,
          'value' => 'account_summary',
          'description' => 'Reporte resumido - Ventas',
          'module_id' => 9,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        52 => 
        array (
          'id' => 54,
          'value' => 'finances_movements',
          'description' => 'Movimientos',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        53 => 
        array (
          'id' => 55,
          'value' => 'finances_incomes',
          'description' => 'Ingresos',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        54 => 
        array (
          'id' => 56,
          'value' => 'finances_unpaid',
          'description' => 'Cuentas por cobrar',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        55 => 
        array (
          'id' => 57,
          'value' => 'finances_to_pay',
          'description' => 'Cuentas por pagar',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        56 => 
        array (
          'id' => 58,
          'value' => 'finances_payments',
          'description' => 'Pagos',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        57 => 
        array (
          'id' => 59,
          'value' => 'finances_balance',
          'description' => 'Balance',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        58 => 
        array (
          'id' => 60,
          'value' => 'finances_payment_method_types',
          'description' => 'Ingresos y Egresos - M. Pago',
          'module_id' => 12,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        59 => 
        array (
          'id' => 61,
          'value' => 'account_users_settings',
          'description' => 'Configuración',
          'module_id' => 11,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        60 => 
        array (
          'id' => 62,
          'value' => 'account_users_list',
          'description' => 'Lista de pagos',
          'module_id' => 11,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        61 => 
        array (
          'id' => 63,
          'value' => 'hotels_reception',
          'description' => 'Recepción',
          'module_id' => 15,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        62 => 
        array (
          'id' => 64,
          'value' => 'hotels_rates',
          'description' => 'Tarifas',
          'module_id' => 15,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        63 => 
        array (
          'id' => 65,
          'value' => 'hotels_floors',
          'description' => 'Pisos',
          'module_id' => 15,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        64 => 
        array (
          'id' => 66,
          'value' => 'hotels_cats',
          'description' => 'Categorías',
          'module_id' => 15,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        65 => 
        array (
          'id' => 67,
          'value' => 'hotels_rooms',
          'description' => 'Habitaciones',
          'module_id' => 15,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        66 => 
        array (
          'id' => 68,
          'value' => 'documentary_offices',
          'description' => 'Oficinas',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        67 => 
        array (
          'id' => 69,
          'value' => 'documentary_process',
          'description' => 'Procesos',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        68 => 
        array (
          'id' => 70,
          'value' => 'documentary_documents',
          'description' => 'Tipos de documento',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        69 => 
        array (
          'id' => 71,
          'value' => 'documentary_actions',
          'description' => 'Acciones',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        70 => 
        array (
          'id' => 72,
          'value' => 'documentary_files',
          'description' => 'Expedientes',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        71 => 
        array (
          'id' => 73,
          'value' => 'digemid',
          'description' => 'Productos',
          'module_id' => 19,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => '2026-08-17 14:14:37',
          'updated_at' => '2026-08-17 14:14:37',
        ),
        72 => 
        array (
          'id' => 74,
          'value' => 'documentary_requirements',
          'description' => 'Requerimientos',
          'module_id' => 16,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => '2026-08-17 14:14:38',
          'updated_at' => '2026-08-17 14:14:38',
        ),
        73 => 
        array (
          'id' => 75,
          'value' => 'inventory_item_extra_data',
          'description' => 'Datos extra de items',
          'module_id' => 8,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        74 => 
        array (
          'id' => 76,
          'value' => 'configuration_company',
          'description' => 'Empresa',
          'module_id' => 5,
          'route_name' => 'tenant.companies.create',
          'route_path' => '/companies/create',
          'label_menu' => 'ME',
          'icon_id' => 'icon_003',
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        75 => 
        array (
          'id' => 77,
          'value' => 'configuration_advance',
          'description' => 'Avanzado',
          'module_id' => 5,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        76 => 
        array (
          'id' => 78,
          'value' => 'configuration_visual',
          'description' => 'Visual',
          'module_id' => 5,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        77 => 
        array (
          'id' => 79,
          'value' => 'advanced_purchase_settlements',
          'description' => 'Liquidaciones de compra',
          'module_id' => 3,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        78 => 
        array (
          'id' => 80,
          'value' => 'suscription_app_client',
          'description' => 'Cliente',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        79 => 
        array (
          'id' => 81,
          'value' => 'suscription_app_service',
          'description' => 'Servicio',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        80 => 
        array (
          'id' => 82,
          'value' => 'suscription_app_payments',
          'description' => 'Pagos',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        81 => 
        array (
          'id' => 83,
          'value' => 'suscription_app_plans',
          'description' => 'Planes',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        82 => 
        array (
          'id' => 84,
          'value' => 'pos_garage',
          'description' => 'Venta rapida',
          'module_id' => 1,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        83 => 
        array (
          'id' => 90,
          'value' => 'dispatches',
          'description' => 'G.R. Remitente',
          'module_id' => 51,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        84 => 
        array (
          'id' => 91,
          'value' => 'dispatch_carrier',
          'description' => 'G.R. Transportista',
          'module_id' => 51,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        85 => 
        array (
          'id' => 92,
          'value' => 'dispatchers',
          'description' => 'Transportistas',
          'module_id' => 51,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        86 => 
        array (
          'id' => 93,
          'value' => 'drivers',
          'description' => 'Conductores',
          'module_id' => 51,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        87 => 
        array (
          'id' => 94,
          'value' => 'transports',
          'description' => 'Vehículos',
          'module_id' => 51,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        88 => 
        array (
          'id' => 95,
          'value' => 'suscription_app_pending_payments',
          'description' => 'Pagos pendientes',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        89 => 
        array (
          'id' => 96,
          'value' => 'suscription_app_payment_reminders',
          'description' => 'Recordatorios de pago',
          'module_id' => 21,
          'route_name' => NULL,
          'route_path' => NULL,
          'label_menu' => NULL,
          'icon_id' => NULL,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
      ),
    ),
    'person_types' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'description' => 'Interno',
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
          'price_label_id' => NULL,
          'enabled_description_person_type' => 0,
          'description_person_type' => NULL,
        ),
        1 => 
        array (
          'id' => 2,
          'description' => 'Distribuidor',
          'created_at' => '2026-08-17 14:14:17',
          'updated_at' => '2026-08-17 14:14:17',
          'price_label_id' => NULL,
          'enabled_description_person_type' => 0,
          'description_person_type' => NULL,
        ),
      ),
    ),
    'provinces' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '0101',
          'department_id' => '01',
          'description' => 'Chachapoyas',
          'active' => 1,
        ),
        1 => 
        array (
          'id' => '0102',
          'department_id' => '01',
          'description' => 'Bagua',
          'active' => 1,
        ),
        2 => 
        array (
          'id' => '0103',
          'department_id' => '01',
          'description' => 'Bongará',
          'active' => 1,
        ),
        3 => 
        array (
          'id' => '0104',
          'department_id' => '01',
          'description' => 'Condorcanqui',
          'active' => 1,
        ),
        4 => 
        array (
          'id' => '0105',
          'department_id' => '01',
          'description' => 'Luya',
          'active' => 1,
        ),
        5 => 
        array (
          'id' => '0106',
          'department_id' => '01',
          'description' => 'Rodríguez de Mendoza',
          'active' => 1,
        ),
        6 => 
        array (
          'id' => '0107',
          'department_id' => '01',
          'description' => 'Utcubamba',
          'active' => 1,
        ),
        7 => 
        array (
          'id' => '0201',
          'department_id' => '02',
          'description' => 'Huaraz',
          'active' => 1,
        ),
        8 => 
        array (
          'id' => '0202',
          'department_id' => '02',
          'description' => 'Aija',
          'active' => 1,
        ),
        9 => 
        array (
          'id' => '0203',
          'department_id' => '02',
          'description' => 'Antonio Raymondi',
          'active' => 1,
        ),
        10 => 
        array (
          'id' => '0204',
          'department_id' => '02',
          'description' => 'Asunción',
          'active' => 1,
        ),
        11 => 
        array (
          'id' => '0205',
          'department_id' => '02',
          'description' => 'Bolognesi',
          'active' => 1,
        ),
        12 => 
        array (
          'id' => '0206',
          'department_id' => '02',
          'description' => 'Carhuaz',
          'active' => 1,
        ),
        13 => 
        array (
          'id' => '0207',
          'department_id' => '02',
          'description' => 'Carlos Fermín Fitzcarrald',
          'active' => 1,
        ),
        14 => 
        array (
          'id' => '0208',
          'department_id' => '02',
          'description' => 'Casma',
          'active' => 1,
        ),
        15 => 
        array (
          'id' => '0209',
          'department_id' => '02',
          'description' => 'Corongo',
          'active' => 1,
        ),
        16 => 
        array (
          'id' => '0210',
          'department_id' => '02',
          'description' => 'Huari',
          'active' => 1,
        ),
        17 => 
        array (
          'id' => '0211',
          'department_id' => '02',
          'description' => 'Huarmey',
          'active' => 1,
        ),
        18 => 
        array (
          'id' => '0212',
          'department_id' => '02',
          'description' => 'Huaylas',
          'active' => 1,
        ),
        19 => 
        array (
          'id' => '0213',
          'department_id' => '02',
          'description' => 'Mariscal Luzuriaga',
          'active' => 1,
        ),
        20 => 
        array (
          'id' => '0214',
          'department_id' => '02',
          'description' => 'Ocros',
          'active' => 1,
        ),
        21 => 
        array (
          'id' => '0215',
          'department_id' => '02',
          'description' => 'Pallasca',
          'active' => 1,
        ),
        22 => 
        array (
          'id' => '0216',
          'department_id' => '02',
          'description' => 'Pomabamba',
          'active' => 1,
        ),
        23 => 
        array (
          'id' => '0217',
          'department_id' => '02',
          'description' => 'Recuay',
          'active' => 1,
        ),
        24 => 
        array (
          'id' => '0218',
          'department_id' => '02',
          'description' => 'Santa',
          'active' => 1,
        ),
        25 => 
        array (
          'id' => '0219',
          'department_id' => '02',
          'description' => 'Sihuas',
          'active' => 1,
        ),
        26 => 
        array (
          'id' => '0220',
          'department_id' => '02',
          'description' => 'Yungay',
          'active' => 1,
        ),
        27 => 
        array (
          'id' => '0301',
          'department_id' => '03',
          'description' => 'Abancay',
          'active' => 1,
        ),
        28 => 
        array (
          'id' => '0302',
          'department_id' => '03',
          'description' => 'Andahuaylas',
          'active' => 1,
        ),
        29 => 
        array (
          'id' => '0303',
          'department_id' => '03',
          'description' => 'Antabamba',
          'active' => 1,
        ),
        30 => 
        array (
          'id' => '0304',
          'department_id' => '03',
          'description' => 'Aymaraes',
          'active' => 1,
        ),
        31 => 
        array (
          'id' => '0305',
          'department_id' => '03',
          'description' => 'Cotabambas',
          'active' => 1,
        ),
        32 => 
        array (
          'id' => '0306',
          'department_id' => '03',
          'description' => 'Chincheros',
          'active' => 1,
        ),
        33 => 
        array (
          'id' => '0307',
          'department_id' => '03',
          'description' => 'Grau',
          'active' => 1,
        ),
        34 => 
        array (
          'id' => '0401',
          'department_id' => '04',
          'description' => 'Arequipa',
          'active' => 1,
        ),
        35 => 
        array (
          'id' => '0402',
          'department_id' => '04',
          'description' => 'Camaná',
          'active' => 1,
        ),
        36 => 
        array (
          'id' => '0403',
          'department_id' => '04',
          'description' => 'Caravelí',
          'active' => 1,
        ),
        37 => 
        array (
          'id' => '0404',
          'department_id' => '04',
          'description' => 'Castilla',
          'active' => 1,
        ),
        38 => 
        array (
          'id' => '0405',
          'department_id' => '04',
          'description' => 'Caylloma',
          'active' => 1,
        ),
        39 => 
        array (
          'id' => '0406',
          'department_id' => '04',
          'description' => 'Condesuyos',
          'active' => 1,
        ),
        40 => 
        array (
          'id' => '0407',
          'department_id' => '04',
          'description' => 'Islay',
          'active' => 1,
        ),
        41 => 
        array (
          'id' => '0408',
          'department_id' => '04',
          'description' => 'La Uniòn',
          'active' => 1,
        ),
        42 => 
        array (
          'id' => '0501',
          'department_id' => '05',
          'description' => 'Huamanga',
          'active' => 1,
        ),
        43 => 
        array (
          'id' => '0502',
          'department_id' => '05',
          'description' => 'Cangallo',
          'active' => 1,
        ),
        44 => 
        array (
          'id' => '0503',
          'department_id' => '05',
          'description' => 'Huanca Sancos',
          'active' => 1,
        ),
        45 => 
        array (
          'id' => '0504',
          'department_id' => '05',
          'description' => 'Huanta',
          'active' => 1,
        ),
        46 => 
        array (
          'id' => '0505',
          'department_id' => '05',
          'description' => 'La Mar',
          'active' => 1,
        ),
        47 => 
        array (
          'id' => '0506',
          'department_id' => '05',
          'description' => 'Lucanas',
          'active' => 1,
        ),
        48 => 
        array (
          'id' => '0507',
          'department_id' => '05',
          'description' => 'Parinacochas',
          'active' => 1,
        ),
        49 => 
        array (
          'id' => '0508',
          'department_id' => '05',
          'description' => 'Pàucar del Sara Sara',
          'active' => 1,
        ),
        50 => 
        array (
          'id' => '0509',
          'department_id' => '05',
          'description' => 'Sucre',
          'active' => 1,
        ),
        51 => 
        array (
          'id' => '0510',
          'department_id' => '05',
          'description' => 'Víctor Fajardo',
          'active' => 1,
        ),
        52 => 
        array (
          'id' => '0511',
          'department_id' => '05',
          'description' => 'Vilcas Huamán',
          'active' => 1,
        ),
        53 => 
        array (
          'id' => '0601',
          'department_id' => '06',
          'description' => 'Cajamarca',
          'active' => 1,
        ),
        54 => 
        array (
          'id' => '0602',
          'department_id' => '06',
          'description' => 'Cajabamba',
          'active' => 1,
        ),
        55 => 
        array (
          'id' => '0603',
          'department_id' => '06',
          'description' => 'Celendín',
          'active' => 1,
        ),
        56 => 
        array (
          'id' => '0604',
          'department_id' => '06',
          'description' => 'Chota',
          'active' => 1,
        ),
        57 => 
        array (
          'id' => '0605',
          'department_id' => '06',
          'description' => 'Contumazá',
          'active' => 1,
        ),
        58 => 
        array (
          'id' => '0606',
          'department_id' => '06',
          'description' => 'Cutervo',
          'active' => 1,
        ),
        59 => 
        array (
          'id' => '0607',
          'department_id' => '06',
          'description' => 'Hualgayoc',
          'active' => 1,
        ),
        60 => 
        array (
          'id' => '0608',
          'department_id' => '06',
          'description' => 'Jaén',
          'active' => 1,
        ),
        61 => 
        array (
          'id' => '0609',
          'department_id' => '06',
          'description' => 'San Ignacio',
          'active' => 1,
        ),
        62 => 
        array (
          'id' => '0610',
          'department_id' => '06',
          'description' => 'San Marcos',
          'active' => 1,
        ),
        63 => 
        array (
          'id' => '0611',
          'department_id' => '06',
          'description' => 'San Miguel',
          'active' => 1,
        ),
        64 => 
        array (
          'id' => '0612',
          'department_id' => '06',
          'description' => 'San Pablo',
          'active' => 1,
        ),
        65 => 
        array (
          'id' => '0613',
          'department_id' => '06',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        66 => 
        array (
          'id' => '0701',
          'department_id' => '07',
          'description' => 'Prov. Const. del Callao',
          'active' => 1,
        ),
        67 => 
        array (
          'id' => '0801',
          'department_id' => '08',
          'description' => 'Cusco',
          'active' => 1,
        ),
        68 => 
        array (
          'id' => '0802',
          'department_id' => '08',
          'description' => 'Acomayo',
          'active' => 1,
        ),
        69 => 
        array (
          'id' => '0803',
          'department_id' => '08',
          'description' => 'Anta',
          'active' => 1,
        ),
        70 => 
        array (
          'id' => '0804',
          'department_id' => '08',
          'description' => 'Calca',
          'active' => 1,
        ),
        71 => 
        array (
          'id' => '0805',
          'department_id' => '08',
          'description' => 'Canas',
          'active' => 1,
        ),
        72 => 
        array (
          'id' => '0806',
          'department_id' => '08',
          'description' => 'Canchis',
          'active' => 1,
        ),
        73 => 
        array (
          'id' => '0807',
          'department_id' => '08',
          'description' => 'Chumbivilcas',
          'active' => 1,
        ),
        74 => 
        array (
          'id' => '0808',
          'department_id' => '08',
          'description' => 'Espinar',
          'active' => 1,
        ),
        75 => 
        array (
          'id' => '0809',
          'department_id' => '08',
          'description' => 'La Convención',
          'active' => 1,
        ),
        76 => 
        array (
          'id' => '0810',
          'department_id' => '08',
          'description' => 'Paruro',
          'active' => 1,
        ),
        77 => 
        array (
          'id' => '0811',
          'department_id' => '08',
          'description' => 'Paucartambo',
          'active' => 1,
        ),
        78 => 
        array (
          'id' => '0812',
          'department_id' => '08',
          'description' => 'Quispicanchi',
          'active' => 1,
        ),
        79 => 
        array (
          'id' => '0813',
          'department_id' => '08',
          'description' => 'Urubamba',
          'active' => 1,
        ),
        80 => 
        array (
          'id' => '0901',
          'department_id' => '09',
          'description' => 'Huancavelica',
          'active' => 1,
        ),
        81 => 
        array (
          'id' => '0902',
          'department_id' => '09',
          'description' => 'Acobamba',
          'active' => 1,
        ),
        82 => 
        array (
          'id' => '0903',
          'department_id' => '09',
          'description' => 'Angaraes',
          'active' => 1,
        ),
        83 => 
        array (
          'id' => '0904',
          'department_id' => '09',
          'description' => 'Castrovirreyna',
          'active' => 1,
        ),
        84 => 
        array (
          'id' => '0905',
          'department_id' => '09',
          'description' => 'Churcampa',
          'active' => 1,
        ),
        85 => 
        array (
          'id' => '0906',
          'department_id' => '09',
          'description' => 'Huaytará',
          'active' => 1,
        ),
        86 => 
        array (
          'id' => '0907',
          'department_id' => '09',
          'description' => 'Tayacaja',
          'active' => 1,
        ),
        87 => 
        array (
          'id' => '1001',
          'department_id' => '10',
          'description' => 'Huánuco',
          'active' => 1,
        ),
        88 => 
        array (
          'id' => '1002',
          'department_id' => '10',
          'description' => 'Ambo',
          'active' => 1,
        ),
        89 => 
        array (
          'id' => '1003',
          'department_id' => '10',
          'description' => 'Dos de Mayo',
          'active' => 1,
        ),
        90 => 
        array (
          'id' => '1004',
          'department_id' => '10',
          'description' => 'Huacaybamba',
          'active' => 1,
        ),
        91 => 
        array (
          'id' => '1005',
          'department_id' => '10',
          'description' => 'Huamalíes',
          'active' => 1,
        ),
        92 => 
        array (
          'id' => '1006',
          'department_id' => '10',
          'description' => 'Leoncio Prado',
          'active' => 1,
        ),
        93 => 
        array (
          'id' => '1007',
          'department_id' => '10',
          'description' => 'Marañón',
          'active' => 1,
        ),
        94 => 
        array (
          'id' => '1008',
          'department_id' => '10',
          'description' => 'Pachitea',
          'active' => 1,
        ),
        95 => 
        array (
          'id' => '1009',
          'department_id' => '10',
          'description' => 'Puerto Inca',
          'active' => 1,
        ),
        96 => 
        array (
          'id' => '1010',
          'department_id' => '10',
          'description' => 'Lauricocha',
          'active' => 1,
        ),
        97 => 
        array (
          'id' => '1011',
          'department_id' => '10',
          'description' => 'Yarowilca',
          'active' => 1,
        ),
        98 => 
        array (
          'id' => '1101',
          'department_id' => '11',
          'description' => 'Ica',
          'active' => 1,
        ),
        99 => 
        array (
          'id' => '1102',
          'department_id' => '11',
          'description' => 'Chincha',
          'active' => 1,
        ),
        100 => 
        array (
          'id' => '1103',
          'department_id' => '11',
          'description' => 'Nasca',
          'active' => 1,
        ),
        101 => 
        array (
          'id' => '1104',
          'department_id' => '11',
          'description' => 'Palpa',
          'active' => 1,
        ),
        102 => 
        array (
          'id' => '1105',
          'department_id' => '11',
          'description' => 'Pisco',
          'active' => 1,
        ),
        103 => 
        array (
          'id' => '1201',
          'department_id' => '12',
          'description' => 'Huancayo',
          'active' => 1,
        ),
        104 => 
        array (
          'id' => '1202',
          'department_id' => '12',
          'description' => 'Concepción',
          'active' => 1,
        ),
        105 => 
        array (
          'id' => '1203',
          'department_id' => '12',
          'description' => 'Chanchamayo',
          'active' => 1,
        ),
        106 => 
        array (
          'id' => '1204',
          'department_id' => '12',
          'description' => 'Jauja',
          'active' => 1,
        ),
        107 => 
        array (
          'id' => '1205',
          'department_id' => '12',
          'description' => 'Junín',
          'active' => 1,
        ),
        108 => 
        array (
          'id' => '1206',
          'department_id' => '12',
          'description' => 'Satipo',
          'active' => 1,
        ),
        109 => 
        array (
          'id' => '1207',
          'department_id' => '12',
          'description' => 'Tarma',
          'active' => 1,
        ),
        110 => 
        array (
          'id' => '1208',
          'department_id' => '12',
          'description' => 'Yauli',
          'active' => 1,
        ),
        111 => 
        array (
          'id' => '1209',
          'department_id' => '12',
          'description' => 'Chupaca',
          'active' => 1,
        ),
        112 => 
        array (
          'id' => '1301',
          'department_id' => '13',
          'description' => 'Trujillo',
          'active' => 1,
        ),
        113 => 
        array (
          'id' => '1302',
          'department_id' => '13',
          'description' => 'Ascope',
          'active' => 1,
        ),
        114 => 
        array (
          'id' => '1303',
          'department_id' => '13',
          'description' => 'Bolívar',
          'active' => 1,
        ),
        115 => 
        array (
          'id' => '1304',
          'department_id' => '13',
          'description' => 'Chepén',
          'active' => 1,
        ),
        116 => 
        array (
          'id' => '1305',
          'department_id' => '13',
          'description' => 'Julcán',
          'active' => 1,
        ),
        117 => 
        array (
          'id' => '1306',
          'department_id' => '13',
          'description' => 'Otuzco',
          'active' => 1,
        ),
        118 => 
        array (
          'id' => '1307',
          'department_id' => '13',
          'description' => 'Pacasmayo',
          'active' => 1,
        ),
        119 => 
        array (
          'id' => '1308',
          'department_id' => '13',
          'description' => 'Pataz',
          'active' => 1,
        ),
        120 => 
        array (
          'id' => '1309',
          'department_id' => '13',
          'description' => 'Sánchez Carrión',
          'active' => 1,
        ),
        121 => 
        array (
          'id' => '1310',
          'department_id' => '13',
          'description' => 'Santiago de Chuco',
          'active' => 1,
        ),
        122 => 
        array (
          'id' => '1311',
          'department_id' => '13',
          'description' => 'Gran Chimú',
          'active' => 1,
        ),
        123 => 
        array (
          'id' => '1312',
          'department_id' => '13',
          'description' => 'Virú',
          'active' => 1,
        ),
        124 => 
        array (
          'id' => '1401',
          'department_id' => '14',
          'description' => 'Chiclayo',
          'active' => 1,
        ),
        125 => 
        array (
          'id' => '1402',
          'department_id' => '14',
          'description' => 'Ferreñafe',
          'active' => 1,
        ),
        126 => 
        array (
          'id' => '1403',
          'department_id' => '14',
          'description' => 'Lambayeque',
          'active' => 1,
        ),
        127 => 
        array (
          'id' => '1501',
          'department_id' => '15',
          'description' => 'Lima',
          'active' => 1,
        ),
        128 => 
        array (
          'id' => '1502',
          'department_id' => '15',
          'description' => 'Barranca',
          'active' => 1,
        ),
        129 => 
        array (
          'id' => '1503',
          'department_id' => '15',
          'description' => 'Cajatambo',
          'active' => 1,
        ),
        130 => 
        array (
          'id' => '1504',
          'department_id' => '15',
          'description' => 'Canta',
          'active' => 1,
        ),
        131 => 
        array (
          'id' => '1505',
          'department_id' => '15',
          'description' => 'Cañete',
          'active' => 1,
        ),
        132 => 
        array (
          'id' => '1506',
          'department_id' => '15',
          'description' => 'Huaral',
          'active' => 1,
        ),
        133 => 
        array (
          'id' => '1507',
          'department_id' => '15',
          'description' => 'Huarochirí',
          'active' => 1,
        ),
        134 => 
        array (
          'id' => '1508',
          'department_id' => '15',
          'description' => 'Huaura',
          'active' => 1,
        ),
        135 => 
        array (
          'id' => '1509',
          'department_id' => '15',
          'description' => 'Oyón',
          'active' => 1,
        ),
        136 => 
        array (
          'id' => '1510',
          'department_id' => '15',
          'description' => 'Yauyos',
          'active' => 1,
        ),
        137 => 
        array (
          'id' => '1601',
          'department_id' => '16',
          'description' => 'Maynas',
          'active' => 1,
        ),
        138 => 
        array (
          'id' => '1602',
          'department_id' => '16',
          'description' => 'Alto Amazonas',
          'active' => 1,
        ),
        139 => 
        array (
          'id' => '1603',
          'department_id' => '16',
          'description' => 'Loreto',
          'active' => 1,
        ),
        140 => 
        array (
          'id' => '1604',
          'department_id' => '16',
          'description' => 'Mariscal Ramón Castilla',
          'active' => 1,
        ),
        141 => 
        array (
          'id' => '1605',
          'department_id' => '16',
          'description' => 'Requena',
          'active' => 1,
        ),
        142 => 
        array (
          'id' => '1606',
          'department_id' => '16',
          'description' => 'Ucayali',
          'active' => 1,
        ),
        143 => 
        array (
          'id' => '1607',
          'department_id' => '16',
          'description' => 'Datem del Marañón',
          'active' => 1,
        ),
        144 => 
        array (
          'id' => '1608',
          'department_id' => '16',
          'description' => 'Putumayo',
          'active' => 1,
        ),
        145 => 
        array (
          'id' => '1701',
          'department_id' => '17',
          'description' => 'Tambopata',
          'active' => 1,
        ),
        146 => 
        array (
          'id' => '1702',
          'department_id' => '17',
          'description' => 'Manu',
          'active' => 1,
        ),
        147 => 
        array (
          'id' => '1703',
          'department_id' => '17',
          'description' => 'Tahuamanu',
          'active' => 1,
        ),
        148 => 
        array (
          'id' => '1801',
          'department_id' => '18',
          'description' => 'Mariscal Nieto',
          'active' => 1,
        ),
        149 => 
        array (
          'id' => '1802',
          'department_id' => '18',
          'description' => 'General Sánchez Cerro',
          'active' => 1,
        ),
        150 => 
        array (
          'id' => '1803',
          'department_id' => '18',
          'description' => 'Ilo',
          'active' => 1,
        ),
        151 => 
        array (
          'id' => '1901',
          'department_id' => '19',
          'description' => 'Pasco',
          'active' => 1,
        ),
        152 => 
        array (
          'id' => '1902',
          'department_id' => '19',
          'description' => 'Daniel Alcides Carrión',
          'active' => 1,
        ),
        153 => 
        array (
          'id' => '1903',
          'department_id' => '19',
          'description' => 'Oxapampa',
          'active' => 1,
        ),
        154 => 
        array (
          'id' => '2001',
          'department_id' => '20',
          'description' => 'Piura',
          'active' => 1,
        ),
        155 => 
        array (
          'id' => '2002',
          'department_id' => '20',
          'description' => 'Ayabaca',
          'active' => 1,
        ),
        156 => 
        array (
          'id' => '2003',
          'department_id' => '20',
          'description' => 'Huancabamba',
          'active' => 1,
        ),
        157 => 
        array (
          'id' => '2004',
          'department_id' => '20',
          'description' => 'Morropón',
          'active' => 1,
        ),
        158 => 
        array (
          'id' => '2005',
          'department_id' => '20',
          'description' => 'Paita',
          'active' => 1,
        ),
        159 => 
        array (
          'id' => '2006',
          'department_id' => '20',
          'description' => 'Sullana',
          'active' => 1,
        ),
        160 => 
        array (
          'id' => '2007',
          'department_id' => '20',
          'description' => 'Talara',
          'active' => 1,
        ),
        161 => 
        array (
          'id' => '2008',
          'department_id' => '20',
          'description' => 'Sechura',
          'active' => 1,
        ),
        162 => 
        array (
          'id' => '2101',
          'department_id' => '21',
          'description' => 'Puno',
          'active' => 1,
        ),
        163 => 
        array (
          'id' => '2102',
          'department_id' => '21',
          'description' => 'Azángaro',
          'active' => 1,
        ),
        164 => 
        array (
          'id' => '2103',
          'department_id' => '21',
          'description' => 'Carabaya',
          'active' => 1,
        ),
        165 => 
        array (
          'id' => '2104',
          'department_id' => '21',
          'description' => 'Chucuito',
          'active' => 1,
        ),
        166 => 
        array (
          'id' => '2105',
          'department_id' => '21',
          'description' => 'El Collao',
          'active' => 1,
        ),
        167 => 
        array (
          'id' => '2106',
          'department_id' => '21',
          'description' => 'Huancané',
          'active' => 1,
        ),
        168 => 
        array (
          'id' => '2107',
          'department_id' => '21',
          'description' => 'Lampa',
          'active' => 1,
        ),
        169 => 
        array (
          'id' => '2108',
          'department_id' => '21',
          'description' => 'Melgar',
          'active' => 1,
        ),
        170 => 
        array (
          'id' => '2109',
          'department_id' => '21',
          'description' => 'Moho',
          'active' => 1,
        ),
        171 => 
        array (
          'id' => '2110',
          'department_id' => '21',
          'description' => 'San Antonio de Putina',
          'active' => 1,
        ),
        172 => 
        array (
          'id' => '2111',
          'department_id' => '21',
          'description' => 'San Román',
          'active' => 1,
        ),
        173 => 
        array (
          'id' => '2112',
          'department_id' => '21',
          'description' => 'Sandia',
          'active' => 1,
        ),
        174 => 
        array (
          'id' => '2113',
          'department_id' => '21',
          'description' => 'Yunguyo',
          'active' => 1,
        ),
        175 => 
        array (
          'id' => '2201',
          'department_id' => '22',
          'description' => 'Moyobamba',
          'active' => 1,
        ),
        176 => 
        array (
          'id' => '2202',
          'department_id' => '22',
          'description' => 'Bellavista',
          'active' => 1,
        ),
        177 => 
        array (
          'id' => '2203',
          'department_id' => '22',
          'description' => 'El Dorado',
          'active' => 1,
        ),
        178 => 
        array (
          'id' => '2204',
          'department_id' => '22',
          'description' => 'Huallaga',
          'active' => 1,
        ),
        179 => 
        array (
          'id' => '2205',
          'department_id' => '22',
          'description' => 'Lamas',
          'active' => 1,
        ),
        180 => 
        array (
          'id' => '2206',
          'department_id' => '22',
          'description' => 'Mariscal Cáceres',
          'active' => 1,
        ),
        181 => 
        array (
          'id' => '2207',
          'department_id' => '22',
          'description' => 'Picota',
          'active' => 1,
        ),
        182 => 
        array (
          'id' => '2208',
          'department_id' => '22',
          'description' => 'Rioja',
          'active' => 1,
        ),
        183 => 
        array (
          'id' => '2209',
          'department_id' => '22',
          'description' => 'San Martín',
          'active' => 1,
        ),
        184 => 
        array (
          'id' => '2210',
          'department_id' => '22',
          'description' => 'Tocache',
          'active' => 1,
        ),
        185 => 
        array (
          'id' => '2301',
          'department_id' => '23',
          'description' => 'Tacna',
          'active' => 1,
        ),
        186 => 
        array (
          'id' => '2302',
          'department_id' => '23',
          'description' => 'Candarave',
          'active' => 1,
        ),
        187 => 
        array (
          'id' => '2303',
          'department_id' => '23',
          'description' => 'Jorge Basadre',
          'active' => 1,
        ),
        188 => 
        array (
          'id' => '2304',
          'department_id' => '23',
          'description' => 'Tarata',
          'active' => 1,
        ),
        189 => 
        array (
          'id' => '2401',
          'department_id' => '24',
          'description' => 'Tumbes',
          'active' => 1,
        ),
        190 => 
        array (
          'id' => '2402',
          'department_id' => '24',
          'description' => 'Contralmirante Villar',
          'active' => 1,
        ),
        191 => 
        array (
          'id' => '2403',
          'department_id' => '24',
          'description' => 'Zarumilla',
          'active' => 1,
        ),
        192 => 
        array (
          'id' => '2501',
          'department_id' => '25',
          'description' => 'Coronel Portillo',
          'active' => 1,
        ),
        193 => 
        array (
          'id' => '2502',
          'department_id' => '25',
          'description' => 'Atalaya',
          'active' => 1,
        ),
        194 => 
        array (
          'id' => '2503',
          'department_id' => '25',
          'description' => 'Padre Abad',
          'active' => 1,
        ),
        195 => 
        array (
          'id' => '2504',
          'department_id' => '25',
          'description' => 'Purús',
          'active' => 1,
        ),
      ),
    ),
    'districts' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => '010101',
          'province_id' => '0101',
          'description' => 'Chachapoyas',
          'active' => 1,
        ),
        1 => 
        array (
          'id' => '010102',
          'province_id' => '0101',
          'description' => 'Asunción',
          'active' => 1,
        ),
        2 => 
        array (
          'id' => '010103',
          'province_id' => '0101',
          'description' => 'Balsas',
          'active' => 1,
        ),
        3 => 
        array (
          'id' => '010104',
          'province_id' => '0101',
          'description' => 'Cheto',
          'active' => 1,
        ),
        4 => 
        array (
          'id' => '010105',
          'province_id' => '0101',
          'description' => 'Chiliquin',
          'active' => 1,
        ),
        5 => 
        array (
          'id' => '010106',
          'province_id' => '0101',
          'description' => 'Chuquibamba',
          'active' => 1,
        ),
        6 => 
        array (
          'id' => '010107',
          'province_id' => '0101',
          'description' => 'Granada',
          'active' => 1,
        ),
        7 => 
        array (
          'id' => '010108',
          'province_id' => '0101',
          'description' => 'Huancas',
          'active' => 1,
        ),
        8 => 
        array (
          'id' => '010109',
          'province_id' => '0101',
          'description' => 'La Jalca',
          'active' => 1,
        ),
        9 => 
        array (
          'id' => '010110',
          'province_id' => '0101',
          'description' => 'Leimebamba',
          'active' => 1,
        ),
        10 => 
        array (
          'id' => '010111',
          'province_id' => '0101',
          'description' => 'Levanto',
          'active' => 1,
        ),
        11 => 
        array (
          'id' => '010112',
          'province_id' => '0101',
          'description' => 'Magdalena',
          'active' => 1,
        ),
        12 => 
        array (
          'id' => '010113',
          'province_id' => '0101',
          'description' => 'Mariscal Castilla',
          'active' => 1,
        ),
        13 => 
        array (
          'id' => '010114',
          'province_id' => '0101',
          'description' => 'Molinopampa',
          'active' => 1,
        ),
        14 => 
        array (
          'id' => '010115',
          'province_id' => '0101',
          'description' => 'Montevideo',
          'active' => 1,
        ),
        15 => 
        array (
          'id' => '010116',
          'province_id' => '0101',
          'description' => 'Olleros',
          'active' => 1,
        ),
        16 => 
        array (
          'id' => '010117',
          'province_id' => '0101',
          'description' => 'Quinjalca',
          'active' => 1,
        ),
        17 => 
        array (
          'id' => '010118',
          'province_id' => '0101',
          'description' => 'San Francisco de Daguas',
          'active' => 1,
        ),
        18 => 
        array (
          'id' => '010119',
          'province_id' => '0101',
          'description' => 'San Isidro de Maino',
          'active' => 1,
        ),
        19 => 
        array (
          'id' => '010120',
          'province_id' => '0101',
          'description' => 'Soloco',
          'active' => 1,
        ),
        20 => 
        array (
          'id' => '010121',
          'province_id' => '0101',
          'description' => 'Sonche',
          'active' => 1,
        ),
        21 => 
        array (
          'id' => '010201',
          'province_id' => '0102',
          'description' => 'Bagua',
          'active' => 1,
        ),
        22 => 
        array (
          'id' => '010202',
          'province_id' => '0102',
          'description' => 'Aramango',
          'active' => 1,
        ),
        23 => 
        array (
          'id' => '010203',
          'province_id' => '0102',
          'description' => 'Copallin',
          'active' => 1,
        ),
        24 => 
        array (
          'id' => '010204',
          'province_id' => '0102',
          'description' => 'El Parco',
          'active' => 1,
        ),
        25 => 
        array (
          'id' => '010205',
          'province_id' => '0102',
          'description' => 'Imaza',
          'active' => 1,
        ),
        26 => 
        array (
          'id' => '010206',
          'province_id' => '0102',
          'description' => 'La Peca',
          'active' => 1,
        ),
        27 => 
        array (
          'id' => '010301',
          'province_id' => '0103',
          'description' => 'Jumbilla',
          'active' => 1,
        ),
        28 => 
        array (
          'id' => '010302',
          'province_id' => '0103',
          'description' => 'Chisquilla',
          'active' => 1,
        ),
        29 => 
        array (
          'id' => '010303',
          'province_id' => '0103',
          'description' => 'Churuja',
          'active' => 1,
        ),
        30 => 
        array (
          'id' => '010304',
          'province_id' => '0103',
          'description' => 'Corosha',
          'active' => 1,
        ),
        31 => 
        array (
          'id' => '010305',
          'province_id' => '0103',
          'description' => 'Cuispes',
          'active' => 1,
        ),
        32 => 
        array (
          'id' => '010306',
          'province_id' => '0103',
          'description' => 'Florida',
          'active' => 1,
        ),
        33 => 
        array (
          'id' => '010307',
          'province_id' => '0103',
          'description' => 'Jazan',
          'active' => 1,
        ),
        34 => 
        array (
          'id' => '010308',
          'province_id' => '0103',
          'description' => 'Recta',
          'active' => 1,
        ),
        35 => 
        array (
          'id' => '010309',
          'province_id' => '0103',
          'description' => 'San Carlos',
          'active' => 1,
        ),
        36 => 
        array (
          'id' => '010310',
          'province_id' => '0103',
          'description' => 'Shipasbamba',
          'active' => 1,
        ),
        37 => 
        array (
          'id' => '010311',
          'province_id' => '0103',
          'description' => 'Valera',
          'active' => 1,
        ),
        38 => 
        array (
          'id' => '010312',
          'province_id' => '0103',
          'description' => 'Yambrasbamba',
          'active' => 1,
        ),
        39 => 
        array (
          'id' => '010401',
          'province_id' => '0104',
          'description' => 'Nieva',
          'active' => 1,
        ),
        40 => 
        array (
          'id' => '010402',
          'province_id' => '0104',
          'description' => 'El Cenepa',
          'active' => 1,
        ),
        41 => 
        array (
          'id' => '010403',
          'province_id' => '0104',
          'description' => 'Río Santiago',
          'active' => 1,
        ),
        42 => 
        array (
          'id' => '010501',
          'province_id' => '0105',
          'description' => 'Lamud',
          'active' => 1,
        ),
        43 => 
        array (
          'id' => '010502',
          'province_id' => '0105',
          'description' => 'Camporredondo',
          'active' => 1,
        ),
        44 => 
        array (
          'id' => '010503',
          'province_id' => '0105',
          'description' => 'Cocabamba',
          'active' => 1,
        ),
        45 => 
        array (
          'id' => '010504',
          'province_id' => '0105',
          'description' => 'Colcamar',
          'active' => 1,
        ),
        46 => 
        array (
          'id' => '010505',
          'province_id' => '0105',
          'description' => 'Conila',
          'active' => 1,
        ),
        47 => 
        array (
          'id' => '010506',
          'province_id' => '0105',
          'description' => 'Inguilpata',
          'active' => 1,
        ),
        48 => 
        array (
          'id' => '010507',
          'province_id' => '0105',
          'description' => 'Longuita',
          'active' => 1,
        ),
        49 => 
        array (
          'id' => '010508',
          'province_id' => '0105',
          'description' => 'Lonya Chico',
          'active' => 1,
        ),
        50 => 
        array (
          'id' => '010509',
          'province_id' => '0105',
          'description' => 'Luya',
          'active' => 1,
        ),
        51 => 
        array (
          'id' => '010510',
          'province_id' => '0105',
          'description' => 'Luya Viejo',
          'active' => 1,
        ),
        52 => 
        array (
          'id' => '010511',
          'province_id' => '0105',
          'description' => 'María',
          'active' => 1,
        ),
        53 => 
        array (
          'id' => '010512',
          'province_id' => '0105',
          'description' => 'Ocalli',
          'active' => 1,
        ),
        54 => 
        array (
          'id' => '010513',
          'province_id' => '0105',
          'description' => 'Ocumal',
          'active' => 1,
        ),
        55 => 
        array (
          'id' => '010514',
          'province_id' => '0105',
          'description' => 'Pisuquia',
          'active' => 1,
        ),
        56 => 
        array (
          'id' => '010515',
          'province_id' => '0105',
          'description' => 'Providencia',
          'active' => 1,
        ),
        57 => 
        array (
          'id' => '010516',
          'province_id' => '0105',
          'description' => 'San Cristóbal',
          'active' => 1,
        ),
        58 => 
        array (
          'id' => '010517',
          'province_id' => '0105',
          'description' => 'San Francisco de Yeso',
          'active' => 1,
        ),
        59 => 
        array (
          'id' => '010518',
          'province_id' => '0105',
          'description' => 'San Jerónimo',
          'active' => 1,
        ),
        60 => 
        array (
          'id' => '010519',
          'province_id' => '0105',
          'description' => 'San Juan de Lopecancha',
          'active' => 1,
        ),
        61 => 
        array (
          'id' => '010520',
          'province_id' => '0105',
          'description' => 'Santa Catalina',
          'active' => 1,
        ),
        62 => 
        array (
          'id' => '010521',
          'province_id' => '0105',
          'description' => 'Santo Tomas',
          'active' => 1,
        ),
        63 => 
        array (
          'id' => '010522',
          'province_id' => '0105',
          'description' => 'Tingo',
          'active' => 1,
        ),
        64 => 
        array (
          'id' => '010523',
          'province_id' => '0105',
          'description' => 'Trita',
          'active' => 1,
        ),
        65 => 
        array (
          'id' => '010601',
          'province_id' => '0106',
          'description' => 'San Nicolás',
          'active' => 1,
        ),
        66 => 
        array (
          'id' => '010602',
          'province_id' => '0106',
          'description' => 'Chirimoto',
          'active' => 1,
        ),
        67 => 
        array (
          'id' => '010603',
          'province_id' => '0106',
          'description' => 'Cochamal',
          'active' => 1,
        ),
        68 => 
        array (
          'id' => '010604',
          'province_id' => '0106',
          'description' => 'Huambo',
          'active' => 1,
        ),
        69 => 
        array (
          'id' => '010605',
          'province_id' => '0106',
          'description' => 'Limabamba',
          'active' => 1,
        ),
        70 => 
        array (
          'id' => '010606',
          'province_id' => '0106',
          'description' => 'Longar',
          'active' => 1,
        ),
        71 => 
        array (
          'id' => '010607',
          'province_id' => '0106',
          'description' => 'Mariscal Benavides',
          'active' => 1,
        ),
        72 => 
        array (
          'id' => '010608',
          'province_id' => '0106',
          'description' => 'Milpuc',
          'active' => 1,
        ),
        73 => 
        array (
          'id' => '010609',
          'province_id' => '0106',
          'description' => 'Omia',
          'active' => 1,
        ),
        74 => 
        array (
          'id' => '010610',
          'province_id' => '0106',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        75 => 
        array (
          'id' => '010611',
          'province_id' => '0106',
          'description' => 'Totora',
          'active' => 1,
        ),
        76 => 
        array (
          'id' => '010612',
          'province_id' => '0106',
          'description' => 'Vista Alegre',
          'active' => 1,
        ),
        77 => 
        array (
          'id' => '010701',
          'province_id' => '0107',
          'description' => 'Bagua Grande',
          'active' => 1,
        ),
        78 => 
        array (
          'id' => '010702',
          'province_id' => '0107',
          'description' => 'Cajaruro',
          'active' => 1,
        ),
        79 => 
        array (
          'id' => '010703',
          'province_id' => '0107',
          'description' => 'Cumba',
          'active' => 1,
        ),
        80 => 
        array (
          'id' => '010704',
          'province_id' => '0107',
          'description' => 'El Milagro',
          'active' => 1,
        ),
        81 => 
        array (
          'id' => '010705',
          'province_id' => '0107',
          'description' => 'Jamalca',
          'active' => 1,
        ),
        82 => 
        array (
          'id' => '010706',
          'province_id' => '0107',
          'description' => 'Lonya Grande',
          'active' => 1,
        ),
        83 => 
        array (
          'id' => '010707',
          'province_id' => '0107',
          'description' => 'Yamon',
          'active' => 1,
        ),
        84 => 
        array (
          'id' => '020101',
          'province_id' => '0201',
          'description' => 'Huaraz',
          'active' => 1,
        ),
        85 => 
        array (
          'id' => '020102',
          'province_id' => '0201',
          'description' => 'Cochabamba',
          'active' => 1,
        ),
        86 => 
        array (
          'id' => '020103',
          'province_id' => '0201',
          'description' => 'Colcabamba',
          'active' => 1,
        ),
        87 => 
        array (
          'id' => '020104',
          'province_id' => '0201',
          'description' => 'Huanchay',
          'active' => 1,
        ),
        88 => 
        array (
          'id' => '020105',
          'province_id' => '0201',
          'description' => 'Independencia',
          'active' => 1,
        ),
        89 => 
        array (
          'id' => '020106',
          'province_id' => '0201',
          'description' => 'Jangas',
          'active' => 1,
        ),
        90 => 
        array (
          'id' => '020107',
          'province_id' => '0201',
          'description' => 'La Libertad',
          'active' => 1,
        ),
        91 => 
        array (
          'id' => '020108',
          'province_id' => '0201',
          'description' => 'Olleros',
          'active' => 1,
        ),
        92 => 
        array (
          'id' => '020109',
          'province_id' => '0201',
          'description' => 'Pampas Grande',
          'active' => 1,
        ),
        93 => 
        array (
          'id' => '020110',
          'province_id' => '0201',
          'description' => 'Pariacoto',
          'active' => 1,
        ),
        94 => 
        array (
          'id' => '020111',
          'province_id' => '0201',
          'description' => 'Pira',
          'active' => 1,
        ),
        95 => 
        array (
          'id' => '020112',
          'province_id' => '0201',
          'description' => 'Tarica',
          'active' => 1,
        ),
        96 => 
        array (
          'id' => '020201',
          'province_id' => '0202',
          'description' => 'Aija',
          'active' => 1,
        ),
        97 => 
        array (
          'id' => '020202',
          'province_id' => '0202',
          'description' => 'Coris',
          'active' => 1,
        ),
        98 => 
        array (
          'id' => '020203',
          'province_id' => '0202',
          'description' => 'Huacllan',
          'active' => 1,
        ),
        99 => 
        array (
          'id' => '020204',
          'province_id' => '0202',
          'description' => 'La Merced',
          'active' => 1,
        ),
        100 => 
        array (
          'id' => '020205',
          'province_id' => '0202',
          'description' => 'Succha',
          'active' => 1,
        ),
        101 => 
        array (
          'id' => '020301',
          'province_id' => '0203',
          'description' => 'Llamellin',
          'active' => 1,
        ),
        102 => 
        array (
          'id' => '020302',
          'province_id' => '0203',
          'description' => 'Aczo',
          'active' => 1,
        ),
        103 => 
        array (
          'id' => '020303',
          'province_id' => '0203',
          'description' => 'Chaccho',
          'active' => 1,
        ),
        104 => 
        array (
          'id' => '020304',
          'province_id' => '0203',
          'description' => 'Chingas',
          'active' => 1,
        ),
        105 => 
        array (
          'id' => '020305',
          'province_id' => '0203',
          'description' => 'Mirgas',
          'active' => 1,
        ),
        106 => 
        array (
          'id' => '020306',
          'province_id' => '0203',
          'description' => 'San Juan de Rontoy',
          'active' => 1,
        ),
        107 => 
        array (
          'id' => '020401',
          'province_id' => '0204',
          'description' => 'Chacas',
          'active' => 1,
        ),
        108 => 
        array (
          'id' => '020402',
          'province_id' => '0204',
          'description' => 'Acochaca',
          'active' => 1,
        ),
        109 => 
        array (
          'id' => '020501',
          'province_id' => '0205',
          'description' => 'Chiquian',
          'active' => 1,
        ),
        110 => 
        array (
          'id' => '020502',
          'province_id' => '0205',
          'description' => 'Abelardo Pardo Lezameta',
          'active' => 1,
        ),
        111 => 
        array (
          'id' => '020503',
          'province_id' => '0205',
          'description' => 'Antonio Raymondi',
          'active' => 1,
        ),
        112 => 
        array (
          'id' => '020504',
          'province_id' => '0205',
          'description' => 'Aquia',
          'active' => 1,
        ),
        113 => 
        array (
          'id' => '020505',
          'province_id' => '0205',
          'description' => 'Cajacay',
          'active' => 1,
        ),
        114 => 
        array (
          'id' => '020506',
          'province_id' => '0205',
          'description' => 'Canis',
          'active' => 1,
        ),
        115 => 
        array (
          'id' => '020507',
          'province_id' => '0205',
          'description' => 'Colquioc',
          'active' => 1,
        ),
        116 => 
        array (
          'id' => '020508',
          'province_id' => '0205',
          'description' => 'Huallanca',
          'active' => 1,
        ),
        117 => 
        array (
          'id' => '020509',
          'province_id' => '0205',
          'description' => 'Huasta',
          'active' => 1,
        ),
        118 => 
        array (
          'id' => '020510',
          'province_id' => '0205',
          'description' => 'Huayllacayan',
          'active' => 1,
        ),
        119 => 
        array (
          'id' => '020511',
          'province_id' => '0205',
          'description' => 'La Primavera',
          'active' => 1,
        ),
        120 => 
        array (
          'id' => '020512',
          'province_id' => '0205',
          'description' => 'Mangas',
          'active' => 1,
        ),
        121 => 
        array (
          'id' => '020513',
          'province_id' => '0205',
          'description' => 'Pacllon',
          'active' => 1,
        ),
        122 => 
        array (
          'id' => '020514',
          'province_id' => '0205',
          'description' => 'San Miguel de Corpanqui',
          'active' => 1,
        ),
        123 => 
        array (
          'id' => '020515',
          'province_id' => '0205',
          'description' => 'Ticllos',
          'active' => 1,
        ),
        124 => 
        array (
          'id' => '020601',
          'province_id' => '0206',
          'description' => 'Carhuaz',
          'active' => 1,
        ),
        125 => 
        array (
          'id' => '020602',
          'province_id' => '0206',
          'description' => 'Acopampa',
          'active' => 1,
        ),
        126 => 
        array (
          'id' => '020603',
          'province_id' => '0206',
          'description' => 'Amashca',
          'active' => 1,
        ),
        127 => 
        array (
          'id' => '020604',
          'province_id' => '0206',
          'description' => 'Anta',
          'active' => 1,
        ),
        128 => 
        array (
          'id' => '020605',
          'province_id' => '0206',
          'description' => 'Ataquero',
          'active' => 1,
        ),
        129 => 
        array (
          'id' => '020606',
          'province_id' => '0206',
          'description' => 'Marcara',
          'active' => 1,
        ),
        130 => 
        array (
          'id' => '020607',
          'province_id' => '0206',
          'description' => 'Pariahuanca',
          'active' => 1,
        ),
        131 => 
        array (
          'id' => '020608',
          'province_id' => '0206',
          'description' => 'San Miguel de Aco',
          'active' => 1,
        ),
        132 => 
        array (
          'id' => '020609',
          'province_id' => '0206',
          'description' => 'Shilla',
          'active' => 1,
        ),
        133 => 
        array (
          'id' => '020610',
          'province_id' => '0206',
          'description' => 'Tinco',
          'active' => 1,
        ),
        134 => 
        array (
          'id' => '020611',
          'province_id' => '0206',
          'description' => 'Yungar',
          'active' => 1,
        ),
        135 => 
        array (
          'id' => '020701',
          'province_id' => '0207',
          'description' => 'San Luis',
          'active' => 1,
        ),
        136 => 
        array (
          'id' => '020702',
          'province_id' => '0207',
          'description' => 'San Nicolás',
          'active' => 1,
        ),
        137 => 
        array (
          'id' => '020703',
          'province_id' => '0207',
          'description' => 'Yauya',
          'active' => 1,
        ),
        138 => 
        array (
          'id' => '020801',
          'province_id' => '0208',
          'description' => 'Casma',
          'active' => 1,
        ),
        139 => 
        array (
          'id' => '020802',
          'province_id' => '0208',
          'description' => 'Buena Vista Alta',
          'active' => 1,
        ),
        140 => 
        array (
          'id' => '020803',
          'province_id' => '0208',
          'description' => 'Comandante Noel',
          'active' => 1,
        ),
        141 => 
        array (
          'id' => '020804',
          'province_id' => '0208',
          'description' => 'Yautan',
          'active' => 1,
        ),
        142 => 
        array (
          'id' => '020901',
          'province_id' => '0209',
          'description' => 'Corongo',
          'active' => 1,
        ),
        143 => 
        array (
          'id' => '020902',
          'province_id' => '0209',
          'description' => 'Aco',
          'active' => 1,
        ),
        144 => 
        array (
          'id' => '020903',
          'province_id' => '0209',
          'description' => 'Bambas',
          'active' => 1,
        ),
        145 => 
        array (
          'id' => '020904',
          'province_id' => '0209',
          'description' => 'Cusca',
          'active' => 1,
        ),
        146 => 
        array (
          'id' => '020905',
          'province_id' => '0209',
          'description' => 'La Pampa',
          'active' => 1,
        ),
        147 => 
        array (
          'id' => '020906',
          'province_id' => '0209',
          'description' => 'Yanac',
          'active' => 1,
        ),
        148 => 
        array (
          'id' => '020907',
          'province_id' => '0209',
          'description' => 'Yupan',
          'active' => 1,
        ),
        149 => 
        array (
          'id' => '021001',
          'province_id' => '0210',
          'description' => 'Huari',
          'active' => 1,
        ),
        150 => 
        array (
          'id' => '021002',
          'province_id' => '0210',
          'description' => 'Anra',
          'active' => 1,
        ),
        151 => 
        array (
          'id' => '021003',
          'province_id' => '0210',
          'description' => 'Cajay',
          'active' => 1,
        ),
        152 => 
        array (
          'id' => '021004',
          'province_id' => '0210',
          'description' => 'Chavin de Huantar',
          'active' => 1,
        ),
        153 => 
        array (
          'id' => '021005',
          'province_id' => '0210',
          'description' => 'Huacachi',
          'active' => 1,
        ),
        154 => 
        array (
          'id' => '021006',
          'province_id' => '0210',
          'description' => 'Huacchis',
          'active' => 1,
        ),
        155 => 
        array (
          'id' => '021007',
          'province_id' => '0210',
          'description' => 'Huachis',
          'active' => 1,
        ),
        156 => 
        array (
          'id' => '021008',
          'province_id' => '0210',
          'description' => 'Huantar',
          'active' => 1,
        ),
        157 => 
        array (
          'id' => '021009',
          'province_id' => '0210',
          'description' => 'Masin',
          'active' => 1,
        ),
        158 => 
        array (
          'id' => '021010',
          'province_id' => '0210',
          'description' => 'Paucas',
          'active' => 1,
        ),
        159 => 
        array (
          'id' => '021011',
          'province_id' => '0210',
          'description' => 'Ponto',
          'active' => 1,
        ),
        160 => 
        array (
          'id' => '021012',
          'province_id' => '0210',
          'description' => 'Rahuapampa',
          'active' => 1,
        ),
        161 => 
        array (
          'id' => '021013',
          'province_id' => '0210',
          'description' => 'Rapayan',
          'active' => 1,
        ),
        162 => 
        array (
          'id' => '021014',
          'province_id' => '0210',
          'description' => 'San Marcos',
          'active' => 1,
        ),
        163 => 
        array (
          'id' => '021015',
          'province_id' => '0210',
          'description' => 'San Pedro de Chana',
          'active' => 1,
        ),
        164 => 
        array (
          'id' => '021016',
          'province_id' => '0210',
          'description' => 'Uco',
          'active' => 1,
        ),
        165 => 
        array (
          'id' => '021101',
          'province_id' => '0211',
          'description' => 'Huarmey',
          'active' => 1,
        ),
        166 => 
        array (
          'id' => '021102',
          'province_id' => '0211',
          'description' => 'Cochapeti',
          'active' => 1,
        ),
        167 => 
        array (
          'id' => '021103',
          'province_id' => '0211',
          'description' => 'Culebras',
          'active' => 1,
        ),
        168 => 
        array (
          'id' => '021104',
          'province_id' => '0211',
          'description' => 'Huayan',
          'active' => 1,
        ),
        169 => 
        array (
          'id' => '021105',
          'province_id' => '0211',
          'description' => 'Malvas',
          'active' => 1,
        ),
        170 => 
        array (
          'id' => '021201',
          'province_id' => '0212',
          'description' => 'Caraz',
          'active' => 1,
        ),
        171 => 
        array (
          'id' => '021202',
          'province_id' => '0212',
          'description' => 'Huallanca',
          'active' => 1,
        ),
        172 => 
        array (
          'id' => '021203',
          'province_id' => '0212',
          'description' => 'Huata',
          'active' => 1,
        ),
        173 => 
        array (
          'id' => '021204',
          'province_id' => '0212',
          'description' => 'Huaylas',
          'active' => 1,
        ),
        174 => 
        array (
          'id' => '021205',
          'province_id' => '0212',
          'description' => 'Mato',
          'active' => 1,
        ),
        175 => 
        array (
          'id' => '021206',
          'province_id' => '0212',
          'description' => 'Pamparomas',
          'active' => 1,
        ),
        176 => 
        array (
          'id' => '021207',
          'province_id' => '0212',
          'description' => 'Pueblo Libre',
          'active' => 1,
        ),
        177 => 
        array (
          'id' => '021208',
          'province_id' => '0212',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        178 => 
        array (
          'id' => '021209',
          'province_id' => '0212',
          'description' => 'Santo Toribio',
          'active' => 1,
        ),
        179 => 
        array (
          'id' => '021210',
          'province_id' => '0212',
          'description' => 'Yuracmarca',
          'active' => 1,
        ),
        180 => 
        array (
          'id' => '021301',
          'province_id' => '0213',
          'description' => 'Piscobamba',
          'active' => 1,
        ),
        181 => 
        array (
          'id' => '021302',
          'province_id' => '0213',
          'description' => 'Casca',
          'active' => 1,
        ),
        182 => 
        array (
          'id' => '021303',
          'province_id' => '0213',
          'description' => 'Eleazar Guzmán Barron',
          'active' => 1,
        ),
        183 => 
        array (
          'id' => '021304',
          'province_id' => '0213',
          'description' => 'Fidel Olivas Escudero',
          'active' => 1,
        ),
        184 => 
        array (
          'id' => '021305',
          'province_id' => '0213',
          'description' => 'Llama',
          'active' => 1,
        ),
        185 => 
        array (
          'id' => '021306',
          'province_id' => '0213',
          'description' => 'Llumpa',
          'active' => 1,
        ),
        186 => 
        array (
          'id' => '021307',
          'province_id' => '0213',
          'description' => 'Lucma',
          'active' => 1,
        ),
        187 => 
        array (
          'id' => '021308',
          'province_id' => '0213',
          'description' => 'Musga',
          'active' => 1,
        ),
        188 => 
        array (
          'id' => '021401',
          'province_id' => '0214',
          'description' => 'Ocros',
          'active' => 1,
        ),
        189 => 
        array (
          'id' => '021402',
          'province_id' => '0214',
          'description' => 'Acas',
          'active' => 1,
        ),
        190 => 
        array (
          'id' => '021403',
          'province_id' => '0214',
          'description' => 'Cajamarquilla',
          'active' => 1,
        ),
        191 => 
        array (
          'id' => '021404',
          'province_id' => '0214',
          'description' => 'Carhuapampa',
          'active' => 1,
        ),
        192 => 
        array (
          'id' => '021405',
          'province_id' => '0214',
          'description' => 'Cochas',
          'active' => 1,
        ),
        193 => 
        array (
          'id' => '021406',
          'province_id' => '0214',
          'description' => 'Congas',
          'active' => 1,
        ),
        194 => 
        array (
          'id' => '021407',
          'province_id' => '0214',
          'description' => 'Llipa',
          'active' => 1,
        ),
        195 => 
        array (
          'id' => '021408',
          'province_id' => '0214',
          'description' => 'San Cristóbal de Rajan',
          'active' => 1,
        ),
        196 => 
        array (
          'id' => '021409',
          'province_id' => '0214',
          'description' => 'San Pedro',
          'active' => 1,
        ),
        197 => 
        array (
          'id' => '021410',
          'province_id' => '0214',
          'description' => 'Santiago de Chilcas',
          'active' => 1,
        ),
        198 => 
        array (
          'id' => '021501',
          'province_id' => '0215',
          'description' => 'Cabana',
          'active' => 1,
        ),
        199 => 
        array (
          'id' => '021502',
          'province_id' => '0215',
          'description' => 'Bolognesi',
          'active' => 1,
        ),
        200 => 
        array (
          'id' => '021503',
          'province_id' => '0215',
          'description' => 'Conchucos',
          'active' => 1,
        ),
        201 => 
        array (
          'id' => '021504',
          'province_id' => '0215',
          'description' => 'Huacaschuque',
          'active' => 1,
        ),
        202 => 
        array (
          'id' => '021505',
          'province_id' => '0215',
          'description' => 'Huandoval',
          'active' => 1,
        ),
        203 => 
        array (
          'id' => '021506',
          'province_id' => '0215',
          'description' => 'Lacabamba',
          'active' => 1,
        ),
        204 => 
        array (
          'id' => '021507',
          'province_id' => '0215',
          'description' => 'Llapo',
          'active' => 1,
        ),
        205 => 
        array (
          'id' => '021508',
          'province_id' => '0215',
          'description' => 'Pallasca',
          'active' => 1,
        ),
        206 => 
        array (
          'id' => '021509',
          'province_id' => '0215',
          'description' => 'Pampas',
          'active' => 1,
        ),
        207 => 
        array (
          'id' => '021510',
          'province_id' => '0215',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        208 => 
        array (
          'id' => '021511',
          'province_id' => '0215',
          'description' => 'Tauca',
          'active' => 1,
        ),
        209 => 
        array (
          'id' => '021601',
          'province_id' => '0216',
          'description' => 'Pomabamba',
          'active' => 1,
        ),
        210 => 
        array (
          'id' => '021602',
          'province_id' => '0216',
          'description' => 'Huayllan',
          'active' => 1,
        ),
        211 => 
        array (
          'id' => '021603',
          'province_id' => '0216',
          'description' => 'Parobamba',
          'active' => 1,
        ),
        212 => 
        array (
          'id' => '021604',
          'province_id' => '0216',
          'description' => 'Quinuabamba',
          'active' => 1,
        ),
        213 => 
        array (
          'id' => '021701',
          'province_id' => '0217',
          'description' => 'Recuay',
          'active' => 1,
        ),
        214 => 
        array (
          'id' => '021702',
          'province_id' => '0217',
          'description' => 'Catac',
          'active' => 1,
        ),
        215 => 
        array (
          'id' => '021703',
          'province_id' => '0217',
          'description' => 'Cotaparaco',
          'active' => 1,
        ),
        216 => 
        array (
          'id' => '021704',
          'province_id' => '0217',
          'description' => 'Huayllapampa',
          'active' => 1,
        ),
        217 => 
        array (
          'id' => '021705',
          'province_id' => '0217',
          'description' => 'Llacllin',
          'active' => 1,
        ),
        218 => 
        array (
          'id' => '021706',
          'province_id' => '0217',
          'description' => 'Marca',
          'active' => 1,
        ),
        219 => 
        array (
          'id' => '021707',
          'province_id' => '0217',
          'description' => 'Pampas Chico',
          'active' => 1,
        ),
        220 => 
        array (
          'id' => '021708',
          'province_id' => '0217',
          'description' => 'Pararin',
          'active' => 1,
        ),
        221 => 
        array (
          'id' => '021709',
          'province_id' => '0217',
          'description' => 'Tapacocha',
          'active' => 1,
        ),
        222 => 
        array (
          'id' => '021710',
          'province_id' => '0217',
          'description' => 'Ticapampa',
          'active' => 1,
        ),
        223 => 
        array (
          'id' => '021801',
          'province_id' => '0218',
          'description' => 'Chimbote',
          'active' => 1,
        ),
        224 => 
        array (
          'id' => '021802',
          'province_id' => '0218',
          'description' => 'Cáceres del Perú',
          'active' => 1,
        ),
        225 => 
        array (
          'id' => '021803',
          'province_id' => '0218',
          'description' => 'Coishco',
          'active' => 1,
        ),
        226 => 
        array (
          'id' => '021804',
          'province_id' => '0218',
          'description' => 'Macate',
          'active' => 1,
        ),
        227 => 
        array (
          'id' => '021805',
          'province_id' => '0218',
          'description' => 'Moro',
          'active' => 1,
        ),
        228 => 
        array (
          'id' => '021806',
          'province_id' => '0218',
          'description' => 'Nepeña',
          'active' => 1,
        ),
        229 => 
        array (
          'id' => '021807',
          'province_id' => '0218',
          'description' => 'Samanco',
          'active' => 1,
        ),
        230 => 
        array (
          'id' => '021808',
          'province_id' => '0218',
          'description' => 'Santa',
          'active' => 1,
        ),
        231 => 
        array (
          'id' => '021809',
          'province_id' => '0218',
          'description' => 'Nuevo Chimbote',
          'active' => 1,
        ),
        232 => 
        array (
          'id' => '021901',
          'province_id' => '0219',
          'description' => 'Sihuas',
          'active' => 1,
        ),
        233 => 
        array (
          'id' => '021902',
          'province_id' => '0219',
          'description' => 'Acobamba',
          'active' => 1,
        ),
        234 => 
        array (
          'id' => '021903',
          'province_id' => '0219',
          'description' => 'Alfonso Ugarte',
          'active' => 1,
        ),
        235 => 
        array (
          'id' => '021904',
          'province_id' => '0219',
          'description' => 'Cashapampa',
          'active' => 1,
        ),
        236 => 
        array (
          'id' => '021905',
          'province_id' => '0219',
          'description' => 'Chingalpo',
          'active' => 1,
        ),
        237 => 
        array (
          'id' => '021906',
          'province_id' => '0219',
          'description' => 'Huayllabamba',
          'active' => 1,
        ),
        238 => 
        array (
          'id' => '021907',
          'province_id' => '0219',
          'description' => 'Quiches',
          'active' => 1,
        ),
        239 => 
        array (
          'id' => '021908',
          'province_id' => '0219',
          'description' => 'Ragash',
          'active' => 1,
        ),
        240 => 
        array (
          'id' => '021909',
          'province_id' => '0219',
          'description' => 'San Juan',
          'active' => 1,
        ),
        241 => 
        array (
          'id' => '021910',
          'province_id' => '0219',
          'description' => 'Sicsibamba',
          'active' => 1,
        ),
        242 => 
        array (
          'id' => '022001',
          'province_id' => '0220',
          'description' => 'Yungay',
          'active' => 1,
        ),
        243 => 
        array (
          'id' => '022002',
          'province_id' => '0220',
          'description' => 'Cascapara',
          'active' => 1,
        ),
        244 => 
        array (
          'id' => '022003',
          'province_id' => '0220',
          'description' => 'Mancos',
          'active' => 1,
        ),
        245 => 
        array (
          'id' => '022004',
          'province_id' => '0220',
          'description' => 'Matacoto',
          'active' => 1,
        ),
        246 => 
        array (
          'id' => '022005',
          'province_id' => '0220',
          'description' => 'Quillo',
          'active' => 1,
        ),
        247 => 
        array (
          'id' => '022006',
          'province_id' => '0220',
          'description' => 'Ranrahirca',
          'active' => 1,
        ),
        248 => 
        array (
          'id' => '022007',
          'province_id' => '0220',
          'description' => 'Shupluy',
          'active' => 1,
        ),
        249 => 
        array (
          'id' => '022008',
          'province_id' => '0220',
          'description' => 'Yanama',
          'active' => 1,
        ),
        250 => 
        array (
          'id' => '030101',
          'province_id' => '0301',
          'description' => 'Abancay',
          'active' => 1,
        ),
        251 => 
        array (
          'id' => '030102',
          'province_id' => '0301',
          'description' => 'Chacoche',
          'active' => 1,
        ),
        252 => 
        array (
          'id' => '030103',
          'province_id' => '0301',
          'description' => 'Circa',
          'active' => 1,
        ),
        253 => 
        array (
          'id' => '030104',
          'province_id' => '0301',
          'description' => 'Curahuasi',
          'active' => 1,
        ),
        254 => 
        array (
          'id' => '030105',
          'province_id' => '0301',
          'description' => 'Huanipaca',
          'active' => 1,
        ),
        255 => 
        array (
          'id' => '030106',
          'province_id' => '0301',
          'description' => 'Lambrama',
          'active' => 1,
        ),
        256 => 
        array (
          'id' => '030107',
          'province_id' => '0301',
          'description' => 'Pichirhua',
          'active' => 1,
        ),
        257 => 
        array (
          'id' => '030108',
          'province_id' => '0301',
          'description' => 'San Pedro de Cachora',
          'active' => 1,
        ),
        258 => 
        array (
          'id' => '030109',
          'province_id' => '0301',
          'description' => 'Tamburco',
          'active' => 1,
        ),
        259 => 
        array (
          'id' => '030201',
          'province_id' => '0302',
          'description' => 'Andahuaylas',
          'active' => 1,
        ),
        260 => 
        array (
          'id' => '030202',
          'province_id' => '0302',
          'description' => 'Andarapa',
          'active' => 1,
        ),
        261 => 
        array (
          'id' => '030203',
          'province_id' => '0302',
          'description' => 'Chiara',
          'active' => 1,
        ),
        262 => 
        array (
          'id' => '030204',
          'province_id' => '0302',
          'description' => 'Huancarama',
          'active' => 1,
        ),
        263 => 
        array (
          'id' => '030205',
          'province_id' => '0302',
          'description' => 'Huancaray',
          'active' => 1,
        ),
        264 => 
        array (
          'id' => '030206',
          'province_id' => '0302',
          'description' => 'Huayana',
          'active' => 1,
        ),
        265 => 
        array (
          'id' => '030207',
          'province_id' => '0302',
          'description' => 'Kishuara',
          'active' => 1,
        ),
        266 => 
        array (
          'id' => '030208',
          'province_id' => '0302',
          'description' => 'Pacobamba',
          'active' => 1,
        ),
        267 => 
        array (
          'id' => '030209',
          'province_id' => '0302',
          'description' => 'Pacucha',
          'active' => 1,
        ),
        268 => 
        array (
          'id' => '030210',
          'province_id' => '0302',
          'description' => 'Pampachiri',
          'active' => 1,
        ),
        269 => 
        array (
          'id' => '030211',
          'province_id' => '0302',
          'description' => 'Pomacocha',
          'active' => 1,
        ),
        270 => 
        array (
          'id' => '030212',
          'province_id' => '0302',
          'description' => 'San Antonio de Cachi',
          'active' => 1,
        ),
        271 => 
        array (
          'id' => '030213',
          'province_id' => '0302',
          'description' => 'San Jerónimo',
          'active' => 1,
        ),
        272 => 
        array (
          'id' => '030214',
          'province_id' => '0302',
          'description' => 'San Miguel de Chaccrampa',
          'active' => 1,
        ),
        273 => 
        array (
          'id' => '030215',
          'province_id' => '0302',
          'description' => 'Santa María de Chicmo',
          'active' => 1,
        ),
        274 => 
        array (
          'id' => '030216',
          'province_id' => '0302',
          'description' => 'Talavera',
          'active' => 1,
        ),
        275 => 
        array (
          'id' => '030217',
          'province_id' => '0302',
          'description' => 'Tumay Huaraca',
          'active' => 1,
        ),
        276 => 
        array (
          'id' => '030218',
          'province_id' => '0302',
          'description' => 'Turpo',
          'active' => 1,
        ),
        277 => 
        array (
          'id' => '030219',
          'province_id' => '0302',
          'description' => 'Kaquiabamba',
          'active' => 1,
        ),
        278 => 
        array (
          'id' => '030220',
          'province_id' => '0302',
          'description' => 'José María Arguedas',
          'active' => 1,
        ),
        279 => 
        array (
          'id' => '030301',
          'province_id' => '0303',
          'description' => 'Antabamba',
          'active' => 1,
        ),
        280 => 
        array (
          'id' => '030302',
          'province_id' => '0303',
          'description' => 'El Oro',
          'active' => 1,
        ),
        281 => 
        array (
          'id' => '030303',
          'province_id' => '0303',
          'description' => 'Huaquirca',
          'active' => 1,
        ),
        282 => 
        array (
          'id' => '030304',
          'province_id' => '0303',
          'description' => 'Juan Espinoza Medrano',
          'active' => 1,
        ),
        283 => 
        array (
          'id' => '030305',
          'province_id' => '0303',
          'description' => 'Oropesa',
          'active' => 1,
        ),
        284 => 
        array (
          'id' => '030306',
          'province_id' => '0303',
          'description' => 'Pachaconas',
          'active' => 1,
        ),
        285 => 
        array (
          'id' => '030307',
          'province_id' => '0303',
          'description' => 'Sabaino',
          'active' => 1,
        ),
        286 => 
        array (
          'id' => '030401',
          'province_id' => '0304',
          'description' => 'Chalhuanca',
          'active' => 1,
        ),
        287 => 
        array (
          'id' => '030402',
          'province_id' => '0304',
          'description' => 'Capaya',
          'active' => 1,
        ),
        288 => 
        array (
          'id' => '030403',
          'province_id' => '0304',
          'description' => 'Caraybamba',
          'active' => 1,
        ),
        289 => 
        array (
          'id' => '030404',
          'province_id' => '0304',
          'description' => 'Chapimarca',
          'active' => 1,
        ),
        290 => 
        array (
          'id' => '030405',
          'province_id' => '0304',
          'description' => 'Colcabamba',
          'active' => 1,
        ),
        291 => 
        array (
          'id' => '030406',
          'province_id' => '0304',
          'description' => 'Cotaruse',
          'active' => 1,
        ),
        292 => 
        array (
          'id' => '030407',
          'province_id' => '0304',
          'description' => 'Ihuayllo',
          'active' => 1,
        ),
        293 => 
        array (
          'id' => '030408',
          'province_id' => '0304',
          'description' => 'Justo Apu Sahuaraura',
          'active' => 1,
        ),
        294 => 
        array (
          'id' => '030409',
          'province_id' => '0304',
          'description' => 'Lucre',
          'active' => 1,
        ),
        295 => 
        array (
          'id' => '030410',
          'province_id' => '0304',
          'description' => 'Pocohuanca',
          'active' => 1,
        ),
        296 => 
        array (
          'id' => '030411',
          'province_id' => '0304',
          'description' => 'San Juan de Chacña',
          'active' => 1,
        ),
        297 => 
        array (
          'id' => '030412',
          'province_id' => '0304',
          'description' => 'Sañayca',
          'active' => 1,
        ),
        298 => 
        array (
          'id' => '030413',
          'province_id' => '0304',
          'description' => 'Soraya',
          'active' => 1,
        ),
        299 => 
        array (
          'id' => '030414',
          'province_id' => '0304',
          'description' => 'Tapairihua',
          'active' => 1,
        ),
        300 => 
        array (
          'id' => '030415',
          'province_id' => '0304',
          'description' => 'Tintay',
          'active' => 1,
        ),
        301 => 
        array (
          'id' => '030416',
          'province_id' => '0304',
          'description' => 'Toraya',
          'active' => 1,
        ),
        302 => 
        array (
          'id' => '030417',
          'province_id' => '0304',
          'description' => 'Yanaca',
          'active' => 1,
        ),
        303 => 
        array (
          'id' => '030501',
          'province_id' => '0305',
          'description' => 'Tambobamba',
          'active' => 1,
        ),
        304 => 
        array (
          'id' => '030502',
          'province_id' => '0305',
          'description' => 'Cotabambas',
          'active' => 1,
        ),
        305 => 
        array (
          'id' => '030503',
          'province_id' => '0305',
          'description' => 'Coyllurqui',
          'active' => 1,
        ),
        306 => 
        array (
          'id' => '030504',
          'province_id' => '0305',
          'description' => 'Haquira',
          'active' => 1,
        ),
        307 => 
        array (
          'id' => '030505',
          'province_id' => '0305',
          'description' => 'Mara',
          'active' => 1,
        ),
        308 => 
        array (
          'id' => '030506',
          'province_id' => '0305',
          'description' => 'Challhuahuacho',
          'active' => 1,
        ),
        309 => 
        array (
          'id' => '030601',
          'province_id' => '0306',
          'description' => 'Chincheros',
          'active' => 1,
        ),
        310 => 
        array (
          'id' => '030602',
          'province_id' => '0306',
          'description' => 'Anco_Huallo',
          'active' => 1,
        ),
        311 => 
        array (
          'id' => '030603',
          'province_id' => '0306',
          'description' => 'Cocharcas',
          'active' => 1,
        ),
        312 => 
        array (
          'id' => '030604',
          'province_id' => '0306',
          'description' => 'Huaccana',
          'active' => 1,
        ),
        313 => 
        array (
          'id' => '030605',
          'province_id' => '0306',
          'description' => 'Ocobamba',
          'active' => 1,
        ),
        314 => 
        array (
          'id' => '030606',
          'province_id' => '0306',
          'description' => 'Ongoy',
          'active' => 1,
        ),
        315 => 
        array (
          'id' => '030607',
          'province_id' => '0306',
          'description' => 'Uranmarca',
          'active' => 1,
        ),
        316 => 
        array (
          'id' => '030608',
          'province_id' => '0306',
          'description' => 'Ranracancha',
          'active' => 1,
        ),
        317 => 
        array (
          'id' => '030609',
          'province_id' => '0306',
          'description' => 'Rocchacc',
          'active' => 1,
        ),
        318 => 
        array (
          'id' => '030610',
          'province_id' => '0306',
          'description' => 'El Porvenir',
          'active' => 1,
        ),
        319 => 
        array (
          'id' => '030701',
          'province_id' => '0307',
          'description' => 'Chuquibambilla',
          'active' => 1,
        ),
        320 => 
        array (
          'id' => '030702',
          'province_id' => '0307',
          'description' => 'Curpahuasi',
          'active' => 1,
        ),
        321 => 
        array (
          'id' => '030703',
          'province_id' => '0307',
          'description' => 'Gamarra',
          'active' => 1,
        ),
        322 => 
        array (
          'id' => '030704',
          'province_id' => '0307',
          'description' => 'Huayllati',
          'active' => 1,
        ),
        323 => 
        array (
          'id' => '030705',
          'province_id' => '0307',
          'description' => 'Mamara',
          'active' => 1,
        ),
        324 => 
        array (
          'id' => '030706',
          'province_id' => '0307',
          'description' => 'Micaela Bastidas',
          'active' => 1,
        ),
        325 => 
        array (
          'id' => '030707',
          'province_id' => '0307',
          'description' => 'Pataypampa',
          'active' => 1,
        ),
        326 => 
        array (
          'id' => '030708',
          'province_id' => '0307',
          'description' => 'Progreso',
          'active' => 1,
        ),
        327 => 
        array (
          'id' => '030709',
          'province_id' => '0307',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        328 => 
        array (
          'id' => '030710',
          'province_id' => '0307',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        329 => 
        array (
          'id' => '030711',
          'province_id' => '0307',
          'description' => 'Turpay',
          'active' => 1,
        ),
        330 => 
        array (
          'id' => '030712',
          'province_id' => '0307',
          'description' => 'Vilcabamba',
          'active' => 1,
        ),
        331 => 
        array (
          'id' => '030713',
          'province_id' => '0307',
          'description' => 'Virundo',
          'active' => 1,
        ),
        332 => 
        array (
          'id' => '030714',
          'province_id' => '0307',
          'description' => 'Curasco',
          'active' => 1,
        ),
        333 => 
        array (
          'id' => '040101',
          'province_id' => '0401',
          'description' => 'Arequipa',
          'active' => 1,
        ),
        334 => 
        array (
          'id' => '040102',
          'province_id' => '0401',
          'description' => 'Alto Selva Alegre',
          'active' => 1,
        ),
        335 => 
        array (
          'id' => '040103',
          'province_id' => '0401',
          'description' => 'Cayma',
          'active' => 1,
        ),
        336 => 
        array (
          'id' => '040104',
          'province_id' => '0401',
          'description' => 'Cerro Colorado',
          'active' => 1,
        ),
        337 => 
        array (
          'id' => '040105',
          'province_id' => '0401',
          'description' => 'Characato',
          'active' => 1,
        ),
        338 => 
        array (
          'id' => '040106',
          'province_id' => '0401',
          'description' => 'Chiguata',
          'active' => 1,
        ),
        339 => 
        array (
          'id' => '040107',
          'province_id' => '0401',
          'description' => 'Jacobo Hunter',
          'active' => 1,
        ),
        340 => 
        array (
          'id' => '040108',
          'province_id' => '0401',
          'description' => 'La Joya',
          'active' => 1,
        ),
        341 => 
        array (
          'id' => '040109',
          'province_id' => '0401',
          'description' => 'Mariano Melgar',
          'active' => 1,
        ),
        342 => 
        array (
          'id' => '040110',
          'province_id' => '0401',
          'description' => 'Miraflores',
          'active' => 1,
        ),
        343 => 
        array (
          'id' => '040111',
          'province_id' => '0401',
          'description' => 'Mollebaya',
          'active' => 1,
        ),
        344 => 
        array (
          'id' => '040112',
          'province_id' => '0401',
          'description' => 'Paucarpata',
          'active' => 1,
        ),
        345 => 
        array (
          'id' => '040113',
          'province_id' => '0401',
          'description' => 'Pocsi',
          'active' => 1,
        ),
        346 => 
        array (
          'id' => '040114',
          'province_id' => '0401',
          'description' => 'Polobaya',
          'active' => 1,
        ),
        347 => 
        array (
          'id' => '040115',
          'province_id' => '0401',
          'description' => 'Quequeña',
          'active' => 1,
        ),
        348 => 
        array (
          'id' => '040116',
          'province_id' => '0401',
          'description' => 'Sabandia',
          'active' => 1,
        ),
        349 => 
        array (
          'id' => '040117',
          'province_id' => '0401',
          'description' => 'Sachaca',
          'active' => 1,
        ),
        350 => 
        array (
          'id' => '040118',
          'province_id' => '0401',
          'description' => 'San Juan de Siguas',
          'active' => 1,
        ),
        351 => 
        array (
          'id' => '040119',
          'province_id' => '0401',
          'description' => 'San Juan de Tarucani',
          'active' => 1,
        ),
        352 => 
        array (
          'id' => '040120',
          'province_id' => '0401',
          'description' => 'Santa Isabel de Siguas',
          'active' => 1,
        ),
        353 => 
        array (
          'id' => '040121',
          'province_id' => '0401',
          'description' => 'Santa Rita de Siguas',
          'active' => 1,
        ),
        354 => 
        array (
          'id' => '040122',
          'province_id' => '0401',
          'description' => 'Socabaya',
          'active' => 1,
        ),
        355 => 
        array (
          'id' => '040123',
          'province_id' => '0401',
          'description' => 'Tiabaya',
          'active' => 1,
        ),
        356 => 
        array (
          'id' => '040124',
          'province_id' => '0401',
          'description' => 'Uchumayo',
          'active' => 1,
        ),
        357 => 
        array (
          'id' => '040125',
          'province_id' => '0401',
          'description' => 'Vitor',
          'active' => 1,
        ),
        358 => 
        array (
          'id' => '040126',
          'province_id' => '0401',
          'description' => 'Yanahuara',
          'active' => 1,
        ),
        359 => 
        array (
          'id' => '040127',
          'province_id' => '0401',
          'description' => 'Yarabamba',
          'active' => 1,
        ),
        360 => 
        array (
          'id' => '040128',
          'province_id' => '0401',
          'description' => 'Yura',
          'active' => 1,
        ),
        361 => 
        array (
          'id' => '040129',
          'province_id' => '0401',
          'description' => 'José Luis Bustamante Y Rivero',
          'active' => 1,
        ),
        362 => 
        array (
          'id' => '040201',
          'province_id' => '0402',
          'description' => 'Camaná',
          'active' => 1,
        ),
        363 => 
        array (
          'id' => '040202',
          'province_id' => '0402',
          'description' => 'José María Quimper',
          'active' => 1,
        ),
        364 => 
        array (
          'id' => '040203',
          'province_id' => '0402',
          'description' => 'Mariano Nicolás Valcárcel',
          'active' => 1,
        ),
        365 => 
        array (
          'id' => '040204',
          'province_id' => '0402',
          'description' => 'Mariscal Cáceres',
          'active' => 1,
        ),
        366 => 
        array (
          'id' => '040205',
          'province_id' => '0402',
          'description' => 'Nicolás de Pierola',
          'active' => 1,
        ),
        367 => 
        array (
          'id' => '040206',
          'province_id' => '0402',
          'description' => 'Ocoña',
          'active' => 1,
        ),
        368 => 
        array (
          'id' => '040207',
          'province_id' => '0402',
          'description' => 'Quilca',
          'active' => 1,
        ),
        369 => 
        array (
          'id' => '040208',
          'province_id' => '0402',
          'description' => 'Samuel Pastor',
          'active' => 1,
        ),
        370 => 
        array (
          'id' => '040301',
          'province_id' => '0403',
          'description' => 'Caravelí',
          'active' => 1,
        ),
        371 => 
        array (
          'id' => '040302',
          'province_id' => '0403',
          'description' => 'Acarí',
          'active' => 1,
        ),
        372 => 
        array (
          'id' => '040303',
          'province_id' => '0403',
          'description' => 'Atico',
          'active' => 1,
        ),
        373 => 
        array (
          'id' => '040304',
          'province_id' => '0403',
          'description' => 'Atiquipa',
          'active' => 1,
        ),
        374 => 
        array (
          'id' => '040305',
          'province_id' => '0403',
          'description' => 'Bella Unión',
          'active' => 1,
        ),
        375 => 
        array (
          'id' => '040306',
          'province_id' => '0403',
          'description' => 'Cahuacho',
          'active' => 1,
        ),
        376 => 
        array (
          'id' => '040307',
          'province_id' => '0403',
          'description' => 'Chala',
          'active' => 1,
        ),
        377 => 
        array (
          'id' => '040308',
          'province_id' => '0403',
          'description' => 'Chaparra',
          'active' => 1,
        ),
        378 => 
        array (
          'id' => '040309',
          'province_id' => '0403',
          'description' => 'Huanuhuanu',
          'active' => 1,
        ),
        379 => 
        array (
          'id' => '040310',
          'province_id' => '0403',
          'description' => 'Jaqui',
          'active' => 1,
        ),
        380 => 
        array (
          'id' => '040311',
          'province_id' => '0403',
          'description' => 'Lomas',
          'active' => 1,
        ),
        381 => 
        array (
          'id' => '040312',
          'province_id' => '0403',
          'description' => 'Quicacha',
          'active' => 1,
        ),
        382 => 
        array (
          'id' => '040313',
          'province_id' => '0403',
          'description' => 'Yauca',
          'active' => 1,
        ),
        383 => 
        array (
          'id' => '040401',
          'province_id' => '0404',
          'description' => 'Aplao',
          'active' => 1,
        ),
        384 => 
        array (
          'id' => '040402',
          'province_id' => '0404',
          'description' => 'Andagua',
          'active' => 1,
        ),
        385 => 
        array (
          'id' => '040403',
          'province_id' => '0404',
          'description' => 'Ayo',
          'active' => 1,
        ),
        386 => 
        array (
          'id' => '040404',
          'province_id' => '0404',
          'description' => 'Chachas',
          'active' => 1,
        ),
        387 => 
        array (
          'id' => '040405',
          'province_id' => '0404',
          'description' => 'Chilcaymarca',
          'active' => 1,
        ),
        388 => 
        array (
          'id' => '040406',
          'province_id' => '0404',
          'description' => 'Choco',
          'active' => 1,
        ),
        389 => 
        array (
          'id' => '040407',
          'province_id' => '0404',
          'description' => 'Huancarqui',
          'active' => 1,
        ),
        390 => 
        array (
          'id' => '040408',
          'province_id' => '0404',
          'description' => 'Machaguay',
          'active' => 1,
        ),
        391 => 
        array (
          'id' => '040409',
          'province_id' => '0404',
          'description' => 'Orcopampa',
          'active' => 1,
        ),
        392 => 
        array (
          'id' => '040410',
          'province_id' => '0404',
          'description' => 'Pampacolca',
          'active' => 1,
        ),
        393 => 
        array (
          'id' => '040411',
          'province_id' => '0404',
          'description' => 'Tipan',
          'active' => 1,
        ),
        394 => 
        array (
          'id' => '040412',
          'province_id' => '0404',
          'description' => 'Uñon',
          'active' => 1,
        ),
        395 => 
        array (
          'id' => '040413',
          'province_id' => '0404',
          'description' => 'Uraca',
          'active' => 1,
        ),
        396 => 
        array (
          'id' => '040414',
          'province_id' => '0404',
          'description' => 'Viraco',
          'active' => 1,
        ),
        397 => 
        array (
          'id' => '040501',
          'province_id' => '0405',
          'description' => 'Chivay',
          'active' => 1,
        ),
        398 => 
        array (
          'id' => '040502',
          'province_id' => '0405',
          'description' => 'Achoma',
          'active' => 1,
        ),
        399 => 
        array (
          'id' => '040503',
          'province_id' => '0405',
          'description' => 'Cabanaconde',
          'active' => 1,
        ),
        400 => 
        array (
          'id' => '040504',
          'province_id' => '0405',
          'description' => 'Callalli',
          'active' => 1,
        ),
        401 => 
        array (
          'id' => '040505',
          'province_id' => '0405',
          'description' => 'Caylloma',
          'active' => 1,
        ),
        402 => 
        array (
          'id' => '040506',
          'province_id' => '0405',
          'description' => 'Coporaque',
          'active' => 1,
        ),
        403 => 
        array (
          'id' => '040507',
          'province_id' => '0405',
          'description' => 'Huambo',
          'active' => 1,
        ),
        404 => 
        array (
          'id' => '040508',
          'province_id' => '0405',
          'description' => 'Huanca',
          'active' => 1,
        ),
        405 => 
        array (
          'id' => '040509',
          'province_id' => '0405',
          'description' => 'Ichupampa',
          'active' => 1,
        ),
        406 => 
        array (
          'id' => '040510',
          'province_id' => '0405',
          'description' => 'Lari',
          'active' => 1,
        ),
        407 => 
        array (
          'id' => '040511',
          'province_id' => '0405',
          'description' => 'Lluta',
          'active' => 1,
        ),
        408 => 
        array (
          'id' => '040512',
          'province_id' => '0405',
          'description' => 'Maca',
          'active' => 1,
        ),
        409 => 
        array (
          'id' => '040513',
          'province_id' => '0405',
          'description' => 'Madrigal',
          'active' => 1,
        ),
        410 => 
        array (
          'id' => '040514',
          'province_id' => '0405',
          'description' => 'San Antonio de Chuca',
          'active' => 1,
        ),
        411 => 
        array (
          'id' => '040515',
          'province_id' => '0405',
          'description' => 'Sibayo',
          'active' => 1,
        ),
        412 => 
        array (
          'id' => '040516',
          'province_id' => '0405',
          'description' => 'Tapay',
          'active' => 1,
        ),
        413 => 
        array (
          'id' => '040517',
          'province_id' => '0405',
          'description' => 'Tisco',
          'active' => 1,
        ),
        414 => 
        array (
          'id' => '040518',
          'province_id' => '0405',
          'description' => 'Tuti',
          'active' => 1,
        ),
        415 => 
        array (
          'id' => '040519',
          'province_id' => '0405',
          'description' => 'Yanque',
          'active' => 1,
        ),
        416 => 
        array (
          'id' => '040520',
          'province_id' => '0405',
          'description' => 'Majes',
          'active' => 1,
        ),
        417 => 
        array (
          'id' => '040601',
          'province_id' => '0406',
          'description' => 'Chuquibamba',
          'active' => 1,
        ),
        418 => 
        array (
          'id' => '040602',
          'province_id' => '0406',
          'description' => 'Andaray',
          'active' => 1,
        ),
        419 => 
        array (
          'id' => '040603',
          'province_id' => '0406',
          'description' => 'Cayarani',
          'active' => 1,
        ),
        420 => 
        array (
          'id' => '040604',
          'province_id' => '0406',
          'description' => 'Chichas',
          'active' => 1,
        ),
        421 => 
        array (
          'id' => '040605',
          'province_id' => '0406',
          'description' => 'Iray',
          'active' => 1,
        ),
        422 => 
        array (
          'id' => '040606',
          'province_id' => '0406',
          'description' => 'Río Grande',
          'active' => 1,
        ),
        423 => 
        array (
          'id' => '040607',
          'province_id' => '0406',
          'description' => 'Salamanca',
          'active' => 1,
        ),
        424 => 
        array (
          'id' => '040608',
          'province_id' => '0406',
          'description' => 'Yanaquihua',
          'active' => 1,
        ),
        425 => 
        array (
          'id' => '040701',
          'province_id' => '0407',
          'description' => 'Mollendo',
          'active' => 1,
        ),
        426 => 
        array (
          'id' => '040702',
          'province_id' => '0407',
          'description' => 'Cocachacra',
          'active' => 1,
        ),
        427 => 
        array (
          'id' => '040703',
          'province_id' => '0407',
          'description' => 'Dean Valdivia',
          'active' => 1,
        ),
        428 => 
        array (
          'id' => '040704',
          'province_id' => '0407',
          'description' => 'Islay',
          'active' => 1,
        ),
        429 => 
        array (
          'id' => '040705',
          'province_id' => '0407',
          'description' => 'Mejia',
          'active' => 1,
        ),
        430 => 
        array (
          'id' => '040706',
          'province_id' => '0407',
          'description' => 'Punta de Bombón',
          'active' => 1,
        ),
        431 => 
        array (
          'id' => '040801',
          'province_id' => '0408',
          'description' => 'Cotahuasi',
          'active' => 1,
        ),
        432 => 
        array (
          'id' => '040802',
          'province_id' => '0408',
          'description' => 'Alca',
          'active' => 1,
        ),
        433 => 
        array (
          'id' => '040803',
          'province_id' => '0408',
          'description' => 'Charcana',
          'active' => 1,
        ),
        434 => 
        array (
          'id' => '040804',
          'province_id' => '0408',
          'description' => 'Huaynacotas',
          'active' => 1,
        ),
        435 => 
        array (
          'id' => '040805',
          'province_id' => '0408',
          'description' => 'Pampamarca',
          'active' => 1,
        ),
        436 => 
        array (
          'id' => '040806',
          'province_id' => '0408',
          'description' => 'Puyca',
          'active' => 1,
        ),
        437 => 
        array (
          'id' => '040807',
          'province_id' => '0408',
          'description' => 'Quechualla',
          'active' => 1,
        ),
        438 => 
        array (
          'id' => '040808',
          'province_id' => '0408',
          'description' => 'Sayla',
          'active' => 1,
        ),
        439 => 
        array (
          'id' => '040809',
          'province_id' => '0408',
          'description' => 'Tauria',
          'active' => 1,
        ),
        440 => 
        array (
          'id' => '040810',
          'province_id' => '0408',
          'description' => 'Tomepampa',
          'active' => 1,
        ),
        441 => 
        array (
          'id' => '040811',
          'province_id' => '0408',
          'description' => 'Toro',
          'active' => 1,
        ),
        442 => 
        array (
          'id' => '050101',
          'province_id' => '0501',
          'description' => 'Ayacucho',
          'active' => 1,
        ),
        443 => 
        array (
          'id' => '050102',
          'province_id' => '0501',
          'description' => 'Acocro',
          'active' => 1,
        ),
        444 => 
        array (
          'id' => '050103',
          'province_id' => '0501',
          'description' => 'Acos Vinchos',
          'active' => 1,
        ),
        445 => 
        array (
          'id' => '050104',
          'province_id' => '0501',
          'description' => 'Carmen Alto',
          'active' => 1,
        ),
        446 => 
        array (
          'id' => '050105',
          'province_id' => '0501',
          'description' => 'Chiara',
          'active' => 1,
        ),
        447 => 
        array (
          'id' => '050106',
          'province_id' => '0501',
          'description' => 'Ocros',
          'active' => 1,
        ),
        448 => 
        array (
          'id' => '050107',
          'province_id' => '0501',
          'description' => 'Pacaycasa',
          'active' => 1,
        ),
        449 => 
        array (
          'id' => '050108',
          'province_id' => '0501',
          'description' => 'Quinua',
          'active' => 1,
        ),
        450 => 
        array (
          'id' => '050109',
          'province_id' => '0501',
          'description' => 'San José de Ticllas',
          'active' => 1,
        ),
        451 => 
        array (
          'id' => '050110',
          'province_id' => '0501',
          'description' => 'San Juan Bautista',
          'active' => 1,
        ),
        452 => 
        array (
          'id' => '050111',
          'province_id' => '0501',
          'description' => 'Santiago de Pischa',
          'active' => 1,
        ),
        453 => 
        array (
          'id' => '050112',
          'province_id' => '0501',
          'description' => 'Socos',
          'active' => 1,
        ),
        454 => 
        array (
          'id' => '050113',
          'province_id' => '0501',
          'description' => 'Tambillo',
          'active' => 1,
        ),
        455 => 
        array (
          'id' => '050114',
          'province_id' => '0501',
          'description' => 'Vinchos',
          'active' => 1,
        ),
        456 => 
        array (
          'id' => '050115',
          'province_id' => '0501',
          'description' => 'Jesús Nazareno',
          'active' => 1,
        ),
        457 => 
        array (
          'id' => '050116',
          'province_id' => '0501',
          'description' => 'Andrés Avelino Cáceres Dorregaray',
          'active' => 1,
        ),
        458 => 
        array (
          'id' => '050201',
          'province_id' => '0502',
          'description' => 'Cangallo',
          'active' => 1,
        ),
        459 => 
        array (
          'id' => '050202',
          'province_id' => '0502',
          'description' => 'Chuschi',
          'active' => 1,
        ),
        460 => 
        array (
          'id' => '050203',
          'province_id' => '0502',
          'description' => 'Los Morochucos',
          'active' => 1,
        ),
        461 => 
        array (
          'id' => '050204',
          'province_id' => '0502',
          'description' => 'María Parado de Bellido',
          'active' => 1,
        ),
        462 => 
        array (
          'id' => '050205',
          'province_id' => '0502',
          'description' => 'Paras',
          'active' => 1,
        ),
        463 => 
        array (
          'id' => '050206',
          'province_id' => '0502',
          'description' => 'Totos',
          'active' => 1,
        ),
        464 => 
        array (
          'id' => '050301',
          'province_id' => '0503',
          'description' => 'Sancos',
          'active' => 1,
        ),
        465 => 
        array (
          'id' => '050302',
          'province_id' => '0503',
          'description' => 'Carapo',
          'active' => 1,
        ),
        466 => 
        array (
          'id' => '050303',
          'province_id' => '0503',
          'description' => 'Sacsamarca',
          'active' => 1,
        ),
        467 => 
        array (
          'id' => '050304',
          'province_id' => '0503',
          'description' => 'Santiago de Lucanamarca',
          'active' => 1,
        ),
        468 => 
        array (
          'id' => '050401',
          'province_id' => '0504',
          'description' => 'Huanta',
          'active' => 1,
        ),
        469 => 
        array (
          'id' => '050402',
          'province_id' => '0504',
          'description' => 'Ayahuanco',
          'active' => 1,
        ),
        470 => 
        array (
          'id' => '050403',
          'province_id' => '0504',
          'description' => 'Huamanguilla',
          'active' => 1,
        ),
        471 => 
        array (
          'id' => '050404',
          'province_id' => '0504',
          'description' => 'Iguain',
          'active' => 1,
        ),
        472 => 
        array (
          'id' => '050405',
          'province_id' => '0504',
          'description' => 'Luricocha',
          'active' => 1,
        ),
        473 => 
        array (
          'id' => '050406',
          'province_id' => '0504',
          'description' => 'Santillana',
          'active' => 1,
        ),
        474 => 
        array (
          'id' => '050407',
          'province_id' => '0504',
          'description' => 'Sivia',
          'active' => 1,
        ),
        475 => 
        array (
          'id' => '050408',
          'province_id' => '0504',
          'description' => 'Llochegua',
          'active' => 1,
        ),
        476 => 
        array (
          'id' => '050409',
          'province_id' => '0504',
          'description' => 'Canayre',
          'active' => 1,
        ),
        477 => 
        array (
          'id' => '050410',
          'province_id' => '0504',
          'description' => 'Uchuraccay',
          'active' => 1,
        ),
        478 => 
        array (
          'id' => '050411',
          'province_id' => '0504',
          'description' => 'Pucacolpa',
          'active' => 1,
        ),
        479 => 
        array (
          'id' => '050412',
          'province_id' => '0504',
          'description' => 'Chaca',
          'active' => 1,
        ),
        480 => 
        array (
          'id' => '050501',
          'province_id' => '0505',
          'description' => 'San Miguel',
          'active' => 1,
        ),
        481 => 
        array (
          'id' => '050502',
          'province_id' => '0505',
          'description' => 'Anco',
          'active' => 1,
        ),
        482 => 
        array (
          'id' => '050503',
          'province_id' => '0505',
          'description' => 'Ayna',
          'active' => 1,
        ),
        483 => 
        array (
          'id' => '050504',
          'province_id' => '0505',
          'description' => 'Chilcas',
          'active' => 1,
        ),
        484 => 
        array (
          'id' => '050505',
          'province_id' => '0505',
          'description' => 'Chungui',
          'active' => 1,
        ),
        485 => 
        array (
          'id' => '050506',
          'province_id' => '0505',
          'description' => 'Luis Carranza',
          'active' => 1,
        ),
        486 => 
        array (
          'id' => '050507',
          'province_id' => '0505',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        487 => 
        array (
          'id' => '050508',
          'province_id' => '0505',
          'description' => 'Tambo',
          'active' => 1,
        ),
        488 => 
        array (
          'id' => '050509',
          'province_id' => '0505',
          'description' => 'Samugari',
          'active' => 1,
        ),
        489 => 
        array (
          'id' => '050510',
          'province_id' => '0505',
          'description' => 'Anchihuay',
          'active' => 1,
        ),
        490 => 
        array (
          'id' => '050511',
          'province_id' => '0505',
          'description' => 'Oronccoy',
          'active' => 1,
        ),
        491 => 
        array (
          'id' => '050601',
          'province_id' => '0506',
          'description' => 'Puquio',
          'active' => 1,
        ),
        492 => 
        array (
          'id' => '050602',
          'province_id' => '0506',
          'description' => 'Aucara',
          'active' => 1,
        ),
        493 => 
        array (
          'id' => '050603',
          'province_id' => '0506',
          'description' => 'Cabana',
          'active' => 1,
        ),
        494 => 
        array (
          'id' => '050604',
          'province_id' => '0506',
          'description' => 'Carmen Salcedo',
          'active' => 1,
        ),
        495 => 
        array (
          'id' => '050605',
          'province_id' => '0506',
          'description' => 'Chaviña',
          'active' => 1,
        ),
        496 => 
        array (
          'id' => '050606',
          'province_id' => '0506',
          'description' => 'Chipao',
          'active' => 1,
        ),
        497 => 
        array (
          'id' => '050607',
          'province_id' => '0506',
          'description' => 'Huac-Huas',
          'active' => 1,
        ),
        498 => 
        array (
          'id' => '050608',
          'province_id' => '0506',
          'description' => 'Laramate',
          'active' => 1,
        ),
        499 => 
        array (
          'id' => '050609',
          'province_id' => '0506',
          'description' => 'Leoncio Prado',
          'active' => 1,
        ),
        500 => 
        array (
          'id' => '050610',
          'province_id' => '0506',
          'description' => 'Llauta',
          'active' => 1,
        ),
        501 => 
        array (
          'id' => '050611',
          'province_id' => '0506',
          'description' => 'Lucanas',
          'active' => 1,
        ),
        502 => 
        array (
          'id' => '050612',
          'province_id' => '0506',
          'description' => 'Ocaña',
          'active' => 1,
        ),
        503 => 
        array (
          'id' => '050613',
          'province_id' => '0506',
          'description' => 'Otoca',
          'active' => 1,
        ),
        504 => 
        array (
          'id' => '050614',
          'province_id' => '0506',
          'description' => 'Saisa',
          'active' => 1,
        ),
        505 => 
        array (
          'id' => '050615',
          'province_id' => '0506',
          'description' => 'San Cristóbal',
          'active' => 1,
        ),
        506 => 
        array (
          'id' => '050616',
          'province_id' => '0506',
          'description' => 'San Juan',
          'active' => 1,
        ),
        507 => 
        array (
          'id' => '050617',
          'province_id' => '0506',
          'description' => 'San Pedro',
          'active' => 1,
        ),
        508 => 
        array (
          'id' => '050618',
          'province_id' => '0506',
          'description' => 'San Pedro de Palco',
          'active' => 1,
        ),
        509 => 
        array (
          'id' => '050619',
          'province_id' => '0506',
          'description' => 'Sancos',
          'active' => 1,
        ),
        510 => 
        array (
          'id' => '050620',
          'province_id' => '0506',
          'description' => 'Santa Ana de Huaycahuacho',
          'active' => 1,
        ),
        511 => 
        array (
          'id' => '050621',
          'province_id' => '0506',
          'description' => 'Santa Lucia',
          'active' => 1,
        ),
        512 => 
        array (
          'id' => '050701',
          'province_id' => '0507',
          'description' => 'Coracora',
          'active' => 1,
        ),
        513 => 
        array (
          'id' => '050702',
          'province_id' => '0507',
          'description' => 'Chumpi',
          'active' => 1,
        ),
        514 => 
        array (
          'id' => '050703',
          'province_id' => '0507',
          'description' => 'Coronel Castañeda',
          'active' => 1,
        ),
        515 => 
        array (
          'id' => '050704',
          'province_id' => '0507',
          'description' => 'Pacapausa',
          'active' => 1,
        ),
        516 => 
        array (
          'id' => '050705',
          'province_id' => '0507',
          'description' => 'Pullo',
          'active' => 1,
        ),
        517 => 
        array (
          'id' => '050706',
          'province_id' => '0507',
          'description' => 'Puyusca',
          'active' => 1,
        ),
        518 => 
        array (
          'id' => '050707',
          'province_id' => '0507',
          'description' => 'San Francisco de Ravacayco',
          'active' => 1,
        ),
        519 => 
        array (
          'id' => '050708',
          'province_id' => '0507',
          'description' => 'Upahuacho',
          'active' => 1,
        ),
        520 => 
        array (
          'id' => '050801',
          'province_id' => '0508',
          'description' => 'Pausa',
          'active' => 1,
        ),
        521 => 
        array (
          'id' => '050802',
          'province_id' => '0508',
          'description' => 'Colta',
          'active' => 1,
        ),
        522 => 
        array (
          'id' => '050803',
          'province_id' => '0508',
          'description' => 'Corculla',
          'active' => 1,
        ),
        523 => 
        array (
          'id' => '050804',
          'province_id' => '0508',
          'description' => 'Lampa',
          'active' => 1,
        ),
        524 => 
        array (
          'id' => '050805',
          'province_id' => '0508',
          'description' => 'Marcabamba',
          'active' => 1,
        ),
        525 => 
        array (
          'id' => '050806',
          'province_id' => '0508',
          'description' => 'Oyolo',
          'active' => 1,
        ),
        526 => 
        array (
          'id' => '050807',
          'province_id' => '0508',
          'description' => 'Pararca',
          'active' => 1,
        ),
        527 => 
        array (
          'id' => '050808',
          'province_id' => '0508',
          'description' => 'San Javier de Alpabamba',
          'active' => 1,
        ),
        528 => 
        array (
          'id' => '050809',
          'province_id' => '0508',
          'description' => 'San José de Ushua',
          'active' => 1,
        ),
        529 => 
        array (
          'id' => '050810',
          'province_id' => '0508',
          'description' => 'Sara Sara',
          'active' => 1,
        ),
        530 => 
        array (
          'id' => '050901',
          'province_id' => '0509',
          'description' => 'Querobamba',
          'active' => 1,
        ),
        531 => 
        array (
          'id' => '050902',
          'province_id' => '0509',
          'description' => 'Belén',
          'active' => 1,
        ),
        532 => 
        array (
          'id' => '050903',
          'province_id' => '0509',
          'description' => 'Chalcos',
          'active' => 1,
        ),
        533 => 
        array (
          'id' => '050904',
          'province_id' => '0509',
          'description' => 'Chilcayoc',
          'active' => 1,
        ),
        534 => 
        array (
          'id' => '050905',
          'province_id' => '0509',
          'description' => 'Huacaña',
          'active' => 1,
        ),
        535 => 
        array (
          'id' => '050906',
          'province_id' => '0509',
          'description' => 'Morcolla',
          'active' => 1,
        ),
        536 => 
        array (
          'id' => '050907',
          'province_id' => '0509',
          'description' => 'Paico',
          'active' => 1,
        ),
        537 => 
        array (
          'id' => '050908',
          'province_id' => '0509',
          'description' => 'San Pedro de Larcay',
          'active' => 1,
        ),
        538 => 
        array (
          'id' => '050909',
          'province_id' => '0509',
          'description' => 'San Salvador de Quije',
          'active' => 1,
        ),
        539 => 
        array (
          'id' => '050910',
          'province_id' => '0509',
          'description' => 'Santiago de Paucaray',
          'active' => 1,
        ),
        540 => 
        array (
          'id' => '050911',
          'province_id' => '0509',
          'description' => 'Soras',
          'active' => 1,
        ),
        541 => 
        array (
          'id' => '051001',
          'province_id' => '0510',
          'description' => 'Huancapi',
          'active' => 1,
        ),
        542 => 
        array (
          'id' => '051002',
          'province_id' => '0510',
          'description' => 'Alcamenca',
          'active' => 1,
        ),
        543 => 
        array (
          'id' => '051003',
          'province_id' => '0510',
          'description' => 'Apongo',
          'active' => 1,
        ),
        544 => 
        array (
          'id' => '051004',
          'province_id' => '0510',
          'description' => 'Asquipata',
          'active' => 1,
        ),
        545 => 
        array (
          'id' => '051005',
          'province_id' => '0510',
          'description' => 'Canaria',
          'active' => 1,
        ),
        546 => 
        array (
          'id' => '051006',
          'province_id' => '0510',
          'description' => 'Cayara',
          'active' => 1,
        ),
        547 => 
        array (
          'id' => '051007',
          'province_id' => '0510',
          'description' => 'Colca',
          'active' => 1,
        ),
        548 => 
        array (
          'id' => '051008',
          'province_id' => '0510',
          'description' => 'Huamanquiquia',
          'active' => 1,
        ),
        549 => 
        array (
          'id' => '051009',
          'province_id' => '0510',
          'description' => 'Huancaraylla',
          'active' => 1,
        ),
        550 => 
        array (
          'id' => '051010',
          'province_id' => '0510',
          'description' => 'Huaya',
          'active' => 1,
        ),
        551 => 
        array (
          'id' => '051011',
          'province_id' => '0510',
          'description' => 'Sarhua',
          'active' => 1,
        ),
        552 => 
        array (
          'id' => '051012',
          'province_id' => '0510',
          'description' => 'Vilcanchos',
          'active' => 1,
        ),
        553 => 
        array (
          'id' => '051101',
          'province_id' => '0511',
          'description' => 'Vilcas Huaman',
          'active' => 1,
        ),
        554 => 
        array (
          'id' => '051102',
          'province_id' => '0511',
          'description' => 'Accomarca',
          'active' => 1,
        ),
        555 => 
        array (
          'id' => '051103',
          'province_id' => '0511',
          'description' => 'Carhuanca',
          'active' => 1,
        ),
        556 => 
        array (
          'id' => '051104',
          'province_id' => '0511',
          'description' => 'Concepción',
          'active' => 1,
        ),
        557 => 
        array (
          'id' => '051105',
          'province_id' => '0511',
          'description' => 'Huambalpa',
          'active' => 1,
        ),
        558 => 
        array (
          'id' => '051106',
          'province_id' => '0511',
          'description' => 'Independencia',
          'active' => 1,
        ),
        559 => 
        array (
          'id' => '051107',
          'province_id' => '0511',
          'description' => 'Saurama',
          'active' => 1,
        ),
        560 => 
        array (
          'id' => '051108',
          'province_id' => '0511',
          'description' => 'Vischongo',
          'active' => 1,
        ),
        561 => 
        array (
          'id' => '060101',
          'province_id' => '0601',
          'description' => 'Cajamarca',
          'active' => 1,
        ),
        562 => 
        array (
          'id' => '060102',
          'province_id' => '0601',
          'description' => 'Asunción',
          'active' => 1,
        ),
        563 => 
        array (
          'id' => '060103',
          'province_id' => '0601',
          'description' => 'Chetilla',
          'active' => 1,
        ),
        564 => 
        array (
          'id' => '060104',
          'province_id' => '0601',
          'description' => 'Cospan',
          'active' => 1,
        ),
        565 => 
        array (
          'id' => '060105',
          'province_id' => '0601',
          'description' => 'Encañada',
          'active' => 1,
        ),
        566 => 
        array (
          'id' => '060106',
          'province_id' => '0601',
          'description' => 'Jesús',
          'active' => 1,
        ),
        567 => 
        array (
          'id' => '060107',
          'province_id' => '0601',
          'description' => 'Llacanora',
          'active' => 1,
        ),
        568 => 
        array (
          'id' => '060108',
          'province_id' => '0601',
          'description' => 'Los Baños del Inca',
          'active' => 1,
        ),
        569 => 
        array (
          'id' => '060109',
          'province_id' => '0601',
          'description' => 'Magdalena',
          'active' => 1,
        ),
        570 => 
        array (
          'id' => '060110',
          'province_id' => '0601',
          'description' => 'Matara',
          'active' => 1,
        ),
        571 => 
        array (
          'id' => '060111',
          'province_id' => '0601',
          'description' => 'Namora',
          'active' => 1,
        ),
        572 => 
        array (
          'id' => '060112',
          'province_id' => '0601',
          'description' => 'San Juan',
          'active' => 1,
        ),
        573 => 
        array (
          'id' => '060201',
          'province_id' => '0602',
          'description' => 'Cajabamba',
          'active' => 1,
        ),
        574 => 
        array (
          'id' => '060202',
          'province_id' => '0602',
          'description' => 'Cachachi',
          'active' => 1,
        ),
        575 => 
        array (
          'id' => '060203',
          'province_id' => '0602',
          'description' => 'Condebamba',
          'active' => 1,
        ),
        576 => 
        array (
          'id' => '060204',
          'province_id' => '0602',
          'description' => 'Sitacocha',
          'active' => 1,
        ),
        577 => 
        array (
          'id' => '060301',
          'province_id' => '0603',
          'description' => 'Celendín',
          'active' => 1,
        ),
        578 => 
        array (
          'id' => '060302',
          'province_id' => '0603',
          'description' => 'Chumuch',
          'active' => 1,
        ),
        579 => 
        array (
          'id' => '060303',
          'province_id' => '0603',
          'description' => 'Cortegana',
          'active' => 1,
        ),
        580 => 
        array (
          'id' => '060304',
          'province_id' => '0603',
          'description' => 'Huasmin',
          'active' => 1,
        ),
        581 => 
        array (
          'id' => '060305',
          'province_id' => '0603',
          'description' => 'Jorge Chávez',
          'active' => 1,
        ),
        582 => 
        array (
          'id' => '060306',
          'province_id' => '0603',
          'description' => 'José Gálvez',
          'active' => 1,
        ),
        583 => 
        array (
          'id' => '060307',
          'province_id' => '0603',
          'description' => 'Miguel Iglesias',
          'active' => 1,
        ),
        584 => 
        array (
          'id' => '060308',
          'province_id' => '0603',
          'description' => 'Oxamarca',
          'active' => 1,
        ),
        585 => 
        array (
          'id' => '060309',
          'province_id' => '0603',
          'description' => 'Sorochuco',
          'active' => 1,
        ),
        586 => 
        array (
          'id' => '060310',
          'province_id' => '0603',
          'description' => 'Sucre',
          'active' => 1,
        ),
        587 => 
        array (
          'id' => '060311',
          'province_id' => '0603',
          'description' => 'Utco',
          'active' => 1,
        ),
        588 => 
        array (
          'id' => '060312',
          'province_id' => '0603',
          'description' => 'La Libertad de Pallan',
          'active' => 1,
        ),
        589 => 
        array (
          'id' => '060401',
          'province_id' => '0604',
          'description' => 'Chota',
          'active' => 1,
        ),
        590 => 
        array (
          'id' => '060402',
          'province_id' => '0604',
          'description' => 'Anguia',
          'active' => 1,
        ),
        591 => 
        array (
          'id' => '060403',
          'province_id' => '0604',
          'description' => 'Chadin',
          'active' => 1,
        ),
        592 => 
        array (
          'id' => '060404',
          'province_id' => '0604',
          'description' => 'Chiguirip',
          'active' => 1,
        ),
        593 => 
        array (
          'id' => '060405',
          'province_id' => '0604',
          'description' => 'Chimban',
          'active' => 1,
        ),
        594 => 
        array (
          'id' => '060406',
          'province_id' => '0604',
          'description' => 'Choropampa',
          'active' => 1,
        ),
        595 => 
        array (
          'id' => '060407',
          'province_id' => '0604',
          'description' => 'Cochabamba',
          'active' => 1,
        ),
        596 => 
        array (
          'id' => '060408',
          'province_id' => '0604',
          'description' => 'Conchan',
          'active' => 1,
        ),
        597 => 
        array (
          'id' => '060409',
          'province_id' => '0604',
          'description' => 'Huambos',
          'active' => 1,
        ),
        598 => 
        array (
          'id' => '060410',
          'province_id' => '0604',
          'description' => 'Lajas',
          'active' => 1,
        ),
        599 => 
        array (
          'id' => '060411',
          'province_id' => '0604',
          'description' => 'Llama',
          'active' => 1,
        ),
        600 => 
        array (
          'id' => '060412',
          'province_id' => '0604',
          'description' => 'Miracosta',
          'active' => 1,
        ),
        601 => 
        array (
          'id' => '060413',
          'province_id' => '0604',
          'description' => 'Paccha',
          'active' => 1,
        ),
        602 => 
        array (
          'id' => '060414',
          'province_id' => '0604',
          'description' => 'Pion',
          'active' => 1,
        ),
        603 => 
        array (
          'id' => '060415',
          'province_id' => '0604',
          'description' => 'Querocoto',
          'active' => 1,
        ),
        604 => 
        array (
          'id' => '060416',
          'province_id' => '0604',
          'description' => 'San Juan de Licupis',
          'active' => 1,
        ),
        605 => 
        array (
          'id' => '060417',
          'province_id' => '0604',
          'description' => 'Tacabamba',
          'active' => 1,
        ),
        606 => 
        array (
          'id' => '060418',
          'province_id' => '0604',
          'description' => 'Tocmoche',
          'active' => 1,
        ),
        607 => 
        array (
          'id' => '060419',
          'province_id' => '0604',
          'description' => 'Chalamarca',
          'active' => 1,
        ),
        608 => 
        array (
          'id' => '060501',
          'province_id' => '0605',
          'description' => 'Contumaza',
          'active' => 1,
        ),
        609 => 
        array (
          'id' => '060502',
          'province_id' => '0605',
          'description' => 'Chilete',
          'active' => 1,
        ),
        610 => 
        array (
          'id' => '060503',
          'province_id' => '0605',
          'description' => 'Cupisnique',
          'active' => 1,
        ),
        611 => 
        array (
          'id' => '060504',
          'province_id' => '0605',
          'description' => 'Guzmango',
          'active' => 1,
        ),
        612 => 
        array (
          'id' => '060505',
          'province_id' => '0605',
          'description' => 'San Benito',
          'active' => 1,
        ),
        613 => 
        array (
          'id' => '060506',
          'province_id' => '0605',
          'description' => 'Santa Cruz de Toledo',
          'active' => 1,
        ),
        614 => 
        array (
          'id' => '060507',
          'province_id' => '0605',
          'description' => 'Tantarica',
          'active' => 1,
        ),
        615 => 
        array (
          'id' => '060508',
          'province_id' => '0605',
          'description' => 'Yonan',
          'active' => 1,
        ),
        616 => 
        array (
          'id' => '060601',
          'province_id' => '0606',
          'description' => 'Cutervo',
          'active' => 1,
        ),
        617 => 
        array (
          'id' => '060602',
          'province_id' => '0606',
          'description' => 'Callayuc',
          'active' => 1,
        ),
        618 => 
        array (
          'id' => '060603',
          'province_id' => '0606',
          'description' => 'Choros',
          'active' => 1,
        ),
        619 => 
        array (
          'id' => '060604',
          'province_id' => '0606',
          'description' => 'Cujillo',
          'active' => 1,
        ),
        620 => 
        array (
          'id' => '060605',
          'province_id' => '0606',
          'description' => 'La Ramada',
          'active' => 1,
        ),
        621 => 
        array (
          'id' => '060606',
          'province_id' => '0606',
          'description' => 'Pimpingos',
          'active' => 1,
        ),
        622 => 
        array (
          'id' => '060607',
          'province_id' => '0606',
          'description' => 'Querocotillo',
          'active' => 1,
        ),
        623 => 
        array (
          'id' => '060608',
          'province_id' => '0606',
          'description' => 'San Andrés de Cutervo',
          'active' => 1,
        ),
        624 => 
        array (
          'id' => '060609',
          'province_id' => '0606',
          'description' => 'San Juan de Cutervo',
          'active' => 1,
        ),
        625 => 
        array (
          'id' => '060610',
          'province_id' => '0606',
          'description' => 'San Luis de Lucma',
          'active' => 1,
        ),
        626 => 
        array (
          'id' => '060611',
          'province_id' => '0606',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        627 => 
        array (
          'id' => '060612',
          'province_id' => '0606',
          'description' => 'Santo Domingo de la Capilla',
          'active' => 1,
        ),
        628 => 
        array (
          'id' => '060613',
          'province_id' => '0606',
          'description' => 'Santo Tomas',
          'active' => 1,
        ),
        629 => 
        array (
          'id' => '060614',
          'province_id' => '0606',
          'description' => 'Socota',
          'active' => 1,
        ),
        630 => 
        array (
          'id' => '060615',
          'province_id' => '0606',
          'description' => 'Toribio Casanova',
          'active' => 1,
        ),
        631 => 
        array (
          'id' => '060701',
          'province_id' => '0607',
          'description' => 'Bambamarca',
          'active' => 1,
        ),
        632 => 
        array (
          'id' => '060702',
          'province_id' => '0607',
          'description' => 'Chugur',
          'active' => 1,
        ),
        633 => 
        array (
          'id' => '060703',
          'province_id' => '0607',
          'description' => 'Hualgayoc',
          'active' => 1,
        ),
        634 => 
        array (
          'id' => '060801',
          'province_id' => '0608',
          'description' => 'Jaén',
          'active' => 1,
        ),
        635 => 
        array (
          'id' => '060802',
          'province_id' => '0608',
          'description' => 'Bellavista',
          'active' => 1,
        ),
        636 => 
        array (
          'id' => '060803',
          'province_id' => '0608',
          'description' => 'Chontali',
          'active' => 1,
        ),
        637 => 
        array (
          'id' => '060804',
          'province_id' => '0608',
          'description' => 'Colasay',
          'active' => 1,
        ),
        638 => 
        array (
          'id' => '060805',
          'province_id' => '0608',
          'description' => 'Huabal',
          'active' => 1,
        ),
        639 => 
        array (
          'id' => '060806',
          'province_id' => '0608',
          'description' => 'Las Pirias',
          'active' => 1,
        ),
        640 => 
        array (
          'id' => '060807',
          'province_id' => '0608',
          'description' => 'Pomahuaca',
          'active' => 1,
        ),
        641 => 
        array (
          'id' => '060808',
          'province_id' => '0608',
          'description' => 'Pucara',
          'active' => 1,
        ),
        642 => 
        array (
          'id' => '060809',
          'province_id' => '0608',
          'description' => 'Sallique',
          'active' => 1,
        ),
        643 => 
        array (
          'id' => '060810',
          'province_id' => '0608',
          'description' => 'San Felipe',
          'active' => 1,
        ),
        644 => 
        array (
          'id' => '060811',
          'province_id' => '0608',
          'description' => 'San José del Alto',
          'active' => 1,
        ),
        645 => 
        array (
          'id' => '060812',
          'province_id' => '0608',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        646 => 
        array (
          'id' => '060901',
          'province_id' => '0609',
          'description' => 'San Ignacio',
          'active' => 1,
        ),
        647 => 
        array (
          'id' => '060902',
          'province_id' => '0609',
          'description' => 'Chirinos',
          'active' => 1,
        ),
        648 => 
        array (
          'id' => '060903',
          'province_id' => '0609',
          'description' => 'Huarango',
          'active' => 1,
        ),
        649 => 
        array (
          'id' => '060904',
          'province_id' => '0609',
          'description' => 'La Coipa',
          'active' => 1,
        ),
        650 => 
        array (
          'id' => '060905',
          'province_id' => '0609',
          'description' => 'Namballe',
          'active' => 1,
        ),
        651 => 
        array (
          'id' => '060906',
          'province_id' => '0609',
          'description' => 'San José de Lourdes',
          'active' => 1,
        ),
        652 => 
        array (
          'id' => '060907',
          'province_id' => '0609',
          'description' => 'Tabaconas',
          'active' => 1,
        ),
        653 => 
        array (
          'id' => '061001',
          'province_id' => '0610',
          'description' => 'Pedro Gálvez',
          'active' => 1,
        ),
        654 => 
        array (
          'id' => '061002',
          'province_id' => '0610',
          'description' => 'Chancay',
          'active' => 1,
        ),
        655 => 
        array (
          'id' => '061003',
          'province_id' => '0610',
          'description' => 'Eduardo Villanueva',
          'active' => 1,
        ),
        656 => 
        array (
          'id' => '061004',
          'province_id' => '0610',
          'description' => 'Gregorio Pita',
          'active' => 1,
        ),
        657 => 
        array (
          'id' => '061005',
          'province_id' => '0610',
          'description' => 'Ichocan',
          'active' => 1,
        ),
        658 => 
        array (
          'id' => '061006',
          'province_id' => '0610',
          'description' => 'José Manuel Quiroz',
          'active' => 1,
        ),
        659 => 
        array (
          'id' => '061007',
          'province_id' => '0610',
          'description' => 'José Sabogal',
          'active' => 1,
        ),
        660 => 
        array (
          'id' => '061101',
          'province_id' => '0611',
          'description' => 'San Miguel',
          'active' => 1,
        ),
        661 => 
        array (
          'id' => '061102',
          'province_id' => '0611',
          'description' => 'Bolívar',
          'active' => 1,
        ),
        662 => 
        array (
          'id' => '061103',
          'province_id' => '0611',
          'description' => 'Calquis',
          'active' => 1,
        ),
        663 => 
        array (
          'id' => '061104',
          'province_id' => '0611',
          'description' => 'Catilluc',
          'active' => 1,
        ),
        664 => 
        array (
          'id' => '061105',
          'province_id' => '0611',
          'description' => 'El Prado',
          'active' => 1,
        ),
        665 => 
        array (
          'id' => '061106',
          'province_id' => '0611',
          'description' => 'La Florida',
          'active' => 1,
        ),
        666 => 
        array (
          'id' => '061107',
          'province_id' => '0611',
          'description' => 'Llapa',
          'active' => 1,
        ),
        667 => 
        array (
          'id' => '061108',
          'province_id' => '0611',
          'description' => 'Nanchoc',
          'active' => 1,
        ),
        668 => 
        array (
          'id' => '061109',
          'province_id' => '0611',
          'description' => 'Niepos',
          'active' => 1,
        ),
        669 => 
        array (
          'id' => '061110',
          'province_id' => '0611',
          'description' => 'San Gregorio',
          'active' => 1,
        ),
        670 => 
        array (
          'id' => '061111',
          'province_id' => '0611',
          'description' => 'San Silvestre de Cochan',
          'active' => 1,
        ),
        671 => 
        array (
          'id' => '061112',
          'province_id' => '0611',
          'description' => 'Tongod',
          'active' => 1,
        ),
        672 => 
        array (
          'id' => '061113',
          'province_id' => '0611',
          'description' => 'Unión Agua Blanca',
          'active' => 1,
        ),
        673 => 
        array (
          'id' => '061201',
          'province_id' => '0612',
          'description' => 'San Pablo',
          'active' => 1,
        ),
        674 => 
        array (
          'id' => '061202',
          'province_id' => '0612',
          'description' => 'San Bernardino',
          'active' => 1,
        ),
        675 => 
        array (
          'id' => '061203',
          'province_id' => '0612',
          'description' => 'San Luis',
          'active' => 1,
        ),
        676 => 
        array (
          'id' => '061204',
          'province_id' => '0612',
          'description' => 'Tumbaden',
          'active' => 1,
        ),
        677 => 
        array (
          'id' => '061301',
          'province_id' => '0613',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        678 => 
        array (
          'id' => '061302',
          'province_id' => '0613',
          'description' => 'Andabamba',
          'active' => 1,
        ),
        679 => 
        array (
          'id' => '061303',
          'province_id' => '0613',
          'description' => 'Catache',
          'active' => 1,
        ),
        680 => 
        array (
          'id' => '061304',
          'province_id' => '0613',
          'description' => 'Chancaybaños',
          'active' => 1,
        ),
        681 => 
        array (
          'id' => '061305',
          'province_id' => '0613',
          'description' => 'La Esperanza',
          'active' => 1,
        ),
        682 => 
        array (
          'id' => '061306',
          'province_id' => '0613',
          'description' => 'Ninabamba',
          'active' => 1,
        ),
        683 => 
        array (
          'id' => '061307',
          'province_id' => '0613',
          'description' => 'Pulan',
          'active' => 1,
        ),
        684 => 
        array (
          'id' => '061308',
          'province_id' => '0613',
          'description' => 'Saucepampa',
          'active' => 1,
        ),
        685 => 
        array (
          'id' => '061309',
          'province_id' => '0613',
          'description' => 'Sexi',
          'active' => 1,
        ),
        686 => 
        array (
          'id' => '061310',
          'province_id' => '0613',
          'description' => 'Uticyacu',
          'active' => 1,
        ),
        687 => 
        array (
          'id' => '061311',
          'province_id' => '0613',
          'description' => 'Yauyucan',
          'active' => 1,
        ),
        688 => 
        array (
          'id' => '070101',
          'province_id' => '0701',
          'description' => 'Callao',
          'active' => 1,
        ),
        689 => 
        array (
          'id' => '070102',
          'province_id' => '0701',
          'description' => 'Bellavista',
          'active' => 1,
        ),
        690 => 
        array (
          'id' => '070103',
          'province_id' => '0701',
          'description' => 'Carmen de la Legua Reynoso',
          'active' => 1,
        ),
        691 => 
        array (
          'id' => '070104',
          'province_id' => '0701',
          'description' => 'La Perla',
          'active' => 1,
        ),
        692 => 
        array (
          'id' => '070105',
          'province_id' => '0701',
          'description' => 'La Punta',
          'active' => 1,
        ),
        693 => 
        array (
          'id' => '070106',
          'province_id' => '0701',
          'description' => 'Ventanilla',
          'active' => 1,
        ),
        694 => 
        array (
          'id' => '070107',
          'province_id' => '0701',
          'description' => 'Mi Perú',
          'active' => 1,
        ),
        695 => 
        array (
          'id' => '080101',
          'province_id' => '0801',
          'description' => 'Cusco',
          'active' => 1,
        ),
        696 => 
        array (
          'id' => '080102',
          'province_id' => '0801',
          'description' => 'Ccorca',
          'active' => 1,
        ),
        697 => 
        array (
          'id' => '080103',
          'province_id' => '0801',
          'description' => 'Poroy',
          'active' => 1,
        ),
        698 => 
        array (
          'id' => '080104',
          'province_id' => '0801',
          'description' => 'San Jerónimo',
          'active' => 1,
        ),
        699 => 
        array (
          'id' => '080105',
          'province_id' => '0801',
          'description' => 'San Sebastian',
          'active' => 1,
        ),
        700 => 
        array (
          'id' => '080106',
          'province_id' => '0801',
          'description' => 'Santiago',
          'active' => 1,
        ),
        701 => 
        array (
          'id' => '080107',
          'province_id' => '0801',
          'description' => 'Saylla',
          'active' => 1,
        ),
        702 => 
        array (
          'id' => '080108',
          'province_id' => '0801',
          'description' => 'Wanchaq',
          'active' => 1,
        ),
        703 => 
        array (
          'id' => '080201',
          'province_id' => '0802',
          'description' => 'Acomayo',
          'active' => 1,
        ),
        704 => 
        array (
          'id' => '080202',
          'province_id' => '0802',
          'description' => 'Acopia',
          'active' => 1,
        ),
        705 => 
        array (
          'id' => '080203',
          'province_id' => '0802',
          'description' => 'Acos',
          'active' => 1,
        ),
        706 => 
        array (
          'id' => '080204',
          'province_id' => '0802',
          'description' => 'Mosoc Llacta',
          'active' => 1,
        ),
        707 => 
        array (
          'id' => '080205',
          'province_id' => '0802',
          'description' => 'Pomacanchi',
          'active' => 1,
        ),
        708 => 
        array (
          'id' => '080206',
          'province_id' => '0802',
          'description' => 'Rondocan',
          'active' => 1,
        ),
        709 => 
        array (
          'id' => '080207',
          'province_id' => '0802',
          'description' => 'Sangarara',
          'active' => 1,
        ),
        710 => 
        array (
          'id' => '080301',
          'province_id' => '0803',
          'description' => 'Anta',
          'active' => 1,
        ),
        711 => 
        array (
          'id' => '080302',
          'province_id' => '0803',
          'description' => 'Ancahuasi',
          'active' => 1,
        ),
        712 => 
        array (
          'id' => '080303',
          'province_id' => '0803',
          'description' => 'Cachimayo',
          'active' => 1,
        ),
        713 => 
        array (
          'id' => '080304',
          'province_id' => '0803',
          'description' => 'Chinchaypujio',
          'active' => 1,
        ),
        714 => 
        array (
          'id' => '080305',
          'province_id' => '0803',
          'description' => 'Huarocondo',
          'active' => 1,
        ),
        715 => 
        array (
          'id' => '080306',
          'province_id' => '0803',
          'description' => 'Limatambo',
          'active' => 1,
        ),
        716 => 
        array (
          'id' => '080307',
          'province_id' => '0803',
          'description' => 'Mollepata',
          'active' => 1,
        ),
        717 => 
        array (
          'id' => '080308',
          'province_id' => '0803',
          'description' => 'Pucyura',
          'active' => 1,
        ),
        718 => 
        array (
          'id' => '080309',
          'province_id' => '0803',
          'description' => 'Zurite',
          'active' => 1,
        ),
        719 => 
        array (
          'id' => '080401',
          'province_id' => '0804',
          'description' => 'Calca',
          'active' => 1,
        ),
        720 => 
        array (
          'id' => '080402',
          'province_id' => '0804',
          'description' => 'Coya',
          'active' => 1,
        ),
        721 => 
        array (
          'id' => '080403',
          'province_id' => '0804',
          'description' => 'Lamay',
          'active' => 1,
        ),
        722 => 
        array (
          'id' => '080404',
          'province_id' => '0804',
          'description' => 'Lares',
          'active' => 1,
        ),
        723 => 
        array (
          'id' => '080405',
          'province_id' => '0804',
          'description' => 'Pisac',
          'active' => 1,
        ),
        724 => 
        array (
          'id' => '080406',
          'province_id' => '0804',
          'description' => 'San Salvador',
          'active' => 1,
        ),
        725 => 
        array (
          'id' => '080407',
          'province_id' => '0804',
          'description' => 'Taray',
          'active' => 1,
        ),
        726 => 
        array (
          'id' => '080408',
          'province_id' => '0804',
          'description' => 'Yanatile',
          'active' => 1,
        ),
        727 => 
        array (
          'id' => '080501',
          'province_id' => '0805',
          'description' => 'Yanaoca',
          'active' => 1,
        ),
        728 => 
        array (
          'id' => '080502',
          'province_id' => '0805',
          'description' => 'Checca',
          'active' => 1,
        ),
        729 => 
        array (
          'id' => '080503',
          'province_id' => '0805',
          'description' => 'Kunturkanki',
          'active' => 1,
        ),
        730 => 
        array (
          'id' => '080504',
          'province_id' => '0805',
          'description' => 'Langui',
          'active' => 1,
        ),
        731 => 
        array (
          'id' => '080505',
          'province_id' => '0805',
          'description' => 'Layo',
          'active' => 1,
        ),
        732 => 
        array (
          'id' => '080506',
          'province_id' => '0805',
          'description' => 'Pampamarca',
          'active' => 1,
        ),
        733 => 
        array (
          'id' => '080507',
          'province_id' => '0805',
          'description' => 'Quehue',
          'active' => 1,
        ),
        734 => 
        array (
          'id' => '080508',
          'province_id' => '0805',
          'description' => 'Tupac Amaru',
          'active' => 1,
        ),
        735 => 
        array (
          'id' => '080601',
          'province_id' => '0806',
          'description' => 'Sicuani',
          'active' => 1,
        ),
        736 => 
        array (
          'id' => '080602',
          'province_id' => '0806',
          'description' => 'Checacupe',
          'active' => 1,
        ),
        737 => 
        array (
          'id' => '080603',
          'province_id' => '0806',
          'description' => 'Combapata',
          'active' => 1,
        ),
        738 => 
        array (
          'id' => '080604',
          'province_id' => '0806',
          'description' => 'Marangani',
          'active' => 1,
        ),
        739 => 
        array (
          'id' => '080605',
          'province_id' => '0806',
          'description' => 'Pitumarca',
          'active' => 1,
        ),
        740 => 
        array (
          'id' => '080606',
          'province_id' => '0806',
          'description' => 'San Pablo',
          'active' => 1,
        ),
        741 => 
        array (
          'id' => '080607',
          'province_id' => '0806',
          'description' => 'San Pedro',
          'active' => 1,
        ),
        742 => 
        array (
          'id' => '080608',
          'province_id' => '0806',
          'description' => 'Tinta',
          'active' => 1,
        ),
        743 => 
        array (
          'id' => '080701',
          'province_id' => '0807',
          'description' => 'Santo Tomas',
          'active' => 1,
        ),
        744 => 
        array (
          'id' => '080702',
          'province_id' => '0807',
          'description' => 'Capacmarca',
          'active' => 1,
        ),
        745 => 
        array (
          'id' => '080703',
          'province_id' => '0807',
          'description' => 'Chamaca',
          'active' => 1,
        ),
        746 => 
        array (
          'id' => '080704',
          'province_id' => '0807',
          'description' => 'Colquemarca',
          'active' => 1,
        ),
        747 => 
        array (
          'id' => '080705',
          'province_id' => '0807',
          'description' => 'Livitaca',
          'active' => 1,
        ),
        748 => 
        array (
          'id' => '080706',
          'province_id' => '0807',
          'description' => 'Llusco',
          'active' => 1,
        ),
        749 => 
        array (
          'id' => '080707',
          'province_id' => '0807',
          'description' => 'Quiñota',
          'active' => 1,
        ),
        750 => 
        array (
          'id' => '080708',
          'province_id' => '0807',
          'description' => 'Velille',
          'active' => 1,
        ),
        751 => 
        array (
          'id' => '080801',
          'province_id' => '0808',
          'description' => 'Espinar',
          'active' => 1,
        ),
        752 => 
        array (
          'id' => '080802',
          'province_id' => '0808',
          'description' => 'Condoroma',
          'active' => 1,
        ),
        753 => 
        array (
          'id' => '080803',
          'province_id' => '0808',
          'description' => 'Coporaque',
          'active' => 1,
        ),
        754 => 
        array (
          'id' => '080804',
          'province_id' => '0808',
          'description' => 'Ocoruro',
          'active' => 1,
        ),
        755 => 
        array (
          'id' => '080805',
          'province_id' => '0808',
          'description' => 'Pallpata',
          'active' => 1,
        ),
        756 => 
        array (
          'id' => '080806',
          'province_id' => '0808',
          'description' => 'Pichigua',
          'active' => 1,
        ),
        757 => 
        array (
          'id' => '080807',
          'province_id' => '0808',
          'description' => 'Suyckutambo',
          'active' => 1,
        ),
        758 => 
        array (
          'id' => '080808',
          'province_id' => '0808',
          'description' => 'Alto Pichigua',
          'active' => 1,
        ),
        759 => 
        array (
          'id' => '080901',
          'province_id' => '0809',
          'description' => 'Santa Ana',
          'active' => 1,
        ),
        760 => 
        array (
          'id' => '080902',
          'province_id' => '0809',
          'description' => 'Echarate',
          'active' => 1,
        ),
        761 => 
        array (
          'id' => '080903',
          'province_id' => '0809',
          'description' => 'Huayopata',
          'active' => 1,
        ),
        762 => 
        array (
          'id' => '080904',
          'province_id' => '0809',
          'description' => 'Maranura',
          'active' => 1,
        ),
        763 => 
        array (
          'id' => '080905',
          'province_id' => '0809',
          'description' => 'Ocobamba',
          'active' => 1,
        ),
        764 => 
        array (
          'id' => '080906',
          'province_id' => '0809',
          'description' => 'Quellouno',
          'active' => 1,
        ),
        765 => 
        array (
          'id' => '080907',
          'province_id' => '0809',
          'description' => 'Kimbiri',
          'active' => 1,
        ),
        766 => 
        array (
          'id' => '080908',
          'province_id' => '0809',
          'description' => 'Santa Teresa',
          'active' => 1,
        ),
        767 => 
        array (
          'id' => '080909',
          'province_id' => '0809',
          'description' => 'Vilcabamba',
          'active' => 1,
        ),
        768 => 
        array (
          'id' => '080910',
          'province_id' => '0809',
          'description' => 'Pichari',
          'active' => 1,
        ),
        769 => 
        array (
          'id' => '080911',
          'province_id' => '0809',
          'description' => 'Inkawasi',
          'active' => 1,
        ),
        770 => 
        array (
          'id' => '080912',
          'province_id' => '0809',
          'description' => 'Villa Virgen',
          'active' => 1,
        ),
        771 => 
        array (
          'id' => '080913',
          'province_id' => '0809',
          'description' => 'Villa Kintiarina',
          'active' => 1,
        ),
        772 => 
        array (
          'id' => '080914',
          'province_id' => '0809',
          'description' => 'Megantoni',
          'active' => 1,
        ),
        773 => 
        array (
          'id' => '080915',
          'province_id' => '0809',
          'description' => 'Kumpirushiato',
          'active' => 1,
        ),
        774 => 
        array (
          'id' => '080916',
          'province_id' => '0809',
          'description' => 'Cielo Punco',
          'active' => 1,
        ),
        775 => 
        array (
          'id' => '080917',
          'province_id' => '0809',
          'description' => 'Manitea',
          'active' => 1,
        ),
        776 => 
        array (
          'id' => '080918',
          'province_id' => '0809',
          'description' => 'Union Ashaninka',
          'active' => 1,
        ),
        777 => 
        array (
          'id' => '081001',
          'province_id' => '0810',
          'description' => 'Paruro',
          'active' => 1,
        ),
        778 => 
        array (
          'id' => '081002',
          'province_id' => '0810',
          'description' => 'Accha',
          'active' => 1,
        ),
        779 => 
        array (
          'id' => '081003',
          'province_id' => '0810',
          'description' => 'Ccapi',
          'active' => 1,
        ),
        780 => 
        array (
          'id' => '081004',
          'province_id' => '0810',
          'description' => 'Colcha',
          'active' => 1,
        ),
        781 => 
        array (
          'id' => '081005',
          'province_id' => '0810',
          'description' => 'Huanoquite',
          'active' => 1,
        ),
        782 => 
        array (
          'id' => '081006',
          'province_id' => '0810',
          'description' => 'Omacha',
          'active' => 1,
        ),
        783 => 
        array (
          'id' => '081007',
          'province_id' => '0810',
          'description' => 'Paccaritambo',
          'active' => 1,
        ),
        784 => 
        array (
          'id' => '081008',
          'province_id' => '0810',
          'description' => 'Pillpinto',
          'active' => 1,
        ),
        785 => 
        array (
          'id' => '081009',
          'province_id' => '0810',
          'description' => 'Yaurisque',
          'active' => 1,
        ),
        786 => 
        array (
          'id' => '081101',
          'province_id' => '0811',
          'description' => 'Paucartambo',
          'active' => 1,
        ),
        787 => 
        array (
          'id' => '081102',
          'province_id' => '0811',
          'description' => 'Caicay',
          'active' => 1,
        ),
        788 => 
        array (
          'id' => '081103',
          'province_id' => '0811',
          'description' => 'Challabamba',
          'active' => 1,
        ),
        789 => 
        array (
          'id' => '081104',
          'province_id' => '0811',
          'description' => 'Colquepata',
          'active' => 1,
        ),
        790 => 
        array (
          'id' => '081105',
          'province_id' => '0811',
          'description' => 'Huancarani',
          'active' => 1,
        ),
        791 => 
        array (
          'id' => '081106',
          'province_id' => '0811',
          'description' => 'Kosñipata',
          'active' => 1,
        ),
        792 => 
        array (
          'id' => '081201',
          'province_id' => '0812',
          'description' => 'Urcos',
          'active' => 1,
        ),
        793 => 
        array (
          'id' => '081202',
          'province_id' => '0812',
          'description' => 'Andahuaylillas',
          'active' => 1,
        ),
        794 => 
        array (
          'id' => '081203',
          'province_id' => '0812',
          'description' => 'Camanti',
          'active' => 1,
        ),
        795 => 
        array (
          'id' => '081204',
          'province_id' => '0812',
          'description' => 'Ccarhuayo',
          'active' => 1,
        ),
        796 => 
        array (
          'id' => '081205',
          'province_id' => '0812',
          'description' => 'Ccatca',
          'active' => 1,
        ),
        797 => 
        array (
          'id' => '081206',
          'province_id' => '0812',
          'description' => 'Cusipata',
          'active' => 1,
        ),
        798 => 
        array (
          'id' => '081207',
          'province_id' => '0812',
          'description' => 'Huaro',
          'active' => 1,
        ),
        799 => 
        array (
          'id' => '081208',
          'province_id' => '0812',
          'description' => 'Lucre',
          'active' => 1,
        ),
        800 => 
        array (
          'id' => '081209',
          'province_id' => '0812',
          'description' => 'Marcapata',
          'active' => 1,
        ),
        801 => 
        array (
          'id' => '081210',
          'province_id' => '0812',
          'description' => 'Ocongate',
          'active' => 1,
        ),
        802 => 
        array (
          'id' => '081211',
          'province_id' => '0812',
          'description' => 'Oropesa',
          'active' => 1,
        ),
        803 => 
        array (
          'id' => '081212',
          'province_id' => '0812',
          'description' => 'Quiquijana',
          'active' => 1,
        ),
        804 => 
        array (
          'id' => '081301',
          'province_id' => '0813',
          'description' => 'Urubamba',
          'active' => 1,
        ),
        805 => 
        array (
          'id' => '081302',
          'province_id' => '0813',
          'description' => 'Chinchero',
          'active' => 1,
        ),
        806 => 
        array (
          'id' => '081303',
          'province_id' => '0813',
          'description' => 'Huayllabamba',
          'active' => 1,
        ),
        807 => 
        array (
          'id' => '081304',
          'province_id' => '0813',
          'description' => 'Machupicchu',
          'active' => 1,
        ),
        808 => 
        array (
          'id' => '081305',
          'province_id' => '0813',
          'description' => 'Maras',
          'active' => 1,
        ),
        809 => 
        array (
          'id' => '081306',
          'province_id' => '0813',
          'description' => 'Ollantaytambo',
          'active' => 1,
        ),
        810 => 
        array (
          'id' => '081307',
          'province_id' => '0813',
          'description' => 'Yucay',
          'active' => 1,
        ),
        811 => 
        array (
          'id' => '090101',
          'province_id' => '0901',
          'description' => 'Huancavelica',
          'active' => 1,
        ),
        812 => 
        array (
          'id' => '090102',
          'province_id' => '0901',
          'description' => 'Acobambilla',
          'active' => 1,
        ),
        813 => 
        array (
          'id' => '090103',
          'province_id' => '0901',
          'description' => 'Acoria',
          'active' => 1,
        ),
        814 => 
        array (
          'id' => '090104',
          'province_id' => '0901',
          'description' => 'Conayca',
          'active' => 1,
        ),
        815 => 
        array (
          'id' => '090105',
          'province_id' => '0901',
          'description' => 'Cuenca',
          'active' => 1,
        ),
        816 => 
        array (
          'id' => '090106',
          'province_id' => '0901',
          'description' => 'Huachocolpa',
          'active' => 1,
        ),
        817 => 
        array (
          'id' => '090107',
          'province_id' => '0901',
          'description' => 'Huayllahuara',
          'active' => 1,
        ),
        818 => 
        array (
          'id' => '090108',
          'province_id' => '0901',
          'description' => 'Izcuchaca',
          'active' => 1,
        ),
        819 => 
        array (
          'id' => '090109',
          'province_id' => '0901',
          'description' => 'Laria',
          'active' => 1,
        ),
        820 => 
        array (
          'id' => '090110',
          'province_id' => '0901',
          'description' => 'Manta',
          'active' => 1,
        ),
        821 => 
        array (
          'id' => '090111',
          'province_id' => '0901',
          'description' => 'Mariscal Cáceres',
          'active' => 1,
        ),
        822 => 
        array (
          'id' => '090112',
          'province_id' => '0901',
          'description' => 'Moya',
          'active' => 1,
        ),
        823 => 
        array (
          'id' => '090113',
          'province_id' => '0901',
          'description' => 'Nuevo Occoro',
          'active' => 1,
        ),
        824 => 
        array (
          'id' => '090114',
          'province_id' => '0901',
          'description' => 'Palca',
          'active' => 1,
        ),
        825 => 
        array (
          'id' => '090115',
          'province_id' => '0901',
          'description' => 'Pilchaca',
          'active' => 1,
        ),
        826 => 
        array (
          'id' => '090116',
          'province_id' => '0901',
          'description' => 'Vilca',
          'active' => 1,
        ),
        827 => 
        array (
          'id' => '090117',
          'province_id' => '0901',
          'description' => 'Yauli',
          'active' => 1,
        ),
        828 => 
        array (
          'id' => '090118',
          'province_id' => '0901',
          'description' => 'Ascensión',
          'active' => 1,
        ),
        829 => 
        array (
          'id' => '090119',
          'province_id' => '0901',
          'description' => 'Huando',
          'active' => 1,
        ),
        830 => 
        array (
          'id' => '090201',
          'province_id' => '0902',
          'description' => 'Acobamba',
          'active' => 1,
        ),
        831 => 
        array (
          'id' => '090202',
          'province_id' => '0902',
          'description' => 'Andabamba',
          'active' => 1,
        ),
        832 => 
        array (
          'id' => '090203',
          'province_id' => '0902',
          'description' => 'Anta',
          'active' => 1,
        ),
        833 => 
        array (
          'id' => '090204',
          'province_id' => '0902',
          'description' => 'Caja',
          'active' => 1,
        ),
        834 => 
        array (
          'id' => '090205',
          'province_id' => '0902',
          'description' => 'Marcas',
          'active' => 1,
        ),
        835 => 
        array (
          'id' => '090206',
          'province_id' => '0902',
          'description' => 'Paucara',
          'active' => 1,
        ),
        836 => 
        array (
          'id' => '090207',
          'province_id' => '0902',
          'description' => 'Pomacocha',
          'active' => 1,
        ),
        837 => 
        array (
          'id' => '090208',
          'province_id' => '0902',
          'description' => 'Rosario',
          'active' => 1,
        ),
        838 => 
        array (
          'id' => '090301',
          'province_id' => '0903',
          'description' => 'Lircay',
          'active' => 1,
        ),
        839 => 
        array (
          'id' => '090302',
          'province_id' => '0903',
          'description' => 'Anchonga',
          'active' => 1,
        ),
        840 => 
        array (
          'id' => '090303',
          'province_id' => '0903',
          'description' => 'Callanmarca',
          'active' => 1,
        ),
        841 => 
        array (
          'id' => '090304',
          'province_id' => '0903',
          'description' => 'Ccochaccasa',
          'active' => 1,
        ),
        842 => 
        array (
          'id' => '090305',
          'province_id' => '0903',
          'description' => 'Chincho',
          'active' => 1,
        ),
        843 => 
        array (
          'id' => '090306',
          'province_id' => '0903',
          'description' => 'Congalla',
          'active' => 1,
        ),
        844 => 
        array (
          'id' => '090307',
          'province_id' => '0903',
          'description' => 'Huanca-Huanca',
          'active' => 1,
        ),
        845 => 
        array (
          'id' => '090308',
          'province_id' => '0903',
          'description' => 'Huayllay Grande',
          'active' => 1,
        ),
        846 => 
        array (
          'id' => '090309',
          'province_id' => '0903',
          'description' => 'Julcamarca',
          'active' => 1,
        ),
        847 => 
        array (
          'id' => '090310',
          'province_id' => '0903',
          'description' => 'San Antonio de Antaparco',
          'active' => 1,
        ),
        848 => 
        array (
          'id' => '090311',
          'province_id' => '0903',
          'description' => 'Santo Tomas de Pata',
          'active' => 1,
        ),
        849 => 
        array (
          'id' => '090312',
          'province_id' => '0903',
          'description' => 'Secclla',
          'active' => 1,
        ),
        850 => 
        array (
          'id' => '090401',
          'province_id' => '0904',
          'description' => 'Castrovirreyna',
          'active' => 1,
        ),
        851 => 
        array (
          'id' => '090402',
          'province_id' => '0904',
          'description' => 'Arma',
          'active' => 1,
        ),
        852 => 
        array (
          'id' => '090403',
          'province_id' => '0904',
          'description' => 'Aurahua',
          'active' => 1,
        ),
        853 => 
        array (
          'id' => '090404',
          'province_id' => '0904',
          'description' => 'Capillas',
          'active' => 1,
        ),
        854 => 
        array (
          'id' => '090405',
          'province_id' => '0904',
          'description' => 'Chupamarca',
          'active' => 1,
        ),
        855 => 
        array (
          'id' => '090406',
          'province_id' => '0904',
          'description' => 'Cocas',
          'active' => 1,
        ),
        856 => 
        array (
          'id' => '090407',
          'province_id' => '0904',
          'description' => 'Huachos',
          'active' => 1,
        ),
        857 => 
        array (
          'id' => '090408',
          'province_id' => '0904',
          'description' => 'Huamatambo',
          'active' => 1,
        ),
        858 => 
        array (
          'id' => '090409',
          'province_id' => '0904',
          'description' => 'Mollepampa',
          'active' => 1,
        ),
        859 => 
        array (
          'id' => '090410',
          'province_id' => '0904',
          'description' => 'San Juan',
          'active' => 1,
        ),
        860 => 
        array (
          'id' => '090411',
          'province_id' => '0904',
          'description' => 'Santa Ana',
          'active' => 1,
        ),
        861 => 
        array (
          'id' => '090412',
          'province_id' => '0904',
          'description' => 'Tantara',
          'active' => 1,
        ),
        862 => 
        array (
          'id' => '090413',
          'province_id' => '0904',
          'description' => 'Ticrapo',
          'active' => 1,
        ),
        863 => 
        array (
          'id' => '090501',
          'province_id' => '0905',
          'description' => 'Churcampa',
          'active' => 1,
        ),
        864 => 
        array (
          'id' => '090502',
          'province_id' => '0905',
          'description' => 'Anco',
          'active' => 1,
        ),
        865 => 
        array (
          'id' => '090503',
          'province_id' => '0905',
          'description' => 'Chinchihuasi',
          'active' => 1,
        ),
        866 => 
        array (
          'id' => '090504',
          'province_id' => '0905',
          'description' => 'El Carmen',
          'active' => 1,
        ),
        867 => 
        array (
          'id' => '090505',
          'province_id' => '0905',
          'description' => 'La Merced',
          'active' => 1,
        ),
        868 => 
        array (
          'id' => '090506',
          'province_id' => '0905',
          'description' => 'Locroja',
          'active' => 1,
        ),
        869 => 
        array (
          'id' => '090507',
          'province_id' => '0905',
          'description' => 'Paucarbamba',
          'active' => 1,
        ),
        870 => 
        array (
          'id' => '090508',
          'province_id' => '0905',
          'description' => 'San Miguel de Mayocc',
          'active' => 1,
        ),
        871 => 
        array (
          'id' => '090509',
          'province_id' => '0905',
          'description' => 'San Pedro de Coris',
          'active' => 1,
        ),
        872 => 
        array (
          'id' => '090510',
          'province_id' => '0905',
          'description' => 'Pachamarca',
          'active' => 1,
        ),
        873 => 
        array (
          'id' => '090511',
          'province_id' => '0905',
          'description' => 'Cosme',
          'active' => 1,
        ),
        874 => 
        array (
          'id' => '090601',
          'province_id' => '0906',
          'description' => 'Huaytara',
          'active' => 1,
        ),
        875 => 
        array (
          'id' => '090602',
          'province_id' => '0906',
          'description' => 'Ayavi',
          'active' => 1,
        ),
        876 => 
        array (
          'id' => '090603',
          'province_id' => '0906',
          'description' => 'Córdova',
          'active' => 1,
        ),
        877 => 
        array (
          'id' => '090604',
          'province_id' => '0906',
          'description' => 'Huayacundo Arma',
          'active' => 1,
        ),
        878 => 
        array (
          'id' => '090605',
          'province_id' => '0906',
          'description' => 'Laramarca',
          'active' => 1,
        ),
        879 => 
        array (
          'id' => '090606',
          'province_id' => '0906',
          'description' => 'Ocoyo',
          'active' => 1,
        ),
        880 => 
        array (
          'id' => '090607',
          'province_id' => '0906',
          'description' => 'Pilpichaca',
          'active' => 1,
        ),
        881 => 
        array (
          'id' => '090608',
          'province_id' => '0906',
          'description' => 'Querco',
          'active' => 1,
        ),
        882 => 
        array (
          'id' => '090609',
          'province_id' => '0906',
          'description' => 'Quito-Arma',
          'active' => 1,
        ),
        883 => 
        array (
          'id' => '090610',
          'province_id' => '0906',
          'description' => 'San Antonio de Cusicancha',
          'active' => 1,
        ),
        884 => 
        array (
          'id' => '090611',
          'province_id' => '0906',
          'description' => 'San Francisco de Sangayaico',
          'active' => 1,
        ),
        885 => 
        array (
          'id' => '090612',
          'province_id' => '0906',
          'description' => 'San Isidro',
          'active' => 1,
        ),
        886 => 
        array (
          'id' => '090613',
          'province_id' => '0906',
          'description' => 'Santiago de Chocorvos',
          'active' => 1,
        ),
        887 => 
        array (
          'id' => '090614',
          'province_id' => '0906',
          'description' => 'Santiago de Quirahuara',
          'active' => 1,
        ),
        888 => 
        array (
          'id' => '090615',
          'province_id' => '0906',
          'description' => 'Santo Domingo de Capillas',
          'active' => 1,
        ),
        889 => 
        array (
          'id' => '090616',
          'province_id' => '0906',
          'description' => 'Tambo',
          'active' => 1,
        ),
        890 => 
        array (
          'id' => '090701',
          'province_id' => '0907',
          'description' => 'Pampas',
          'active' => 1,
        ),
        891 => 
        array (
          'id' => '090702',
          'province_id' => '0907',
          'description' => 'Acostambo',
          'active' => 1,
        ),
        892 => 
        array (
          'id' => '090703',
          'province_id' => '0907',
          'description' => 'Acraquia',
          'active' => 1,
        ),
        893 => 
        array (
          'id' => '090704',
          'province_id' => '0907',
          'description' => 'Ahuaycha',
          'active' => 1,
        ),
        894 => 
        array (
          'id' => '090705',
          'province_id' => '0907',
          'description' => 'Colcabamba',
          'active' => 1,
        ),
        895 => 
        array (
          'id' => '090706',
          'province_id' => '0907',
          'description' => 'Daniel Hernández',
          'active' => 1,
        ),
        896 => 
        array (
          'id' => '090707',
          'province_id' => '0907',
          'description' => 'Huachocolpa',
          'active' => 1,
        ),
        897 => 
        array (
          'id' => '090709',
          'province_id' => '0907',
          'description' => 'Huaribamba',
          'active' => 1,
        ),
        898 => 
        array (
          'id' => '090710',
          'province_id' => '0907',
          'description' => 'Ñahuimpuquio',
          'active' => 1,
        ),
        899 => 
        array (
          'id' => '090711',
          'province_id' => '0907',
          'description' => 'Pazos',
          'active' => 1,
        ),
        900 => 
        array (
          'id' => '090713',
          'province_id' => '0907',
          'description' => 'Quishuar',
          'active' => 1,
        ),
        901 => 
        array (
          'id' => '090714',
          'province_id' => '0907',
          'description' => 'Salcabamba',
          'active' => 1,
        ),
        902 => 
        array (
          'id' => '090715',
          'province_id' => '0907',
          'description' => 'Salcahuasi',
          'active' => 1,
        ),
        903 => 
        array (
          'id' => '090716',
          'province_id' => '0907',
          'description' => 'San Marcos de Rocchac',
          'active' => 1,
        ),
        904 => 
        array (
          'id' => '090717',
          'province_id' => '0907',
          'description' => 'Surcubamba',
          'active' => 1,
        ),
        905 => 
        array (
          'id' => '090718',
          'province_id' => '0907',
          'description' => 'Tintay Puncu',
          'active' => 1,
        ),
        906 => 
        array (
          'id' => '090719',
          'province_id' => '0907',
          'description' => 'Quichuas',
          'active' => 1,
        ),
        907 => 
        array (
          'id' => '090720',
          'province_id' => '0907',
          'description' => 'Andaymarca',
          'active' => 1,
        ),
        908 => 
        array (
          'id' => '090721',
          'province_id' => '0907',
          'description' => 'Roble',
          'active' => 1,
        ),
        909 => 
        array (
          'id' => '090722',
          'province_id' => '0907',
          'description' => 'Pichos',
          'active' => 1,
        ),
        910 => 
        array (
          'id' => '100101',
          'province_id' => '1001',
          'description' => 'Huanuco',
          'active' => 1,
        ),
        911 => 
        array (
          'id' => '100102',
          'province_id' => '1001',
          'description' => 'Amarilis',
          'active' => 1,
        ),
        912 => 
        array (
          'id' => '100103',
          'province_id' => '1001',
          'description' => 'Chinchao',
          'active' => 1,
        ),
        913 => 
        array (
          'id' => '100104',
          'province_id' => '1001',
          'description' => 'Churubamba',
          'active' => 1,
        ),
        914 => 
        array (
          'id' => '100105',
          'province_id' => '1001',
          'description' => 'Margos',
          'active' => 1,
        ),
        915 => 
        array (
          'id' => '100106',
          'province_id' => '1001',
          'description' => 'Quisqui (Kichki)',
          'active' => 1,
        ),
        916 => 
        array (
          'id' => '100107',
          'province_id' => '1001',
          'description' => 'San Francisco de Cayran',
          'active' => 1,
        ),
        917 => 
        array (
          'id' => '100108',
          'province_id' => '1001',
          'description' => 'San Pedro de Chaulan',
          'active' => 1,
        ),
        918 => 
        array (
          'id' => '100109',
          'province_id' => '1001',
          'description' => 'Santa María del Valle',
          'active' => 1,
        ),
        919 => 
        array (
          'id' => '100110',
          'province_id' => '1001',
          'description' => 'Yarumayo',
          'active' => 1,
        ),
        920 => 
        array (
          'id' => '100111',
          'province_id' => '1001',
          'description' => 'Pillco Marca',
          'active' => 1,
        ),
        921 => 
        array (
          'id' => '100112',
          'province_id' => '1001',
          'description' => 'Yacus',
          'active' => 1,
        ),
        922 => 
        array (
          'id' => '100113',
          'province_id' => '1001',
          'description' => 'San Pablo de Pillao',
          'active' => 1,
        ),
        923 => 
        array (
          'id' => '100201',
          'province_id' => '1002',
          'description' => 'Ambo',
          'active' => 1,
        ),
        924 => 
        array (
          'id' => '100202',
          'province_id' => '1002',
          'description' => 'Cayna',
          'active' => 1,
        ),
        925 => 
        array (
          'id' => '100203',
          'province_id' => '1002',
          'description' => 'Colpas',
          'active' => 1,
        ),
        926 => 
        array (
          'id' => '100204',
          'province_id' => '1002',
          'description' => 'Conchamarca',
          'active' => 1,
        ),
        927 => 
        array (
          'id' => '100205',
          'province_id' => '1002',
          'description' => 'Huacar',
          'active' => 1,
        ),
        928 => 
        array (
          'id' => '100206',
          'province_id' => '1002',
          'description' => 'San Francisco',
          'active' => 1,
        ),
        929 => 
        array (
          'id' => '100207',
          'province_id' => '1002',
          'description' => 'San Rafael',
          'active' => 1,
        ),
        930 => 
        array (
          'id' => '100208',
          'province_id' => '1002',
          'description' => 'Tomay Kichwa',
          'active' => 1,
        ),
        931 => 
        array (
          'id' => '100301',
          'province_id' => '1003',
          'description' => 'La Unión',
          'active' => 1,
        ),
        932 => 
        array (
          'id' => '100307',
          'province_id' => '1003',
          'description' => 'Chuquis',
          'active' => 1,
        ),
        933 => 
        array (
          'id' => '100311',
          'province_id' => '1003',
          'description' => 'Marías',
          'active' => 1,
        ),
        934 => 
        array (
          'id' => '100313',
          'province_id' => '1003',
          'description' => 'Pachas',
          'active' => 1,
        ),
        935 => 
        array (
          'id' => '100316',
          'province_id' => '1003',
          'description' => 'Quivilla',
          'active' => 1,
        ),
        936 => 
        array (
          'id' => '100317',
          'province_id' => '1003',
          'description' => 'Ripan',
          'active' => 1,
        ),
        937 => 
        array (
          'id' => '100321',
          'province_id' => '1003',
          'description' => 'Shunqui',
          'active' => 1,
        ),
        938 => 
        array (
          'id' => '100322',
          'province_id' => '1003',
          'description' => 'Sillapata',
          'active' => 1,
        ),
        939 => 
        array (
          'id' => '100323',
          'province_id' => '1003',
          'description' => 'Yanas',
          'active' => 1,
        ),
        940 => 
        array (
          'id' => '100401',
          'province_id' => '1004',
          'description' => 'Huacaybamba',
          'active' => 1,
        ),
        941 => 
        array (
          'id' => '100402',
          'province_id' => '1004',
          'description' => 'Canchabamba',
          'active' => 1,
        ),
        942 => 
        array (
          'id' => '100403',
          'province_id' => '1004',
          'description' => 'Cochabamba',
          'active' => 1,
        ),
        943 => 
        array (
          'id' => '100404',
          'province_id' => '1004',
          'description' => 'Pinra',
          'active' => 1,
        ),
        944 => 
        array (
          'id' => '100501',
          'province_id' => '1005',
          'description' => 'Llata',
          'active' => 1,
        ),
        945 => 
        array (
          'id' => '100502',
          'province_id' => '1005',
          'description' => 'Arancay',
          'active' => 1,
        ),
        946 => 
        array (
          'id' => '100503',
          'province_id' => '1005',
          'description' => 'Chavín de Pariarca',
          'active' => 1,
        ),
        947 => 
        array (
          'id' => '100504',
          'province_id' => '1005',
          'description' => 'Jacas Grande',
          'active' => 1,
        ),
        948 => 
        array (
          'id' => '100505',
          'province_id' => '1005',
          'description' => 'Jircan',
          'active' => 1,
        ),
        949 => 
        array (
          'id' => '100506',
          'province_id' => '1005',
          'description' => 'Miraflores',
          'active' => 1,
        ),
        950 => 
        array (
          'id' => '100507',
          'province_id' => '1005',
          'description' => 'Monzón',
          'active' => 1,
        ),
        951 => 
        array (
          'id' => '100508',
          'province_id' => '1005',
          'description' => 'Punchao',
          'active' => 1,
        ),
        952 => 
        array (
          'id' => '100509',
          'province_id' => '1005',
          'description' => 'Puños',
          'active' => 1,
        ),
        953 => 
        array (
          'id' => '100510',
          'province_id' => '1005',
          'description' => 'Singa',
          'active' => 1,
        ),
        954 => 
        array (
          'id' => '100511',
          'province_id' => '1005',
          'description' => 'Tantamayo',
          'active' => 1,
        ),
        955 => 
        array (
          'id' => '100601',
          'province_id' => '1006',
          'description' => 'Rupa-Rupa',
          'active' => 1,
        ),
        956 => 
        array (
          'id' => '100602',
          'province_id' => '1006',
          'description' => 'Daniel Alomía Robles',
          'active' => 1,
        ),
        957 => 
        array (
          'id' => '100603',
          'province_id' => '1006',
          'description' => 'Hermílio Valdizan',
          'active' => 1,
        ),
        958 => 
        array (
          'id' => '100604',
          'province_id' => '1006',
          'description' => 'José Crespo y Castillo',
          'active' => 1,
        ),
        959 => 
        array (
          'id' => '100605',
          'province_id' => '1006',
          'description' => 'Luyando',
          'active' => 1,
        ),
        960 => 
        array (
          'id' => '100606',
          'province_id' => '1006',
          'description' => 'Mariano Damaso Beraun',
          'active' => 1,
        ),
        961 => 
        array (
          'id' => '100607',
          'province_id' => '1006',
          'description' => 'Pucayacu',
          'active' => 1,
        ),
        962 => 
        array (
          'id' => '100608',
          'province_id' => '1006',
          'description' => 'Castillo Grande',
          'active' => 1,
        ),
        963 => 
        array (
          'id' => '100701',
          'province_id' => '1007',
          'description' => 'Huacrachuco',
          'active' => 1,
        ),
        964 => 
        array (
          'id' => '100702',
          'province_id' => '1007',
          'description' => 'Cholon',
          'active' => 1,
        ),
        965 => 
        array (
          'id' => '100703',
          'province_id' => '1007',
          'description' => 'San Buenaventura',
          'active' => 1,
        ),
        966 => 
        array (
          'id' => '100704',
          'province_id' => '1007',
          'description' => 'La Morada',
          'active' => 1,
        ),
        967 => 
        array (
          'id' => '100705',
          'province_id' => '1007',
          'description' => 'Santa Rosa de Alto Yanajanca',
          'active' => 1,
        ),
        968 => 
        array (
          'id' => '100801',
          'province_id' => '1008',
          'description' => 'Panao',
          'active' => 1,
        ),
        969 => 
        array (
          'id' => '100802',
          'province_id' => '1008',
          'description' => 'Chaglla',
          'active' => 1,
        ),
        970 => 
        array (
          'id' => '100803',
          'province_id' => '1008',
          'description' => 'Molino',
          'active' => 1,
        ),
        971 => 
        array (
          'id' => '100804',
          'province_id' => '1008',
          'description' => 'Umari',
          'active' => 1,
        ),
        972 => 
        array (
          'id' => '100901',
          'province_id' => '1009',
          'description' => 'Puerto Inca',
          'active' => 1,
        ),
        973 => 
        array (
          'id' => '100902',
          'province_id' => '1009',
          'description' => 'Codo del Pozuzo',
          'active' => 1,
        ),
        974 => 
        array (
          'id' => '100903',
          'province_id' => '1009',
          'description' => 'Honoria',
          'active' => 1,
        ),
        975 => 
        array (
          'id' => '100904',
          'province_id' => '1009',
          'description' => 'Tournavista',
          'active' => 1,
        ),
        976 => 
        array (
          'id' => '100905',
          'province_id' => '1009',
          'description' => 'Yuyapichis',
          'active' => 1,
        ),
        977 => 
        array (
          'id' => '101001',
          'province_id' => '1010',
          'description' => 'Jesús',
          'active' => 1,
        ),
        978 => 
        array (
          'id' => '101002',
          'province_id' => '1010',
          'description' => 'Baños',
          'active' => 1,
        ),
        979 => 
        array (
          'id' => '101003',
          'province_id' => '1010',
          'description' => 'Jivia',
          'active' => 1,
        ),
        980 => 
        array (
          'id' => '101004',
          'province_id' => '1010',
          'description' => 'Queropalca',
          'active' => 1,
        ),
        981 => 
        array (
          'id' => '101005',
          'province_id' => '1010',
          'description' => 'Rondos',
          'active' => 1,
        ),
        982 => 
        array (
          'id' => '101006',
          'province_id' => '1010',
          'description' => 'San Francisco de Asís',
          'active' => 1,
        ),
        983 => 
        array (
          'id' => '101007',
          'province_id' => '1010',
          'description' => 'San Miguel de Cauri',
          'active' => 1,
        ),
        984 => 
        array (
          'id' => '101101',
          'province_id' => '1011',
          'description' => 'Chavinillo',
          'active' => 1,
        ),
        985 => 
        array (
          'id' => '101102',
          'province_id' => '1011',
          'description' => 'Cahuac',
          'active' => 1,
        ),
        986 => 
        array (
          'id' => '101103',
          'province_id' => '1011',
          'description' => 'Chacabamba',
          'active' => 1,
        ),
        987 => 
        array (
          'id' => '101104',
          'province_id' => '1011',
          'description' => 'Aparicio Pomares',
          'active' => 1,
        ),
        988 => 
        array (
          'id' => '101105',
          'province_id' => '1011',
          'description' => 'Jacas Chico',
          'active' => 1,
        ),
        989 => 
        array (
          'id' => '101106',
          'province_id' => '1011',
          'description' => 'Obas',
          'active' => 1,
        ),
        990 => 
        array (
          'id' => '101107',
          'province_id' => '1011',
          'description' => 'Pampamarca',
          'active' => 1,
        ),
        991 => 
        array (
          'id' => '101108',
          'province_id' => '1011',
          'description' => 'Choras',
          'active' => 1,
        ),
        992 => 
        array (
          'id' => '110101',
          'province_id' => '1101',
          'description' => 'Ica',
          'active' => 1,
        ),
        993 => 
        array (
          'id' => '110102',
          'province_id' => '1101',
          'description' => 'La Tinguiña',
          'active' => 1,
        ),
        994 => 
        array (
          'id' => '110103',
          'province_id' => '1101',
          'description' => 'Los Aquijes',
          'active' => 1,
        ),
        995 => 
        array (
          'id' => '110104',
          'province_id' => '1101',
          'description' => 'Ocucaje',
          'active' => 1,
        ),
        996 => 
        array (
          'id' => '110105',
          'province_id' => '1101',
          'description' => 'Pachacutec',
          'active' => 1,
        ),
        997 => 
        array (
          'id' => '110106',
          'province_id' => '1101',
          'description' => 'Parcona',
          'active' => 1,
        ),
        998 => 
        array (
          'id' => '110107',
          'province_id' => '1101',
          'description' => 'Pueblo Nuevo',
          'active' => 1,
        ),
        999 => 
        array (
          'id' => '110108',
          'province_id' => '1101',
          'description' => 'Salas',
          'active' => 1,
        ),
        1000 => 
        array (
          'id' => '110109',
          'province_id' => '1101',
          'description' => 'San José de Los Molinos',
          'active' => 1,
        ),
        1001 => 
        array (
          'id' => '110110',
          'province_id' => '1101',
          'description' => 'San Juan Bautista',
          'active' => 1,
        ),
        1002 => 
        array (
          'id' => '110111',
          'province_id' => '1101',
          'description' => 'Santiago',
          'active' => 1,
        ),
        1003 => 
        array (
          'id' => '110112',
          'province_id' => '1101',
          'description' => 'Subtanjalla',
          'active' => 1,
        ),
        1004 => 
        array (
          'id' => '110113',
          'province_id' => '1101',
          'description' => 'Tate',
          'active' => 1,
        ),
        1005 => 
        array (
          'id' => '110114',
          'province_id' => '1101',
          'description' => 'Yauca del Rosario',
          'active' => 1,
        ),
        1006 => 
        array (
          'id' => '110201',
          'province_id' => '1102',
          'description' => 'Chincha Alta',
          'active' => 1,
        ),
        1007 => 
        array (
          'id' => '110202',
          'province_id' => '1102',
          'description' => 'Alto Laran',
          'active' => 1,
        ),
        1008 => 
        array (
          'id' => '110203',
          'province_id' => '1102',
          'description' => 'Chavin',
          'active' => 1,
        ),
        1009 => 
        array (
          'id' => '110204',
          'province_id' => '1102',
          'description' => 'Chincha Baja',
          'active' => 1,
        ),
        1010 => 
        array (
          'id' => '110205',
          'province_id' => '1102',
          'description' => 'El Carmen',
          'active' => 1,
        ),
        1011 => 
        array (
          'id' => '110206',
          'province_id' => '1102',
          'description' => 'Grocio Prado',
          'active' => 1,
        ),
        1012 => 
        array (
          'id' => '110207',
          'province_id' => '1102',
          'description' => 'Pueblo Nuevo',
          'active' => 1,
        ),
        1013 => 
        array (
          'id' => '110208',
          'province_id' => '1102',
          'description' => 'San Juan de Yanac',
          'active' => 1,
        ),
        1014 => 
        array (
          'id' => '110209',
          'province_id' => '1102',
          'description' => 'San Pedro de Huacarpana',
          'active' => 1,
        ),
        1015 => 
        array (
          'id' => '110210',
          'province_id' => '1102',
          'description' => 'Sunampe',
          'active' => 1,
        ),
        1016 => 
        array (
          'id' => '110211',
          'province_id' => '1102',
          'description' => 'Tambo de Mora',
          'active' => 1,
        ),
        1017 => 
        array (
          'id' => '110301',
          'province_id' => '1103',
          'description' => 'Nasca',
          'active' => 1,
        ),
        1018 => 
        array (
          'id' => '110302',
          'province_id' => '1103',
          'description' => 'Changuillo',
          'active' => 1,
        ),
        1019 => 
        array (
          'id' => '110303',
          'province_id' => '1103',
          'description' => 'El Ingenio',
          'active' => 1,
        ),
        1020 => 
        array (
          'id' => '110304',
          'province_id' => '1103',
          'description' => 'Marcona',
          'active' => 1,
        ),
        1021 => 
        array (
          'id' => '110305',
          'province_id' => '1103',
          'description' => 'Vista Alegre',
          'active' => 1,
        ),
        1022 => 
        array (
          'id' => '110401',
          'province_id' => '1104',
          'description' => 'Palpa',
          'active' => 1,
        ),
        1023 => 
        array (
          'id' => '110402',
          'province_id' => '1104',
          'description' => 'Llipata',
          'active' => 1,
        ),
        1024 => 
        array (
          'id' => '110403',
          'province_id' => '1104',
          'description' => 'Río Grande',
          'active' => 1,
        ),
        1025 => 
        array (
          'id' => '110404',
          'province_id' => '1104',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        1026 => 
        array (
          'id' => '110405',
          'province_id' => '1104',
          'description' => 'Tibillo',
          'active' => 1,
        ),
        1027 => 
        array (
          'id' => '110501',
          'province_id' => '1105',
          'description' => 'Pisco',
          'active' => 1,
        ),
        1028 => 
        array (
          'id' => '110502',
          'province_id' => '1105',
          'description' => 'Huancano',
          'active' => 1,
        ),
        1029 => 
        array (
          'id' => '110503',
          'province_id' => '1105',
          'description' => 'Humay',
          'active' => 1,
        ),
        1030 => 
        array (
          'id' => '110504',
          'province_id' => '1105',
          'description' => 'Independencia',
          'active' => 1,
        ),
        1031 => 
        array (
          'id' => '110505',
          'province_id' => '1105',
          'description' => 'Paracas',
          'active' => 1,
        ),
        1032 => 
        array (
          'id' => '110506',
          'province_id' => '1105',
          'description' => 'San Andrés',
          'active' => 1,
        ),
        1033 => 
        array (
          'id' => '110507',
          'province_id' => '1105',
          'description' => 'San Clemente',
          'active' => 1,
        ),
        1034 => 
        array (
          'id' => '110508',
          'province_id' => '1105',
          'description' => 'Tupac Amaru Inca',
          'active' => 1,
        ),
        1035 => 
        array (
          'id' => '120101',
          'province_id' => '1201',
          'description' => 'Huancayo',
          'active' => 1,
        ),
        1036 => 
        array (
          'id' => '120104',
          'province_id' => '1201',
          'description' => 'Carhuacallanga',
          'active' => 1,
        ),
        1037 => 
        array (
          'id' => '120105',
          'province_id' => '1201',
          'description' => 'Chacapampa',
          'active' => 1,
        ),
        1038 => 
        array (
          'id' => '120106',
          'province_id' => '1201',
          'description' => 'Chicche',
          'active' => 1,
        ),
        1039 => 
        array (
          'id' => '120107',
          'province_id' => '1201',
          'description' => 'Chilca',
          'active' => 1,
        ),
        1040 => 
        array (
          'id' => '120108',
          'province_id' => '1201',
          'description' => 'Chongos Alto',
          'active' => 1,
        ),
        1041 => 
        array (
          'id' => '120111',
          'province_id' => '1201',
          'description' => 'Chupuro',
          'active' => 1,
        ),
        1042 => 
        array (
          'id' => '120112',
          'province_id' => '1201',
          'description' => 'Colca',
          'active' => 1,
        ),
        1043 => 
        array (
          'id' => '120113',
          'province_id' => '1201',
          'description' => 'Cullhuas',
          'active' => 1,
        ),
        1044 => 
        array (
          'id' => '120114',
          'province_id' => '1201',
          'description' => 'El Tambo',
          'active' => 1,
        ),
        1045 => 
        array (
          'id' => '120116',
          'province_id' => '1201',
          'description' => 'Huacrapuquio',
          'active' => 1,
        ),
        1046 => 
        array (
          'id' => '120117',
          'province_id' => '1201',
          'description' => 'Hualhuas',
          'active' => 1,
        ),
        1047 => 
        array (
          'id' => '120119',
          'province_id' => '1201',
          'description' => 'Huancan',
          'active' => 1,
        ),
        1048 => 
        array (
          'id' => '120120',
          'province_id' => '1201',
          'description' => 'Huasicancha',
          'active' => 1,
        ),
        1049 => 
        array (
          'id' => '120121',
          'province_id' => '1201',
          'description' => 'Huayucachi',
          'active' => 1,
        ),
        1050 => 
        array (
          'id' => '120122',
          'province_id' => '1201',
          'description' => 'Ingenio',
          'active' => 1,
        ),
        1051 => 
        array (
          'id' => '120124',
          'province_id' => '1201',
          'description' => 'Pariahuanca',
          'active' => 1,
        ),
        1052 => 
        array (
          'id' => '120125',
          'province_id' => '1201',
          'description' => 'Pilcomayo',
          'active' => 1,
        ),
        1053 => 
        array (
          'id' => '120126',
          'province_id' => '1201',
          'description' => 'Pucara',
          'active' => 1,
        ),
        1054 => 
        array (
          'id' => '120127',
          'province_id' => '1201',
          'description' => 'Quichuay',
          'active' => 1,
        ),
        1055 => 
        array (
          'id' => '120128',
          'province_id' => '1201',
          'description' => 'Quilcas',
          'active' => 1,
        ),
        1056 => 
        array (
          'id' => '120129',
          'province_id' => '1201',
          'description' => 'San Agustín',
          'active' => 1,
        ),
        1057 => 
        array (
          'id' => '120130',
          'province_id' => '1201',
          'description' => 'San Jerónimo de Tunan',
          'active' => 1,
        ),
        1058 => 
        array (
          'id' => '120132',
          'province_id' => '1201',
          'description' => 'Saño',
          'active' => 1,
        ),
        1059 => 
        array (
          'id' => '120133',
          'province_id' => '1201',
          'description' => 'Sapallanga',
          'active' => 1,
        ),
        1060 => 
        array (
          'id' => '120134',
          'province_id' => '1201',
          'description' => 'Sicaya',
          'active' => 1,
        ),
        1061 => 
        array (
          'id' => '120135',
          'province_id' => '1201',
          'description' => 'Santo Domingo de Acobamba',
          'active' => 1,
        ),
        1062 => 
        array (
          'id' => '120136',
          'province_id' => '1201',
          'description' => 'Viques',
          'active' => 1,
        ),
        1063 => 
        array (
          'id' => '120201',
          'province_id' => '1202',
          'description' => 'Concepción',
          'active' => 1,
        ),
        1064 => 
        array (
          'id' => '120202',
          'province_id' => '1202',
          'description' => 'Aco',
          'active' => 1,
        ),
        1065 => 
        array (
          'id' => '120203',
          'province_id' => '1202',
          'description' => 'Andamarca',
          'active' => 1,
        ),
        1066 => 
        array (
          'id' => '120204',
          'province_id' => '1202',
          'description' => 'Chambara',
          'active' => 1,
        ),
        1067 => 
        array (
          'id' => '120205',
          'province_id' => '1202',
          'description' => 'Cochas',
          'active' => 1,
        ),
        1068 => 
        array (
          'id' => '120206',
          'province_id' => '1202',
          'description' => 'Comas',
          'active' => 1,
        ),
        1069 => 
        array (
          'id' => '120207',
          'province_id' => '1202',
          'description' => 'Heroínas Toledo',
          'active' => 1,
        ),
        1070 => 
        array (
          'id' => '120208',
          'province_id' => '1202',
          'description' => 'Manzanares',
          'active' => 1,
        ),
        1071 => 
        array (
          'id' => '120209',
          'province_id' => '1202',
          'description' => 'Mariscal Castilla',
          'active' => 1,
        ),
        1072 => 
        array (
          'id' => '120210',
          'province_id' => '1202',
          'description' => 'Matahuasi',
          'active' => 1,
        ),
        1073 => 
        array (
          'id' => '120211',
          'province_id' => '1202',
          'description' => 'Mito',
          'active' => 1,
        ),
        1074 => 
        array (
          'id' => '120212',
          'province_id' => '1202',
          'description' => 'Nueve de Julio',
          'active' => 1,
        ),
        1075 => 
        array (
          'id' => '120213',
          'province_id' => '1202',
          'description' => 'Orcotuna',
          'active' => 1,
        ),
        1076 => 
        array (
          'id' => '120214',
          'province_id' => '1202',
          'description' => 'San José de Quero',
          'active' => 1,
        ),
        1077 => 
        array (
          'id' => '120215',
          'province_id' => '1202',
          'description' => 'Santa Rosa de Ocopa',
          'active' => 1,
        ),
        1078 => 
        array (
          'id' => '120301',
          'province_id' => '1203',
          'description' => 'Chanchamayo',
          'active' => 1,
        ),
        1079 => 
        array (
          'id' => '120302',
          'province_id' => '1203',
          'description' => 'Perene',
          'active' => 1,
        ),
        1080 => 
        array (
          'id' => '120303',
          'province_id' => '1203',
          'description' => 'Pichanaqui',
          'active' => 1,
        ),
        1081 => 
        array (
          'id' => '120304',
          'province_id' => '1203',
          'description' => 'San Luis de Shuaro',
          'active' => 1,
        ),
        1082 => 
        array (
          'id' => '120305',
          'province_id' => '1203',
          'description' => 'San Ramón',
          'active' => 1,
        ),
        1083 => 
        array (
          'id' => '120306',
          'province_id' => '1203',
          'description' => 'Vitoc',
          'active' => 1,
        ),
        1084 => 
        array (
          'id' => '120401',
          'province_id' => '1204',
          'description' => 'Jauja',
          'active' => 1,
        ),
        1085 => 
        array (
          'id' => '120402',
          'province_id' => '1204',
          'description' => 'Acolla',
          'active' => 1,
        ),
        1086 => 
        array (
          'id' => '120403',
          'province_id' => '1204',
          'description' => 'Apata',
          'active' => 1,
        ),
        1087 => 
        array (
          'id' => '120404',
          'province_id' => '1204',
          'description' => 'Ataura',
          'active' => 1,
        ),
        1088 => 
        array (
          'id' => '120405',
          'province_id' => '1204',
          'description' => 'Canchayllo',
          'active' => 1,
        ),
        1089 => 
        array (
          'id' => '120406',
          'province_id' => '1204',
          'description' => 'Curicaca',
          'active' => 1,
        ),
        1090 => 
        array (
          'id' => '120407',
          'province_id' => '1204',
          'description' => 'El Mantaro',
          'active' => 1,
        ),
        1091 => 
        array (
          'id' => '120408',
          'province_id' => '1204',
          'description' => 'Huamali',
          'active' => 1,
        ),
        1092 => 
        array (
          'id' => '120409',
          'province_id' => '1204',
          'description' => 'Huaripampa',
          'active' => 1,
        ),
        1093 => 
        array (
          'id' => '120410',
          'province_id' => '1204',
          'description' => 'Huertas',
          'active' => 1,
        ),
        1094 => 
        array (
          'id' => '120411',
          'province_id' => '1204',
          'description' => 'Janjaillo',
          'active' => 1,
        ),
        1095 => 
        array (
          'id' => '120412',
          'province_id' => '1204',
          'description' => 'Julcán',
          'active' => 1,
        ),
        1096 => 
        array (
          'id' => '120413',
          'province_id' => '1204',
          'description' => 'Leonor Ordóñez',
          'active' => 1,
        ),
        1097 => 
        array (
          'id' => '120414',
          'province_id' => '1204',
          'description' => 'Llocllapampa',
          'active' => 1,
        ),
        1098 => 
        array (
          'id' => '120415',
          'province_id' => '1204',
          'description' => 'Marco',
          'active' => 1,
        ),
        1099 => 
        array (
          'id' => '120416',
          'province_id' => '1204',
          'description' => 'Masma',
          'active' => 1,
        ),
        1100 => 
        array (
          'id' => '120417',
          'province_id' => '1204',
          'description' => 'Masma Chicche',
          'active' => 1,
        ),
        1101 => 
        array (
          'id' => '120418',
          'province_id' => '1204',
          'description' => 'Molinos',
          'active' => 1,
        ),
        1102 => 
        array (
          'id' => '120419',
          'province_id' => '1204',
          'description' => 'Monobamba',
          'active' => 1,
        ),
        1103 => 
        array (
          'id' => '120420',
          'province_id' => '1204',
          'description' => 'Muqui',
          'active' => 1,
        ),
        1104 => 
        array (
          'id' => '120421',
          'province_id' => '1204',
          'description' => 'Muquiyauyo',
          'active' => 1,
        ),
        1105 => 
        array (
          'id' => '120422',
          'province_id' => '1204',
          'description' => 'Paca',
          'active' => 1,
        ),
        1106 => 
        array (
          'id' => '120423',
          'province_id' => '1204',
          'description' => 'Paccha',
          'active' => 1,
        ),
        1107 => 
        array (
          'id' => '120424',
          'province_id' => '1204',
          'description' => 'Pancan',
          'active' => 1,
        ),
        1108 => 
        array (
          'id' => '120425',
          'province_id' => '1204',
          'description' => 'Parco',
          'active' => 1,
        ),
        1109 => 
        array (
          'id' => '120426',
          'province_id' => '1204',
          'description' => 'Pomacancha',
          'active' => 1,
        ),
        1110 => 
        array (
          'id' => '120427',
          'province_id' => '1204',
          'description' => 'Ricran',
          'active' => 1,
        ),
        1111 => 
        array (
          'id' => '120428',
          'province_id' => '1204',
          'description' => 'San Lorenzo',
          'active' => 1,
        ),
        1112 => 
        array (
          'id' => '120429',
          'province_id' => '1204',
          'description' => 'San Pedro de Chunan',
          'active' => 1,
        ),
        1113 => 
        array (
          'id' => '120430',
          'province_id' => '1204',
          'description' => 'Sausa',
          'active' => 1,
        ),
        1114 => 
        array (
          'id' => '120431',
          'province_id' => '1204',
          'description' => 'Sincos',
          'active' => 1,
        ),
        1115 => 
        array (
          'id' => '120432',
          'province_id' => '1204',
          'description' => 'Tunan Marca',
          'active' => 1,
        ),
        1116 => 
        array (
          'id' => '120433',
          'province_id' => '1204',
          'description' => 'Yauli',
          'active' => 1,
        ),
        1117 => 
        array (
          'id' => '120434',
          'province_id' => '1204',
          'description' => 'Yauyos',
          'active' => 1,
        ),
        1118 => 
        array (
          'id' => '120501',
          'province_id' => '1205',
          'description' => 'Junin',
          'active' => 1,
        ),
        1119 => 
        array (
          'id' => '120502',
          'province_id' => '1205',
          'description' => 'Carhuamayo',
          'active' => 1,
        ),
        1120 => 
        array (
          'id' => '120503',
          'province_id' => '1205',
          'description' => 'Ondores',
          'active' => 1,
        ),
        1121 => 
        array (
          'id' => '120504',
          'province_id' => '1205',
          'description' => 'Ulcumayo',
          'active' => 1,
        ),
        1122 => 
        array (
          'id' => '120601',
          'province_id' => '1206',
          'description' => 'Satipo',
          'active' => 1,
        ),
        1123 => 
        array (
          'id' => '120602',
          'province_id' => '1206',
          'description' => 'Coviriali',
          'active' => 1,
        ),
        1124 => 
        array (
          'id' => '120603',
          'province_id' => '1206',
          'description' => 'Llaylla',
          'active' => 1,
        ),
        1125 => 
        array (
          'id' => '120604',
          'province_id' => '1206',
          'description' => 'Mazamari',
          'active' => 1,
        ),
        1126 => 
        array (
          'id' => '120605',
          'province_id' => '1206',
          'description' => 'Pampa Hermosa',
          'active' => 1,
        ),
        1127 => 
        array (
          'id' => '120606',
          'province_id' => '1206',
          'description' => 'Pangoa',
          'active' => 1,
        ),
        1128 => 
        array (
          'id' => '120607',
          'province_id' => '1206',
          'description' => 'Río Negro',
          'active' => 1,
        ),
        1129 => 
        array (
          'id' => '120608',
          'province_id' => '1206',
          'description' => 'Río Tambo',
          'active' => 1,
        ),
        1130 => 
        array (
          'id' => '120609',
          'province_id' => '1206',
          'description' => 'Vizcatan del Ene',
          'active' => 1,
        ),
        1131 => 
        array (
          'id' => '120701',
          'province_id' => '1207',
          'description' => 'Tarma',
          'active' => 1,
        ),
        1132 => 
        array (
          'id' => '120702',
          'province_id' => '1207',
          'description' => 'Acobamba',
          'active' => 1,
        ),
        1133 => 
        array (
          'id' => '120703',
          'province_id' => '1207',
          'description' => 'Huaricolca',
          'active' => 1,
        ),
        1134 => 
        array (
          'id' => '120704',
          'province_id' => '1207',
          'description' => 'Huasahuasi',
          'active' => 1,
        ),
        1135 => 
        array (
          'id' => '120705',
          'province_id' => '1207',
          'description' => 'La Unión',
          'active' => 1,
        ),
        1136 => 
        array (
          'id' => '120706',
          'province_id' => '1207',
          'description' => 'Palca',
          'active' => 1,
        ),
        1137 => 
        array (
          'id' => '120707',
          'province_id' => '1207',
          'description' => 'Palcamayo',
          'active' => 1,
        ),
        1138 => 
        array (
          'id' => '120708',
          'province_id' => '1207',
          'description' => 'San Pedro de Cajas',
          'active' => 1,
        ),
        1139 => 
        array (
          'id' => '120709',
          'province_id' => '1207',
          'description' => 'Tapo',
          'active' => 1,
        ),
        1140 => 
        array (
          'id' => '120801',
          'province_id' => '1208',
          'description' => 'La Oroya',
          'active' => 1,
        ),
        1141 => 
        array (
          'id' => '120802',
          'province_id' => '1208',
          'description' => 'Chacapalpa',
          'active' => 1,
        ),
        1142 => 
        array (
          'id' => '120803',
          'province_id' => '1208',
          'description' => 'Huay-Huay',
          'active' => 1,
        ),
        1143 => 
        array (
          'id' => '120804',
          'province_id' => '1208',
          'description' => 'Marcapomacocha',
          'active' => 1,
        ),
        1144 => 
        array (
          'id' => '120805',
          'province_id' => '1208',
          'description' => 'Morococha',
          'active' => 1,
        ),
        1145 => 
        array (
          'id' => '120806',
          'province_id' => '1208',
          'description' => 'Paccha',
          'active' => 1,
        ),
        1146 => 
        array (
          'id' => '120807',
          'province_id' => '1208',
          'description' => 'Santa Bárbara de Carhuacayan',
          'active' => 1,
        ),
        1147 => 
        array (
          'id' => '120808',
          'province_id' => '1208',
          'description' => 'Santa Rosa de Sacco',
          'active' => 1,
        ),
        1148 => 
        array (
          'id' => '120809',
          'province_id' => '1208',
          'description' => 'Suitucancha',
          'active' => 1,
        ),
        1149 => 
        array (
          'id' => '120810',
          'province_id' => '1208',
          'description' => 'Yauli',
          'active' => 1,
        ),
        1150 => 
        array (
          'id' => '120901',
          'province_id' => '1209',
          'description' => 'Chupaca',
          'active' => 1,
        ),
        1151 => 
        array (
          'id' => '120902',
          'province_id' => '1209',
          'description' => 'Ahuac',
          'active' => 1,
        ),
        1152 => 
        array (
          'id' => '120903',
          'province_id' => '1209',
          'description' => 'Chongos Bajo',
          'active' => 1,
        ),
        1153 => 
        array (
          'id' => '120904',
          'province_id' => '1209',
          'description' => 'Huachac',
          'active' => 1,
        ),
        1154 => 
        array (
          'id' => '120905',
          'province_id' => '1209',
          'description' => 'Huamancaca Chico',
          'active' => 1,
        ),
        1155 => 
        array (
          'id' => '120906',
          'province_id' => '1209',
          'description' => 'San Juan de Iscos',
          'active' => 1,
        ),
        1156 => 
        array (
          'id' => '120907',
          'province_id' => '1209',
          'description' => 'San Juan de Jarpa',
          'active' => 1,
        ),
        1157 => 
        array (
          'id' => '120908',
          'province_id' => '1209',
          'description' => 'Tres de Diciembre',
          'active' => 1,
        ),
        1158 => 
        array (
          'id' => '120909',
          'province_id' => '1209',
          'description' => 'Yanacancha',
          'active' => 1,
        ),
        1159 => 
        array (
          'id' => '130101',
          'province_id' => '1301',
          'description' => 'Trujillo',
          'active' => 1,
        ),
        1160 => 
        array (
          'id' => '130102',
          'province_id' => '1301',
          'description' => 'El Porvenir',
          'active' => 1,
        ),
        1161 => 
        array (
          'id' => '130103',
          'province_id' => '1301',
          'description' => 'Florencia de Mora',
          'active' => 1,
        ),
        1162 => 
        array (
          'id' => '130104',
          'province_id' => '1301',
          'description' => 'Huanchaco',
          'active' => 1,
        ),
        1163 => 
        array (
          'id' => '130105',
          'province_id' => '1301',
          'description' => 'La Esperanza',
          'active' => 1,
        ),
        1164 => 
        array (
          'id' => '130106',
          'province_id' => '1301',
          'description' => 'Laredo',
          'active' => 1,
        ),
        1165 => 
        array (
          'id' => '130107',
          'province_id' => '1301',
          'description' => 'Moche',
          'active' => 1,
        ),
        1166 => 
        array (
          'id' => '130108',
          'province_id' => '1301',
          'description' => 'Poroto',
          'active' => 1,
        ),
        1167 => 
        array (
          'id' => '130109',
          'province_id' => '1301',
          'description' => 'Salaverry',
          'active' => 1,
        ),
        1168 => 
        array (
          'id' => '130110',
          'province_id' => '1301',
          'description' => 'Simbal',
          'active' => 1,
        ),
        1169 => 
        array (
          'id' => '130111',
          'province_id' => '1301',
          'description' => 'Victor Larco Herrera',
          'active' => 1,
        ),
        1170 => 
        array (
          'id' => '130201',
          'province_id' => '1302',
          'description' => 'Ascope',
          'active' => 1,
        ),
        1171 => 
        array (
          'id' => '130202',
          'province_id' => '1302',
          'description' => 'Chicama',
          'active' => 1,
        ),
        1172 => 
        array (
          'id' => '130203',
          'province_id' => '1302',
          'description' => 'Chocope',
          'active' => 1,
        ),
        1173 => 
        array (
          'id' => '130204',
          'province_id' => '1302',
          'description' => 'Magdalena de Cao',
          'active' => 1,
        ),
        1174 => 
        array (
          'id' => '130205',
          'province_id' => '1302',
          'description' => 'Paijan',
          'active' => 1,
        ),
        1175 => 
        array (
          'id' => '130206',
          'province_id' => '1302',
          'description' => 'Rázuri',
          'active' => 1,
        ),
        1176 => 
        array (
          'id' => '130207',
          'province_id' => '1302',
          'description' => 'Santiago de Cao',
          'active' => 1,
        ),
        1177 => 
        array (
          'id' => '130208',
          'province_id' => '1302',
          'description' => 'Casa Grande',
          'active' => 1,
        ),
        1178 => 
        array (
          'id' => '130301',
          'province_id' => '1303',
          'description' => 'Bolívar',
          'active' => 1,
        ),
        1179 => 
        array (
          'id' => '130302',
          'province_id' => '1303',
          'description' => 'Bambamarca',
          'active' => 1,
        ),
        1180 => 
        array (
          'id' => '130303',
          'province_id' => '1303',
          'description' => 'Condormarca',
          'active' => 1,
        ),
        1181 => 
        array (
          'id' => '130304',
          'province_id' => '1303',
          'description' => 'Longotea',
          'active' => 1,
        ),
        1182 => 
        array (
          'id' => '130305',
          'province_id' => '1303',
          'description' => 'Uchumarca',
          'active' => 1,
        ),
        1183 => 
        array (
          'id' => '130306',
          'province_id' => '1303',
          'description' => 'Ucuncha',
          'active' => 1,
        ),
        1184 => 
        array (
          'id' => '130401',
          'province_id' => '1304',
          'description' => 'Chepen',
          'active' => 1,
        ),
        1185 => 
        array (
          'id' => '130402',
          'province_id' => '1304',
          'description' => 'Pacanga',
          'active' => 1,
        ),
        1186 => 
        array (
          'id' => '130403',
          'province_id' => '1304',
          'description' => 'Pueblo Nuevo',
          'active' => 1,
        ),
        1187 => 
        array (
          'id' => '130501',
          'province_id' => '1305',
          'description' => 'Julcan',
          'active' => 1,
        ),
        1188 => 
        array (
          'id' => '130502',
          'province_id' => '1305',
          'description' => 'Calamarca',
          'active' => 1,
        ),
        1189 => 
        array (
          'id' => '130503',
          'province_id' => '1305',
          'description' => 'Carabamba',
          'active' => 1,
        ),
        1190 => 
        array (
          'id' => '130504',
          'province_id' => '1305',
          'description' => 'Huaso',
          'active' => 1,
        ),
        1191 => 
        array (
          'id' => '130601',
          'province_id' => '1306',
          'description' => 'Otuzco',
          'active' => 1,
        ),
        1192 => 
        array (
          'id' => '130602',
          'province_id' => '1306',
          'description' => 'Agallpampa',
          'active' => 1,
        ),
        1193 => 
        array (
          'id' => '130604',
          'province_id' => '1306',
          'description' => 'Charat',
          'active' => 1,
        ),
        1194 => 
        array (
          'id' => '130605',
          'province_id' => '1306',
          'description' => 'Huaranchal',
          'active' => 1,
        ),
        1195 => 
        array (
          'id' => '130606',
          'province_id' => '1306',
          'description' => 'La Cuesta',
          'active' => 1,
        ),
        1196 => 
        array (
          'id' => '130608',
          'province_id' => '1306',
          'description' => 'Mache',
          'active' => 1,
        ),
        1197 => 
        array (
          'id' => '130610',
          'province_id' => '1306',
          'description' => 'Paranday',
          'active' => 1,
        ),
        1198 => 
        array (
          'id' => '130611',
          'province_id' => '1306',
          'description' => 'Salpo',
          'active' => 1,
        ),
        1199 => 
        array (
          'id' => '130613',
          'province_id' => '1306',
          'description' => 'Sinsicap',
          'active' => 1,
        ),
        1200 => 
        array (
          'id' => '130614',
          'province_id' => '1306',
          'description' => 'Usquil',
          'active' => 1,
        ),
        1201 => 
        array (
          'id' => '130701',
          'province_id' => '1307',
          'description' => 'San Pedro de Lloc',
          'active' => 1,
        ),
        1202 => 
        array (
          'id' => '130702',
          'province_id' => '1307',
          'description' => 'Guadalupe',
          'active' => 1,
        ),
        1203 => 
        array (
          'id' => '130703',
          'province_id' => '1307',
          'description' => 'Jequetepeque',
          'active' => 1,
        ),
        1204 => 
        array (
          'id' => '130704',
          'province_id' => '1307',
          'description' => 'Pacasmayo',
          'active' => 1,
        ),
        1205 => 
        array (
          'id' => '130705',
          'province_id' => '1307',
          'description' => 'San José',
          'active' => 1,
        ),
        1206 => 
        array (
          'id' => '130801',
          'province_id' => '1308',
          'description' => 'Tayabamba',
          'active' => 1,
        ),
        1207 => 
        array (
          'id' => '130802',
          'province_id' => '1308',
          'description' => 'Buldibuyo',
          'active' => 1,
        ),
        1208 => 
        array (
          'id' => '130803',
          'province_id' => '1308',
          'description' => 'Chillia',
          'active' => 1,
        ),
        1209 => 
        array (
          'id' => '130804',
          'province_id' => '1308',
          'description' => 'Huancaspata',
          'active' => 1,
        ),
        1210 => 
        array (
          'id' => '130805',
          'province_id' => '1308',
          'description' => 'Huaylillas',
          'active' => 1,
        ),
        1211 => 
        array (
          'id' => '130806',
          'province_id' => '1308',
          'description' => 'Huayo',
          'active' => 1,
        ),
        1212 => 
        array (
          'id' => '130807',
          'province_id' => '1308',
          'description' => 'Ongon',
          'active' => 1,
        ),
        1213 => 
        array (
          'id' => '130808',
          'province_id' => '1308',
          'description' => 'Parcoy',
          'active' => 1,
        ),
        1214 => 
        array (
          'id' => '130809',
          'province_id' => '1308',
          'description' => 'Pataz',
          'active' => 1,
        ),
        1215 => 
        array (
          'id' => '130810',
          'province_id' => '1308',
          'description' => 'Pias',
          'active' => 1,
        ),
        1216 => 
        array (
          'id' => '130811',
          'province_id' => '1308',
          'description' => 'Santiago de Challas',
          'active' => 1,
        ),
        1217 => 
        array (
          'id' => '130812',
          'province_id' => '1308',
          'description' => 'Taurija',
          'active' => 1,
        ),
        1218 => 
        array (
          'id' => '130813',
          'province_id' => '1308',
          'description' => 'Urpay',
          'active' => 1,
        ),
        1219 => 
        array (
          'id' => '130901',
          'province_id' => '1309',
          'description' => 'Huamachuco',
          'active' => 1,
        ),
        1220 => 
        array (
          'id' => '130902',
          'province_id' => '1309',
          'description' => 'Chugay',
          'active' => 1,
        ),
        1221 => 
        array (
          'id' => '130903',
          'province_id' => '1309',
          'description' => 'Cochorco',
          'active' => 1,
        ),
        1222 => 
        array (
          'id' => '130904',
          'province_id' => '1309',
          'description' => 'Curgos',
          'active' => 1,
        ),
        1223 => 
        array (
          'id' => '130905',
          'province_id' => '1309',
          'description' => 'Marcabal',
          'active' => 1,
        ),
        1224 => 
        array (
          'id' => '130906',
          'province_id' => '1309',
          'description' => 'Sanagoran',
          'active' => 1,
        ),
        1225 => 
        array (
          'id' => '130907',
          'province_id' => '1309',
          'description' => 'Sarin',
          'active' => 1,
        ),
        1226 => 
        array (
          'id' => '130908',
          'province_id' => '1309',
          'description' => 'Sartimbamba',
          'active' => 1,
        ),
        1227 => 
        array (
          'id' => '131001',
          'province_id' => '1310',
          'description' => 'Santiago de Chuco',
          'active' => 1,
        ),
        1228 => 
        array (
          'id' => '131002',
          'province_id' => '1310',
          'description' => 'Angasmarca',
          'active' => 1,
        ),
        1229 => 
        array (
          'id' => '131003',
          'province_id' => '1310',
          'description' => 'Cachicadan',
          'active' => 1,
        ),
        1230 => 
        array (
          'id' => '131004',
          'province_id' => '1310',
          'description' => 'Mollebamba',
          'active' => 1,
        ),
        1231 => 
        array (
          'id' => '131005',
          'province_id' => '1310',
          'description' => 'Mollepata',
          'active' => 1,
        ),
        1232 => 
        array (
          'id' => '131006',
          'province_id' => '1310',
          'description' => 'Quiruvilca',
          'active' => 1,
        ),
        1233 => 
        array (
          'id' => '131007',
          'province_id' => '1310',
          'description' => 'Santa Cruz de Chuca',
          'active' => 1,
        ),
        1234 => 
        array (
          'id' => '131008',
          'province_id' => '1310',
          'description' => 'Sitabamba',
          'active' => 1,
        ),
        1235 => 
        array (
          'id' => '131101',
          'province_id' => '1311',
          'description' => 'Cascas',
          'active' => 1,
        ),
        1236 => 
        array (
          'id' => '131102',
          'province_id' => '1311',
          'description' => 'Lucma',
          'active' => 1,
        ),
        1237 => 
        array (
          'id' => '131103',
          'province_id' => '1311',
          'description' => 'Marmot',
          'active' => 1,
        ),
        1238 => 
        array (
          'id' => '131104',
          'province_id' => '1311',
          'description' => 'Sayapullo',
          'active' => 1,
        ),
        1239 => 
        array (
          'id' => '131201',
          'province_id' => '1312',
          'description' => 'Viru',
          'active' => 1,
        ),
        1240 => 
        array (
          'id' => '131202',
          'province_id' => '1312',
          'description' => 'Chao',
          'active' => 1,
        ),
        1241 => 
        array (
          'id' => '131203',
          'province_id' => '1312',
          'description' => 'Guadalupito',
          'active' => 1,
        ),
        1242 => 
        array (
          'id' => '140101',
          'province_id' => '1401',
          'description' => 'Chiclayo',
          'active' => 1,
        ),
        1243 => 
        array (
          'id' => '140102',
          'province_id' => '1401',
          'description' => 'Chongoyape',
          'active' => 1,
        ),
        1244 => 
        array (
          'id' => '140103',
          'province_id' => '1401',
          'description' => 'Eten',
          'active' => 1,
        ),
        1245 => 
        array (
          'id' => '140104',
          'province_id' => '1401',
          'description' => 'Eten Puerto',
          'active' => 1,
        ),
        1246 => 
        array (
          'id' => '140105',
          'province_id' => '1401',
          'description' => 'José Leonardo Ortiz',
          'active' => 1,
        ),
        1247 => 
        array (
          'id' => '140106',
          'province_id' => '1401',
          'description' => 'La Victoria',
          'active' => 1,
        ),
        1248 => 
        array (
          'id' => '140107',
          'province_id' => '1401',
          'description' => 'Lagunas',
          'active' => 1,
        ),
        1249 => 
        array (
          'id' => '140108',
          'province_id' => '1401',
          'description' => 'Monsefu',
          'active' => 1,
        ),
        1250 => 
        array (
          'id' => '140109',
          'province_id' => '1401',
          'description' => 'Nueva Arica',
          'active' => 1,
        ),
        1251 => 
        array (
          'id' => '140110',
          'province_id' => '1401',
          'description' => 'Oyotun',
          'active' => 1,
        ),
        1252 => 
        array (
          'id' => '140111',
          'province_id' => '1401',
          'description' => 'Picsi',
          'active' => 1,
        ),
        1253 => 
        array (
          'id' => '140112',
          'province_id' => '1401',
          'description' => 'Pimentel',
          'active' => 1,
        ),
        1254 => 
        array (
          'id' => '140113',
          'province_id' => '1401',
          'description' => 'Reque',
          'active' => 1,
        ),
        1255 => 
        array (
          'id' => '140114',
          'province_id' => '1401',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        1256 => 
        array (
          'id' => '140115',
          'province_id' => '1401',
          'description' => 'Saña',
          'active' => 1,
        ),
        1257 => 
        array (
          'id' => '140116',
          'province_id' => '1401',
          'description' => 'Cayalti',
          'active' => 1,
        ),
        1258 => 
        array (
          'id' => '140117',
          'province_id' => '1401',
          'description' => 'Patapo',
          'active' => 1,
        ),
        1259 => 
        array (
          'id' => '140118',
          'province_id' => '1401',
          'description' => 'Pomalca',
          'active' => 1,
        ),
        1260 => 
        array (
          'id' => '140119',
          'province_id' => '1401',
          'description' => 'Pucala',
          'active' => 1,
        ),
        1261 => 
        array (
          'id' => '140120',
          'province_id' => '1401',
          'description' => 'Tuman',
          'active' => 1,
        ),
        1262 => 
        array (
          'id' => '140201',
          'province_id' => '1402',
          'description' => 'Ferreñafe',
          'active' => 1,
        ),
        1263 => 
        array (
          'id' => '140202',
          'province_id' => '1402',
          'description' => 'Cañaris',
          'active' => 1,
        ),
        1264 => 
        array (
          'id' => '140203',
          'province_id' => '1402',
          'description' => 'Incahuasi',
          'active' => 1,
        ),
        1265 => 
        array (
          'id' => '140204',
          'province_id' => '1402',
          'description' => 'Manuel Antonio Mesones Muro',
          'active' => 1,
        ),
        1266 => 
        array (
          'id' => '140205',
          'province_id' => '1402',
          'description' => 'Pitipo',
          'active' => 1,
        ),
        1267 => 
        array (
          'id' => '140206',
          'province_id' => '1402',
          'description' => 'Pueblo Nuevo',
          'active' => 1,
        ),
        1268 => 
        array (
          'id' => '140301',
          'province_id' => '1403',
          'description' => 'Lambayeque',
          'active' => 1,
        ),
        1269 => 
        array (
          'id' => '140302',
          'province_id' => '1403',
          'description' => 'Chochope',
          'active' => 1,
        ),
        1270 => 
        array (
          'id' => '140303',
          'province_id' => '1403',
          'description' => 'Illimo',
          'active' => 1,
        ),
        1271 => 
        array (
          'id' => '140304',
          'province_id' => '1403',
          'description' => 'Jayanca',
          'active' => 1,
        ),
        1272 => 
        array (
          'id' => '140305',
          'province_id' => '1403',
          'description' => 'Mochumi',
          'active' => 1,
        ),
        1273 => 
        array (
          'id' => '140306',
          'province_id' => '1403',
          'description' => 'Morrope',
          'active' => 1,
        ),
        1274 => 
        array (
          'id' => '140307',
          'province_id' => '1403',
          'description' => 'Motupe',
          'active' => 1,
        ),
        1275 => 
        array (
          'id' => '140308',
          'province_id' => '1403',
          'description' => 'Olmos',
          'active' => 1,
        ),
        1276 => 
        array (
          'id' => '140309',
          'province_id' => '1403',
          'description' => 'Pacora',
          'active' => 1,
        ),
        1277 => 
        array (
          'id' => '140310',
          'province_id' => '1403',
          'description' => 'Salas',
          'active' => 1,
        ),
        1278 => 
        array (
          'id' => '140311',
          'province_id' => '1403',
          'description' => 'San José',
          'active' => 1,
        ),
        1279 => 
        array (
          'id' => '140312',
          'province_id' => '1403',
          'description' => 'Tucume',
          'active' => 1,
        ),
        1280 => 
        array (
          'id' => '150101',
          'province_id' => '1501',
          'description' => 'Lima',
          'active' => 1,
        ),
        1281 => 
        array (
          'id' => '150102',
          'province_id' => '1501',
          'description' => 'Ancón',
          'active' => 1,
        ),
        1282 => 
        array (
          'id' => '150103',
          'province_id' => '1501',
          'description' => 'Ate',
          'active' => 1,
        ),
        1283 => 
        array (
          'id' => '150104',
          'province_id' => '1501',
          'description' => 'Barranco',
          'active' => 1,
        ),
        1284 => 
        array (
          'id' => '150105',
          'province_id' => '1501',
          'description' => 'Breña',
          'active' => 1,
        ),
        1285 => 
        array (
          'id' => '150106',
          'province_id' => '1501',
          'description' => 'Carabayllo',
          'active' => 1,
        ),
        1286 => 
        array (
          'id' => '150107',
          'province_id' => '1501',
          'description' => 'Chaclacayo',
          'active' => 1,
        ),
        1287 => 
        array (
          'id' => '150108',
          'province_id' => '1501',
          'description' => 'Chorrillos',
          'active' => 1,
        ),
        1288 => 
        array (
          'id' => '150109',
          'province_id' => '1501',
          'description' => 'Cieneguilla',
          'active' => 1,
        ),
        1289 => 
        array (
          'id' => '150110',
          'province_id' => '1501',
          'description' => 'Comas',
          'active' => 1,
        ),
        1290 => 
        array (
          'id' => '150111',
          'province_id' => '1501',
          'description' => 'El Agustino',
          'active' => 1,
        ),
        1291 => 
        array (
          'id' => '150112',
          'province_id' => '1501',
          'description' => 'Independencia',
          'active' => 1,
        ),
        1292 => 
        array (
          'id' => '150113',
          'province_id' => '1501',
          'description' => 'Jesús María',
          'active' => 1,
        ),
        1293 => 
        array (
          'id' => '150114',
          'province_id' => '1501',
          'description' => 'La Molina',
          'active' => 1,
        ),
        1294 => 
        array (
          'id' => '150115',
          'province_id' => '1501',
          'description' => 'La Victoria',
          'active' => 1,
        ),
        1295 => 
        array (
          'id' => '150116',
          'province_id' => '1501',
          'description' => 'Lince',
          'active' => 1,
        ),
        1296 => 
        array (
          'id' => '150117',
          'province_id' => '1501',
          'description' => 'Los Olivos',
          'active' => 1,
        ),
        1297 => 
        array (
          'id' => '150118',
          'province_id' => '1501',
          'description' => 'Lurigancho',
          'active' => 1,
        ),
        1298 => 
        array (
          'id' => '150119',
          'province_id' => '1501',
          'description' => 'Lurin',
          'active' => 1,
        ),
        1299 => 
        array (
          'id' => '150120',
          'province_id' => '1501',
          'description' => 'Magdalena del Mar',
          'active' => 1,
        ),
        1300 => 
        array (
          'id' => '150121',
          'province_id' => '1501',
          'description' => 'Pueblo Libre',
          'active' => 1,
        ),
        1301 => 
        array (
          'id' => '150122',
          'province_id' => '1501',
          'description' => 'Miraflores',
          'active' => 1,
        ),
        1302 => 
        array (
          'id' => '150123',
          'province_id' => '1501',
          'description' => 'Pachacamac',
          'active' => 1,
        ),
        1303 => 
        array (
          'id' => '150124',
          'province_id' => '1501',
          'description' => 'Pucusana',
          'active' => 1,
        ),
        1304 => 
        array (
          'id' => '150125',
          'province_id' => '1501',
          'description' => 'Puente Piedra',
          'active' => 1,
        ),
        1305 => 
        array (
          'id' => '150126',
          'province_id' => '1501',
          'description' => 'Punta Hermosa',
          'active' => 1,
        ),
        1306 => 
        array (
          'id' => '150127',
          'province_id' => '1501',
          'description' => 'Punta Negra',
          'active' => 1,
        ),
        1307 => 
        array (
          'id' => '150128',
          'province_id' => '1501',
          'description' => 'Rímac',
          'active' => 1,
        ),
        1308 => 
        array (
          'id' => '150129',
          'province_id' => '1501',
          'description' => 'San Bartolo',
          'active' => 1,
        ),
        1309 => 
        array (
          'id' => '150130',
          'province_id' => '1501',
          'description' => 'San Borja',
          'active' => 1,
        ),
        1310 => 
        array (
          'id' => '150131',
          'province_id' => '1501',
          'description' => 'San Isidro',
          'active' => 1,
        ),
        1311 => 
        array (
          'id' => '150132',
          'province_id' => '1501',
          'description' => 'San Juan de Lurigancho',
          'active' => 1,
        ),
        1312 => 
        array (
          'id' => '150133',
          'province_id' => '1501',
          'description' => 'San Juan de Miraflores',
          'active' => 1,
        ),
        1313 => 
        array (
          'id' => '150134',
          'province_id' => '1501',
          'description' => 'San Luis',
          'active' => 1,
        ),
        1314 => 
        array (
          'id' => '150135',
          'province_id' => '1501',
          'description' => 'San Martín de Porres',
          'active' => 1,
        ),
        1315 => 
        array (
          'id' => '150136',
          'province_id' => '1501',
          'description' => 'San Miguel',
          'active' => 1,
        ),
        1316 => 
        array (
          'id' => '150137',
          'province_id' => '1501',
          'description' => 'Santa Anita',
          'active' => 1,
        ),
        1317 => 
        array (
          'id' => '150138',
          'province_id' => '1501',
          'description' => 'Santa María del Mar',
          'active' => 1,
        ),
        1318 => 
        array (
          'id' => '150139',
          'province_id' => '1501',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        1319 => 
        array (
          'id' => '150140',
          'province_id' => '1501',
          'description' => 'Santiago de Surco',
          'active' => 1,
        ),
        1320 => 
        array (
          'id' => '150141',
          'province_id' => '1501',
          'description' => 'Surquillo',
          'active' => 1,
        ),
        1321 => 
        array (
          'id' => '150142',
          'province_id' => '1501',
          'description' => 'Villa El Salvador',
          'active' => 1,
        ),
        1322 => 
        array (
          'id' => '150143',
          'province_id' => '1501',
          'description' => 'Villa María del Triunfo',
          'active' => 1,
        ),
        1323 => 
        array (
          'id' => '150201',
          'province_id' => '1502',
          'description' => 'Barranca',
          'active' => 1,
        ),
        1324 => 
        array (
          'id' => '150202',
          'province_id' => '1502',
          'description' => 'Paramonga',
          'active' => 1,
        ),
        1325 => 
        array (
          'id' => '150203',
          'province_id' => '1502',
          'description' => 'Pativilca',
          'active' => 1,
        ),
        1326 => 
        array (
          'id' => '150204',
          'province_id' => '1502',
          'description' => 'Supe',
          'active' => 1,
        ),
        1327 => 
        array (
          'id' => '150205',
          'province_id' => '1502',
          'description' => 'Supe Puerto',
          'active' => 1,
        ),
        1328 => 
        array (
          'id' => '150301',
          'province_id' => '1503',
          'description' => 'Cajatambo',
          'active' => 1,
        ),
        1329 => 
        array (
          'id' => '150302',
          'province_id' => '1503',
          'description' => 'Copa',
          'active' => 1,
        ),
        1330 => 
        array (
          'id' => '150303',
          'province_id' => '1503',
          'description' => 'Gorgor',
          'active' => 1,
        ),
        1331 => 
        array (
          'id' => '150304',
          'province_id' => '1503',
          'description' => 'Huancapon',
          'active' => 1,
        ),
        1332 => 
        array (
          'id' => '150305',
          'province_id' => '1503',
          'description' => 'Manas',
          'active' => 1,
        ),
        1333 => 
        array (
          'id' => '150401',
          'province_id' => '1504',
          'description' => 'Canta',
          'active' => 1,
        ),
        1334 => 
        array (
          'id' => '150402',
          'province_id' => '1504',
          'description' => 'Arahuay',
          'active' => 1,
        ),
        1335 => 
        array (
          'id' => '150403',
          'province_id' => '1504',
          'description' => 'Huamantanga',
          'active' => 1,
        ),
        1336 => 
        array (
          'id' => '150404',
          'province_id' => '1504',
          'description' => 'Huaros',
          'active' => 1,
        ),
        1337 => 
        array (
          'id' => '150405',
          'province_id' => '1504',
          'description' => 'Lachaqui',
          'active' => 1,
        ),
        1338 => 
        array (
          'id' => '150406',
          'province_id' => '1504',
          'description' => 'San Buenaventura',
          'active' => 1,
        ),
        1339 => 
        array (
          'id' => '150407',
          'province_id' => '1504',
          'description' => 'Santa Rosa de Quives',
          'active' => 1,
        ),
        1340 => 
        array (
          'id' => '150501',
          'province_id' => '1505',
          'description' => 'San Vicente de Cañete',
          'active' => 1,
        ),
        1341 => 
        array (
          'id' => '150502',
          'province_id' => '1505',
          'description' => 'Asia',
          'active' => 1,
        ),
        1342 => 
        array (
          'id' => '150503',
          'province_id' => '1505',
          'description' => 'Calango',
          'active' => 1,
        ),
        1343 => 
        array (
          'id' => '150504',
          'province_id' => '1505',
          'description' => 'Cerro Azul',
          'active' => 1,
        ),
        1344 => 
        array (
          'id' => '150505',
          'province_id' => '1505',
          'description' => 'Chilca',
          'active' => 1,
        ),
        1345 => 
        array (
          'id' => '150506',
          'province_id' => '1505',
          'description' => 'Coayllo',
          'active' => 1,
        ),
        1346 => 
        array (
          'id' => '150507',
          'province_id' => '1505',
          'description' => 'Imperial',
          'active' => 1,
        ),
        1347 => 
        array (
          'id' => '150508',
          'province_id' => '1505',
          'description' => 'Lunahuana',
          'active' => 1,
        ),
        1348 => 
        array (
          'id' => '150509',
          'province_id' => '1505',
          'description' => 'Mala',
          'active' => 1,
        ),
        1349 => 
        array (
          'id' => '150510',
          'province_id' => '1505',
          'description' => 'Nuevo Imperial',
          'active' => 1,
        ),
        1350 => 
        array (
          'id' => '150511',
          'province_id' => '1505',
          'description' => 'Pacaran',
          'active' => 1,
        ),
        1351 => 
        array (
          'id' => '150512',
          'province_id' => '1505',
          'description' => 'Quilmana',
          'active' => 1,
        ),
        1352 => 
        array (
          'id' => '150513',
          'province_id' => '1505',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        1353 => 
        array (
          'id' => '150514',
          'province_id' => '1505',
          'description' => 'San Luis',
          'active' => 1,
        ),
        1354 => 
        array (
          'id' => '150515',
          'province_id' => '1505',
          'description' => 'Santa Cruz de Flores',
          'active' => 1,
        ),
        1355 => 
        array (
          'id' => '150516',
          'province_id' => '1505',
          'description' => 'Zúñiga',
          'active' => 1,
        ),
        1356 => 
        array (
          'id' => '150601',
          'province_id' => '1506',
          'description' => 'Huaral',
          'active' => 1,
        ),
        1357 => 
        array (
          'id' => '150602',
          'province_id' => '1506',
          'description' => 'Atavillos Alto',
          'active' => 1,
        ),
        1358 => 
        array (
          'id' => '150603',
          'province_id' => '1506',
          'description' => 'Atavillos Bajo',
          'active' => 1,
        ),
        1359 => 
        array (
          'id' => '150604',
          'province_id' => '1506',
          'description' => 'Aucallama',
          'active' => 1,
        ),
        1360 => 
        array (
          'id' => '150605',
          'province_id' => '1506',
          'description' => 'Chancay',
          'active' => 1,
        ),
        1361 => 
        array (
          'id' => '150606',
          'province_id' => '1506',
          'description' => 'Ihuari',
          'active' => 1,
        ),
        1362 => 
        array (
          'id' => '150607',
          'province_id' => '1506',
          'description' => 'Lampian',
          'active' => 1,
        ),
        1363 => 
        array (
          'id' => '150608',
          'province_id' => '1506',
          'description' => 'Pacaraos',
          'active' => 1,
        ),
        1364 => 
        array (
          'id' => '150609',
          'province_id' => '1506',
          'description' => 'San Miguel de Acos',
          'active' => 1,
        ),
        1365 => 
        array (
          'id' => '150610',
          'province_id' => '1506',
          'description' => 'Santa Cruz de Andamarca',
          'active' => 1,
        ),
        1366 => 
        array (
          'id' => '150611',
          'province_id' => '1506',
          'description' => 'Sumbilca',
          'active' => 1,
        ),
        1367 => 
        array (
          'id' => '150612',
          'province_id' => '1506',
          'description' => 'Veintisiete de Noviembre',
          'active' => 1,
        ),
        1368 => 
        array (
          'id' => '150701',
          'province_id' => '1507',
          'description' => 'Matucana',
          'active' => 1,
        ),
        1369 => 
        array (
          'id' => '150702',
          'province_id' => '1507',
          'description' => 'Antioquia',
          'active' => 1,
        ),
        1370 => 
        array (
          'id' => '150703',
          'province_id' => '1507',
          'description' => 'Callahuanca',
          'active' => 1,
        ),
        1371 => 
        array (
          'id' => '150704',
          'province_id' => '1507',
          'description' => 'Carampoma',
          'active' => 1,
        ),
        1372 => 
        array (
          'id' => '150705',
          'province_id' => '1507',
          'description' => 'Chicla',
          'active' => 1,
        ),
        1373 => 
        array (
          'id' => '150706',
          'province_id' => '1507',
          'description' => 'Cuenca',
          'active' => 1,
        ),
        1374 => 
        array (
          'id' => '150707',
          'province_id' => '1507',
          'description' => 'Huachupampa',
          'active' => 1,
        ),
        1375 => 
        array (
          'id' => '150708',
          'province_id' => '1507',
          'description' => 'Huanza',
          'active' => 1,
        ),
        1376 => 
        array (
          'id' => '150709',
          'province_id' => '1507',
          'description' => 'Huarochiri',
          'active' => 1,
        ),
        1377 => 
        array (
          'id' => '150710',
          'province_id' => '1507',
          'description' => 'Lahuaytambo',
          'active' => 1,
        ),
        1378 => 
        array (
          'id' => '150711',
          'province_id' => '1507',
          'description' => 'Langa',
          'active' => 1,
        ),
        1379 => 
        array (
          'id' => '150712',
          'province_id' => '1507',
          'description' => 'Laraos',
          'active' => 1,
        ),
        1380 => 
        array (
          'id' => '150713',
          'province_id' => '1507',
          'description' => 'Mariatana',
          'active' => 1,
        ),
        1381 => 
        array (
          'id' => '150714',
          'province_id' => '1507',
          'description' => 'Ricardo Palma',
          'active' => 1,
        ),
        1382 => 
        array (
          'id' => '150715',
          'province_id' => '1507',
          'description' => 'San Andrés de Tupicocha',
          'active' => 1,
        ),
        1383 => 
        array (
          'id' => '150716',
          'province_id' => '1507',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        1384 => 
        array (
          'id' => '150717',
          'province_id' => '1507',
          'description' => 'San Bartolomé',
          'active' => 1,
        ),
        1385 => 
        array (
          'id' => '150718',
          'province_id' => '1507',
          'description' => 'San Damian',
          'active' => 1,
        ),
        1386 => 
        array (
          'id' => '150719',
          'province_id' => '1507',
          'description' => 'San Juan de Iris',
          'active' => 1,
        ),
        1387 => 
        array (
          'id' => '150720',
          'province_id' => '1507',
          'description' => 'San Juan de Tantaranche',
          'active' => 1,
        ),
        1388 => 
        array (
          'id' => '150721',
          'province_id' => '1507',
          'description' => 'San Lorenzo de Quinti',
          'active' => 1,
        ),
        1389 => 
        array (
          'id' => '150722',
          'province_id' => '1507',
          'description' => 'San Mateo',
          'active' => 1,
        ),
        1390 => 
        array (
          'id' => '150723',
          'province_id' => '1507',
          'description' => 'San Mateo de Otao',
          'active' => 1,
        ),
        1391 => 
        array (
          'id' => '150724',
          'province_id' => '1507',
          'description' => 'San Pedro de Casta',
          'active' => 1,
        ),
        1392 => 
        array (
          'id' => '150725',
          'province_id' => '1507',
          'description' => 'San Pedro de Huancayre',
          'active' => 1,
        ),
        1393 => 
        array (
          'id' => '150726',
          'province_id' => '1507',
          'description' => 'Sangallaya',
          'active' => 1,
        ),
        1394 => 
        array (
          'id' => '150727',
          'province_id' => '1507',
          'description' => 'Santa Cruz de Cocachacra',
          'active' => 1,
        ),
        1395 => 
        array (
          'id' => '150728',
          'province_id' => '1507',
          'description' => 'Santa Eulalia',
          'active' => 1,
        ),
        1396 => 
        array (
          'id' => '150729',
          'province_id' => '1507',
          'description' => 'Santiago de Anchucaya',
          'active' => 1,
        ),
        1397 => 
        array (
          'id' => '150730',
          'province_id' => '1507',
          'description' => 'Santiago de Tuna',
          'active' => 1,
        ),
        1398 => 
        array (
          'id' => '150731',
          'province_id' => '1507',
          'description' => 'Santo Domingo de Los Olleros',
          'active' => 1,
        ),
        1399 => 
        array (
          'id' => '150732',
          'province_id' => '1507',
          'description' => 'Surco',
          'active' => 1,
        ),
        1400 => 
        array (
          'id' => '150801',
          'province_id' => '1508',
          'description' => 'Huacho',
          'active' => 1,
        ),
        1401 => 
        array (
          'id' => '150802',
          'province_id' => '1508',
          'description' => 'Ambar',
          'active' => 1,
        ),
        1402 => 
        array (
          'id' => '150803',
          'province_id' => '1508',
          'description' => 'Caleta de Carquin',
          'active' => 1,
        ),
        1403 => 
        array (
          'id' => '150804',
          'province_id' => '1508',
          'description' => 'Checras',
          'active' => 1,
        ),
        1404 => 
        array (
          'id' => '150805',
          'province_id' => '1508',
          'description' => 'Hualmay',
          'active' => 1,
        ),
        1405 => 
        array (
          'id' => '150806',
          'province_id' => '1508',
          'description' => 'Huaura',
          'active' => 1,
        ),
        1406 => 
        array (
          'id' => '150807',
          'province_id' => '1508',
          'description' => 'Leoncio Prado',
          'active' => 1,
        ),
        1407 => 
        array (
          'id' => '150808',
          'province_id' => '1508',
          'description' => 'Paccho',
          'active' => 1,
        ),
        1408 => 
        array (
          'id' => '150809',
          'province_id' => '1508',
          'description' => 'Santa Leonor',
          'active' => 1,
        ),
        1409 => 
        array (
          'id' => '150810',
          'province_id' => '1508',
          'description' => 'Santa María',
          'active' => 1,
        ),
        1410 => 
        array (
          'id' => '150811',
          'province_id' => '1508',
          'description' => 'Sayan',
          'active' => 1,
        ),
        1411 => 
        array (
          'id' => '150812',
          'province_id' => '1508',
          'description' => 'Vegueta',
          'active' => 1,
        ),
        1412 => 
        array (
          'id' => '150901',
          'province_id' => '1509',
          'description' => 'Oyon',
          'active' => 1,
        ),
        1413 => 
        array (
          'id' => '150902',
          'province_id' => '1509',
          'description' => 'Andajes',
          'active' => 1,
        ),
        1414 => 
        array (
          'id' => '150903',
          'province_id' => '1509',
          'description' => 'Caujul',
          'active' => 1,
        ),
        1415 => 
        array (
          'id' => '150904',
          'province_id' => '1509',
          'description' => 'Cochamarca',
          'active' => 1,
        ),
        1416 => 
        array (
          'id' => '150905',
          'province_id' => '1509',
          'description' => 'Navan',
          'active' => 1,
        ),
        1417 => 
        array (
          'id' => '150906',
          'province_id' => '1509',
          'description' => 'Pachangara',
          'active' => 1,
        ),
        1418 => 
        array (
          'id' => '151001',
          'province_id' => '1510',
          'description' => 'Yauyos',
          'active' => 1,
        ),
        1419 => 
        array (
          'id' => '151002',
          'province_id' => '1510',
          'description' => 'Alis',
          'active' => 1,
        ),
        1420 => 
        array (
          'id' => '151003',
          'province_id' => '1510',
          'description' => 'Allauca',
          'active' => 1,
        ),
        1421 => 
        array (
          'id' => '151004',
          'province_id' => '1510',
          'description' => 'Ayaviri',
          'active' => 1,
        ),
        1422 => 
        array (
          'id' => '151005',
          'province_id' => '1510',
          'description' => 'Azángaro',
          'active' => 1,
        ),
        1423 => 
        array (
          'id' => '151006',
          'province_id' => '1510',
          'description' => 'Cacra',
          'active' => 1,
        ),
        1424 => 
        array (
          'id' => '151007',
          'province_id' => '1510',
          'description' => 'Carania',
          'active' => 1,
        ),
        1425 => 
        array (
          'id' => '151008',
          'province_id' => '1510',
          'description' => 'Catahuasi',
          'active' => 1,
        ),
        1426 => 
        array (
          'id' => '151009',
          'province_id' => '1510',
          'description' => 'Chocos',
          'active' => 1,
        ),
        1427 => 
        array (
          'id' => '151010',
          'province_id' => '1510',
          'description' => 'Cochas',
          'active' => 1,
        ),
        1428 => 
        array (
          'id' => '151011',
          'province_id' => '1510',
          'description' => 'Colonia',
          'active' => 1,
        ),
        1429 => 
        array (
          'id' => '151012',
          'province_id' => '1510',
          'description' => 'Hongos',
          'active' => 1,
        ),
        1430 => 
        array (
          'id' => '151013',
          'province_id' => '1510',
          'description' => 'Huampara',
          'active' => 1,
        ),
        1431 => 
        array (
          'id' => '151014',
          'province_id' => '1510',
          'description' => 'Huancaya',
          'active' => 1,
        ),
        1432 => 
        array (
          'id' => '151015',
          'province_id' => '1510',
          'description' => 'Huangascar',
          'active' => 1,
        ),
        1433 => 
        array (
          'id' => '151016',
          'province_id' => '1510',
          'description' => 'Huantan',
          'active' => 1,
        ),
        1434 => 
        array (
          'id' => '151017',
          'province_id' => '1510',
          'description' => 'Huañec',
          'active' => 1,
        ),
        1435 => 
        array (
          'id' => '151018',
          'province_id' => '1510',
          'description' => 'Laraos',
          'active' => 1,
        ),
        1436 => 
        array (
          'id' => '151019',
          'province_id' => '1510',
          'description' => 'Lincha',
          'active' => 1,
        ),
        1437 => 
        array (
          'id' => '151020',
          'province_id' => '1510',
          'description' => 'Madean',
          'active' => 1,
        ),
        1438 => 
        array (
          'id' => '151021',
          'province_id' => '1510',
          'description' => 'Miraflores',
          'active' => 1,
        ),
        1439 => 
        array (
          'id' => '151022',
          'province_id' => '1510',
          'description' => 'Omas',
          'active' => 1,
        ),
        1440 => 
        array (
          'id' => '151023',
          'province_id' => '1510',
          'description' => 'Putinza',
          'active' => 1,
        ),
        1441 => 
        array (
          'id' => '151024',
          'province_id' => '1510',
          'description' => 'Quinches',
          'active' => 1,
        ),
        1442 => 
        array (
          'id' => '151025',
          'province_id' => '1510',
          'description' => 'Quinocay',
          'active' => 1,
        ),
        1443 => 
        array (
          'id' => '151026',
          'province_id' => '1510',
          'description' => 'San Joaquín',
          'active' => 1,
        ),
        1444 => 
        array (
          'id' => '151027',
          'province_id' => '1510',
          'description' => 'San Pedro de Pilas',
          'active' => 1,
        ),
        1445 => 
        array (
          'id' => '151028',
          'province_id' => '1510',
          'description' => 'Tanta',
          'active' => 1,
        ),
        1446 => 
        array (
          'id' => '151029',
          'province_id' => '1510',
          'description' => 'Tauripampa',
          'active' => 1,
        ),
        1447 => 
        array (
          'id' => '151030',
          'province_id' => '1510',
          'description' => 'Tomas',
          'active' => 1,
        ),
        1448 => 
        array (
          'id' => '151031',
          'province_id' => '1510',
          'description' => 'Tupe',
          'active' => 1,
        ),
        1449 => 
        array (
          'id' => '151032',
          'province_id' => '1510',
          'description' => 'Viñac',
          'active' => 1,
        ),
        1450 => 
        array (
          'id' => '151033',
          'province_id' => '1510',
          'description' => 'Vitis',
          'active' => 1,
        ),
        1451 => 
        array (
          'id' => '160101',
          'province_id' => '1601',
          'description' => 'Iquitos',
          'active' => 1,
        ),
        1452 => 
        array (
          'id' => '160102',
          'province_id' => '1601',
          'description' => 'Alto Nanay',
          'active' => 1,
        ),
        1453 => 
        array (
          'id' => '160103',
          'province_id' => '1601',
          'description' => 'Fernando Lores',
          'active' => 1,
        ),
        1454 => 
        array (
          'id' => '160104',
          'province_id' => '1601',
          'description' => 'Indiana',
          'active' => 1,
        ),
        1455 => 
        array (
          'id' => '160105',
          'province_id' => '1601',
          'description' => 'Las Amazonas',
          'active' => 1,
        ),
        1456 => 
        array (
          'id' => '160106',
          'province_id' => '1601',
          'description' => 'Mazan',
          'active' => 1,
        ),
        1457 => 
        array (
          'id' => '160107',
          'province_id' => '1601',
          'description' => 'Napo',
          'active' => 1,
        ),
        1458 => 
        array (
          'id' => '160108',
          'province_id' => '1601',
          'description' => 'Punchana',
          'active' => 1,
        ),
        1459 => 
        array (
          'id' => '160110',
          'province_id' => '1601',
          'description' => 'Torres Causana',
          'active' => 1,
        ),
        1460 => 
        array (
          'id' => '160112',
          'province_id' => '1601',
          'description' => 'Belén',
          'active' => 1,
        ),
        1461 => 
        array (
          'id' => '160113',
          'province_id' => '1601',
          'description' => 'San Juan Bautista',
          'active' => 1,
        ),
        1462 => 
        array (
          'id' => '160201',
          'province_id' => '1602',
          'description' => 'Yurimaguas',
          'active' => 1,
        ),
        1463 => 
        array (
          'id' => '160202',
          'province_id' => '1602',
          'description' => 'Balsapuerto',
          'active' => 1,
        ),
        1464 => 
        array (
          'id' => '160205',
          'province_id' => '1602',
          'description' => 'Jeberos',
          'active' => 1,
        ),
        1465 => 
        array (
          'id' => '160206',
          'province_id' => '1602',
          'description' => 'Lagunas',
          'active' => 1,
        ),
        1466 => 
        array (
          'id' => '160210',
          'province_id' => '1602',
          'description' => 'Santa Cruz',
          'active' => 1,
        ),
        1467 => 
        array (
          'id' => '160211',
          'province_id' => '1602',
          'description' => 'Teniente Cesar López Rojas',
          'active' => 1,
        ),
        1468 => 
        array (
          'id' => '160301',
          'province_id' => '1603',
          'description' => 'Nauta',
          'active' => 1,
        ),
        1469 => 
        array (
          'id' => '160302',
          'province_id' => '1603',
          'description' => 'Parinari',
          'active' => 1,
        ),
        1470 => 
        array (
          'id' => '160303',
          'province_id' => '1603',
          'description' => 'Tigre',
          'active' => 1,
        ),
        1471 => 
        array (
          'id' => '160304',
          'province_id' => '1603',
          'description' => 'Trompeteros',
          'active' => 1,
        ),
        1472 => 
        array (
          'id' => '160305',
          'province_id' => '1603',
          'description' => 'Urarinas',
          'active' => 1,
        ),
        1473 => 
        array (
          'id' => '160401',
          'province_id' => '1604',
          'description' => 'Ramón Castilla',
          'active' => 1,
        ),
        1474 => 
        array (
          'id' => '160402',
          'province_id' => '1604',
          'description' => 'Pebas',
          'active' => 1,
        ),
        1475 => 
        array (
          'id' => '160403',
          'province_id' => '1604',
          'description' => 'Yavari',
          'active' => 1,
        ),
        1476 => 
        array (
          'id' => '160404',
          'province_id' => '1604',
          'description' => 'San Pablo',
          'active' => 1,
        ),
        1477 => 
        array (
          'id' => '160501',
          'province_id' => '1605',
          'description' => 'Requena',
          'active' => 1,
        ),
        1478 => 
        array (
          'id' => '160502',
          'province_id' => '1605',
          'description' => 'Alto Tapiche',
          'active' => 1,
        ),
        1479 => 
        array (
          'id' => '160503',
          'province_id' => '1605',
          'description' => 'Capelo',
          'active' => 1,
        ),
        1480 => 
        array (
          'id' => '160504',
          'province_id' => '1605',
          'description' => 'Emilio San Martín',
          'active' => 1,
        ),
        1481 => 
        array (
          'id' => '160505',
          'province_id' => '1605',
          'description' => 'Maquia',
          'active' => 1,
        ),
        1482 => 
        array (
          'id' => '160506',
          'province_id' => '1605',
          'description' => 'Puinahua',
          'active' => 1,
        ),
        1483 => 
        array (
          'id' => '160507',
          'province_id' => '1605',
          'description' => 'Saquena',
          'active' => 1,
        ),
        1484 => 
        array (
          'id' => '160508',
          'province_id' => '1605',
          'description' => 'Soplin',
          'active' => 1,
        ),
        1485 => 
        array (
          'id' => '160509',
          'province_id' => '1605',
          'description' => 'Tapiche',
          'active' => 1,
        ),
        1486 => 
        array (
          'id' => '160510',
          'province_id' => '1605',
          'description' => 'Jenaro Herrera',
          'active' => 1,
        ),
        1487 => 
        array (
          'id' => '160511',
          'province_id' => '1605',
          'description' => 'Yaquerana',
          'active' => 1,
        ),
        1488 => 
        array (
          'id' => '160601',
          'province_id' => '1606',
          'description' => 'Contamana',
          'active' => 1,
        ),
        1489 => 
        array (
          'id' => '160602',
          'province_id' => '1606',
          'description' => 'Inahuaya',
          'active' => 1,
        ),
        1490 => 
        array (
          'id' => '160603',
          'province_id' => '1606',
          'description' => 'Padre Márquez',
          'active' => 1,
        ),
        1491 => 
        array (
          'id' => '160604',
          'province_id' => '1606',
          'description' => 'Pampa Hermosa',
          'active' => 1,
        ),
        1492 => 
        array (
          'id' => '160605',
          'province_id' => '1606',
          'description' => 'Sarayacu',
          'active' => 1,
        ),
        1493 => 
        array (
          'id' => '160606',
          'province_id' => '1606',
          'description' => 'Vargas Guerra',
          'active' => 1,
        ),
        1494 => 
        array (
          'id' => '160701',
          'province_id' => '1607',
          'description' => 'Barranca',
          'active' => 1,
        ),
        1495 => 
        array (
          'id' => '160702',
          'province_id' => '1607',
          'description' => 'Cahuapanas',
          'active' => 1,
        ),
        1496 => 
        array (
          'id' => '160703',
          'province_id' => '1607',
          'description' => 'Manseriche',
          'active' => 1,
        ),
        1497 => 
        array (
          'id' => '160704',
          'province_id' => '1607',
          'description' => 'Morona',
          'active' => 1,
        ),
        1498 => 
        array (
          'id' => '160705',
          'province_id' => '1607',
          'description' => 'Pastaza',
          'active' => 1,
        ),
        1499 => 
        array (
          'id' => '160706',
          'province_id' => '1607',
          'description' => 'Andoas',
          'active' => 1,
        ),
        1500 => 
        array (
          'id' => '160801',
          'province_id' => '1608',
          'description' => 'Putumayo',
          'active' => 1,
        ),
        1501 => 
        array (
          'id' => '160802',
          'province_id' => '1608',
          'description' => 'Rosa Panduro',
          'active' => 1,
        ),
        1502 => 
        array (
          'id' => '160803',
          'province_id' => '1608',
          'description' => 'Teniente Manuel Clavero',
          'active' => 1,
        ),
        1503 => 
        array (
          'id' => '160804',
          'province_id' => '1608',
          'description' => 'Yaguas',
          'active' => 1,
        ),
        1504 => 
        array (
          'id' => '170101',
          'province_id' => '1701',
          'description' => 'Tambopata',
          'active' => 1,
        ),
        1505 => 
        array (
          'id' => '170102',
          'province_id' => '1701',
          'description' => 'Inambari',
          'active' => 1,
        ),
        1506 => 
        array (
          'id' => '170103',
          'province_id' => '1701',
          'description' => 'Las Piedras',
          'active' => 1,
        ),
        1507 => 
        array (
          'id' => '170104',
          'province_id' => '1701',
          'description' => 'Laberinto',
          'active' => 1,
        ),
        1508 => 
        array (
          'id' => '170201',
          'province_id' => '1702',
          'description' => 'Manu',
          'active' => 1,
        ),
        1509 => 
        array (
          'id' => '170202',
          'province_id' => '1702',
          'description' => 'Fitzcarrald',
          'active' => 1,
        ),
        1510 => 
        array (
          'id' => '170203',
          'province_id' => '1702',
          'description' => 'Madre de Dios',
          'active' => 1,
        ),
        1511 => 
        array (
          'id' => '170204',
          'province_id' => '1702',
          'description' => 'Huepetuhe',
          'active' => 1,
        ),
        1512 => 
        array (
          'id' => '170301',
          'province_id' => '1703',
          'description' => 'Iñapari',
          'active' => 1,
        ),
        1513 => 
        array (
          'id' => '170302',
          'province_id' => '1703',
          'description' => 'Iberia',
          'active' => 1,
        ),
        1514 => 
        array (
          'id' => '170303',
          'province_id' => '1703',
          'description' => 'Tahuamanu',
          'active' => 1,
        ),
        1515 => 
        array (
          'id' => '180101',
          'province_id' => '1801',
          'description' => 'Moquegua',
          'active' => 1,
        ),
        1516 => 
        array (
          'id' => '180102',
          'province_id' => '1801',
          'description' => 'Carumas',
          'active' => 1,
        ),
        1517 => 
        array (
          'id' => '180103',
          'province_id' => '1801',
          'description' => 'Cuchumbaya',
          'active' => 1,
        ),
        1518 => 
        array (
          'id' => '180104',
          'province_id' => '1801',
          'description' => 'Samegua',
          'active' => 1,
        ),
        1519 => 
        array (
          'id' => '180105',
          'province_id' => '1801',
          'description' => 'San Cristóbal',
          'active' => 1,
        ),
        1520 => 
        array (
          'id' => '180106',
          'province_id' => '1801',
          'description' => 'Torata',
          'active' => 1,
        ),
        1521 => 
        array (
          'id' => '180107',
          'province_id' => '1801',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        1522 => 
        array (
          'id' => '180201',
          'province_id' => '1802',
          'description' => 'Omate',
          'active' => 1,
        ),
        1523 => 
        array (
          'id' => '180202',
          'province_id' => '1802',
          'description' => 'Chojata',
          'active' => 1,
        ),
        1524 => 
        array (
          'id' => '180203',
          'province_id' => '1802',
          'description' => 'Coalaque',
          'active' => 1,
        ),
        1525 => 
        array (
          'id' => '180204',
          'province_id' => '1802',
          'description' => 'Ichuña',
          'active' => 1,
        ),
        1526 => 
        array (
          'id' => '180205',
          'province_id' => '1802',
          'description' => 'La Capilla',
          'active' => 1,
        ),
        1527 => 
        array (
          'id' => '180206',
          'province_id' => '1802',
          'description' => 'Lloque',
          'active' => 1,
        ),
        1528 => 
        array (
          'id' => '180207',
          'province_id' => '1802',
          'description' => 'Matalaque',
          'active' => 1,
        ),
        1529 => 
        array (
          'id' => '180208',
          'province_id' => '1802',
          'description' => 'Puquina',
          'active' => 1,
        ),
        1530 => 
        array (
          'id' => '180209',
          'province_id' => '1802',
          'description' => 'Quinistaquillas',
          'active' => 1,
        ),
        1531 => 
        array (
          'id' => '180210',
          'province_id' => '1802',
          'description' => 'Ubinas',
          'active' => 1,
        ),
        1532 => 
        array (
          'id' => '180211',
          'province_id' => '1802',
          'description' => 'Yunga',
          'active' => 1,
        ),
        1533 => 
        array (
          'id' => '180301',
          'province_id' => '1803',
          'description' => 'Ilo',
          'active' => 1,
        ),
        1534 => 
        array (
          'id' => '180302',
          'province_id' => '1803',
          'description' => 'El Algarrobal',
          'active' => 1,
        ),
        1535 => 
        array (
          'id' => '180303',
          'province_id' => '1803',
          'description' => 'Pacocha',
          'active' => 1,
        ),
        1536 => 
        array (
          'id' => '190101',
          'province_id' => '1901',
          'description' => 'Chaupimarca',
          'active' => 1,
        ),
        1537 => 
        array (
          'id' => '190102',
          'province_id' => '1901',
          'description' => 'Huachon',
          'active' => 1,
        ),
        1538 => 
        array (
          'id' => '190103',
          'province_id' => '1901',
          'description' => 'Huariaca',
          'active' => 1,
        ),
        1539 => 
        array (
          'id' => '190104',
          'province_id' => '1901',
          'description' => 'Huayllay',
          'active' => 1,
        ),
        1540 => 
        array (
          'id' => '190105',
          'province_id' => '1901',
          'description' => 'Ninacaca',
          'active' => 1,
        ),
        1541 => 
        array (
          'id' => '190106',
          'province_id' => '1901',
          'description' => 'Pallanchacra',
          'active' => 1,
        ),
        1542 => 
        array (
          'id' => '190107',
          'province_id' => '1901',
          'description' => 'Paucartambo',
          'active' => 1,
        ),
        1543 => 
        array (
          'id' => '190108',
          'province_id' => '1901',
          'description' => 'San Francisco de Asís de Yarusyacan',
          'active' => 1,
        ),
        1544 => 
        array (
          'id' => '190109',
          'province_id' => '1901',
          'description' => 'Simon Bolívar',
          'active' => 1,
        ),
        1545 => 
        array (
          'id' => '190110',
          'province_id' => '1901',
          'description' => 'Ticlacayan',
          'active' => 1,
        ),
        1546 => 
        array (
          'id' => '190111',
          'province_id' => '1901',
          'description' => 'Tinyahuarco',
          'active' => 1,
        ),
        1547 => 
        array (
          'id' => '190112',
          'province_id' => '1901',
          'description' => 'Vicco',
          'active' => 1,
        ),
        1548 => 
        array (
          'id' => '190113',
          'province_id' => '1901',
          'description' => 'Yanacancha',
          'active' => 1,
        ),
        1549 => 
        array (
          'id' => '190201',
          'province_id' => '1902',
          'description' => 'Yanahuanca',
          'active' => 1,
        ),
        1550 => 
        array (
          'id' => '190202',
          'province_id' => '1902',
          'description' => 'Chacayan',
          'active' => 1,
        ),
        1551 => 
        array (
          'id' => '190203',
          'province_id' => '1902',
          'description' => 'Goyllarisquizga',
          'active' => 1,
        ),
        1552 => 
        array (
          'id' => '190204',
          'province_id' => '1902',
          'description' => 'Paucar',
          'active' => 1,
        ),
        1553 => 
        array (
          'id' => '190205',
          'province_id' => '1902',
          'description' => 'San Pedro de Pillao',
          'active' => 1,
        ),
        1554 => 
        array (
          'id' => '190206',
          'province_id' => '1902',
          'description' => 'Santa Ana de Tusi',
          'active' => 1,
        ),
        1555 => 
        array (
          'id' => '190207',
          'province_id' => '1902',
          'description' => 'Tapuc',
          'active' => 1,
        ),
        1556 => 
        array (
          'id' => '190208',
          'province_id' => '1902',
          'description' => 'Vilcabamba',
          'active' => 1,
        ),
        1557 => 
        array (
          'id' => '190301',
          'province_id' => '1903',
          'description' => 'Oxapampa',
          'active' => 1,
        ),
        1558 => 
        array (
          'id' => '190302',
          'province_id' => '1903',
          'description' => 'Chontabamba',
          'active' => 1,
        ),
        1559 => 
        array (
          'id' => '190303',
          'province_id' => '1903',
          'description' => 'Huancabamba',
          'active' => 1,
        ),
        1560 => 
        array (
          'id' => '190304',
          'province_id' => '1903',
          'description' => 'Palcazu',
          'active' => 1,
        ),
        1561 => 
        array (
          'id' => '190305',
          'province_id' => '1903',
          'description' => 'Pozuzo',
          'active' => 1,
        ),
        1562 => 
        array (
          'id' => '190306',
          'province_id' => '1903',
          'description' => 'Puerto Bermúdez',
          'active' => 1,
        ),
        1563 => 
        array (
          'id' => '190307',
          'province_id' => '1903',
          'description' => 'Villa Rica',
          'active' => 1,
        ),
        1564 => 
        array (
          'id' => '190308',
          'province_id' => '1903',
          'description' => 'Constitución',
          'active' => 1,
        ),
        1565 => 
        array (
          'id' => '200101',
          'province_id' => '2001',
          'description' => 'Piura',
          'active' => 1,
        ),
        1566 => 
        array (
          'id' => '200104',
          'province_id' => '2001',
          'description' => 'Castilla',
          'active' => 1,
        ),
        1567 => 
        array (
          'id' => '200105',
          'province_id' => '2001',
          'description' => 'Catacaos',
          'active' => 1,
        ),
        1568 => 
        array (
          'id' => '200107',
          'province_id' => '2001',
          'description' => 'Cura Mori',
          'active' => 1,
        ),
        1569 => 
        array (
          'id' => '200108',
          'province_id' => '2001',
          'description' => 'El Tallan',
          'active' => 1,
        ),
        1570 => 
        array (
          'id' => '200109',
          'province_id' => '2001',
          'description' => 'La Arena',
          'active' => 1,
        ),
        1571 => 
        array (
          'id' => '200110',
          'province_id' => '2001',
          'description' => 'La Unión',
          'active' => 1,
        ),
        1572 => 
        array (
          'id' => '200111',
          'province_id' => '2001',
          'description' => 'Las Lomas',
          'active' => 1,
        ),
        1573 => 
        array (
          'id' => '200114',
          'province_id' => '2001',
          'description' => 'Tambo Grande',
          'active' => 1,
        ),
        1574 => 
        array (
          'id' => '200115',
          'province_id' => '2001',
          'description' => 'Veintiseis de Octubre',
          'active' => 1,
        ),
        1575 => 
        array (
          'id' => '200201',
          'province_id' => '2002',
          'description' => 'Ayabaca',
          'active' => 1,
        ),
        1576 => 
        array (
          'id' => '200202',
          'province_id' => '2002',
          'description' => 'Frias',
          'active' => 1,
        ),
        1577 => 
        array (
          'id' => '200203',
          'province_id' => '2002',
          'description' => 'Jilili',
          'active' => 1,
        ),
        1578 => 
        array (
          'id' => '200204',
          'province_id' => '2002',
          'description' => 'Lagunas',
          'active' => 1,
        ),
        1579 => 
        array (
          'id' => '200205',
          'province_id' => '2002',
          'description' => 'Montero',
          'active' => 1,
        ),
        1580 => 
        array (
          'id' => '200206',
          'province_id' => '2002',
          'description' => 'Pacaipampa',
          'active' => 1,
        ),
        1581 => 
        array (
          'id' => '200207',
          'province_id' => '2002',
          'description' => 'Paimas',
          'active' => 1,
        ),
        1582 => 
        array (
          'id' => '200208',
          'province_id' => '2002',
          'description' => 'Sapillica',
          'active' => 1,
        ),
        1583 => 
        array (
          'id' => '200209',
          'province_id' => '2002',
          'description' => 'Sicchez',
          'active' => 1,
        ),
        1584 => 
        array (
          'id' => '200210',
          'province_id' => '2002',
          'description' => 'Suyo',
          'active' => 1,
        ),
        1585 => 
        array (
          'id' => '200301',
          'province_id' => '2003',
          'description' => 'Huancabamba',
          'active' => 1,
        ),
        1586 => 
        array (
          'id' => '200302',
          'province_id' => '2003',
          'description' => 'Canchaque',
          'active' => 1,
        ),
        1587 => 
        array (
          'id' => '200303',
          'province_id' => '2003',
          'description' => 'El Carmen de la Frontera',
          'active' => 1,
        ),
        1588 => 
        array (
          'id' => '200304',
          'province_id' => '2003',
          'description' => 'Huarmaca',
          'active' => 1,
        ),
        1589 => 
        array (
          'id' => '200305',
          'province_id' => '2003',
          'description' => 'Lalaquiz',
          'active' => 1,
        ),
        1590 => 
        array (
          'id' => '200306',
          'province_id' => '2003',
          'description' => 'San Miguel de El Faique',
          'active' => 1,
        ),
        1591 => 
        array (
          'id' => '200307',
          'province_id' => '2003',
          'description' => 'Sondor',
          'active' => 1,
        ),
        1592 => 
        array (
          'id' => '200308',
          'province_id' => '2003',
          'description' => 'Sondorillo',
          'active' => 1,
        ),
        1593 => 
        array (
          'id' => '200401',
          'province_id' => '2004',
          'description' => 'Chulucanas',
          'active' => 1,
        ),
        1594 => 
        array (
          'id' => '200402',
          'province_id' => '2004',
          'description' => 'Buenos Aires',
          'active' => 1,
        ),
        1595 => 
        array (
          'id' => '200403',
          'province_id' => '2004',
          'description' => 'Chalaco',
          'active' => 1,
        ),
        1596 => 
        array (
          'id' => '200404',
          'province_id' => '2004',
          'description' => 'La Matanza',
          'active' => 1,
        ),
        1597 => 
        array (
          'id' => '200405',
          'province_id' => '2004',
          'description' => 'Morropon',
          'active' => 1,
        ),
        1598 => 
        array (
          'id' => '200406',
          'province_id' => '2004',
          'description' => 'Salitral',
          'active' => 1,
        ),
        1599 => 
        array (
          'id' => '200407',
          'province_id' => '2004',
          'description' => 'San Juan de Bigote',
          'active' => 1,
        ),
        1600 => 
        array (
          'id' => '200408',
          'province_id' => '2004',
          'description' => 'Santa Catalina de Mossa',
          'active' => 1,
        ),
        1601 => 
        array (
          'id' => '200409',
          'province_id' => '2004',
          'description' => 'Santo Domingo',
          'active' => 1,
        ),
        1602 => 
        array (
          'id' => '200410',
          'province_id' => '2004',
          'description' => 'YAMANGO',
          'active' => 1,
        ),
        1603 => 
        array (
          'id' => '200501',
          'province_id' => '2005',
          'description' => 'PAITA',
          'active' => 1,
        ),
        1604 => 
        array (
          'id' => '200502',
          'province_id' => '2005',
          'description' => 'AMOTAPE',
          'active' => 1,
        ),
        1605 => 
        array (
          'id' => '200503',
          'province_id' => '2005',
          'description' => 'ARENAL',
          'active' => 1,
        ),
        1606 => 
        array (
          'id' => '200504',
          'province_id' => '2005',
          'description' => 'COLAN',
          'active' => 1,
        ),
        1607 => 
        array (
          'id' => '200505',
          'province_id' => '2005',
          'description' => 'LA HUACA',
          'active' => 1,
        ),
        1608 => 
        array (
          'id' => '200506',
          'province_id' => '2005',
          'description' => 'Tamarindo',
          'active' => 1,
        ),
        1609 => 
        array (
          'id' => '200507',
          'province_id' => '2005',
          'description' => 'Vichayal',
          'active' => 1,
        ),
        1610 => 
        array (
          'id' => '200601',
          'province_id' => '2006',
          'description' => 'SULLANA',
          'active' => 1,
        ),
        1611 => 
        array (
          'id' => '200602',
          'province_id' => '2006',
          'description' => 'Bellavista',
          'active' => 1,
        ),
        1612 => 
        array (
          'id' => '200603',
          'province_id' => '2006',
          'description' => 'Ignacio Escudero',
          'active' => 1,
        ),
        1613 => 
        array (
          'id' => '200604',
          'province_id' => '2006',
          'description' => 'Lancones',
          'active' => 1,
        ),
        1614 => 
        array (
          'id' => '200605',
          'province_id' => '2006',
          'description' => 'Marcavelica',
          'active' => 1,
        ),
        1615 => 
        array (
          'id' => '200606',
          'province_id' => '2006',
          'description' => 'Miguel Checa',
          'active' => 1,
        ),
        1616 => 
        array (
          'id' => '200607',
          'province_id' => '2006',
          'description' => 'Querecotillo',
          'active' => 1,
        ),
        1617 => 
        array (
          'id' => '200608',
          'province_id' => '2006',
          'description' => 'Salitral',
          'active' => 1,
        ),
        1618 => 
        array (
          'id' => '200701',
          'province_id' => '2007',
          'description' => 'PARIÑAS',
          'active' => 1,
        ),
        1619 => 
        array (
          'id' => '200702',
          'province_id' => '2007',
          'description' => 'EL ALTO',
          'active' => 1,
        ),
        1620 => 
        array (
          'id' => '200703',
          'province_id' => '2007',
          'description' => 'LA BREA',
          'active' => 1,
        ),
        1621 => 
        array (
          'id' => '200704',
          'province_id' => '2007',
          'description' => 'LOBITOS',
          'active' => 1,
        ),
        1622 => 
        array (
          'id' => '200705',
          'province_id' => '2007',
          'description' => 'Los Organos',
          'active' => 1,
        ),
        1623 => 
        array (
          'id' => '200706',
          'province_id' => '2007',
          'description' => 'MANCORA',
          'active' => 1,
        ),
        1624 => 
        array (
          'id' => '200801',
          'province_id' => '2008',
          'description' => 'SECHURA',
          'active' => 1,
        ),
        1625 => 
        array (
          'id' => '200802',
          'province_id' => '2008',
          'description' => 'Bellavista de la Unión',
          'active' => 1,
        ),
        1626 => 
        array (
          'id' => '200803',
          'province_id' => '2008',
          'description' => 'BERNAL',
          'active' => 1,
        ),
        1627 => 
        array (
          'id' => '200804',
          'province_id' => '2008',
          'description' => 'Cristo Nos Valga',
          'active' => 1,
        ),
        1628 => 
        array (
          'id' => '200805',
          'province_id' => '2008',
          'description' => 'Vice',
          'active' => 1,
        ),
        1629 => 
        array (
          'id' => '200806',
          'province_id' => '2008',
          'description' => 'Rinconada Llicuar',
          'active' => 1,
        ),
        1630 => 
        array (
          'id' => '210101',
          'province_id' => '2101',
          'description' => 'Puno',
          'active' => 1,
        ),
        1631 => 
        array (
          'id' => '210102',
          'province_id' => '2101',
          'description' => 'Acora',
          'active' => 1,
        ),
        1632 => 
        array (
          'id' => '210103',
          'province_id' => '2101',
          'description' => 'Amantani',
          'active' => 1,
        ),
        1633 => 
        array (
          'id' => '210104',
          'province_id' => '2101',
          'description' => 'Atuncolla',
          'active' => 1,
        ),
        1634 => 
        array (
          'id' => '210105',
          'province_id' => '2101',
          'description' => 'Capachica',
          'active' => 1,
        ),
        1635 => 
        array (
          'id' => '210106',
          'province_id' => '2101',
          'description' => 'Chucuito',
          'active' => 1,
        ),
        1636 => 
        array (
          'id' => '210107',
          'province_id' => '2101',
          'description' => 'Coata',
          'active' => 1,
        ),
        1637 => 
        array (
          'id' => '210108',
          'province_id' => '2101',
          'description' => 'Huata',
          'active' => 1,
        ),
        1638 => 
        array (
          'id' => '210109',
          'province_id' => '2101',
          'description' => 'Mañazo',
          'active' => 1,
        ),
        1639 => 
        array (
          'id' => '210110',
          'province_id' => '2101',
          'description' => 'Paucarcolla',
          'active' => 1,
        ),
        1640 => 
        array (
          'id' => '210111',
          'province_id' => '2101',
          'description' => 'Pichacani',
          'active' => 1,
        ),
        1641 => 
        array (
          'id' => '210112',
          'province_id' => '2101',
          'description' => 'Plateria',
          'active' => 1,
        ),
        1642 => 
        array (
          'id' => '210113',
          'province_id' => '2101',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        1643 => 
        array (
          'id' => '210114',
          'province_id' => '2101',
          'description' => 'Tiquillaca',
          'active' => 1,
        ),
        1644 => 
        array (
          'id' => '210115',
          'province_id' => '2101',
          'description' => 'Vilque',
          'active' => 1,
        ),
        1645 => 
        array (
          'id' => '210201',
          'province_id' => '2102',
          'description' => 'Azángaro',
          'active' => 1,
        ),
        1646 => 
        array (
          'id' => '210202',
          'province_id' => '2102',
          'description' => 'Achaya',
          'active' => 1,
        ),
        1647 => 
        array (
          'id' => '210203',
          'province_id' => '2102',
          'description' => 'Arapa',
          'active' => 1,
        ),
        1648 => 
        array (
          'id' => '210204',
          'province_id' => '2102',
          'description' => 'Asillo',
          'active' => 1,
        ),
        1649 => 
        array (
          'id' => '210205',
          'province_id' => '2102',
          'description' => 'Caminaca',
          'active' => 1,
        ),
        1650 => 
        array (
          'id' => '210206',
          'province_id' => '2102',
          'description' => 'Chupa',
          'active' => 1,
        ),
        1651 => 
        array (
          'id' => '210207',
          'province_id' => '2102',
          'description' => 'José Domingo Choquehuanca',
          'active' => 1,
        ),
        1652 => 
        array (
          'id' => '210208',
          'province_id' => '2102',
          'description' => 'Muñani',
          'active' => 1,
        ),
        1653 => 
        array (
          'id' => '210209',
          'province_id' => '2102',
          'description' => 'Potoni',
          'active' => 1,
        ),
        1654 => 
        array (
          'id' => '210210',
          'province_id' => '2102',
          'description' => 'Saman',
          'active' => 1,
        ),
        1655 => 
        array (
          'id' => '210211',
          'province_id' => '2102',
          'description' => 'San Anton',
          'active' => 1,
        ),
        1656 => 
        array (
          'id' => '210212',
          'province_id' => '2102',
          'description' => 'San José',
          'active' => 1,
        ),
        1657 => 
        array (
          'id' => '210213',
          'province_id' => '2102',
          'description' => 'San Juan de Salinas',
          'active' => 1,
        ),
        1658 => 
        array (
          'id' => '210214',
          'province_id' => '2102',
          'description' => 'Santiago de Pupuja',
          'active' => 1,
        ),
        1659 => 
        array (
          'id' => '210215',
          'province_id' => '2102',
          'description' => 'Tirapata',
          'active' => 1,
        ),
        1660 => 
        array (
          'id' => '210301',
          'province_id' => '2103',
          'description' => 'Macusani',
          'active' => 1,
        ),
        1661 => 
        array (
          'id' => '210302',
          'province_id' => '2103',
          'description' => 'Ajoyani',
          'active' => 1,
        ),
        1662 => 
        array (
          'id' => '210303',
          'province_id' => '2103',
          'description' => 'Ayapata',
          'active' => 1,
        ),
        1663 => 
        array (
          'id' => '210304',
          'province_id' => '2103',
          'description' => 'Coasa',
          'active' => 1,
        ),
        1664 => 
        array (
          'id' => '210305',
          'province_id' => '2103',
          'description' => 'Corani',
          'active' => 1,
        ),
        1665 => 
        array (
          'id' => '210306',
          'province_id' => '2103',
          'description' => 'Crucero',
          'active' => 1,
        ),
        1666 => 
        array (
          'id' => '210307',
          'province_id' => '2103',
          'description' => 'Ituata',
          'active' => 1,
        ),
        1667 => 
        array (
          'id' => '210308',
          'province_id' => '2103',
          'description' => 'Ollachea',
          'active' => 1,
        ),
        1668 => 
        array (
          'id' => '210309',
          'province_id' => '2103',
          'description' => 'San Gaban',
          'active' => 1,
        ),
        1669 => 
        array (
          'id' => '210310',
          'province_id' => '2103',
          'description' => 'Usicayos',
          'active' => 1,
        ),
        1670 => 
        array (
          'id' => '210401',
          'province_id' => '2104',
          'description' => 'Juli',
          'active' => 1,
        ),
        1671 => 
        array (
          'id' => '210402',
          'province_id' => '2104',
          'description' => 'Desaguadero',
          'active' => 1,
        ),
        1672 => 
        array (
          'id' => '210403',
          'province_id' => '2104',
          'description' => 'Huacullani',
          'active' => 1,
        ),
        1673 => 
        array (
          'id' => '210404',
          'province_id' => '2104',
          'description' => 'Kelluyo',
          'active' => 1,
        ),
        1674 => 
        array (
          'id' => '210405',
          'province_id' => '2104',
          'description' => 'Pisacoma',
          'active' => 1,
        ),
        1675 => 
        array (
          'id' => '210406',
          'province_id' => '2104',
          'description' => 'Pomata',
          'active' => 1,
        ),
        1676 => 
        array (
          'id' => '210407',
          'province_id' => '2104',
          'description' => 'Zepita',
          'active' => 1,
        ),
        1677 => 
        array (
          'id' => '210501',
          'province_id' => '2105',
          'description' => 'Ilave',
          'active' => 1,
        ),
        1678 => 
        array (
          'id' => '210502',
          'province_id' => '2105',
          'description' => 'Capazo',
          'active' => 1,
        ),
        1679 => 
        array (
          'id' => '210503',
          'province_id' => '2105',
          'description' => 'Pilcuyo',
          'active' => 1,
        ),
        1680 => 
        array (
          'id' => '210504',
          'province_id' => '2105',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        1681 => 
        array (
          'id' => '210505',
          'province_id' => '2105',
          'description' => 'Conduriri',
          'active' => 1,
        ),
        1682 => 
        array (
          'id' => '210601',
          'province_id' => '2106',
          'description' => 'Huancane',
          'active' => 1,
        ),
        1683 => 
        array (
          'id' => '210602',
          'province_id' => '2106',
          'description' => 'Cojata',
          'active' => 1,
        ),
        1684 => 
        array (
          'id' => '210603',
          'province_id' => '2106',
          'description' => 'Huatasani',
          'active' => 1,
        ),
        1685 => 
        array (
          'id' => '210604',
          'province_id' => '2106',
          'description' => 'Inchupalla',
          'active' => 1,
        ),
        1686 => 
        array (
          'id' => '210605',
          'province_id' => '2106',
          'description' => 'Pusi',
          'active' => 1,
        ),
        1687 => 
        array (
          'id' => '210606',
          'province_id' => '2106',
          'description' => 'Rosaspata',
          'active' => 1,
        ),
        1688 => 
        array (
          'id' => '210607',
          'province_id' => '2106',
          'description' => 'Taraco',
          'active' => 1,
        ),
        1689 => 
        array (
          'id' => '210608',
          'province_id' => '2106',
          'description' => 'Vilque Chico',
          'active' => 1,
        ),
        1690 => 
        array (
          'id' => '210701',
          'province_id' => '2107',
          'description' => 'Lampa',
          'active' => 1,
        ),
        1691 => 
        array (
          'id' => '210702',
          'province_id' => '2107',
          'description' => 'Cabanilla',
          'active' => 1,
        ),
        1692 => 
        array (
          'id' => '210703',
          'province_id' => '2107',
          'description' => 'Calapuja',
          'active' => 1,
        ),
        1693 => 
        array (
          'id' => '210704',
          'province_id' => '2107',
          'description' => 'Nicasio',
          'active' => 1,
        ),
        1694 => 
        array (
          'id' => '210705',
          'province_id' => '2107',
          'description' => 'Ocuviri',
          'active' => 1,
        ),
        1695 => 
        array (
          'id' => '210706',
          'province_id' => '2107',
          'description' => 'Palca',
          'active' => 1,
        ),
        1696 => 
        array (
          'id' => '210707',
          'province_id' => '2107',
          'description' => 'Paratia',
          'active' => 1,
        ),
        1697 => 
        array (
          'id' => '210708',
          'province_id' => '2107',
          'description' => 'Pucara',
          'active' => 1,
        ),
        1698 => 
        array (
          'id' => '210709',
          'province_id' => '2107',
          'description' => 'Santa Lucia',
          'active' => 1,
        ),
        1699 => 
        array (
          'id' => '210710',
          'province_id' => '2107',
          'description' => 'Vilavila',
          'active' => 1,
        ),
        1700 => 
        array (
          'id' => '210801',
          'province_id' => '2108',
          'description' => 'Ayaviri',
          'active' => 1,
        ),
        1701 => 
        array (
          'id' => '210802',
          'province_id' => '2108',
          'description' => 'Antauta',
          'active' => 1,
        ),
        1702 => 
        array (
          'id' => '210803',
          'province_id' => '2108',
          'description' => 'Cupi',
          'active' => 1,
        ),
        1703 => 
        array (
          'id' => '210804',
          'province_id' => '2108',
          'description' => 'Llalli',
          'active' => 1,
        ),
        1704 => 
        array (
          'id' => '210805',
          'province_id' => '2108',
          'description' => 'Macari',
          'active' => 1,
        ),
        1705 => 
        array (
          'id' => '210806',
          'province_id' => '2108',
          'description' => 'Nuñoa',
          'active' => 1,
        ),
        1706 => 
        array (
          'id' => '210807',
          'province_id' => '2108',
          'description' => 'Orurillo',
          'active' => 1,
        ),
        1707 => 
        array (
          'id' => '210808',
          'province_id' => '2108',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        1708 => 
        array (
          'id' => '210809',
          'province_id' => '2108',
          'description' => 'Umachiri',
          'active' => 1,
        ),
        1709 => 
        array (
          'id' => '210901',
          'province_id' => '2109',
          'description' => 'Moho',
          'active' => 1,
        ),
        1710 => 
        array (
          'id' => '210902',
          'province_id' => '2109',
          'description' => 'Conima',
          'active' => 1,
        ),
        1711 => 
        array (
          'id' => '210903',
          'province_id' => '2109',
          'description' => 'Huayrapata',
          'active' => 1,
        ),
        1712 => 
        array (
          'id' => '210904',
          'province_id' => '2109',
          'description' => 'Tilali',
          'active' => 1,
        ),
        1713 => 
        array (
          'id' => '211001',
          'province_id' => '2110',
          'description' => 'Putina',
          'active' => 1,
        ),
        1714 => 
        array (
          'id' => '211002',
          'province_id' => '2110',
          'description' => 'Ananea',
          'active' => 1,
        ),
        1715 => 
        array (
          'id' => '211003',
          'province_id' => '2110',
          'description' => 'Pedro Vilca Apaza',
          'active' => 1,
        ),
        1716 => 
        array (
          'id' => '211004',
          'province_id' => '2110',
          'description' => 'Quilcapuncu',
          'active' => 1,
        ),
        1717 => 
        array (
          'id' => '211005',
          'province_id' => '2110',
          'description' => 'Sina',
          'active' => 1,
        ),
        1718 => 
        array (
          'id' => '211101',
          'province_id' => '2111',
          'description' => 'Juliaca',
          'active' => 1,
        ),
        1719 => 
        array (
          'id' => '211102',
          'province_id' => '2111',
          'description' => 'Cabana',
          'active' => 1,
        ),
        1720 => 
        array (
          'id' => '211103',
          'province_id' => '2111',
          'description' => 'Cabanillas',
          'active' => 1,
        ),
        1721 => 
        array (
          'id' => '211104',
          'province_id' => '2111',
          'description' => 'Caracoto',
          'active' => 1,
        ),
        1722 => 
        array (
          'id' => '211105',
          'province_id' => '2111',
          'description' => 'San Miguel',
          'active' => 1,
        ),
        1723 => 
        array (
          'id' => '211201',
          'province_id' => '2112',
          'description' => 'Sandia',
          'active' => 1,
        ),
        1724 => 
        array (
          'id' => '211202',
          'province_id' => '2112',
          'description' => 'Cuyocuyo',
          'active' => 1,
        ),
        1725 => 
        array (
          'id' => '211203',
          'province_id' => '2112',
          'description' => 'Limbani',
          'active' => 1,
        ),
        1726 => 
        array (
          'id' => '211204',
          'province_id' => '2112',
          'description' => 'Patambuco',
          'active' => 1,
        ),
        1727 => 
        array (
          'id' => '211205',
          'province_id' => '2112',
          'description' => 'Phara',
          'active' => 1,
        ),
        1728 => 
        array (
          'id' => '211206',
          'province_id' => '2112',
          'description' => 'Quiaca',
          'active' => 1,
        ),
        1729 => 
        array (
          'id' => '211207',
          'province_id' => '2112',
          'description' => 'San Juan del Oro',
          'active' => 1,
        ),
        1730 => 
        array (
          'id' => '211208',
          'province_id' => '2112',
          'description' => 'Yanahuaya',
          'active' => 1,
        ),
        1731 => 
        array (
          'id' => '211209',
          'province_id' => '2112',
          'description' => 'Alto Inambari',
          'active' => 1,
        ),
        1732 => 
        array (
          'id' => '211210',
          'province_id' => '2112',
          'description' => 'San Pedro de Putina Punco',
          'active' => 1,
        ),
        1733 => 
        array (
          'id' => '211301',
          'province_id' => '2113',
          'description' => 'Yunguyo',
          'active' => 1,
        ),
        1734 => 
        array (
          'id' => '211302',
          'province_id' => '2113',
          'description' => 'Anapia',
          'active' => 1,
        ),
        1735 => 
        array (
          'id' => '211303',
          'province_id' => '2113',
          'description' => 'Copani',
          'active' => 1,
        ),
        1736 => 
        array (
          'id' => '211304',
          'province_id' => '2113',
          'description' => 'Cuturapi',
          'active' => 1,
        ),
        1737 => 
        array (
          'id' => '211305',
          'province_id' => '2113',
          'description' => 'Ollaraya',
          'active' => 1,
        ),
        1738 => 
        array (
          'id' => '211306',
          'province_id' => '2113',
          'description' => 'Tinicachi',
          'active' => 1,
        ),
        1739 => 
        array (
          'id' => '211307',
          'province_id' => '2113',
          'description' => 'Unicachi',
          'active' => 1,
        ),
        1740 => 
        array (
          'id' => '220101',
          'province_id' => '2201',
          'description' => 'Moyobamba',
          'active' => 1,
        ),
        1741 => 
        array (
          'id' => '220102',
          'province_id' => '2201',
          'description' => 'Calzada',
          'active' => 1,
        ),
        1742 => 
        array (
          'id' => '220103',
          'province_id' => '2201',
          'description' => 'Habana',
          'active' => 1,
        ),
        1743 => 
        array (
          'id' => '220104',
          'province_id' => '2201',
          'description' => 'Jepelacio',
          'active' => 1,
        ),
        1744 => 
        array (
          'id' => '220105',
          'province_id' => '2201',
          'description' => 'Soritor',
          'active' => 1,
        ),
        1745 => 
        array (
          'id' => '220106',
          'province_id' => '2201',
          'description' => 'Yantalo',
          'active' => 1,
        ),
        1746 => 
        array (
          'id' => '220201',
          'province_id' => '2202',
          'description' => 'Bellavista',
          'active' => 1,
        ),
        1747 => 
        array (
          'id' => '220202',
          'province_id' => '2202',
          'description' => 'Alto Biavo',
          'active' => 1,
        ),
        1748 => 
        array (
          'id' => '220203',
          'province_id' => '2202',
          'description' => 'Bajo Biavo',
          'active' => 1,
        ),
        1749 => 
        array (
          'id' => '220204',
          'province_id' => '2202',
          'description' => 'Huallaga',
          'active' => 1,
        ),
        1750 => 
        array (
          'id' => '220205',
          'province_id' => '2202',
          'description' => 'San Pablo',
          'active' => 1,
        ),
        1751 => 
        array (
          'id' => '220206',
          'province_id' => '2202',
          'description' => 'San Rafael',
          'active' => 1,
        ),
        1752 => 
        array (
          'id' => '220301',
          'province_id' => '2203',
          'description' => 'San José de Sisa',
          'active' => 1,
        ),
        1753 => 
        array (
          'id' => '220302',
          'province_id' => '2203',
          'description' => 'Agua Blanca',
          'active' => 1,
        ),
        1754 => 
        array (
          'id' => '220303',
          'province_id' => '2203',
          'description' => 'San Martín',
          'active' => 1,
        ),
        1755 => 
        array (
          'id' => '220304',
          'province_id' => '2203',
          'description' => 'Santa Rosa',
          'active' => 1,
        ),
        1756 => 
        array (
          'id' => '220305',
          'province_id' => '2203',
          'description' => 'Shatoja',
          'active' => 1,
        ),
        1757 => 
        array (
          'id' => '220401',
          'province_id' => '2204',
          'description' => 'Saposoa',
          'active' => 1,
        ),
        1758 => 
        array (
          'id' => '220402',
          'province_id' => '2204',
          'description' => 'Alto Saposoa',
          'active' => 1,
        ),
        1759 => 
        array (
          'id' => '220403',
          'province_id' => '2204',
          'description' => 'El Eslabón',
          'active' => 1,
        ),
        1760 => 
        array (
          'id' => '220404',
          'province_id' => '2204',
          'description' => 'Piscoyacu',
          'active' => 1,
        ),
        1761 => 
        array (
          'id' => '220405',
          'province_id' => '2204',
          'description' => 'Sacanche',
          'active' => 1,
        ),
        1762 => 
        array (
          'id' => '220406',
          'province_id' => '2204',
          'description' => 'Tingo de Saposoa',
          'active' => 1,
        ),
        1763 => 
        array (
          'id' => '220501',
          'province_id' => '2205',
          'description' => 'Lamas',
          'active' => 1,
        ),
        1764 => 
        array (
          'id' => '220502',
          'province_id' => '2205',
          'description' => 'Alonso de Alvarado',
          'active' => 1,
        ),
        1765 => 
        array (
          'id' => '220503',
          'province_id' => '2205',
          'description' => 'Barranquita',
          'active' => 1,
        ),
        1766 => 
        array (
          'id' => '220504',
          'province_id' => '2205',
          'description' => 'Caynarachi',
          'active' => 1,
        ),
        1767 => 
        array (
          'id' => '220505',
          'province_id' => '2205',
          'description' => 'Cuñumbuqui',
          'active' => 1,
        ),
        1768 => 
        array (
          'id' => '220506',
          'province_id' => '2205',
          'description' => 'Pinto Recodo',
          'active' => 1,
        ),
        1769 => 
        array (
          'id' => '220507',
          'province_id' => '2205',
          'description' => 'Rumisapa',
          'active' => 1,
        ),
        1770 => 
        array (
          'id' => '220508',
          'province_id' => '2205',
          'description' => 'San Roque de Cumbaza',
          'active' => 1,
        ),
        1771 => 
        array (
          'id' => '220509',
          'province_id' => '2205',
          'description' => 'Shanao',
          'active' => 1,
        ),
        1772 => 
        array (
          'id' => '220510',
          'province_id' => '2205',
          'description' => 'Tabalosos',
          'active' => 1,
        ),
        1773 => 
        array (
          'id' => '220511',
          'province_id' => '2205',
          'description' => 'Zapatero',
          'active' => 1,
        ),
        1774 => 
        array (
          'id' => '220601',
          'province_id' => '2206',
          'description' => 'Juanjuí',
          'active' => 1,
        ),
        1775 => 
        array (
          'id' => '220602',
          'province_id' => '2206',
          'description' => 'Campanilla',
          'active' => 1,
        ),
        1776 => 
        array (
          'id' => '220603',
          'province_id' => '2206',
          'description' => 'Huicungo',
          'active' => 1,
        ),
        1777 => 
        array (
          'id' => '220604',
          'province_id' => '2206',
          'description' => 'Pachiza',
          'active' => 1,
        ),
        1778 => 
        array (
          'id' => '220605',
          'province_id' => '2206',
          'description' => 'Pajarillo',
          'active' => 1,
        ),
        1779 => 
        array (
          'id' => '220701',
          'province_id' => '2207',
          'description' => 'Picota',
          'active' => 1,
        ),
        1780 => 
        array (
          'id' => '220702',
          'province_id' => '2207',
          'description' => 'Buenos Aires',
          'active' => 1,
        ),
        1781 => 
        array (
          'id' => '220703',
          'province_id' => '2207',
          'description' => 'Caspisapa',
          'active' => 1,
        ),
        1782 => 
        array (
          'id' => '220704',
          'province_id' => '2207',
          'description' => 'Pilluana',
          'active' => 1,
        ),
        1783 => 
        array (
          'id' => '220705',
          'province_id' => '2207',
          'description' => 'Pucacaca',
          'active' => 1,
        ),
        1784 => 
        array (
          'id' => '220706',
          'province_id' => '2207',
          'description' => 'San Cristóbal',
          'active' => 1,
        ),
        1785 => 
        array (
          'id' => '220707',
          'province_id' => '2207',
          'description' => 'San Hilarión',
          'active' => 1,
        ),
        1786 => 
        array (
          'id' => '220708',
          'province_id' => '2207',
          'description' => 'Shamboyacu',
          'active' => 1,
        ),
        1787 => 
        array (
          'id' => '220709',
          'province_id' => '2207',
          'description' => 'Tingo de Ponasa',
          'active' => 1,
        ),
        1788 => 
        array (
          'id' => '220710',
          'province_id' => '2207',
          'description' => 'Tres Unidos',
          'active' => 1,
        ),
        1789 => 
        array (
          'id' => '220801',
          'province_id' => '2208',
          'description' => 'Rioja',
          'active' => 1,
        ),
        1790 => 
        array (
          'id' => '220802',
          'province_id' => '2208',
          'description' => 'Awajun',
          'active' => 1,
        ),
        1791 => 
        array (
          'id' => '220803',
          'province_id' => '2208',
          'description' => 'Elías Soplin Vargas',
          'active' => 1,
        ),
        1792 => 
        array (
          'id' => '220804',
          'province_id' => '2208',
          'description' => 'Nueva Cajamarca',
          'active' => 1,
        ),
        1793 => 
        array (
          'id' => '220805',
          'province_id' => '2208',
          'description' => 'Pardo Miguel',
          'active' => 1,
        ),
        1794 => 
        array (
          'id' => '220806',
          'province_id' => '2208',
          'description' => 'Posic',
          'active' => 1,
        ),
        1795 => 
        array (
          'id' => '220807',
          'province_id' => '2208',
          'description' => 'San Fernando',
          'active' => 1,
        ),
        1796 => 
        array (
          'id' => '220808',
          'province_id' => '2208',
          'description' => 'Yorongos',
          'active' => 1,
        ),
        1797 => 
        array (
          'id' => '220809',
          'province_id' => '2208',
          'description' => 'Yuracyacu',
          'active' => 1,
        ),
        1798 => 
        array (
          'id' => '220901',
          'province_id' => '2209',
          'description' => 'Tarapoto',
          'active' => 1,
        ),
        1799 => 
        array (
          'id' => '220902',
          'province_id' => '2209',
          'description' => 'Alberto Leveau',
          'active' => 1,
        ),
        1800 => 
        array (
          'id' => '220903',
          'province_id' => '2209',
          'description' => 'Cacatachi',
          'active' => 1,
        ),
        1801 => 
        array (
          'id' => '220904',
          'province_id' => '2209',
          'description' => 'Chazuta',
          'active' => 1,
        ),
        1802 => 
        array (
          'id' => '220905',
          'province_id' => '2209',
          'description' => 'Chipurana',
          'active' => 1,
        ),
        1803 => 
        array (
          'id' => '220906',
          'province_id' => '2209',
          'description' => 'El Porvenir',
          'active' => 1,
        ),
        1804 => 
        array (
          'id' => '220907',
          'province_id' => '2209',
          'description' => 'Huimbayoc',
          'active' => 1,
        ),
        1805 => 
        array (
          'id' => '220908',
          'province_id' => '2209',
          'description' => 'Juan Guerra',
          'active' => 1,
        ),
        1806 => 
        array (
          'id' => '220909',
          'province_id' => '2209',
          'description' => 'La Banda de Shilcayo',
          'active' => 1,
        ),
        1807 => 
        array (
          'id' => '220910',
          'province_id' => '2209',
          'description' => 'Morales',
          'active' => 1,
        ),
        1808 => 
        array (
          'id' => '220911',
          'province_id' => '2209',
          'description' => 'Papaplaya',
          'active' => 1,
        ),
        1809 => 
        array (
          'id' => '220912',
          'province_id' => '2209',
          'description' => 'San Antonio',
          'active' => 1,
        ),
        1810 => 
        array (
          'id' => '220913',
          'province_id' => '2209',
          'description' => 'Sauce',
          'active' => 1,
        ),
        1811 => 
        array (
          'id' => '220914',
          'province_id' => '2209',
          'description' => 'Shapaja',
          'active' => 1,
        ),
        1812 => 
        array (
          'id' => '221001',
          'province_id' => '2210',
          'description' => 'Tocache',
          'active' => 1,
        ),
        1813 => 
        array (
          'id' => '221002',
          'province_id' => '2210',
          'description' => 'Nuevo Progreso',
          'active' => 1,
        ),
        1814 => 
        array (
          'id' => '221003',
          'province_id' => '2210',
          'description' => 'Polvora',
          'active' => 1,
        ),
        1815 => 
        array (
          'id' => '221004',
          'province_id' => '2210',
          'description' => 'Shunte',
          'active' => 1,
        ),
        1816 => 
        array (
          'id' => '221005',
          'province_id' => '2210',
          'description' => 'Uchiza',
          'active' => 1,
        ),
        1817 => 
        array (
          'id' => '230101',
          'province_id' => '2301',
          'description' => 'Tacna',
          'active' => 1,
        ),
        1818 => 
        array (
          'id' => '230102',
          'province_id' => '2301',
          'description' => 'Alto de la Alianza',
          'active' => 1,
        ),
        1819 => 
        array (
          'id' => '230103',
          'province_id' => '2301',
          'description' => 'Calana',
          'active' => 1,
        ),
        1820 => 
        array (
          'id' => '230104',
          'province_id' => '2301',
          'description' => 'Ciudad Nueva',
          'active' => 1,
        ),
        1821 => 
        array (
          'id' => '230105',
          'province_id' => '2301',
          'description' => 'Inclan',
          'active' => 1,
        ),
        1822 => 
        array (
          'id' => '230106',
          'province_id' => '2301',
          'description' => 'Pachia',
          'active' => 1,
        ),
        1823 => 
        array (
          'id' => '230107',
          'province_id' => '2301',
          'description' => 'Palca',
          'active' => 1,
        ),
        1824 => 
        array (
          'id' => '230108',
          'province_id' => '2301',
          'description' => 'Pocollay',
          'active' => 1,
        ),
        1825 => 
        array (
          'id' => '230109',
          'province_id' => '2301',
          'description' => 'Sama',
          'active' => 1,
        ),
        1826 => 
        array (
          'id' => '230110',
          'province_id' => '2301',
          'description' => 'Coronel Gregorio Albarracín Lanchipa',
          'active' => 1,
        ),
        1827 => 
        array (
          'id' => '230111',
          'province_id' => '2301',
          'description' => 'La Yarada los Palos',
          'active' => 1,
        ),
        1828 => 
        array (
          'id' => '230201',
          'province_id' => '2302',
          'description' => 'Candarave',
          'active' => 1,
        ),
        1829 => 
        array (
          'id' => '230202',
          'province_id' => '2302',
          'description' => 'Cairani',
          'active' => 1,
        ),
        1830 => 
        array (
          'id' => '230203',
          'province_id' => '2302',
          'description' => 'Camilaca',
          'active' => 1,
        ),
        1831 => 
        array (
          'id' => '230204',
          'province_id' => '2302',
          'description' => 'Curibaya',
          'active' => 1,
        ),
        1832 => 
        array (
          'id' => '230205',
          'province_id' => '2302',
          'description' => 'Huanuara',
          'active' => 1,
        ),
        1833 => 
        array (
          'id' => '230206',
          'province_id' => '2302',
          'description' => 'Quilahuani',
          'active' => 1,
        ),
        1834 => 
        array (
          'id' => '230301',
          'province_id' => '2303',
          'description' => 'Locumba',
          'active' => 1,
        ),
        1835 => 
        array (
          'id' => '230302',
          'province_id' => '2303',
          'description' => 'Ilabaya',
          'active' => 1,
        ),
        1836 => 
        array (
          'id' => '230303',
          'province_id' => '2303',
          'description' => 'Ite',
          'active' => 1,
        ),
        1837 => 
        array (
          'id' => '230401',
          'province_id' => '2304',
          'description' => 'Tarata',
          'active' => 1,
        ),
        1838 => 
        array (
          'id' => '230402',
          'province_id' => '2304',
          'description' => 'Héroes Albarracín',
          'active' => 1,
        ),
        1839 => 
        array (
          'id' => '230403',
          'province_id' => '2304',
          'description' => 'Estique',
          'active' => 1,
        ),
        1840 => 
        array (
          'id' => '230404',
          'province_id' => '2304',
          'description' => 'Estique-Pampa',
          'active' => 1,
        ),
        1841 => 
        array (
          'id' => '230405',
          'province_id' => '2304',
          'description' => 'Sitajara',
          'active' => 1,
        ),
        1842 => 
        array (
          'id' => '230406',
          'province_id' => '2304',
          'description' => 'Susapaya',
          'active' => 1,
        ),
        1843 => 
        array (
          'id' => '230407',
          'province_id' => '2304',
          'description' => 'Tarucachi',
          'active' => 1,
        ),
        1844 => 
        array (
          'id' => '230408',
          'province_id' => '2304',
          'description' => 'Ticaco',
          'active' => 1,
        ),
        1845 => 
        array (
          'id' => '240101',
          'province_id' => '2401',
          'description' => 'Tumbes',
          'active' => 1,
        ),
        1846 => 
        array (
          'id' => '240102',
          'province_id' => '2401',
          'description' => 'Corrales',
          'active' => 1,
        ),
        1847 => 
        array (
          'id' => '240103',
          'province_id' => '2401',
          'description' => 'La Cruz',
          'active' => 1,
        ),
        1848 => 
        array (
          'id' => '240104',
          'province_id' => '2401',
          'description' => 'Pampas de Hospital',
          'active' => 1,
        ),
        1849 => 
        array (
          'id' => '240105',
          'province_id' => '2401',
          'description' => 'San Jacinto',
          'active' => 1,
        ),
        1850 => 
        array (
          'id' => '240106',
          'province_id' => '2401',
          'description' => 'San Juan de la Virgen',
          'active' => 1,
        ),
        1851 => 
        array (
          'id' => '240201',
          'province_id' => '2402',
          'description' => 'Zorritos',
          'active' => 1,
        ),
        1852 => 
        array (
          'id' => '240202',
          'province_id' => '2402',
          'description' => 'Casitas',
          'active' => 1,
        ),
        1853 => 
        array (
          'id' => '240203',
          'province_id' => '2402',
          'description' => 'Canoas de Punta Sal',
          'active' => 1,
        ),
        1854 => 
        array (
          'id' => '240301',
          'province_id' => '2403',
          'description' => 'Zarumilla',
          'active' => 1,
        ),
        1855 => 
        array (
          'id' => '240302',
          'province_id' => '2403',
          'description' => 'Aguas Verdes',
          'active' => 1,
        ),
        1856 => 
        array (
          'id' => '240303',
          'province_id' => '2403',
          'description' => 'Matapalo',
          'active' => 1,
        ),
        1857 => 
        array (
          'id' => '240304',
          'province_id' => '2403',
          'description' => 'Papayal',
          'active' => 1,
        ),
        1858 => 
        array (
          'id' => '250101',
          'province_id' => '2501',
          'description' => 'Calleria',
          'active' => 1,
        ),
        1859 => 
        array (
          'id' => '250102',
          'province_id' => '2501',
          'description' => 'Campoverde',
          'active' => 1,
        ),
        1860 => 
        array (
          'id' => '250103',
          'province_id' => '2501',
          'description' => 'Iparia',
          'active' => 1,
        ),
        1861 => 
        array (
          'id' => '250104',
          'province_id' => '2501',
          'description' => 'Masisea',
          'active' => 1,
        ),
        1862 => 
        array (
          'id' => '250105',
          'province_id' => '2501',
          'description' => 'Yarinacocha',
          'active' => 1,
        ),
        1863 => 
        array (
          'id' => '250106',
          'province_id' => '2501',
          'description' => 'Nueva Requena',
          'active' => 1,
        ),
        1864 => 
        array (
          'id' => '250107',
          'province_id' => '2501',
          'description' => 'Manantay',
          'active' => 1,
        ),
        1865 => 
        array (
          'id' => '250201',
          'province_id' => '2502',
          'description' => 'Raymondi',
          'active' => 1,
        ),
        1866 => 
        array (
          'id' => '250202',
          'province_id' => '2502',
          'description' => 'Sepahua',
          'active' => 1,
        ),
        1867 => 
        array (
          'id' => '250203',
          'province_id' => '2502',
          'description' => 'Tahuania',
          'active' => 1,
        ),
        1868 => 
        array (
          'id' => '250204',
          'province_id' => '2502',
          'description' => 'Yurua',
          'active' => 1,
        ),
        1869 => 
        array (
          'id' => '250301',
          'province_id' => '2503',
          'description' => 'Padre Abad',
          'active' => 1,
        ),
        1870 => 
        array (
          'id' => '250302',
          'province_id' => '2503',
          'description' => 'Irazola',
          'active' => 1,
        ),
        1871 => 
        array (
          'id' => '250303',
          'province_id' => '2503',
          'description' => 'Curimana',
          'active' => 1,
        ),
        1872 => 
        array (
          'id' => '250304',
          'province_id' => '2503',
          'description' => 'Neshuya',
          'active' => 1,
        ),
        1873 => 
        array (
          'id' => '250305',
          'province_id' => '2503',
          'description' => 'Alexander Von Humboldt',
          'active' => 1,
        ),
        1874 => 
        array (
          'id' => '250307',
          'province_id' => '2503',
          'description' => 'Boqueron',
          'active' => 1,
        ),
        1875 => 
        array (
          'id' => '250401',
          'province_id' => '2504',
          'description' => 'Purus',
          'active' => 1,
        ),
      ),
    ),
    'persons' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'type' => 'customers',
          'identity_document_type_id' => '0',
          'number' => '99999999',
          'name' => 'Clientes - Varios',
          'trade_name' => NULL,
          'internal_code' => NULL,
          'barcode' => NULL,
          'country_id' => 'PE',
          'nationality_id' => NULL,
          'department_id' => NULL,
          'province_id' => NULL,
          'district_id' => NULL,
          'address_type_id' => NULL,
          'address' => NULL,
          'establishment_code' => '0000',
          'condition' => NULL,
          'state' => NULL,
          'email' => NULL,
          'password' => NULL,
          'remember_token' => NULL,
          'telephone' => NULL,
          'accumulated_points' => '0.00',
          'perception_agent' => 0,
          'is_agent_retention' => 0,
          'person_type_id' => NULL,
          'contact' => NULL,
          'comment' => NULL,
          'percentage_perception' => NULL,
          'enabled' => 1,
          'website' => NULL,
          'zone' => NULL,
          'observation' => NULL,
          'created_at' => '2026-08-17 14:14:56',
          'updated_at' => '2026-08-17 14:14:56',
          'status' => 1,
          'credit_days' => 0,
          'optional_email' => NULL,
          'parent_id' => 0,
          'zone_id' => NULL,
          'seller_id' => NULL,
          'has_discount' => 0,
          'discount_type' => '01',
          'discount_amount' => '0.00',
          'text_filter' => NULL,
        ),
      ),
    ),
    'items' => 
    array (
      'key_columns' => 
      array (
        0 => 'id',
      ),
      'rows' => 
      array (
        0 => 
        array (
          'id' => 1,
          'name' => NULL,
          'preparation_area_id' => NULL,
          'second_name' => NULL,
          'description' => 'Costo de Envío',
          'text_filter' => NULL,
          'model' => NULL,
          'factory_code' => NULL,
          'barcode' => NULL,
          'technical_specifications' => NULL,
          'item_type_id' => '02',
          'internal_id' => 'DELIVERY-ECOM',
          'item_code' => NULL,
          'date_of_due' => NULL,
          'account_id' => NULL,
          'item_code_gs1' => NULL,
          'unit_type_id' => 'ZZ',
          'currency_type_id' => 'PEN',
          'sale_unit_price' => '0.000000',
          'purchase_has_igv' => 1,
          'has_igv' => 1,
          'subject_to_detraction' => 0,
          'purchase_unit_price' => '0.000000',
          'has_isc' => 0,
          'restrict_sale_cpe' => 0,
          'exchange_points' => 0,
          'quantity_of_points' => '0.00',
          'commission_amount' => NULL,
          'line' => NULL,
          'commission_type' => NULL,
          'amount_plastic_bag_taxes' => '0.10',
          'system_isc_type_id' => NULL,
          'percentage_isc' => '0.00',
          'suggested_price' => '0.00',
          'purchase_has_isc' => 0,
          'purchase_system_isc_type_id' => NULL,
          'purchase_percentage_isc' => '0.00',
          'sale_affectation_igv_type_id' => '10',
          'purchase_affectation_igv_type_id' => '10',
          'calculate_quantity' => 0,
          'sale_unit_price_set' => NULL,
          'is_set' => 0,
          'favorite' => 0,
          'category_id' => NULL,
          'brand_id' => NULL,
          'image' => 'imagen-no-disponible.jpg',
          'image_medium' => 'imagen-no-disponible.jpg',
          'image_small' => 'imagen-no-disponible.jpg',
          'stock' => '0.0000',
          'stock_min' => '0.00',
          'has_plastic_bag_taxes' => 0,
          'lot_code' => NULL,
          'lots_enabled' => 0,
          'series_enabled' => 0,
          'percentage_of_profit' => '0.00',
          'has_perception' => 0,
          'percentage_perception' => NULL,
          'attributes' => NULL,
          'active' => 1,
          'hidden_search' => 1,
          'web_platform_id' => NULL,
          'created_at' => '2026-08-17 14:15:26',
          'updated_at' => '2026-08-17 14:15:26',
          'warehouse_id' => NULL,
          'status' => 1,
          'is_dish' => 0,
          'apply_store' => 0,
          'apply_restaurant' => 0,
          'restaurant_favorite' => 0,
          'cod_digemid' => NULL,
          'sanitary' => NULL,
          'is_for_production' => 0,
        ),
        1 => 
        array (
          'id' => 2,
          'name' => 'Penalidad',
          'preparation_area_id' => NULL,
          'second_name' => NULL,
          'description' => 'Penalidad',
          'text_filter' => NULL,
          'model' => NULL,
          'factory_code' => NULL,
          'barcode' => NULL,
          'technical_specifications' => NULL,
          'item_type_id' => '02',
          'internal_id' => 'PENALIDAD',
          'item_code' => NULL,
          'date_of_due' => NULL,
          'account_id' => NULL,
          'item_code_gs1' => NULL,
          'unit_type_id' => 'ZZ',
          'currency_type_id' => 'PEN',
          'sale_unit_price' => '0.000000',
          'purchase_has_igv' => 0,
          'has_igv' => 0,
          'subject_to_detraction' => 0,
          'purchase_unit_price' => '0.000000',
          'has_isc' => 0,
          'restrict_sale_cpe' => 0,
          'exchange_points' => 0,
          'quantity_of_points' => '0.00',
          'commission_amount' => NULL,
          'line' => NULL,
          'commission_type' => NULL,
          'amount_plastic_bag_taxes' => '0.10',
          'system_isc_type_id' => NULL,
          'percentage_isc' => '0.00',
          'suggested_price' => '0.00',
          'purchase_has_isc' => 0,
          'purchase_system_isc_type_id' => NULL,
          'purchase_percentage_isc' => '0.00',
          'sale_affectation_igv_type_id' => '30',
          'purchase_affectation_igv_type_id' => '30',
          'calculate_quantity' => 0,
          'sale_unit_price_set' => NULL,
          'is_set' => 0,
          'favorite' => 0,
          'category_id' => NULL,
          'brand_id' => NULL,
          'image' => 'imagen-no-disponible.jpg',
          'image_medium' => 'imagen-no-disponible.jpg',
          'image_small' => 'imagen-no-disponible.jpg',
          'stock' => '0.0000',
          'stock_min' => '0.00',
          'has_plastic_bag_taxes' => 0,
          'lot_code' => NULL,
          'lots_enabled' => 0,
          'series_enabled' => 0,
          'percentage_of_profit' => '0.00',
          'has_perception' => 0,
          'percentage_perception' => NULL,
          'attributes' => NULL,
          'active' => 1,
          'hidden_search' => 0,
          'web_platform_id' => NULL,
          'created_at' => '2026-08-17 14:15:33',
          'updated_at' => '2026-08-17 14:15:33',
          'warehouse_id' => NULL,
          'status' => 1,
          'is_dish' => 0,
          'apply_store' => 0,
          'apply_restaurant' => 0,
          'restaurant_favorite' => 0,
          'cod_digemid' => NULL,
          'sanitary' => NULL,
          'is_for_production' => 0,
        ),
      ),
    ),
  ),
);
