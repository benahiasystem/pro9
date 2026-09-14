<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOrderStockGuard;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOrderStockGuardTest extends FiscalDatabaseTestCase
{
    public function test_unbilled_order_can_continue_its_stock_workflow(): void
    {
        FiscalOrderStockGuard::assertCanMutate(25, null, $this->db);
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
    }

    public function test_existing_invoice_link_blocks_direct_stock_changes(): void
    {
        $this->expectException(\DomainException::class);
        FiscalOrderStockGuard::assertCanMutate(25, 'invoice-external-id', $this->db);
    }

    public function test_committed_invoice_blocks_changes_before_order_link_is_saved(): void
    {
        $sequence = $this->repository->createSequence('01', 'P', 1, 1);
        $reservation = $this->repository->reserve($sequence, 'ecommerce-order-25-invoice', str_repeat('a', 64), 1);
        $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update(['document_id' => 42]);
        FiscalOrderStockGuard::assertCanMutate(26, null, $this->db);
        $this->expectException(\DomainException::class);
        FiscalOrderStockGuard::assertCanMutate(25, null, $this->db);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
