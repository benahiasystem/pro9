<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SaleNotesInvoiceCustomerContractTest extends TestCase
{
    /** @test */
    public function invoice_preserves_only_an_eligible_customer_preloaded_from_sale_notes(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string) file_get_contents($root.'/resources/js/views/tenant/documents/invoice_generate.vue');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Tenant/DocumentController.php');
        $skill = (string) file_get_contents($root.'/.codex/skills/gestionar-clientes-venezuela/SKILL.md');

        self::assertStringContainsString('this.preloadedCustomerId = client.id;', $source);
        self::assertStringContainsString('await this.reloadDataCustomers(client.id);', $source);
        self::assertStringContainsString('this.ensurePreloadedCustomerInList();', $source);
        self::assertStringContainsString('->whereSalesIdentityActive()', $controller);
        self::assertStringContainsString('Un tipo inactivo debe bloquear la emisión también en conversiones', $skill);
    }

    /** @test */
    public function invoice_sources_no_longer_pair_a_receipt_with_one_identity_type(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['invoice.vue', 'invoice_generate.vue', 'invoiceupdate.vue', 'invoicetensu.vue'] as $file) {
            $source = (string) file_get_contents($root.'/resources/js/views/tenant/documents/'.$file);
            self::assertStringContainsString('this.customers = this.all_customers', $source, $file);
        }

        $garage = (string) file_get_contents($root.'/resources/js/views/tenant/pos/partials/fast_payment_garage.vue');
        self::assertStringNotContainsString('Para emitir factura el cliente debe tener RIF', $garage);
    }
}
