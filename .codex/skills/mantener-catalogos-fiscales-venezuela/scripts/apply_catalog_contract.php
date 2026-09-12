<?php

declare(strict_types=1);

/**
 * Aplica al archivo consolidado el contrato venezolano documentado por esta skill.
 * Solo reemplaza o elimina definiciones completas de las tablas incluidas abajo.
 */

$projectRoot = dirname(__DIR__, 4);
$dataPath = $projectRoot.'/database/seeders/data/tenant_initial_data.php';
$payload = require $dataPath;
$source = (string) file_get_contents($dataPath);
$tables = $payload['tables'] ?? [];

$row = static fn ($id, string $description, array $extra = []): array => array_merge([
    'id' => $id,
    'description' => $description,
], $extra);

$activeCatalogRow = static fn (string $id, string $description): array => [
    'id' => $id,
    'active' => 1,
    'description' => $description,
];

$banks = [
    'BANCO DE VENEZUELA',
    'BANESCO',
    'BANCO MERCANTIL',
    'BBVA BANCO PROVINCIAL',
    'BANCO NACIONAL DE CRÉDITO',
    'BANCO EXTERIOR',
    'BANCO DE LA FUERZA ARMADA NACIONAL BOLIVARIANA',
    'BANCO BICENTENARIO',
    'BANCO CARONÍ',
    'BANCO DEL CARIBE',
    'BANCO FONDO COMÚN',
    'BANCO PLAZA',
    'BANCO VENEZOLANO DE CRÉDITO',
];

