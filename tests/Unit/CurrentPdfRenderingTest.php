<?php

namespace Tests\Unit;

use App\CoreFacturalo\Template;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Tests\TestCase;

class CurrentPdfRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::connection('tenant')->create('configurations', function ($table): void {
            $table->increments('id');
            $table->boolean('change_decimal_quantity_unit_price_pdf')->default(false);
            $table->unsignedTinyInteger('decimal_quantity_unit_price_pdf')->default(2);
            $table->boolean('show_seller_in_pdf')->default(false);
            $table->boolean('show_bank_accounts_in_pdf')->default(false);
            $table->boolean('legend_footer_sale')->default(false);
            $table->boolean('enabled_guarantee_fund')->default(false);
            $table->boolean('select_establishment_bank_account')->default(false);
            $table->boolean('print_new_line_to_observation')->default(false);
        });
        Schema::connection('tenant')->create('companies', function ($table): void {
            $table->increments('id');
            $table->string('fiscal_environment');
        });
        Schema::connection('tenant')->create('cat_identity_document_types', function ($table): void {
            $table->string('id')->primary();
        });
        Schema::connection('tenant')->create('bank_accounts', function ($table): void {
            $table->increments('id');
            $table->boolean('show_in_documents')->default(false);
            $table->unsignedInteger('establishment_id')->nullable();
        });

        DB::connection('tenant')->table('configurations')->insert(['id' => 1]);
        DB::connection('tenant')->table('companies')->insert([
            'id' => 1,
            'fiscal_environment' => 'demo',
        ]);
    }

    public function test_default_invoice_template_produces_a_readable_pdf(): void
    {
        $company = new Fluent([
            'name' => 'Empresa Venezolana de Prueba, C.A.',
            'trade_name' => 'Pro9 Prueba',
            'number' => 'J-12345678-9',
            'logo' => null,
        ]);
        $document = $this->documentFixture();

        $html = (new Template())->pdf('default', 'invoice', $company, $document, 'a4', [
            'is_preview' => false,
            'enabled_price_items_dispatch' => false,
        ]);

        self::assertStringContainsString('FACTURA', $html);
        self::assertStringContainsString('J-12345678-9', $html);
        self::assertStringContainsString('IVA:', $html);
        self::assertStringNotContainsStringIgnoringCase('BOLETA', $html);
        self::assertStringNotContainsStringIgnoringCase('SUNAT', $html);
        self::assertStringNotContainsString('Código Hash:', $html);
        self::assertStringNotContainsString('data:image/png;base64', $html);

        $mpdf = new Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'margin_top' => 15,
            'margin_right' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'default_font' => 'arial',
        ]);
        $stylesheet = file_get_contents(app_path('CoreFacturalo/Templates/pdf/default/style.css'));
        $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
        $pdf = $mpdf->Output('', 'S');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10000, strlen($pdf));

        $path = getenv('PRO9_PDF_FIXTURE_PATH') ?: sys_get_temp_dir().'/pro9-current-invoice.pdf';
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($path, $pdf);
        self::assertFileExists($path);
    }

    private function documentFixture(): CurrentPdfDocumentFixture
    {
        $location = new Fluent(['description' => 'Caracas']);
        $personType = new Fluent(['enabled_description_person_type' => false]);
        $customer = new Fluent([
            'name' => 'Cliente Venezolano, C.A.',
            'number' => 'J-87654321-0',
            'identity_document_type_id' => '6',
            'identity_document_type' => new Fluent(['description' => 'RIF']),
            'address' => 'Avenida Principal',
            'district_id' => '-',
            'department_id' => '14',
            'district' => $location,
            'province' => new Fluent(['description' => 'Libertador']),
            'department' => new Fluent(['description' => 'Distrito Capital']),
            'person_type' => $personType,
        ]);
        $item = new Fluent([
            'internal_id' => 'PRUEBA-001',
            'unit_type_id' => 'NIU',
            'description' => 'Producto gravado de prueba',
            'model' => null,
            'lots' => null,
            'presentation' => null,
            'is_set' => false,
            'used_points_for_exchange' => null,
            'IdLoteSelected' => null,
        ]);
        $row = new CurrentPdfItemFixture([
            'item' => $item,
            'm_item' => new Fluent(['brand' => null]),
            'relation_item' => new Fluent(['date_of_due' => null]),
            'quantity' => 1,
            'unit_price' => 116,
            'total' => 116,
            'name_product_pdf' => null,
            'discounts' => null,
            'charges' => null,
            'attributes' => null,
        ]);

        return new CurrentPdfDocumentFixture([
            'establishment_id' => 1,
            'establishment' => new Fluent([
                'logo' => null,
                'address' => 'Avenida Principal',
                'district_id' => '-',
                'province_id' => '-',
                'department_id' => '-',
                'telephone' => '0212-5550000',
                'email' => 'facturacion@example.test',
            ]),
            'customer' => $customer,
            'person' => $customer,
            'invoice' => new Fluent(['date_of_due' => Carbon::parse('2026-09-10')]),
            'note' => null,
            'itinerant' => null,
            'series' => 'FF01',
            'number' => 1,
            'document_type_id' => '01',
            'document_type' => new Fluent(['id' => '01', 'description' => 'FACTURA']),
            'state_type' => new Fluent(['id' => '01']),
            'currency_type_id' => 'VES',
            'currency_type' => new Fluent(['id' => 'VES', 'symbol' => 'Bs.', 'description' => 'Bolívares']),
            'date_of_issue' => Carbon::parse('2026-09-10'),
            'time_of_issue' => '10:30:00',
            'items' => new Collection([$row]),
            'payments' => new Collection(),
            'fee' => new Collection(),
            'legends' => [new Fluent(['code' => '1000', 'value' => 'CIENTO DIECISÉIS'])],
            'additional_information' => [],
            'reference_guides' => new Collection(),
            'guides' => null,
            'prepayments' => null,
            'dispatch' => null,
            'transport' => null,
            'quotation' => null,
            'retention' => null,
            'perception' => null,
            'charges' => null,
            'payment_condition_id' => '01',
            'payment_condition' => null,
            'payment_method_type_id' => null,
            'total' => 116,
            'subtotal' => 116,
            'total_taxed' => 100,
            'total_igv' => 16,
            'total_exportation' => 0,
            'total_free' => 0,
            'total_unaffected' => 0,
            'total_exonerated' => 0,
            'total_discount' => 0,
            'total_discount_with_igv' => 0,
            'total_charge' => 0,
            'total_prepayment' => 0,
            'total_pending_payment' => 0,
            'has_prepayment' => false,
            'was_deducted_prepayment' => false,
            'purchase_order' => null,
            'quotation_id' => null,
            'consigned_id' => null,
            'reference_data' => null,
            'plate_number' => null,
            'folio' => null,
            'terms_condition' => null,
            'custom_fields_data' => null,
            'qr' => null,
            'hash' => null,
            'seller' => null,
        ]);
    }
}

class CurrentPdfDocumentFixture extends Fluent
{
    public function load($relations): self
    {
        return $this;
    }

    public function isPointSystem(): bool
    {
        return false;
    }

    public function getPointsBySale(): int
    {
        return 0;
    }
}

class CurrentPdfItemFixture extends Fluent
{
    public function getUnitPrice(bool $isPreview, $document): float
    {
        return (float) $this->unit_price;
    }

    public function generalApplyNumberFormat($value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
