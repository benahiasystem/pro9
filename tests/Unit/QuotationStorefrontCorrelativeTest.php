<?php

namespace Tests\Unit;

use App\Models\Tenant\Quotation;
use Carbon\Carbon;
use Tests\TestCase;

class QuotationStorefrontCorrelativeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
    }

    public function test_storefront_code_uses_ecommerce_number_not_id(): void
    {
        $quotation = new Quotation();
        $quotation->syncOriginal();
        $quotation->exists = false;
        $quotation->id = 12;
        $quotation->source = Quotation::SOURCE_ECOMMERCE;
        $quotation->series = Quotation::SERIES_ECOMMERCE;
        $quotation->number = 5;
        $quotation->number_year = 2026;
        $quotation->prefix = 'COT';
        $quotation->date_of_issue = Carbon::parse('2026-07-22');

        $this->assertSame('COT-TV-2026-0005', $quotation->storefront_code);
        $this->assertSame('COT-TV-2026-0005', $quotation->identifier);
        $this->assertSame('COT-TV-2026-0005', $quotation->pdf_title);
        $this->assertSame('Tienda virtual', $quotation->source_label);
        $this->assertTrue($quotation->isFromEcommerce());
    }

    public function test_admin_identifier_keeps_prefix_id(): void
    {
        $quotation = new Quotation();
        $quotation->id = 12;
        $quotation->source = Quotation::SOURCE_ADMIN;
        $quotation->number = 0;
        $quotation->prefix = 'COT';
        $quotation->date_of_issue = Carbon::parse('2026-07-22');

        $this->assertSame('COT-12', $quotation->identifier);
        $this->assertSame('COT-00000012', $quotation->pdf_title);
        $this->assertSame('Empresa', $quotation->source_label);
        $this->assertFalse($quotation->isFromEcommerce());
    }

    public function test_source_scopes_constants(): void
    {
        $this->assertSame('admin', Quotation::SOURCE_ADMIN);
        $this->assertSame('ecommerce', Quotation::SOURCE_ECOMMERCE);
        $this->assertSame('COTV', Quotation::SERIES_ECOMMERCE);
    }
}
