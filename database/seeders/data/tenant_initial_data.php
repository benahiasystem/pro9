<?php

// ######## INICIO DATOS INICIALES VENEZUELA ########
/** Catálogos vigentes para instalaciones nuevas. */
return array (
  'source' => 'catálogos iniciales Venezuela',
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
          'description' => 'Factura de venta',
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
          'description' => 'Orden de entrega',
          'order_menu' => 14,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        14 =>
        array (
          'id' => 16,
          'value' => 'inventory',
          'description' => 'Inventario',
          'order_menu' => 16,
          'created_at' => NULL,
          'updated_at' => NULL,
        ),
        15 =>
        array (
          'id' => 17,
          'value' => 'finance',
          'description' => 'Finanzas',
          'order_menu' => 17,
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
          'description' => 'BANCO DE VENEZUELA',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        1 =>
        array (
          'id' => 2,
          'description' => 'BANESCO',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        2 =>
        array (
          'id' => 3,
          'description' => 'BANCO MERCANTIL',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        3 =>
        array (
          'id' => 4,
          'description' => 'BBVA BANCO PROVINCIAL',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        4 =>
        array (
          'id' => 5,
          'description' => 'BANCO NACIONAL DE CRÉDITO',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        5 =>
        array (
          'id' => 6,
          'description' => 'BANCO EXTERIOR',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        6 =>
        array (
          'id' => 7,
          'description' => 'BANCO DE LA FUERZA ARMADA NACIONAL BOLIVARIANA',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        7 =>
        array (
          'id' => 8,
          'description' => 'BANCO BICENTENARIO',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        8 =>
        array (
          'id' => 9,
          'description' => 'BANCO CARONÍ',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        9 =>
        array (
          'id' => 10,
          'description' => 'BANCO DEL CARIBE',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        10 =>
        array (
          'id' => 11,
          'description' => 'BANCO FONDO COMÚN',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        11 =>
        array (
          'id' => 12,
          'description' => 'BANCO PLAZA',
          'created_at' => NULL,
          'updated_at' => NULL,
          'active' => 1,
        ),
        12 =>
        array (
          'id' => 13,
          'description' => 'BANCO VENEZOLANO DE CRÉDITO',
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
          'description' => 'Gravado',
        ),
        1 =>
        array (
          'id' => '20',
          'active' => 1,
          'exportation' => 0,
          'free' => 0,
          'description' => 'Exento',
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
          'id' => '5010',
          'active' => 1,
          'description' => 'Numero de Placa',
        ),
        1 =>
        array (
          'id' => '5011',
          'active' => 1,
          'description' => 'Categoria',
        ),
        2 =>
        array (
          'id' => '5012',
          'active' => 1,
          'description' => 'Marca',
        ),
        3 =>
        array (
          'id' => '5013',
          'active' => 1,
          'description' => 'Modelo',
        ),
        4 =>
        array (
          'id' => '5014',
          'active' => 1,
          'description' => 'Color',
        ),
        5 =>
        array (
          'id' => '5015',
          'active' => 1,
          'description' => 'Motor',
        ),
        6 =>
        array (
          'id' => '5016',
          'active' => 1,
          'description' => 'Combustible',
        ),
        7 =>
        array (
          'id' => '5017',
          'active' => 1,
          'description' => 'Form. Rodante',
        ),
        8 =>
        array (
          'id' => '5018',
          'active' => 1,
          'description' => 'VIN',
        ),
        9 =>
        array (
          'id' => '5019',
          'active' => 1,
          'description' => 'Serie/Chasis',
        ),
        10 =>
        array (
          'id' => '5020',
          'active' => 1,
          'description' => 'Año fabricacion',
        ),
        11 =>
        array (
          'id' => '5021',
          'active' => 1,
          'description' => 'Año modelo',
        ),
        12 =>
        array (
          'id' => '5022',
          'active' => 1,
          'description' => 'Version',
        ),
        13 =>
        array (
          'id' => '5023',
          'active' => 1,
          'description' => 'Ejes',
        ),
        14 =>
        array (
          'id' => '5024',
          'active' => 1,
          'description' => 'Asientos',
        ),
        15 =>
        array (
          'id' => '5025',
          'active' => 1,
          'description' => 'Pasajeros',
        ),
        16 =>
        array (
          'id' => '5026',
          'active' => 1,
          'description' => 'Ruedas',
        ),
        17 =>
        array (
          'id' => '5027',
          'active' => 1,
          'description' => 'Carroceria',
        ),
        18 =>
        array (
          'id' => '5028',
          'active' => 1,
          'description' => 'Potencia',
        ),
        19 =>
        array (
          'id' => '5029',
          'active' => 1,
          'description' => 'Cilindros',
        ),
        20 =>
        array (
          'id' => '5030',
          'active' => 1,
          'description' => 'Ciliindrada',
        ),
        21 =>
        array (
          'id' => '5031',
          'active' => 1,
          'description' => 'Peso Bruto',
        ),
        22 =>
        array (
          'id' => '5032',
          'active' => 1,
          'description' => 'Peso Neto',
        ),
        23 =>
        array (
          'id' => '5033',
          'active' => 1,
          'description' => 'Carga Util',
        ),
        24 =>
        array (
          'id' => '5034',
          'active' => 1,
          'description' => 'Longitud',
        ),
        25 =>
        array (
          'id' => '5035',
          'active' => 1,
          'description' => 'Altura',
        ),
        26 =>
        array (
          'id' => '5036',
          'active' => 1,
          'description' => 'Ancho',
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
          'description' => 'Descuentos que afectan la base imponible del IVA',
        ),
        1 =>
        array (
          'id' => '01',
          'active' => 1,
          'base' => 0,
          'level' => 'item',
          'type' => 'discount',
          'description' => 'Descuentos que no afectan la base imponible del IVA',
        ),
        2 =>
        array (
          'id' => '02',
          'active' => 0,
          'base' => 1,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales que afectan la base imponible del IVA',
        ),
        3 =>
        array (
          'id' => '03',
          'active' => 0,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Descuentos globales que no afectan la base imponible del IVA',
        ),
        4 =>
        array (
          'id' => '46',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'charge',
          'description' => 'Recargo al consumo y/o propinas',
        ),
        5 =>
        array (
          'id' => '62',
          'active' => 1,
          'base' => 0,
          'level' => 'global',
          'type' => 'discount',
          'description' => 'Retención del IVA',
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
          'id' => 'VES',
          'active' => 1,
          'symbol' => 'Bs.',
          'description' => 'Bolívares',
        ),
        1 =>
        array (
          'id' => 'USD',
          'active' => 1,
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
          'description' => 'FACTURA',
          'is_sunat' => 1,
        ),
        1 =>
        array (
          'id' => 'FE',
          'active' => 1,
          'short' => NULL,
          'description' => 'FACTURA DE EXPORTACIÓN',
          'is_sunat' => 1,
        ),
        2 =>
        array (
          'id' => '07',
          'active' => 1,
          'short' => 'NC',
          'description' => 'NOTA DE CRÉDITO',
          'is_sunat' => 1,
        ),
        3 =>
        array (
          'id' => '08',
          'active' => 1,
          'short' => 'ND',
          'description' => 'NOTA DE DÉBITO',
          'is_sunat' => 1,
        ),
        4 =>
        array (
          'id' => '20',
          'active' => 1,
          'short' => NULL,
          'description' => 'COMPROBANTE DE RETENCIÓN DE IVA',
          'is_sunat' => 1,
        ),
        5 =>
        array (
          'id' => 'ISLR',
          'active' => 1,
          'short' => NULL,
          'description' => 'COMPROBANTE DE RETENCIÓN DE I.S.L.R.',
          'is_sunat' => 1,
        ),
        6 =>
        array (
          'id' => '09',
          'active' => 1,
          'short' => NULL,
          'description' => 'ORDEN DE ENTREGA',
          'is_sunat' => 1,
        ),
        7 =>
        array (
          'id' => 'CBU',
          'active' => 1,
          'short' => NULL,
          'description' => 'CERTIFICACIÓN DE COMPRA DE BIENES USADOS',
          'is_sunat' => 1,
        ),
        8 =>
        array (
          'id' => '80',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE VENTA',
          'is_sunat' => 1,
        ),
        9 =>
        array (
          'id' => 'U2',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE INGRESO ALMACÉN',
          'is_sunat' => 1,
        ),
        10 =>
        array (
          'id' => 'U3',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE SALIDA ALMACÉN',
          'is_sunat' => 1,
        ),
        11 =>
        array (
          'id' => 'U4',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE TRANSFERENCIA ALMACÉN',
          'is_sunat' => 1,
        ),
        12 =>
        array (
          'id' => 'NE76',
          'active' => 1,
          'short' => NULL,
          'description' => 'NOTA DE ENTRADA',
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
          'description' => 'Doc.sin.rif',
        ),
        1 =>
        array (
          'id' => '1',
          'active' => 1,
          'description' => 'Venezolano',
        ),
        2 =>
        array (
          'id' => '6',
          'active' => 1,
          'description' => 'Juridico',
        ),
        3 =>
        array (
          'id' => '7',
          'active' => 1,
          'description' => 'Pasaporte',
        ),
        4 =>
        array (
          'id' => 'E',
          'active' => 0,
          'description' => 'Extranjero',
        ),
        5 =>
        array (
          'id' => 'C',
          'active' => 0,
          'description' => 'Comuna',
        ),
        6 =>
        array (
          'id' => 'G',
          'active' => 0,
          'description' => 'Gubernamental',
        ),
        7 =>
        array (
          'id' => 'R',
          'active' => 0,
          'description' => 'Firma Personal',
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
          'description' => 'Anulación total de factura',
        ),
        1 =>
        array (
          'id' => '07',
          'active' => 1,
          'description' => 'Devolución parcial de mercancía',
        ),
        2 =>
        array (
          'id' => '04',
          'active' => 1,
          'description' => 'Descuento o rebajas concedidas',
        ),
        3 =>
        array (
          'id' => '09',
          'active' => 1,
          'description' => 'Corrección de precios o cálculos',
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
          'id' => '02',
          'active' => 1,
          'description' => 'Ajustes por incremento de precios',
        ),
        1 =>
        array (
          'id' => '01',
          'active' => 1,
          'description' => 'Intereses de mora o financiamiento',
        ),
        2 =>
        array (
          'id' => '03',
          'active' => 1,
          'description' => 'Gastos de despacho, fletes, seguros o embalaje',
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
          'id' => '0200',
          'active' => 0,
          'exportation' => 1,
          'description' => 'Exportación de Bienes',
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
          'description' => 'Precio unitario (incluye el IVA)',
        ),
        1 =>
        array (
          'id' => '02',
          'active' => 1,
          'description' => 'Valor referencial unitario en operaciones no onerosas',
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
          'id' => '04',
          'active' => 1,
          'description' => 'Traslado entre almacenes',
          'discount_stock' => 0,
        ),
        2 =>
        array (
          'id' => '06',
          'active' => 1,
          'description' => 'Devolución a proveedor',
          'discount_stock' => 0,
        ),
        3 =>
        array (
          'id' => '05',
          'active' => 1,
          'description' => 'Demostración, evento o consignación',
          'discount_stock' => 0,
        ),
        4 =>
        array (
          'id' => '20',
          'active' => 1,
          'description' => 'Demostración o evento',
          'discount_stock' => 0,
        ),
        5 =>
        array (
          'id' => '21',
          'active' => 1,
          'description' => 'Reparación, servicio técnico o mantenimiento',
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
          'quotation_enabled' => 0,
          'quotation_mode' => 'quote_and_sell',
          'quotation_show_prices' => 1,
          'quotation_success_message' => NULL,
          'quotation_validity_days' => 7,
          'quotation_terms' => NULL,
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
          'description' => 'Honorarios profesionales',
        ),
        1 =>
        array (
          'id' => 2,
          'description' => 'Publicidad, propaganda y mercadeo',
        ),
        2 =>
        array (
          'id' => 3,
          'description' => 'Comisiones de ventas y corretaje',
        ),
        3 =>
        array (
          'id' => 4,
          'description' => 'Mantenimiento y reparación',
        ),
        4 =>
        array (
          'id' => 5,
          'description' => 'Vigilancia y seguridad',
        ),
        5 =>
        array (
          'id' => 6,
          'description' => 'Limpieza y aseo',
        ),
        6 =>
        array (
          'id' => 7,
          'description' => 'Fletes, transporte y mensajería',
        ),
        7 =>
        array (
          'id' => 8,
          'description' => 'Arrendamiento de inmuebles',
        ),
        8 =>
        array (
          'id' => 9,
          'description' => 'Alquiler de bienes muebles y equipos',
        ),
        9 =>
        array (
          'id' => 10,
          'description' => 'Energía eléctrica',
        ),
        10 =>
        array (
          'id' => 11,
          'description' => 'Agua potable',
        ),
        11 =>
        array (
          'id' => 12,
          'description' => 'Telecomunicaciones, Internet y servicios digitales',
        ),
        12 =>
        array (
          'id' => 13,
          'description' => 'Viáticos, viajes y movilización',
        ),
        13 =>
        array (
          'id' => 14,
          'description' => 'Gastos de representación',
        ),
        14 =>
        array (
          'id' => 15,
          'description' => 'Papelería, útiles y suministros de oficina',
        ),
        15 =>
        array (
          'id' => 16,
          'description' => 'Impuestos, tasas y contribuciones',
        ),
        16 =>
        array (
          'id' => 17,
          'description' => 'Multas, sanciones e intereses de mora',
        ),
        17 =>
        array (
          'id' => 18,
          'description' => 'Gastos sin soporte fiscal válido',
        ),
        18 =>
        array (
          'id' => 19,
          'description' => 'Sueldos, salarios y remuneraciones',
        ),
        19 =>
        array (
          'id' => 20,
          'description' => 'Beneficios laborales y prestaciones sociales',
        ),
        20 =>
        array (
          'id' => 21,
          'description' => 'Aportes patronales: IVSS, FAOV e INCES',
        ),
        21 =>
        array (
          'id' => 22,
          'description' => 'Seguros y pólizas',
        ),
        22 =>
        array (
          'id' => 23,
          'description' => 'Gastos bancarios, comisiones y servicios financieros',
        ),
        23 =>
        array (
          'id' => 24,
          'description' => 'Intereses y gastos de financiamiento',
        ),
        24 =>
        array (
          'id' => 25,
          'description' => 'Depreciación y amortización',
        ),
        25 =>
        array (
          'id' => 26,
          'description' => 'Combustible, lubricantes y peajes',
        ),
        26 =>
        array (
          'id' => 27,
          'description' => 'Repuestos y mantenimiento de vehículos',
        ),
        27 =>
        array (
          'id' => 28,
          'description' => 'Sistemas, software, licencias y suscripciones',
        ),
        28 =>
        array (
          'id' => 29,
          'description' => 'Servicios profesionales técnicos y consultoría',
        ),
        29 =>
        array (
          'id' => 30,
          'description' => 'Otros gastos operativos',
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
          'description' => 'Órdenes de entrega',
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
          'description' => 'Efectivo Bolivares',
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
          'description' => 'Transferencia Bancaria',
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
          'description' => 'Crédito a 30 días',
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
          'description' => 'Tarjeta Internacional',
          'has_card' => 1,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 0,
        ),
        6 =>
        array (
          'id' => '07',
          'description' => 'Delivery / Pago en Sitio',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 0,
          'is_active' => 0,
        ),
        7 =>
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
        8 =>
        array (
          'id' => '10',
          'description' => 'Efectivo Dólares',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        9 =>
        array (
          'id' => '11',
          'description' => 'Pago Móvil',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        10 =>
        array (
          'id' => '12',
          'description' => 'Biopago',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 1,
        ),
        11 =>
        array (
          'id' => '13',
          'description' => 'Zelle',
          'has_card' => 0,
          'charge' => NULL,
          'number_days' => NULL,
          'is_credit' => 0,
          'is_cash' => 1,
          'is_active' => 0,
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
    'fiscal_environments' =>
    array (
      'key_columns' =>
      array (
        0 => 'id',
      ),
      'rows' =>
      array (
        0 =>
        array (
          'id' => 'demo',
          'description' => 'Demo',
        ),
        1 =>
        array (
          'id' => 'production',
          'description' => 'Producción',
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
          'description' => 'Pago pendiente',
          'color' => '#ffc107',
          'sort_order' => 1,
          'is_initial' => true,
          'is_final' => false,
          'is_payment_status' => true,
          'is_order_status' => false,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => false,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        1 =>
        array (
          'id' => 2,
          'description' => 'Pago completado',
          'color' => '#28a745',
          'sort_order' => 2,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => true,
          'is_order_status' => false,
          'is_shipping_status' => false,
          'action_generate_document' => true,
          'action_discount_stock' => 0,
          'action_mark_payment' => true,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        2 =>
        array (
          'id' => 3,
          'description' => 'Pago rechazado',
          'color' => '#dc3545',
          'sort_order' => 3,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => true,
          'is_order_status' => false,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => true,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        3 =>
        array (
          'id' => 4,
          'description' => 'Reembolso',
          'color' => '#6c757d',
          'sort_order' => 4,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => true,
          'is_order_status' => false,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => true,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        4 =>
        array (
          'id' => 5,
          'description' => 'Preparando pedido',
          'color' => '#17a2b8',
          'sort_order' => 5,
          'is_initial' => true,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => false,
          'is_shipping_status' => true,
          'action_generate_document' => 0,
          'action_discount_stock' => true,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        5 =>
        array (
          'id' => 6,
          'description' => 'Listo para recojo',
          'color' => '#fd7e14',
          'sort_order' => 6,
          'is_initial' => false,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => false,
          'is_shipping_status' => true,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => true,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        6 =>
        array (
          'id' => 7,
          'description' => 'En camino',
          'color' => '#007bff',
          'sort_order' => 7,
          'is_initial' => false,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => false,
          'is_shipping_status' => true,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => true,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        7 =>
        array (
          'id' => 8,
          'description' => 'Entregado',
          'color' => '#28a745',
          'sort_order' => 8,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => false,
          'is_order_status' => false,
          'is_shipping_status' => true,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => false,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        8 =>
        array (
          'id' => 9,
          'description' => 'Entrega pendiente',
          'color' => '#ffc107',
          'sort_order' => 9,
          'is_initial' => false,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => false,
          'is_shipping_status' => true,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => true,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        9 =>
        array (
          'id' => 10,
          'description' => 'Nuevo pedido',
          'color' => '#17a2b8',
          'sort_order' => 10,
          'is_initial' => true,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => true,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        10 =>
        array (
          'id' => 11,
          'description' => 'En proceso',
          'color' => '#007bff',
          'sort_order' => 11,
          'is_initial' => false,
          'is_final' => false,
          'is_payment_status' => false,
          'is_order_status' => true,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        11 =>
        array (
          'id' => 12,
          'description' => 'Cancelado',
          'color' => '#dc3545',
          'sort_order' => 12,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => false,
          'is_order_status' => true,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => true,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => true,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
        ),
        12 =>
        array (
          'id' => 13,
          'description' => 'Completado',
          'color' => '#28a745',
          'sort_order' => 13,
          'is_initial' => false,
          'is_final' => true,
          'is_payment_status' => false,
          'is_order_status' => true,
          'is_shipping_status' => false,
          'action_generate_document' => 0,
          'action_discount_stock' => 0,
          'action_mark_payment' => 0,
          'action_send_email' => 0,
          'action_notify_dispatch' => 0,
          'action_generate_remission' => 0,
          'action_free_reserved_stock' => 0,
          'action_block_returns' => 0,
          'action_void_order' => 0,
          'created_at' => '2026-09-10 00:00:00',
          'updated_at' => '2026-09-10 00:00:00',
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
          'currency_type_id' => 'VES',
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
          'description' => 'Anulaciones',
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
          'description' => 'Orden de entrega',
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
          'country_id' => 'VE',
          'nationality_id' => NULL,
          'department_id' => '14',
          'province_id' => '0229',
          'district_id' => '000619',
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
          'currency_type_id' => 'VES',
          'sale_unit_price' => '0.000000',
          'purchase_has_igv' => 1,
          'has_igv' => 1,
          'purchase_unit_price' => '0.000000',
          'restrict_sale_cpe' => 0,
          'exchange_points' => 0,
          'quantity_of_points' => '0.00',
          'commission_amount' => NULL,
          'line' => NULL,
          'commission_type' => NULL,
          'suggested_price' => '0.00',
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
      ),
    ),
  ),
);
// ######## FIN DATOS INICIALES VENEZUELA ########
