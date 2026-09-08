<?php

namespace Tests\Unit;

use Tests\TestCase;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
class VenezuelaInitialCatalogContractTest extends TestCase
{
    /** @test */
    public function initial_catalog_ids_and_descriptions_match_the_venezuelan_contract(): void
    {
        $expected = [
            'cat_affectation_igv_types' => ['10' => 'Gravado', '20' => 'Exento'],
            'cat_document_types' => [
                '01' => 'FACTURA DE VENTA', '07' => 'NOTA DE CRÉDITO', '08' => 'NOTA DE DÉBITO',
                '09' => 'ORDEN DE ENTREGA', '20' => 'COMPROBANTE DE RETENCIÓN', '80' => 'NOTA DE VENTA',
                'NE76' => 'NOTA DE ENTRADA', 'U2' => 'Nota de Ingreso Almacén',
                'U3' => 'Nota de Salida Almacén', 'U4' => 'Nota de Transferencia Almacén',
            ],
            'cat_identity_document_types' => [
                '0' => 'Doc.sin.rif', '1' => 'Venezolano', '6' => 'Juridico', '7' => 'Pasaporte',
                'E' => 'Extranjero', 'C' => 'Comuna', 'G' => 'Gubernamental', 'R' => 'Firma Personal',
            ],
            'cat_legend_types' => ['1000' => 'Monto en Letras'],
            'cat_charge_discount_types' => [
                '00' => 'Descuentos que afectan la base imponible del IVA',
                '01' => 'Descuentos que no afectan la base imponible del IVA',
                '02' => 'Descuentos globales que afectan la base imponible del IVA',
                '03' => 'Descuentos globales que no afectan la base imponible del IVA',
                '46' => 'Recargo al consumo y/o propinas',
                '62' => 'Retención del IVA',
            ],
            'cat_note_credit_types' => [
                '01' => 'Anulación total de factura',
                '07' => 'Devolución parcial de mercancía',
                '04' => 'Descuento o rebajas concedidas',
                '09' => 'Corrección de precios o cálculos',
            ],
            'cat_note_debit_types' => [
                '02' => 'Ajustes por incremento de precios',
                '01' => 'Intereses de mora o financiamiento',
                '03' => 'Gastos de despacho, fletes, seguros o embalaje',
            ],
            'cat_operation_types' => ['0101' => 'Venta interna', '0200' => 'Exportación de Bienes'],
            'cat_transfer_reason_types' => [
                '01' => 'Venta',
                '04' => 'Traslado entre almacenes',
                '06' => 'Devolución a proveedor',
                '05' => 'Demostración, evento o consignación',
                '20' => 'Demostración o evento',
                '21' => 'Reparación, servicio técnico o mantenimiento',
            ],
        ];

        foreach ($expected as $table => $rows) {
            self::assertSame($rows, $this->descriptionsById($table), $table);
        }
    }

    /** @test */
    public function banks_and_payment_methods_are_the_exact_pro6_contract(): void
    {
        self::assertSame([
            1 => 'BANCO DE VENEZUELA', 2 => 'BANESCO', 3 => 'BANCO MERCANTIL',
            4 => 'BBVA BANCO PROVINCIAL', 5 => 'BANCO NACIONAL DE CRÉDITO',
            6 => 'BANCO EXTERIOR', 7 => 'BANCO DE LA FUERZA ARMADA NACIONAL BOLIVARIANA',
            8 => 'BANCO BICENTENARIO', 9 => 'BANCO CARONÍ', 10 => 'BANCO DEL CARIBE',
            11 => 'BANCO FONDO COMÚN', 12 => 'BANCO PLAZA', 13 => 'BANCO VENEZOLANO DE CRÉDITO',
        ], $this->descriptionsById('banks'));

        self::assertSame([
            '001' => 'Depósito en cuenta',
            '002' => 'Giro',
            '003' => 'Transferencia de fondos',
            '004' => 'Orden de pago',
            '005' => 'Tarjeta de débito',
            '006' => 'Tarjeta de crédito emitida en el país por una empresa del sistema financiero',
            '010' => 'Medios de pago usados en comercio exterior',
            '101' => 'Transferencias – Comercio exterior',
            '102' => 'Cheques bancarios - Comercio exterior',
            '103' => 'Orden de pago simple - Comercio exterior',
            '104' => 'Orden de pago documentario - Comercio exterior',
            '105' => 'Remesa simple - Comercio exterior',
            '999' => 'Otros medios de pago',
        ], $this->descriptionsById('cat_payment_method_types'));
    }

