<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalDispatchConversion;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalDispatchConversionTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE dispatches (id INTEGER PRIMARY KEY, establishment_id INTEGER, customer_id INTEGER, user_id INTEGER, fiscal_environment TEXT, state_type_id TEXT, document_id INTEGER, reference_document_id INTEGER)');
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, dispatch_id INTEGER)');
        $this->db->table('dispatches')->insert(['id' => 1, 'establishment_id' => 1, 'customer_id' => 10, 'user_id' => 5, 'fiscal_environment' => 'demo', 'state_type_id' => '01']);
    }

    private function actor(): User
    {
        $actor = new User();
        $actor->setRawAttributes(['id' => 5, 'type' => 'seller', 'establishment_id' => 1]);
        return $actor;
    }

    private function context(): array
    {
        return ['dispatch_id' => 1, 'document_type_id' => '01', 'customer_id' => 10, 'establishment_id' => 1, 'fiscal_environment' => 'demo'];
    }

    private function convert(?array $context = null): int
    {
        return $this->db->transaction(fn () => FiscalDispatchConversion::register($this->db, $context ?? $this->context(), $this->actor(), function (): int {
            return $this->db->table('documents')->insertGetId(['dispatch_id' => 1]);
        }));
    }

    public function test_conversion_links_both_sides_and_rejects_another_sale(): void
    {
        $id = $this->convert();
        $this->assertSame($id, (int) $this->db->table('dispatches')->value('document_id'));
        try {
            $this->convert();
            $this->fail('Duplicate conversion accepted.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('ya está vinculada', $e->getMessage());
        }
        $this->assertSame(1, $this->db->table('documents')->count());
    }

    /** @dataProvider incompatibleContexts */
    public function test_incompatible_source_does_not_create_an_invoice(string $field, $value): void
    {
        $this->expectException(\DomainException::class);
        $this->convert(array_replace($this->context(), [$field => $value]));
    }

    public static function incompatibleContexts(): array
    {
        return [['customer_id', 11], ['establishment_id', 2], ['fiscal_environment', 'production'], ['document_type_id', '07'], ['dispatch_id', 99]];
    }

    public function test_other_sellers_order_is_rejected(): void
    {
        $this->db->table('dispatches')->update(['user_id' => 6]);
        $this->expectException(\DomainException::class);
        $this->convert();
    }

    public function test_dispatch_created_from_an_invoice_cannot_be_invoiced_again(): void
    {
        $this->db->table('dispatches')->update(['reference_document_id' => 40]);
        $this->expectException(\DomainException::class);
        $this->convert();
    }

    public function test_writer_failure_rolls_back_invoice_and_keeps_source_available(): void
    {
        try {
            $this->db->transaction(fn () => FiscalDispatchConversion::register($this->db, $this->context(), $this->actor(), function (): int {
                $this->db->table('documents')->insert(['dispatch_id' => 1]);
                throw new \RuntimeException('inventory failure');
            }));
            $this->fail('Writer failure swallowed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('inventory failure', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('documents')->count());
        $this->assertNull($this->db->table('dispatches')->value('document_id'));
        $this->assertGreaterThan(0, $this->convert());
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
