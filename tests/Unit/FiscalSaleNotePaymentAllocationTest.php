<?php
namespace Tests\Unit;
use App\Services\Fiscal\FiscalSaleNotePaymentAllocation;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalSaleNotePaymentAllocationTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $context = 'establishment_id INTEGER, customer_id INTEGER, fiscal_environment TEXT, currency_type_id TEXT, total TEXT';
        $this->db->statement("CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, document_id INTEGER, $context)");
        $this->db->statement("CREATE TABLE documents (id INTEGER PRIMARY KEY, sale_note_id INTEGER, total_canceled INTEGER DEFAULT 0, $context)");
        $this->db->statement('CREATE TABLE sale_note_payments (id INTEGER PRIMARY KEY, sale_note_id INTEGER, date_of_payment TEXT, payment_method_type_id TEXT, payment TEXT)');
        $this->db->statement('CREATE TABLE document_payments (id INTEGER PRIMARY KEY, document_id INTEGER, source_sale_note_payment_id INTEGER UNIQUE, date_of_payment TEXT, payment_method_type_id TEXT, payment TEXT, payment_received INTEGER)');
        $context = ['establishment_id' => 1, 'customer_id' => 1, 'fiscal_environment' => 'demo', 'currency_type_id' => 'VES', 'total' => '232.00'];
        $this->db->table('sale_notes')->insert($context + ['id' => 1]);
        $this->db->table('documents')->insert($context + ['id' => 1, 'sale_note_id' => 1]);
        $this->db->table('sale_note_payments')->insert(['id' => 1, 'sale_note_id' => 1, 'date_of_payment' => '2026-09-13', 'payment_method_type_id' => '01', 'payment' => '100.00']);
    }
    private function apply(): void { $this->db->transaction(fn () => FiscalSaleNotePaymentAllocation::apply($this->db, 1, 1)); }
    public function test_partial_receipt_is_applied_once_without_erasing_source(): void
    {
        $source = $this->db->table('sale_note_payments')->first();
        $this->apply(); $this->apply();
        self::assertSame(1, $this->db->table('document_payments')->count());
        self::assertSame('100.00', $this->db->table('document_payments')->value('payment'));
        self::assertEquals($source, $this->db->table('sale_note_payments')->first());
        self::assertSame(0, (int) $this->db->table('documents')->value('total_canceled'));
    }
    public function test_full_payment_cancels_balance_exactly(): void
    {
        $this->db->table('sale_note_payments')->insert(['id' => 2, 'sale_note_id' => 1, 'payment' => '132.00']);
        $this->apply();
        self::assertSame(1, (int) $this->db->table('documents')->value('total_canceled'));
    }
    public function test_reapplication_keeps_payments_collected_after_conversion(): void
    {
        $this->apply();
        $this->db->table('document_payments')->insert(['document_id' => 1, 'payment' => '132.00']);
        $this->apply();
        self::assertSame(2, $this->db->table('document_payments')->count());
        self::assertSame(1, (int) $this->db->table('documents')->value('total_canceled'));
    }
    private function prepareGroup(): void
    {
        $this->db->statement('ALTER TABLE documents ADD COLUMN sale_notes_relateds TEXT');
        $this->db->table('sale_notes')->insert(array_replace((array) $this->db->table('sale_notes')->first(), ['id' => 2]));
        $this->db->table('sale_note_payments')->insert(['id' => 2, 'sale_note_id' => 2, 'payment' => '50.00']);
        $this->db->table('documents')->update(['sale_note_id' => null, 'sale_notes_relateds' => json_encode([['id' => 2], ['id' => 1]]), 'total' => '464.00']);
    }
    public function test_grouped_receipts_are_applied_once_and_preserve_their_sources(): void
    {
        $this->prepareGroup();
        for ($i = 0; $i < 2; $i++) $this->db->transaction(fn () => FiscalSaleNotePaymentAllocation::applyMany($this->db, [2, 1], 1));
        self::assertSame(2, $this->db->table('document_payments')->count());
        self::assertSame(2, $this->db->table('sale_note_payments')->count());
        self::assertEquals(150, $this->db->table('document_payments')->sum('payment'));
        self::assertSame(0, (int) $this->db->table('documents')->value('total_canceled'));
    }
    public function test_failed_second_source_rolls_back_every_application(): void
    {
        $this->prepareGroup();
        $this->db->table('document_payments')->insert(['document_id' => 99, 'source_sale_note_payment_id' => 2, 'payment' => '50.00']);
        try {
            $this->db->transaction(fn () => FiscalSaleNotePaymentAllocation::applyMany($this->db, [1, 2], 1));
            self::fail('Conflicting application accepted');
        } catch (\DomainException $e) {
            self::assertSame(0, $this->db->table('document_payments')->where('document_id', 1)->count());
            self::assertSame(1, $this->db->table('document_payments')->count());
        }
    }
    public function test_cannot_apply_only_a_subset_of_grouped_sources(): void
    {
        $this->prepareGroup();
        $this->expectException(\DomainException::class);
        $this->db->transaction(fn () => FiscalSaleNotePaymentAllocation::applyMany($this->db, [1], 1));
    }
    /** @dataProvider incompatible */
    public function test_incompatible_invoice_rolls_back(array $change): void
    {
        $this->db->table('documents')->update($change);
        try { $this->apply(); self::fail('Invalid allocation accepted'); }
        catch (\DomainException $e) { self::assertSame(0, $this->db->table('document_payments')->count()); }
    }
    public static function incompatible(): array { return [[['currency_type_id' => 'USD']], [['total' => '233']], [['customer_id' => 2]], [['sale_note_id' => 2]], [['establishment_id' => 2]]]; }
    public function test_source_changed_after_application_is_rejected(): void
    {
        $this->apply();
        $this->db->table('sale_note_payments')->update(['payment' => '90.00']);
        $this->expectException(\DomainException::class); $this->apply();
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
