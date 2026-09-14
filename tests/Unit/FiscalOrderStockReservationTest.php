<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOrderStockReservation;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOrderStockReservationTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE orders (id INTEGER PRIMARY KEY, items TEXT, purchase TEXT, stock_discounted INTEGER DEFAULT 0, stock_reservation TEXT, document_external_id TEXT, deleted_at TEXT, updated_at TEXT, status_order_id INTEGER)');
        $this->db->statement('CREATE TABLE warehouses (id INTEGER PRIMARY KEY, establishment_id INTEGER)');
        $this->db->statement('CREATE TABLE items (id INTEGER PRIMARY KEY, unit_type_id TEXT, is_set INTEGER DEFAULT 0)');
        $this->db->statement('CREATE TABLE item_sets (item_id INTEGER, individual_item_id INTEGER, quantity TEXT)');
        $this->db->statement('CREATE TABLE item_warehouse (id INTEGER PRIMARY KEY, item_id INTEGER, warehouse_id INTEGER, stock TEXT)');
        $this->db->table('warehouses')->insert([['id' => 1, 'establishment_id' => 1], ['id' => 2, 'establishment_id' => 2]]);
        $this->db->table('items')->insert([['id' => 1, 'unit_type_id' => 'UND'], ['id' => 2, 'unit_type_id' => 'SERV']]);
        $this->db->table('item_warehouse')->insert([['id' => 1, 'item_id' => 1, 'warehouse_id' => 1, 'stock' => '10'], ['id' => 2, 'item_id' => 1, 'warehouse_id' => 2, 'stock' => '20']]);
        $this->db->table('orders')->insert(['id' => 25, 'items' => json_encode([['id' => 1, 'cantidad' => 2], ['id' => 2, 'cantidad' => 1]])]);
    }

    private function discount(?array $selection = null): void
    {
        FiscalOrderStockReservation::change($this->db, 25, 1, $selection ?? [['id' => 1, 'cantidad' => 2]], 'status_order_id', 3);
    }

    private function stock(): string
    {
        return $this->db->table('item_warehouse')->where('id', 1)->value('stock');
    }

    public function test_discount_and_release_are_idempotent_and_services_do_not_need_stock(): void
    {
        $this->discount();
        $this->discount();
        $this->assertSame('8.0000', $this->stock());
        $snapshot = json_decode($this->db->table('orders')->value('stock_reservation'), true);
        $this->assertCount(1, $snapshot['movements']);
        FiscalOrderStockReservation::change($this->db, 25, 1, null, 'status_order_id', 4);
        FiscalOrderStockReservation::change($this->db, 25, 1, null, 'status_order_id', 4);
        $this->assertSame('10.0000', $this->stock());
        $this->assertNull($this->db->table('orders')->value('stock_reservation'));
    }

    /** @dataProvider invalidSelections */
    public function test_untrusted_selection_cannot_change_stock(array $selection): void
    {
        try {
            $this->discount($selection);
            $this->fail('Invalid selection accepted');
        } catch (\DomainException $exception) {
            $this->assertSame('10', $this->stock());
            $this->assertNull($this->db->table('orders')->value('status_order_id'));
        }
    }

    public static function invalidSelections(): array
    {
        return [[[['id' => 1, 'cantidad' => 8]]], [[['id' => 2, 'cantidad' => 2]]], [[['id' => 1, 'cantidad' => -2]]], [[['id' => 1, 'cantidad' => true]]], [[]]];
    }

    public function test_failed_invoice_after_release_restores_the_reservation(): void
    {
        $this->discount();
        try {
            $this->db->transaction(function () {
                FiscalOrderStockReservation::releaseLocked($this->db, $this->db->table('orders')->find(25), 1);
                $this->assertSame('10.0000', $this->stock());
                throw new \RuntimeException('invoice failed');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('invoice failed', $exception->getMessage());
        }
        $this->assertSame('8.0000', $this->stock());
        $this->assertSame(1, (int) $this->db->table('orders')->value('stock_discounted'));
    }

    public function test_invoice_uses_reserved_warehouse_instead_of_payload_warehouse(): void
    {
        $this->discount();
        $order = $this->db->table('orders')->find(25);
        $input = FiscalOrderStockReservation::applyWarehouses(['items' => [['item_id' => 1, 'warehouse_id' => 2], ['item_id' => 2]]], $order);
        $this->assertSame(1, $input['items'][0]['warehouse_id']);
        $this->assertArrayNotHasKey('warehouse_id', $input['items'][1]);
    }

    public function test_releasing_twice_with_a_stale_order_object_does_not_double_stock(): void
    {
        $this->discount();
        $order = $this->db->table('orders')->find(25);
        $this->db->transaction(function () use ($order) {
            FiscalOrderStockReservation::releaseLocked($this->db, $order, 1);
            FiscalOrderStockReservation::releaseLocked($this->db, $order, 1);
        });
        $this->assertSame('10.0000', $this->stock());
    }

    public function test_insufficient_stock_rolls_back_status_and_reservation(): void
    {
        $this->db->table('item_warehouse')->where('id', 1)->update(['stock' => '1']);
        try {
            $this->discount();
            $this->fail('Insufficient stock accepted');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('insuficientes', $exception->getMessage());
        }
        $this->assertSame('1', $this->stock());
        $this->assertNull($this->db->table('orders')->value('stock_reservation'));
        $this->assertNull($this->db->table('orders')->value('status_order_id'));
    }

    public function test_pack_reserves_components_and_releases_recorded_quantities(): void
    {
        $this->db->table('items')->insert(['id' => 3, 'unit_type_id' => 'UND', 'is_set' => 1]);
        $this->db->table('item_sets')->insert(['item_id' => 3, 'individual_item_id' => 1, 'quantity' => '3']);
        $this->db->table('item_warehouse')->insert(['id' => 3, 'item_id' => 3, 'warehouse_id' => 1, 'stock' => '0']);
        $this->db->table('orders')->update(['items' => json_encode([['id' => 3, 'cantidad' => 2]])]);
        $this->discount([['id' => 3, 'cantidad' => 2]]);
        $this->assertSame('4.0000', $this->stock());
        $this->db->table('item_sets')->update(['quantity' => '5']);
        FiscalOrderStockReservation::change($this->db, 25, 1, null, 'status_order_id', 4);
        $this->assertSame('10.0000', $this->stock());
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
