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
    'cat_document_types' => array_values(array_map(
        static function (array $catalogRow): array {
            if ((string) $catalogRow['id'] === 'U4') {
                $catalogRow['description'] = 'Nota de Transferencia Almacén';
            }
            return $catalogRow;
        },
        array_filter(
            $tables['cat_document_types']['rows'] ?? [],
            static fn (array $catalogRow): bool => in_array((string) $catalogRow['id'], ['01', '07', '08', '09', '20', '80', 'NE76', 'U2', 'U3', 'U4'], true)
        )
    )),
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
    'expense_reasons' => array_map(
        static fn (string $description, int $index): array => $row($index + 1, $description),
        [
            'Honorarios profesionales jurídicos y contables',
            'Servicios de publicidad, propaganda y mercadeo',
            'Comisiones de ventas y corretaje',
            'Mantenimiento y reparación de activos fijos',
            'Servicios de vigilancia y seguridad privada',
            'Servicios de limpieza y aseo industrial',
            'Fletes y transporte nacional',
            'Arrendamiento de inmuebles',
            'Alquiler de bienes muebles',
            'Energía eléctrica',
            'Servicio de agua potable',
            'Telecomunicaciones e Internet',
            'Viáticos, gastos de viaje y movilización',
            'Gastos de representación',
            'Suministros de oficina y papelería',
            'Pago de impuestos y tasas municipales',
            'Intereses de mora, multas y sanciones',
            'Gastos sin factura legal o soportes informales',
        ],
        range(0, 17)
    ),
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
