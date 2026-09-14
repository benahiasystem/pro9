<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOrderConversion;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOrderConversionTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE orders (id INTEGER PRIMARY KEY, purchase TEXT, stock_discounted INTEGER DEFAULT 0, stock_reservation TEXT, document_external_id TEXT, number_document TEXT, deleted_at TEXT, updated_at TEXT)');
        $this->db->statement('CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, order_id INTEGER)');
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, document_type_id TEXT, external_id TEXT, series TEXT, number INTEGER)');
        $this->db->table('orders')->insert(['id' => 25, 'purchase' => json_encode(['total' => 232])]);
    }

    private function source(): array
    {
        return ['id' => 25, 'purchase_fingerprint' => FiscalOrderConversion::fingerprint(['total' => 232])];
    }

    private function write(): int
    {
        return $this->db->table('documents')->insertGetId(['document_type_id' => '01', 'external_id' => 'invoice-uuid', 'series' => 'P', 'number' => 1]);
    }

    private function convert(): int
    {
        return $this->db->transaction(fn () => FiscalOrderConversion::register($this->db, $this->source(), 'ecommerce-order-25-invoice', fn () => $this->write()));
    }

    public function test_invoice_and_order_link_are_committed_together(): void
    {
        $this->assertGreaterThan(0, $this->convert());
        $order = $this->db->table('orders')->find(25);
        $this->assertSame('invoice-uuid', $order->document_external_id);
        $this->assertSame('P-1', $order->number_document);
        $this->expectException(\DomainException::class);
        $this->convert();
    }

    public function test_failure_after_linking_rolls_back_invoice_and_link(): void
    {
        try {
            $this->db->transaction(function () {
                FiscalOrderConversion::register($this->db, $this->source(), 'ecommerce-order-25-invoice', fn () => $this->write());
                throw new \RuntimeException('PDF validation failed');
            });
            $this->fail('Failure swallowed');
        } catch (\RuntimeException $exception) {
            $this->assertSame('PDF validation failed', $exception->getMessage());
        }
        $this->assertSame(0, $this->db->table('documents')->count());
        $this->assertNull($this->db->table('orders')->value('document_external_id'));
    }

    public function test_changed_purchase_is_rejected_before_writer(): void
    {
        $this->db->table('orders')->update(['purchase' => json_encode(['total' => 500])]);
        try {
            $this->convert();
            $this->fail('Changed purchase accepted');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('cambió', $exception->getMessage());
        }
        $this->assertSame(0, $this->db->table('documents')->count());
    }

    public function test_existing_sales_note_prevents_a_second_receipt(): void
    {
        $this->db->table('sale_notes')->insert(['order_id' => 25]);
        $this->expectException(\DomainException::class);
        $this->convert();
    }

    public function test_operation_for_another_order_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->db->transaction(fn () => FiscalOrderConversion::register($this->db, $this->source(), 'ecommerce-order-26-invoice', fn () => $this->write()));
    }

    public function test_source_fingerprint_includes_series_and_ignores_object_key_order(): void
    {
        $this->assertSame(FiscalOrderConversion::fingerprint(['series' => 'A', 'total' => 2]), FiscalOrderConversion::fingerprint(['total' => 2, 'series' => 'A']));
        $this->assertNotSame(FiscalOrderConversion::fingerprint(['series' => 'A']), FiscalOrderConversion::fingerprint(['series' => 'B']));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
