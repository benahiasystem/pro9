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
        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('80');

        self::assertTrue(true);
    }

    /** @test */
    public function it_rejects_a_new_receipt(): void
    {
        $this->expectException(ValidationException::class);

        SalesDocumentTypePolicy::assertNewFiscalDocumentAllowed('03');
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
                ['establishment_id' => 7, 'document_type_id' => '07', 'number' => 'FC01'],
                ['establishment_id' => 7, 'document_type_id' => '08', 'number' => 'FD01'],
                ['establishment_id' => 7, 'document_type_id' => '20', 'number' => 'RR01'],
                ['establishment_id' => 7, 'document_type_id' => '09', 'number' => 'TT01'],
                ['establishment_id' => 7, 'document_type_id' => '80', 'number' => 'NV01'],
                ['establishment_id' => 7, 'document_type_id' => 'U2', 'number' => 'AI01'],
                ['establishment_id' => 7, 'document_type_id' => 'U3', 'number' => 'AS01'],
                ['establishment_id' => 7, 'document_type_id' => 'U4', 'number' => 'AT01'],
            ],
            $series
        );
    }
}
// ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
