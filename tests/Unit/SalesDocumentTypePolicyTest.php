<?php

namespace Tests\Unit;

use App\Services\SalesDocumentTypePolicy;
use App\Services\SeriesCodeGenerator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
class SalesDocumentTypePolicyTest extends TestCase
{
    /** @test */
    public function it_allows_the_new_sales_document_types(): void
    {
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('01');
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('07');
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('08');
        SalesDocumentTypePolicy::assertAllowedForFlow('80', SalesDocumentTypePolicy::PRIMARY_DOCUMENT_TYPE_IDS);

        self::assertTrue(true);
    }

    /** @test */
    public function it_rejects_a_new_receipt(): void
    {
        $this->expectException(ValidationException::class);

        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('03');
    }

    /** @dataProvider nonInvoiceTypes */
    public function test_invoice_pipeline_rejects_types_handled_by_other_flows(?string $type): void
    {
        $this->expectException(ValidationException::class);
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed($type);
    }

    public static function nonInvoiceTypes(): array
    {
        return [['80'], ['09'], ['20'], ['99'], [null]];
    }

    /** @test */
    public function it_enforces_the_document_types_of_each_flow(): void
    {
        SalesDocumentTypePolicy::assertAllowedForFlow(
            'nv',
            SalesDocumentTypePolicy::TECHNICAL_SERVICE_DOCUMENT_TYPE_IDS
        );

        $this->expectException(ValidationException::class);
        SalesDocumentTypePolicy::assertAllowedForFlow(
            '03',
            SalesDocumentTypePolicy::PRIMARY_DOCUMENT_TYPE_IDS
        );
    }

    /** @test */
    public function it_blocks_new_receipt_series_and_keeps_invoice_series(): void
    {
        self::assertTrue(SalesDocumentTypePolicy::isProhibitedNewSeries('03', 'BB01'));
        self::assertTrue(SalesDocumentTypePolicy::isProhibitedNewSeries('07', 'BC01'));
        self::assertTrue(SalesDocumentTypePolicy::isProhibitedNewSeries('08', 'BD01'));
        self::assertFalse(SalesDocumentTypePolicy::isProhibitedNewSeries('01', 'FF01'));
        self::assertFalse(SalesDocumentTypePolicy::isProhibitedNewSeries('07', 'FC01'));
        self::assertFalse(SalesDocumentTypePolicy::isProhibitedNewSeries('08', 'FD01'));
    }

    /** @test */
    public function new_tenants_receive_sales_and_warehouse_internal_series(): void
    {
        $series = SeriesCodeGenerator::defaultTenantSeries(7);

        self::assertSame(
            [
                ['establishment_id' => 7, 'document_type_id' => '01', 'number' => 'FF01'],
                ['establishment_id' => 7, 'document_type_id' => 'FE', 'number' => 'FE01'],
                ['establishment_id' => 7, 'document_type_id' => '07', 'number' => 'FC01'],
                ['establishment_id' => 7, 'document_type_id' => '08', 'number' => 'FD01'],
                ['establishment_id' => 7, 'document_type_id' => '80', 'number' => 'NV01'],
                ['establishment_id' => 7, 'document_type_id' => 'U2', 'number' => 'AI01'],
                ['establishment_id' => 7, 'document_type_id' => 'U3', 'number' => 'AS01'],
                ['establishment_id' => 7, 'document_type_id' => 'U4', 'number' => 'AT01'],
            ],
            $series
        );
    }

    /** @test */
    public function series_catalog_has_the_required_seniat_and_internal_order(): void
    {
        $actual = array_map(static function (array $type): array {
            return [
                $type['document_type_id'],
                $type['label'],
                $type['category'],
                $type['prefix'],
                $type['sort_order'],
            ];
        }, SeriesCodeGenerator::SERIES_TYPES);

        self::assertSame([
            ['01', 'FACTURA', 'basic', 'FF', 10],
            ['FE', 'FACTURA DE EXPORTACIÓN', 'basic', 'FE', 20],
            ['07', 'NOTA DE CRÉDITO', 'basic', 'FC', 30],
            ['08', 'NOTA DE DÉBITO', 'basic', 'FD', 40],
            ['20', 'COMPROBANTE DE RETENCIÓN DE IVA', 'advanced', 'RI', 50],
            ['ISLR', 'COMPROBANTE DE RETENCIÓN DE I.S.L.R.', 'advanced', 'RL', 60],
            ['09', 'ORDEN DE ENTREGA', 'advanced', 'TT', 70],
            ['CBU', 'CERTIFICACIÓN DE COMPRA DE BIENES USADOS', 'advanced', 'CB', 80],
            ['80', 'NOTA DE VENTA', 'internal', 'NV', 90],
            ['U2', 'NOTA DE INGRESO ALMACÉN', 'internal', 'AI', 100],
            ['U3', 'NOTA DE SALIDA ALMACÉN', 'internal', 'AS', 110],
            ['U4', 'NOTA DE TRANSFERENCIA ALMACÉN', 'internal', 'AT', 120],
        ], $actual);

        $seed = require base_path('database/seeders/data/tenant_initial_data.php');
        $seedIds = array_map('strval', array_column($seed['tables']['cat_document_types']['rows'], 'id'));
        foreach (SeriesCodeGenerator::SERIES_TYPES as $type) {
            self::assertContains($type['document_type_id'], $seedIds);
            self::assertSame(
                $type,
                SeriesCodeGenerator::typeByNumber($type['prefix'].'01', $type['document_type_id'])
            );
        }

        self::assertNull(SeriesCodeGenerator::typeByDocumentType('NE76'));
        self::assertNull(SeriesCodeGenerator::typeByDocumentType('04'));
        self::assertNull(SeriesCodeGenerator::typeByDocumentType('40'));
    }

    /** @test */
    public function series_ui_uses_seniat_category_labels_and_catalog_order(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/views/tenant/establishments/partials/series.vue'));

        self::assertStringContainsString('Básico SENIAT', $source);
        self::assertStringContainsString('Avanzado SENIAT', $source);
        self::assertStringContainsString('a.sort_order', $source);
        self::assertStringNotContainsString('Básico (SUNAT)', $source);
        self::assertStringNotContainsString('Avanzado (SUNAT)', $source);
    }
}
// ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
