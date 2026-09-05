<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SaleNotesInvoiceCustomerContractTest extends TestCase
{
    /** @test */
    public function invoice_preserves_the_customer_preloaded_from_multiple_sale_notes_regardless_of_identity_type(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root.'/resources/js/views/tenant/documents/invoice_generate.vue');
        $skill = file_get_contents($root.'/.codex/skills/gestionar-clientes-venezuela/SKILL.md');

        self::assertNotFalse($source);
        self::assertNotFalse($skill);
        self::assertStringContainsString('this.preloadedCustomerId = client.id;', $source);
        self::assertStringContainsString('await this.reloadDataCustomers(client.id);', $source);
        self::assertStringContainsString('return Boolean(this.preloadedCustomerId);', $source);
        self::assertStringContainsString('this.filterCustomers();', $source);
        self::assertStringContainsString('localStorage.removeItem("client");', $source);
        self::assertStringContainsString('admitir para Facturas todos los tipos del catálogo venezolano', $skill);
        self::assertStringContainsString('aunque su tipo de identidad no sea `Juridico`', $skill);
    }

    /** @test */
    public function invoices_accept_each_venezuelan_identity_document_type_in_web_api_and_customer_searches(): void
    {
        $root = dirname(__DIR__, 2);
        $webValidation = file_get_contents($root.'/app/CoreFacturalo/Requests/Web/Validation/Functions.php');
        $apiValidation = file_get_contents($root.'/app/CoreFacturalo/Requests/Api/Validation/Functions.php');
        $documentController = file_get_contents($root.'/app/Http/Controllers/Tenant/DocumentController.php');
        $invoice = file_get_contents($root.'/resources/js/views/tenant/documents/invoice_generate.vue');

        self::assertStringContainsString('IdentityDocument::ids()', $webValidation);
        self::assertStringContainsString('IdentityDocument::ids()', $apiValidation);
        self::assertStringContainsString('$identity_document_type_id = IdentityDocument::ids();', $documentController);
        self::assertStringContainsString('this.customers = this.all_customers;', $invoice);
    }
}