    /** @test */
    public function only_active_attributes_and_the_eighteen_expense_reasons_are_seeded(): void
    {
        $attributes = $this->rows('cat_attribute_types');
        self::assertSame([
            5010 => 'Numero de Placa', 5011 => 'Categoria', 5012 => 'Marca', 5013 => 'Modelo',
            5014 => 'Color', 5015 => 'Motor', 5016 => 'Combustible', 5017 => 'Form. Rodante',
            5018 => 'VIN', 5019 => 'Serie/Chasis', 5020 => 'Año fabricacion', 5021 => 'Año modelo',
            5022 => 'Version', 5023 => 'Ejes', 5024 => 'Asientos', 5025 => 'Pasajeros',
            5026 => 'Ruedas', 5027 => 'Carroceria', 5028 => 'Potencia', 5029 => 'Cilindros',
            5030 => 'Ciliindrada', 5031 => 'Peso Bruto', 5032 => 'Peso Neto', 5033 => 'Carga Util',
            5034 => 'Longitud', 5035 => 'Altura', 5036 => 'Ancho',
        ], $this->descriptionsById('cat_attribute_types'));
        self::assertSame([], array_filter($attributes, static fn (array $row): bool => (int) $row['active'] !== 1));

        self::assertSame([
            1 => 'Honorarios profesionales jurídicos y contables',
            2 => 'Servicios de publicidad, propaganda y mercadeo',
            3 => 'Comisiones de ventas y corretaje',
            4 => 'Mantenimiento y reparación de activos fijos',
            5 => 'Servicios de vigilancia y seguridad privada',
            6 => 'Servicios de limpieza y aseo industrial',
            7 => 'Fletes y transporte nacional',
            8 => 'Arrendamiento de inmuebles',
            9 => 'Alquiler de bienes muebles',
            10 => 'Energía eléctrica',
            11 => 'Servicio de agua potable',
            12 => 'Telecomunicaciones e Internet',
            13 => 'Viáticos, gastos de viaje y movilización',
            14 => 'Gastos de representación',
            15 => 'Suministros de oficina y papelería',
            16 => 'Pago de impuestos y tasas municipales',
            17 => 'Intereses de mora, multas y sanciones',
            18 => 'Gastos sin factura legal o soportes informales',
        ], $this->descriptionsById('expense_reasons'));
        self::assertSame(['01' => 'Facturas'], $this->descriptionsById('groups'));
    }

    /** @test */
    public function the_skill_keeps_a_discoverable_historical_catalog_record(): void
    {
        $skill = (string) file_get_contents(base_path('.codex/skills/mantener-catalogos-fiscales-venezuela/SKILL.md'));
        $history = base_path('.codex/skills/mantener-catalogos-fiscales-venezuela/references/catalogos-iniciales.md');

        self::assertStringContainsString('references/catalogos-iniciales.md', $skill);
        self::assertFileExists($history);
        self::assertStringContainsString('Registro histórico', (string) file_get_contents($history));
    }

    /** @test */
    public function removed_catalog_features_are_not_exposed_by_routes_or_navigation(): void
    {
        $routeSources = implode("\n", array_map(static fn (string $path): string => (string) file_get_contents(base_path($path)), [
            'routes/web.php',
            'modules/Document/Routes/web.php',
            'modules/MobileApp/Routes/api-v2.php',
            'modules/PseService/Routes/tenant.php',
        ]));
        $activeRoutes = preg_replace('/^\s*\/\/.*$/m', '', $routeSources) ?? $routeSources;

        foreach (['list-detractions', 'detraction_types/', 'detraction-tables', "Route::get('perceptions", 'companies/pse-providers', 'store-send-pse'] as $fragment) {
            self::assertStringNotContainsString($fragment, $activeRoutes, $fragment);
        }

        self::assertStringNotContainsString(
            'tenant.perceptions.index',
            (string) file_get_contents(resource_path('views/tenant/layouts/partials/sidebar.blade.php'))
        );
        self::assertStringNotContainsString(
            '<tenant-signature-pse-index>',
            (string) file_get_contents(resource_path('views/tenant/companies/form.blade.php'))
        );
    }

    private function rows(string $table): array
    {
        $payload = require database_path('seeders/data/tenant_initial_data.php');

        return $payload['tables'][$table]['rows'];
    }

    private function descriptionsById(string $table): array
    {
        return array_column($this->rows($table), 'description', 'id');
    }
}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
