<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOrderConversion;
use App\Services\Fiscal\FiscalOrderSalesNoteContext;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOrderSalesNoteContextTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert(['id' => 5, 'establishment_id' => 1, 'type' => 'admin', 'active' => 1]);
        $this->db->statement('CREATE TABLE orders (id INTEGER PRIMARY KEY, purchase TEXT, stock_discounted INTEGER, stock_reservation TEXT, document_external_id TEXT, deleted_at TEXT)');
        $this->db->statement('CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, order_id INTEGER)');
        $this->db->statement('CREATE TABLE item_warehouse (id INTEGER PRIMARY KEY, item_id INTEGER, warehouse_id INTEGER, stock TEXT)');
        $this->db->table('item_warehouse')->insert(['id' => 1, 'item_id' => 4, 'warehouse_id' => 3, 'stock' => '8']);
        $this->db->table('orders')->insert(['id' => 25, 'purchase' => json_encode(['total' => 232]), 'stock_discounted' => 1,
            'stock_reservation' => json_encode(['establishment_id' => 1, 'warehouses' => [4 => 3], 'movements' => [['id' => 1, 'item_id' => 4, 'warehouse_id' => 3, 'quantity' => '2']]])]);
    }

    private function prepare(): array
    {
        return FiscalOrderSalesNoteContext::prepare($this->db,
            ['id' => 25, 'emitter_user_id' => 5, 'purchase_fingerprint' => FiscalOrderConversion::fingerprint(['total' => 232])],
            ['order_id' => 25, 'establishment_id' => 1, 'items' => [['item_id' => 4, 'warehouse_id' => 1]]]);
    }

    public function test_new_receipt_releases_stock_and_preserves_reserved_warehouse(): void
    {
        $result = $this->db->transaction(fn () => $this->prepare());
        $this->assertNull($result['existing_id']);
        $this->assertSame(5, $result['inputs']['user_id']);
        $this->assertSame(3, $result['inputs']['items'][0]['warehouse_id']);
        $this->assertSame('10.0000', $this->db->table('item_warehouse')->value('stock'));
        $this->assertSame(0, (int) $this->db->table('orders')->value('stock_discounted'));
    }

    public function test_existing_receipt_is_returned_without_releasing_stock_again(): void
    {
        $this->db->table('sale_notes')->insert(['id' => 10, 'order_id' => 25]);
        $result = $this->db->transaction(fn () => $this->prepare());
        $this->assertSame(10, $result['existing_id']);
        $this->assertSame('8', $this->db->table('item_warehouse')->value('stock'));
    }

    public function test_receipt_failure_restores_previous_reservation(): void
    {
        try {
            $this->db->transaction(function () {
                $this->prepare();
                throw new \RuntimeException('receipt failed');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('receipt failed', $exception->getMessage());
        }
        $this->assertSame('8', $this->db->table('item_warehouse')->value('stock'));
        $this->assertSame(1, (int) $this->db->table('orders')->value('stock_discounted'));
    }

    public function test_invoiced_order_cannot_generate_an_additional_sales_note(): void
    {
        $this->db->table('orders')->update(['document_external_id' => 'invoice-uuid']);
        $this->expectException(\DomainException::class);
        $this->db->transaction(fn () => $this->prepare());
    }

    public function test_changed_source_cannot_generate_receipt(): void
    {
        $this->db->table('orders')->update(['purchase' => json_encode(['total' => 500])]);
        $this->expectException(\DomainException::class);
        $this->db->transaction(fn () => $this->prepare());
    }

    public function test_emitter_from_another_branch_does_not_release_reservation(): void
    {
        $this->db->table('users')->where('id', 5)->update(['establishment_id' => 2]);
        try {
            $this->db->transaction(fn () => $this->prepare());
            $this->fail('Wrong branch accepted');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('emisor activo', $exception->getMessage());
        }
        $this->assertSame('8', $this->db->table('item_warehouse')->value('stock'));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