$desiredRows = [
    'banks' => array_map(static fn (string $description, int $index): array => [
        'id' => $index + 1,
        'description' => $description,
        'created_at' => null,
        'updated_at' => null,
        'active' => 1,
    ], $banks, array_keys($banks)),
    'cat_affectation_igv_types' => array_values(array_filter(
        $tables['cat_affectation_igv_types']['rows'] ?? [],
        static fn (array $catalogRow): bool => (int) ($catalogRow['active'] ?? 0) === 1
    )),
    'cat_attribute_types' => array_values(array_filter(
        $tables['cat_attribute_types']['rows'] ?? [],
        static fn (array $catalogRow): bool => (int) ($catalogRow['active'] ?? 0) === 1
    )),
    'cat_charge_discount_types' => array_values(array_filter(
        $tables['cat_charge_discount_types']['rows'] ?? [],
        static fn (array $catalogRow): bool => in_array((string) $catalogRow['id'], ['00', '01', '02', '03', '46', '62'], true)
    )),
    'cat_document_types' => [
        $row('01', 'FACTURA', ['active' => 1, 'short' => 'FT', 'is_sunat' => 1]),
        $row('FE', 'FACTURA DE EXPORTACIÓN', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('07', 'NOTA DE CRÉDITO', ['active' => 1, 'short' => 'NC', 'is_sunat' => 1]),
        $row('08', 'NOTA DE DÉBITO', ['active' => 1, 'short' => 'ND', 'is_sunat' => 1]),
        $row('20', 'COMPROBANTE DE RETENCIÓN DE IVA', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('ISLR', 'COMPROBANTE DE RETENCIÓN DE I.S.L.R.', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('09', 'ORDEN DE ENTREGA', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('CBU', 'CERTIFICACIÓN DE COMPRA DE BIENES USADOS', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('80', 'NOTA DE VENTA', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('U2', 'NOTA DE INGRESO ALMACÉN', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('U3', 'NOTA DE SALIDA ALMACÉN', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('U4', 'NOTA DE TRANSFERENCIA ALMACÉN', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
        $row('NE76', 'NOTA DE ENTRADA', ['active' => 1, 'short' => null, 'is_sunat' => 1]),
    ],
    'cat_legend_types' => array_values(array_filter(
        $tables['cat_legend_types']['rows'] ?? [],
        static fn (array $catalogRow): bool => (string) $catalogRow['id'] === '1000'
    )),
    'cat_note_credit_types' => [
        $activeCatalogRow('01', 'Anulación total de factura'),
        $activeCatalogRow('07', 'Devolución parcial de mercancía'),
        $activeCatalogRow('04', 'Descuento o rebajas concedidas'),
        $activeCatalogRow('09', 'Corrección de precios o cálculos'),
    ],
    'cat_note_debit_types' => [
        $activeCatalogRow('02', 'Ajustes por incremento de precios'),
        $activeCatalogRow('01', 'Intereses de mora o financiamiento'),
        $activeCatalogRow('03', 'Gastos de despacho, fletes, seguros o embalaje'),
    ],
    'cat_operation_types' => array_values(array_filter(
        $tables['cat_operation_types']['rows'] ?? [],
        static fn (array $catalogRow): bool => in_array((string) $catalogRow['id'], ['0101', '0200'], true)
    )),
    'cat_transfer_reason_types' => [
        $activeCatalogRow('01', 'Venta') + ['discount_stock' => 0],
        $activeCatalogRow('04', 'Traslado entre almacenes') + ['discount_stock' => 0],
        $activeCatalogRow('06', 'Devolución a proveedor') + ['discount_stock' => 0],
        $activeCatalogRow('05', 'Demostración, evento o consignación') + ['discount_stock' => 0],
        $activeCatalogRow('20', 'Demostración o evento') + ['discount_stock' => 0],
        $activeCatalogRow('21', 'Reparación, servicio técnico o mantenimiento') + ['discount_stock' => 0],
    ],
    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    'expense_reasons' => array_map(
        static fn (string $description, int $index): array => $row($index + 1, $description),
        [
            'Honorarios profesionales',
            'Publicidad, propaganda y mercadeo',
            'Comisiones de ventas y corretaje',
            'Mantenimiento y reparación',
            'Vigilancia y seguridad',
            'Limpieza y aseo',
            'Fletes, transporte y mensajería',
            'Arrendamiento de inmuebles',
            'Alquiler de bienes muebles y equipos',
            'Energía eléctrica',
            'Agua potable',
            'Telecomunicaciones, Internet y servicios digitales',
            'Viáticos, viajes y movilización',
            'Gastos de representación',
            'Papelería, útiles y suministros de oficina',
            'Impuestos, tasas y contribuciones',
            'Multas, sanciones e intereses de mora',
            'Gastos sin soporte fiscal válido',
            'Sueldos, salarios y remuneraciones',
            'Beneficios laborales y prestaciones sociales',
            'Aportes patronales: IVSS, FAOV e INCES',
            'Seguros y pólizas',
            'Gastos bancarios, comisiones y servicios financieros',
            'Intereses y gastos de financiamiento',
            'Depreciación y amortización',
            'Combustible, lubricantes y peajes',
            'Repuestos y mantenimiento de vehículos',
            'Sistemas, software, licencias y suscripciones',
            'Servicios profesionales técnicos y consultoría',
            'Otros gastos operativos',
        ],
        range(0, 29)
    ),
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    'groups' => [
        $row('01', 'Facturas'),
    ],
];

$removedTables = [
    'cat_other_tax_concept_types',
    'cat_perception_types',
    'cat_related_documents_types',
    'cat_related_tax_document_types',
    'cat_summary_status_types',
    'cat_system_isc_types',
    'pse_providers',
    'cat_detraction_types',
    'cat_payment_method_types',
    'departments',
    'provinces',
    'districts',
];

$replaceDefinition = static function (string $contents, string $table, ?array $definition): string {
    $pattern = "/^    '".preg_quote($table, '/')."' =>\R.*?(?=^    '[^']+' =>|^  \),\R\);)/ms";

    if (!preg_match($pattern, $contents)) {
        throw new RuntimeException("No se encontró la definición de {$table}.");
    }

    if ($definition === null) {
        return (string) preg_replace($pattern, '', $contents, 1);
    }

    $export = preg_replace('/^/m', '    ', var_export($definition, true));
    $replacement = "    '{$table}' =>\n{$export},\n";

    return (string) preg_replace_callback($pattern, static fn (): string => $replacement, $contents, 1);
};

foreach ($desiredRows as $table => $rows) {
    if (!isset($tables[$table])) {
        throw new RuntimeException("No existe {$table} en los datos iniciales.");
    }

    $definition = $tables[$table];
    $definition['rows'] = $rows;
    $source = $replaceDefinition($source, $table, $definition);
}

foreach ($removedTables as $table) {
    if (isset($tables[$table])) {
        $source = $replaceDefinition($source, $table, null);
    }
}

$source = (string) preg_replace(
    '/^\s*\/\/ ##########? (?:INICIO|FIN) CAMBIO CATÁLOGOS DE NOMBRES\R/m',
    '',
    $source
);
$source = (string) preg_replace(
    '/^return array \(/m',
    "// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES\nreturn array (",
    $source,
    1
);
$source = preg_replace(
    '/\n\);\n\n(\/\/ ######## FIN MIGRACIÓN MONEDA VENEZUELA ########)/',
    "\n);\n// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES\n\n$1",
    $source,
    1
);
$source = (string) preg_replace('/[ \t]+$/m', '', $source);

file_put_contents($dataPath, $source);

$obsoleteMigrationFiles = [
    '2026_08_17_000045_create_cat_other_tax_concept_types_table.php',
    '2026_08_17_000047_create_cat_perception_types_table.php',
    '2026_08_17_000050_create_cat_related_documents_types_table.php',
    '2026_08_17_000051_create_cat_related_tax_document_types_table.php',
    '2026_08_17_000053_create_cat_summary_status_types_table.php',
    '2026_08_17_000054_create_cat_system_isc_types_table.php',
    '2026_08_17_000140_create_pse_providers_table.php',
    '2026_08_17_000176_create_cat_detraction_types_table.php',
    '2026_08_17_000046_create_cat_payment_method_types_table.php',
];

foreach ($obsoleteMigrationFiles as $migrationFile) {
    $migrationPath = $projectRoot.'/database/migrations/tenant/'.$migrationFile;
    if (is_file($migrationPath)) {
        unlink($migrationPath);
    }
}

$foreignKeyPath = $projectRoot.'/database/migrations/tenant/2026_08_17_000328_add_tenant_foreign_keys.php';
$foreignKeySource = (string) file_get_contents($foreignKeyPath);
$obsoleteConstraints = [
    'cat_detraction_types_operation_type_id_foreign',
    'companies_pse_provider_id_foreign',
    'items_purchase_system_isc_type_id_foreign',
    'items_system_isc_type_id_foreign',
    'perceptions_perception_type_id_foreign',
    'summaries_summary_status_type_id_foreign',
    'fixed_asset_purchase_items_system_isc_type_id_foreign',
    'order_note_items_system_isc_type_id_foreign',
    'purchase_order_items_system_isc_type_id_foreign',
    'quotation_items_system_isc_type_id_foreign',
    'contract_items_system_isc_type_id_foreign',
    'purchase_items_system_isc_type_id_foreign',
    'sale_note_items_system_isc_type_id_foreign',
    'document_items_system_isc_type_id_foreign',
];
$constraintPattern = implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $obsoleteConstraints));

$foreignKeySource = (string) preg_replace(
    "/^            <<<'SQL'\RALTER TABLE `[^`]+` ADD CONSTRAINT `(?:{$constraintPattern})`.*\RSQL,\R/m",
    '',
    $foreignKeySource
);
$foreignKeySource = (string) preg_replace(
    "/^            \['table' => '[^']+', 'name' => '(?:{$constraintPattern})'\],?\R/m",
    '',
    $foreignKeySource
);
file_put_contents($foreignKeyPath, $foreignKeySource);

echo "Contrato venezolano aplicado a datos y migraciones tenant.\n";
