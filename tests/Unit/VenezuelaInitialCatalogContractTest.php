<?php

namespace Tests\Unit;

use App\Http\Requests\Tenant\DispatchRequest;
use App\Http\Requests\Tenant\TransferReasonTypeRequest;
use App\Models\Tenant\Catalogs\TransferReasonType;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\PaymentMethodType;
use Modules\Sale\Http\Controllers\PaymentMethodTypeController;
use Modules\Sale\Http\Requests\PaymentMethodTypeRequest;
use Modules\Finance\Http\Controllers\PaymentMethodTypeController as FinancePaymentMethodTypeController;
use Illuminate\Http\Request;
use Modules\Order\Http\Requests\OrderFormRequest;
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
                '01' => 'FACTURA', 'FE' => 'FACTURA DE EXPORTACIÓN',
                '07' => 'NOTA DE CRÉDITO', '08' => 'NOTA DE DÉBITO',
                '20' => 'COMPROBANTE DE RETENCIÓN DE IVA',
                'ISLR' => 'COMPROBANTE DE RETENCIÓN DE I.S.L.R.',
                'ARCV' => 'COMPROBANTE DE RETENCIONES VARIAS ARCV',
                '09' => 'ORDEN DE ENTREGA', 'CBU' => 'CERTIFICACIÓN DE COMPRA DE BIENES USADOS',
                '80' => 'NOTA DE VENTA', 'U2' => 'NOTA DE INGRESO ALMACÉN',
                'U3' => 'NOTA DE SALIDA ALMACÉN', 'U4' => 'NOTA DE TRANSFERENCIA ALMACÉN',
                'NE76' => 'NOTA DE ENTRADA',
            ],
            'cat_identity_document_types' => [
                '0' => 'Doc.sin.rif', '1' => 'Venezolano', '6' => 'Juridico', '7' => 'Pasaporte',
                'E' => 'Extranjero', 'C' => 'Comuna', 'G' => 'Gubernamental', 'R' => 'Firma Personal',
                'ND' => 'No Domiciliado',
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
            'cat_operation_types' => [
                '0101' => 'Venta interna', '0200' => 'Exportación de Bienes',
                '0201' => 'Exportación FOB', '0202' => 'Exportación CIF', '0203' => 'Exportación EXW',
            ],
            // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
            'cat_product_origins' => [
                1 => 'Nacional', 2 => 'Importado', 3 => 'Nacional e Importado',
            ],
            'cat_product_types' => [
                1 => 'Alcohol', 2 => 'Cigarrillos',
            ],
            'cat_providers_types' => [
                1 => 'Normal', 2 => 'Sin RIF', 3 => 'No Residenciado', 4 => 'No Domiciliado',
            ],
            'cat_transactions_types' => [
                '01' => 'Registro', '02' => 'Complemento', '03' => 'Anulación',
                '04' => 'Ajuste', '98' => 'ND por IGTF',
                '99' => 'Solo cuando la factura es a Terceros',
            ],
            'cat_special_tax_regime' => [
                1 => 'Zonas económicas especiales',
                2 => 'Zona franca de Paraguaná',
                3 => 'Zona libre de Paraguaná',
                4 => 'Puerto libre Santa Elena de Uairén',
                5 => 'Zona Libre de Mérida',
                6 => 'Puerto libre Estado Nueva Esparta',
                7 => 'Dutty Free',
            ],
            'cat_taxation_products' => [
                1 => 'Tierra Firme', 2 => 'Régimen Especial',
            ],
            // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
            'cat_transfer_reason_types' => [
                '04' => 'Traslado entre almacenes propios',
                '21' => 'Reparación o perfeccionamiento',
                '22' => 'Almacenes, depósitos o bodegas de otros',
                '23' => 'Tránsito aduanero',
                '24' => 'Otras causas (especifique)',
            ],
        ];

        foreach ($expected as $table => $rows) {
            self::assertSame($rows, $this->descriptionsById($table), $table);
        }

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $validatorSource = (string) file_get_contents(base_path('.codex/skills/mantener-catalogos-fiscales-venezuela/scripts/validate_catalog_contract.php'));
        self::assertStringContainsString("'cat_document_types' => 14", $validatorSource);
        self::assertStringContainsString("'ARCV', '09', 'CBU'", $validatorSource);
        $applySource = (string) file_get_contents(base_path('.codex/skills/mantener-catalogos-fiscales-venezuela/scripts/apply_catalog_contract.php'));
        self::assertStringContainsString("\$row('ARCV', 'COMPROBANTE DE RETENCIONES VARIAS ARCV'", $applySource);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        $transactions = $this->rows('cat_transactions_types');
        self::assertSame(['01', '02', '03', '04', '98', '99'], array_column($transactions, 'id'));
        foreach ($transactions as $transaction) {
            self::assertSame(1, (int) $transaction['active']);
        }

        self::assertSame([
            ['0101', 1, 0, null],
            ['0200', 0, 1, null],
            ['0201', 0, 1, 'FOB'],
            ['0202', 0, 1, 'CIF'],
            ['0203', 0, 1, 'EXW'],
        ], array_map(static fn (array $row): array => [
            $row['id'], $row['active'], $row['exportation'], $row['incoterm'],
        ], $this->rows('cat_operation_types')));

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $productOrigins = $this->rows('cat_product_origins');
        self::assertSame([1, 2, 3], array_column($productOrigins, 'id'));
        foreach ($productOrigins as $productOrigin) {
            self::assertSame(1, (int) $productOrigin['active']);
            self::assertSame(['id', 'description', 'active'], array_keys($productOrigin));
        }
        $productOriginMigration = file_get_contents(base_path('database/migrations/tenant/2026_08_17_000046_create_cat_product_origins_table.php'));
        self::assertStringContainsString('`id` tinyint unsigned NOT NULL', $productOriginMigration);
        self::assertStringContainsString('`description` varchar(255)', $productOriginMigration);
        self::assertStringContainsString('`active` tinyint(1) NOT NULL', $productOriginMigration);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $productOriginMigration);
        self::assertStringContainsString("DROP TABLE IF EXISTS `cat_product_origins`", $productOriginMigration);

        $productTypes = $this->rows('cat_product_types');
        self::assertSame([1, 2], array_column($productTypes, 'id'));
        foreach ($productTypes as $productType) {
            self::assertSame(1, (int) $productType['active']);
            self::assertSame(['id', 'description', 'active'], array_keys($productType));
        }
        $productTypeMigration = file_get_contents(base_path('database/migrations/tenant/2026_08_17_000045_create_cat_product_types_table.php'));
        self::assertStringContainsString('`id` tinyint unsigned NOT NULL', $productTypeMigration);
        self::assertStringContainsString('`description` varchar(255)', $productTypeMigration);
        self::assertStringContainsString('`active` tinyint(1) NOT NULL', $productTypeMigration);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $productTypeMigration);
        self::assertStringContainsString("DROP TABLE IF EXISTS `cat_product_types`", $productTypeMigration);

        $taxationProducts = $this->rows('cat_taxation_products');
        self::assertSame([1, 2], array_column($taxationProducts, 'id'));
        foreach ($taxationProducts as $taxationProduct) {
            self::assertSame(1, (int) $taxationProduct['active']);
            self::assertSame(['id', 'description', 'active'], array_keys($taxationProduct));
        }
        $taxationProductMigration = file_get_contents(base_path('database/migrations/tenant/2026_08_17_000328_create_cat_taxation_products_table.php'));
        self::assertStringContainsString('`id` tinyint unsigned NOT NULL', $taxationProductMigration);
        self::assertStringContainsString('`description` varchar(255)', $taxationProductMigration);
        self::assertStringContainsString('`active` tinyint(1) NOT NULL', $taxationProductMigration);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $taxationProductMigration);
        self::assertStringContainsString("DROP TABLE IF EXISTS `cat_taxation_products`", $taxationProductMigration);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

        $providers = $this->rows('cat_providers_types');
        self::assertSame([1, 2, 3, 4], array_column($providers, 'id'));
        self::assertSame([null, 'SR', 'NR', 'ND'], array_column($providers, 'code'));
        foreach ($providers as $provider) {
            self::assertSame(1, (int) $provider['active']);
        }

        $regimes = $this->rows('cat_special_tax_regime');
        self::assertSame([1, 2, 3, 4, 5, 6, 7], array_column($regimes, 'id'));
        foreach ($regimes as $regime) {
            self::assertSame(1, (int) $regime['active']);
            self::assertSame(['id', 'description', 'active'], array_keys($regime));
        }

        $transferReasons = $this->rows('cat_transfer_reason_types');
        self::assertSame(['04', '21', '22', '23', '24'], array_column($transferReasons, 'id'));
        foreach ($transferReasons as $transferReason) {
            self::assertSame(1, (int) $transferReason['active']);
            self::assertSame(0, (int) $transferReason['discount_stock']);
        }

        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $transferReasonMigration = file_get_contents(base_path('database/migrations/tenant/2026_08_17_000055_create_cat_transfer_reason_types_table.php'));
        self::assertStringContainsString('`id` varchar(2)', $transferReasonMigration);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $transferReasonMigration);
        self::assertStringNotContainsString('cat_transfer_reason_types_id_index', $transferReasonMigration);
        self::assertStringContainsString('`transfer_reason_type_id` varchar(2)', file_get_contents(base_path('database/migrations/tenant/2026_08_17_000313_create_dispatches_table.php')));
        self::assertStringContainsString('`discount_stock` tinyint(1) NOT NULL DEFAULT \'0\'', file_get_contents(base_path('database/migrations/tenant/2026_08_17_000313_create_dispatches_table.php')));
        $orderFormMigration = file_get_contents(base_path('database/migrations/tenant/2026_08_17_000229_create_order_forms_table.php'));
        self::assertStringContainsString('`transfer_reason_type_id` varchar(2)', $orderFormMigration);
        self::assertStringContainsString('`transfer_reason_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL', $orderFormMigration);

        $dispatchRequest = DispatchRequest::create('/', 'POST', [
            'transfer_reason_type_id' => '24',
        ]);
        self::assertContains('required_if:transfer_reason_type_id,24', $dispatchRequest->rules()['transfer_reason_description']);
        self::assertSame(['04', '21', '22', '23', '24'], TransferReasonType::CONTRACT_IDS);
        self::assertInstanceOf(\Illuminate\Validation\Rules\Exists::class, $dispatchRequest->rules()['transfer_reason_type_id'][2]);
        $otherReason = new TransferReasonType(['id' => '24', 'description' => 'Otras causas (especifique)']);
        self::assertSame('Otras causas (especifique): Caso especial', $otherReason->displayDescription(' Caso especial '));
        $dispatch = new Dispatch(['document_type_id' => '09', 'discount_stock' => true]);
        $dispatch->setRelation('transfer_reason_type', new TransferReasonType(['id' => '21', 'discount_stock' => false]));
        self::assertTrue($dispatch->discountsPhysicalStock(), 'El snapshot no debe cambiar con el valor actual del catálogo.');
        $legacyDispatch = new Dispatch(['document_type_id' => '09']);
        $legacyDispatch->setRelation('transfer_reason_type', new TransferReasonType(['id' => '21', 'discount_stock' => true]));
        self::assertTrue($legacyDispatch->discountsPhysicalStock(), 'Un tenant anterior debe usar la regla histórica si la columna no existe.');
        $legacyReferencedDispatch = new Dispatch(['document_type_id' => '09', 'reference_document_id' => 10]);
        $legacyReferencedDispatch->setRelation('transfer_reason_type', new TransferReasonType(['id' => '21', 'discount_stock' => true]));
        self::assertFalse($legacyReferencedDispatch->discountsPhysicalStock(), 'La compatibilidad no debe duplicar el descuento de un documento relacionado.');
        $closedResponse = (new \App\Http\Controllers\Tenant\TransferReasonTypeController())->destroy('04');
        self::assertSame(409, $closedResponse->getStatusCode());
        self::assertStringContainsString('TRANSFER_REASON_CATALOG_CLOSED', $closedResponse->getContent());
        $protectedUpdate = TransferReasonTypeRequest::create('/', 'POST', [
            'id' => '04',
            'discount_stock' => true,
            'description' => 'Descripción no autorizada',
        ]);
        $protectedResponse = (new \App\Http\Controllers\Tenant\TransferReasonTypeController())->store($protectedUpdate);
        self::assertSame(409, $protectedResponse->getStatusCode());
        $validator = app('validator');
        $descriptionRules = ['transfer_reason_description' => $dispatchRequest->rules()['transfer_reason_description']];
        self::assertTrue($validator->make(['transfer_reason_type_id' => '24'], $descriptionRules)->fails());
        self::assertTrue($validator->make(['transfer_reason_type_id' => '24', 'transfer_reason_description' => 'Caso especial'], $descriptionRules)->passes());
        self::assertTrue($validator->make(['transfer_reason_type_id' => '23'], $descriptionRules)->passes());

        $orderFormRequest = OrderFormRequest::create('/', 'POST', [
            'transfer_reason_type_id' => '24',
        ]);
        self::assertContains('required_if:transfer_reason_type_id,24', $orderFormRequest->rules()['transfer_reason_description']);
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    /** @test */
    public function incoterm_export_types_cannot_be_activated_before_the_export_flow_exists(): void
    {
        $controller = new \App\Http\Controllers\Tenant\OperationTypeController();

        foreach (['0201', '0202', '0203'] as $id) {
            $response = $controller->changeActive($id, '1');
            self::assertSame(409, $response->getStatusCode());
            self::assertSame('EXPORT_OPERATION_DISABLED', json_decode($response->getContent(), true)['code']);
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

        self::assertArrayNotHasKey('cat_payment_method_types', (require base_path('database/seeders/data/tenant_initial_data.php'))['tables']);
    }

    /** @test */
    public function tenant_payment_methods_match_the_venezuelan_contract(): void
    {
        // ######### INICIO CONTRATO MÉTODOS DE PAGO VENEZUELA #########
        self::assertSame([
            ['id' => '01', 'description' => 'Efectivo Bolivares', 'hka_code' => '08', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '02', 'description' => 'Tarjeta de crédito', 'hka_code' => '06', 'has_card' => 1, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '03', 'description' => 'Tarjeta de débito', 'hka_code' => '05', 'has_card' => 1, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '04', 'description' => 'Transferencia Bancaria', 'hka_code' => '03', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 1],
            ['id' => '05', 'description' => 'Crédito a 30 días', 'hka_code' => '99', 'has_card' => 0, 'charge' => null, 'number_days' => 30, 'is_credit' => 1, 'is_cash' => 0, 'is_active' => 1],
            ['id' => '06', 'description' => 'Tarjeta Internacional', 'hka_code' => '99', 'has_card' => 1, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 0],
            ['id' => '07', 'description' => 'Delivery / Pago en Sitio', 'hka_code' => '99', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '09', 'description' => 'Crédito', 'hka_code' => '99', 'has_card' => 1, 'charge' => null, 'number_days' => null, 'is_credit' => 1, 'is_cash' => 0, 'is_active' => 1],
            ['id' => '10', 'description' => 'Efectivo Dólares', 'hka_code' => '09', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '11', 'description' => 'Pago Móvil', 'hka_code' => '02', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '12', 'description' => 'Biopago', 'hka_code' => '99', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 1],
            ['id' => '13', 'description' => 'Zelle', 'hka_code' => '99', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 1, 'is_active' => 0],
            ['id' => '14', 'description' => 'Depósito en cuenta', 'hka_code' => '01', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '15', 'description' => 'Orden de Pago', 'hka_code' => '04', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '16', 'description' => 'Cheques con cláusula «NO NEGOCIABLE», «INTRANSFERIBLES», «NO A LA ORDEN» o equivalente', 'hka_code' => '07', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '17', 'description' => 'Medios de pago usados en comercio exterior', 'hka_code' => '10', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '18', 'description' => 'Transferencias – Comercio exterior', 'hka_code' => '11', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '19', 'description' => 'Cheques bancarios – Comercio exterior', 'hka_code' => '12', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '20', 'description' => 'Orden de pago simple – Comercio exterior', 'hka_code' => '13', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '21', 'description' => 'Orden de pago documentario – Comercio exterior', 'hka_code' => '14', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '22', 'description' => 'Remesa simple – Comercio exterior', 'hka_code' => '15', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '23', 'description' => 'Remesa documentaria – Comercio exterior', 'hka_code' => '16', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '24', 'description' => 'Carta de crédito simple – Comercio exterior', 'hka_code' => '17', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '25', 'description' => 'Carta de crédito documentario – Comercio exterior', 'hka_code' => '18', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
            ['id' => '26', 'description' => 'Otros medios de pago', 'hka_code' => '99', 'has_card' => 0, 'charge' => null, 'number_days' => null, 'is_credit' => 0, 'is_cash' => 0, 'is_active' => 0],
        ], $this->rows('payment_method_types'));
        $methods = $this->rows('payment_method_types');
        $codes = array_values(array_unique(array_column($methods, 'hka_code')));
        sort($codes);
        self::assertSame([
            '01', '02', '03', '04', '05', '06', '07', '08', '09',
            '10', '11', '12', '13', '14', '15', '16', '17', '18', '99',
        ], $codes);
        self::assertSame(7, count(array_filter($methods, static fn (array $row): bool => $row['hka_code'] === '99')));
        self::assertSame([], array_filter(array_slice($methods, 12), static fn (array $row): bool => $row['is_active'] !== 0));
        // ######### FIN CONTRATO MÉTODOS DE PAGO VENEZUELA #########
    }

    /** @test */
    public function initial_venezuelan_payment_methods_cannot_be_deleted(): void
    {
        // ######### INICIO PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########
        self::assertSame([
            '01', '02', '03', '04', '05', '06', '07',
            '09', '10', '11', '12', '13',
            '14', '15', '16', '17', '18', '19', '20',
            '21', '22', '23', '24', '25', '26',
        ], PaymentMethodType::INITIAL_PAYMENT_METHOD_IDS);

        foreach (PaymentMethodType::INITIAL_PAYMENT_METHOD_IDS as $id) {
            self::assertTrue(PaymentMethodType::isInitialPaymentMethodId($id), $id);
        }

        self::assertFalse(PaymentMethodType::isInitialPaymentMethodId('08'));
        self::assertTrue(PaymentMethodType::isInitialPaymentMethodId('14'));

        $response = app(PaymentMethodTypeController::class)->destroy('11');
        self::assertFalse($response['success']);
        self::assertSame('Los métodos de pago iniciales no se pueden eliminar', $response['message']);
        // ######### FIN PROTECCIÓN MÉTODOS INICIALES VENEZUELA #########
    }

    /** @test */
    public function hka_reference_payment_methods_cannot_be_edited_or_activated(): void
    {
        // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
        $edit = PaymentMethodTypeRequest::create('/', 'POST', ['id' => '14', 'is_active' => 1]);
        self::assertSame(409, app(PaymentMethodTypeController::class)->store($edit)->getStatusCode());
        $activate = Request::create('/', 'POST', ['id' => '14', 'is_active' => 1]);
        self::assertSame(409, app(FinancePaymentMethodTypeController::class)->active($activate)->getStatusCode());
        // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
    }

    /** @test */
    public function only_active_attributes_and_the_thirty_expense_reasons_are_seeded(): void
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
            1 => 'Honorarios profesionales',
            2 => 'Publicidad, propaganda y mercadeo',
            3 => 'Comisiones de ventas y corretaje',
            4 => 'Mantenimiento y reparación',
            5 => 'Vigilancia y seguridad',
            6 => 'Limpieza y aseo',
            7 => 'Fletes, transporte y mensajería',
            8 => 'Arrendamiento de inmuebles',
            9 => 'Alquiler de bienes muebles y equipos',
            10 => 'Energía eléctrica',
            11 => 'Agua potable',
            12 => 'Telecomunicaciones, Internet y servicios digitales',
            13 => 'Viáticos, viajes y movilización',
            14 => 'Gastos de representación',
            15 => 'Papelería, útiles y suministros de oficina',
            16 => 'Impuestos, tasas y contribuciones',
            17 => 'Multas, sanciones e intereses de mora',
            18 => 'Gastos sin soporte fiscal válido',
            19 => 'Sueldos, salarios y remuneraciones',
            20 => 'Beneficios laborales y prestaciones sociales',
            21 => 'Aportes patronales: IVSS, FAOV e INCES',
            22 => 'Seguros y pólizas',
            23 => 'Gastos bancarios, comisiones y servicios financieros',
            24 => 'Intereses y gastos de financiamiento',
            25 => 'Depreciación y amortización',
            26 => 'Combustible, lubricantes y peajes',
            27 => 'Repuestos y mantenimiento de vehículos',
            28 => 'Sistemas, software, licencias y suscripciones',
            29 => 'Servicios profesionales técnicos y consultoría',
            30 => 'Otros gastos operativos',
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
