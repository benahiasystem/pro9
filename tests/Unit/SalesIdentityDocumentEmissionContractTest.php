<?php

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

class SalesIdentityDocumentEmissionContractTest extends TestCase
{
    /** @test */
    public function every_persistence_entry_point_has_an_active_identity_gate(): void
    {
        $root = dirname(__DIR__, 2);
        $expected = [
            'app/CoreFacturalo/Facturalo.php' => 'SalesCustomerIdentityPolicy::assertCustomerAllowed',
            'app/CoreFacturalo/Requests/Inputs/DocumentInput.php' => 'SalesCustomerIdentityPolicy::assertCustomerAllowed',
            'app/CoreFacturalo/Requests/Inputs/DocumentUpdateInput.php' => 'SalesCustomerIdentityPolicy::assertCustomerAllowed',
            'app/CoreFacturalo/Requests/Api/Validation/DocumentValidation.php' => 'SalesCustomerIdentityPolicy::assertIdentityTypeAllowed',
            'app/Http/Controllers/Tenant/SaleNoteController.php' => 'SalesCustomerIdentityPolicy::assertCustomerAllowed',
            'app/Http/Controllers/Tenant/Api/SaleNoteController.php' => 'SalesCustomerIdentityPolicy::assertIdentityTypeAllowed',
            'modules/Ecommerce/Http/Controllers/CulqiController.php' => 'IdentityDocumentType::salesEmissionValidationRule()',
            'modules/Ecommerce/Http/Controllers/EcommerceController.php' => 'IdentityDocumentType::salesEmissionValidationRule()',
            'modules/Restaurant/Http/Controllers/RestaurantController.php' => 'IdentityDocumentType::salesEmissionValidationRule()',
            'modules/WhatsAppBot/Services/Tools/CreateDocumentTool.php' => 'SalesCustomerIdentityPolicy::assertCustomerAllowed',
            'modules/WhatsAppBot/Services/Validators/PolicyValidator.php' => 'SalesCustomerIdentityPolicy::assertIdentityTypeAllowed',
        ];
        foreach ($expected as $path => $fragment) {
            self::assertStringContainsString($fragment, (string) file_get_contents($root.'/'.$path), $path);
        }
    }

    /** @test */
    public function every_sales_customer_channel_uses_the_active_identity_scope(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            'app/Http/Controllers/Controller.php',
            'app/Http/Controllers/SearchCustomerController.php',
            'app/Http/Controllers/Tenant/DocumentController.php',
            'app/Http/Controllers/Tenant/PosController.php',
            'app/Http/Controllers/Tenant/SaleNoteController.php',
            'app/Http/Controllers/Tenant/Api/CompanyController.php',
            'app/Http/Controllers/Tenant/Api/MobileController.php',
            'modules/Hotel/Http/Controllers/HotelRentController.php',
            'modules/Order/Http/Controllers/OrderFormController.php',
            'modules/Order/Http/Controllers/OrderNoteController.php',
            'modules/Sale/Http/Controllers/GenerateDocumentController.php',
            'modules/Sale/Http/Controllers/TechnicalServiceController.php',
            'modules/Store/Http/Controllers/StoreController.php',
            'modules/WhatsAppBot/Services/Tools/LookupPersonTool.php',
        ];
        foreach ($paths as $path) {
            self::assertStringContainsString('whereSalesIdentityActive', (string) file_get_contents($root.'/'.$path), $path);
        }
        foreach (['modules/Ecommerce/Http/Controllers/EcommerceController.php', 'modules/Restaurant/Http/Controllers/RestaurantController.php'] as $path) {
            self::assertStringContainsString('whereSalesEmissionActive', (string) file_get_contents($root.'/'.$path), $path);
        }
    }

    /** @test */
    public function sales_interfaces_do_not_restore_identity_receipt_or_amount_pairing(): void
    {
        $root = dirname(__DIR__, 2);
        $sources = [
            'modules/Ecommerce/Resources/assets/js/frontend/cart-app.js',
            'modules/Ecommerce/Resources/views/cart/detail2.blade.php',
            'resources/js/views/tenant/pos/partials/fast_payment_garage.vue',
            'modules/WhatsAppBot/Services/Validators/PolicyValidator.php',
            'modules/WhatsAppBot/Prompts/SystemPrompt.php',
        ];
        $forbidden = [
            'Para emitir factura el cliente debe tener RIF',
            'factura_requires_ruc',
            'total > 700 || typeDocument',
            'compras mayores a Bs. 700',
        ];
        foreach ($sources as $path) {
            $source = (string) file_get_contents($root.'/'.$path);
            foreach ($forbidden as $fragment) {
                self::assertStringNotContainsString($fragment, $source, $path);
            }
        }
    }

    /** @test */
    public function massive_invoice_template_and_mapper_require_an_active_receiver_identity_type(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root.'/app/Services/MassiveInvoiceService.php');
        self::assertStringContainsString('$row[21]', $service);
        self::assertStringContainsString('IdentityDocument::activeIds()', $service);

        $sheet = IOFactory::load($root.'/public/formats/formato_facturas_masivas.xlsx')->getActiveSheet();
        self::assertSame('Tipo_documento_identidad_receptor', (string) $sheet->getCell('V1')->getValue());
        self::assertSame('6', (string) $sheet->getCell('V2')->getValue());
        self::assertSame('1', (string) $sheet->getCell('V3')->getValue());
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($root.'/public/formats/formato_facturas_masivas.xlsx'));
        $worksheetXml = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        self::assertStringContainsString('sqref="V2:V1000"', $worksheetXml);
        self::assertMatchesRegularExpression(
            '/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?formula1>"0,1,6,7,E,C,G,R"<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?formula1>/',
            $worksheetXml
        );
    }
}
