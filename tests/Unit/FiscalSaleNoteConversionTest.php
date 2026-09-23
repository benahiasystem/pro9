<?php
namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalSaleNoteConversion;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalSaleNoteConversionTest extends FiscalDatabaseTestCase
{
    private array $input;
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, user_id INTEGER, establishment_id INTEGER, customer_id INTEGER, fiscal_environment TEXT, currency_type_id TEXT, total TEXT, document_id INTEGER, changed INTEGER, state_type_id TEXT)');
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, sale_note_id INTEGER)');
        $this->db->statement('CREATE TABLE sale_note_items (id INTEGER PRIMARY KEY, sale_note_id INTEGER, item_id INTEGER, quantity TEXT)');
        $this->db->statement('CREATE TABLE sale_note_payments (sale_note_id INTEGER, payment TEXT)');
        $context = ['establishment_id' => 1, 'customer_id' => 1, 'fiscal_environment' => 'demo', 'currency_type_id' => 'VES', 'total' => '232.00'];
        $this->db->table('sale_notes')->insert($context + ['id' => 1, 'user_id' => 5, 'changed' => 0, 'state_type_id' => '01']);
        $this->db->table('sale_note_items')->insert(['sale_note_id' => 1, 'item_id' => 1, 'quantity' => '2']);
        $this->db->table('sale_note_payments')->insert(['sale_note_id' => 1, 'payment' => '100.00']);
        $this->input = $context + ['sale_note_id' => 1, 'items' => [['item_id' => 1, 'quantity' => 2]], 'payments' => []];
    }
    private function check(array $input = [], array $actor = []): void
    {
        $user = new User();
        $user->setRawAttributes(array_replace(['id' => 5, 'establishment_id' => 1, 'type' => 'seller'], $actor));
        $this->db->transaction(fn () => FiscalSaleNoteConversion::assertAvailable($this->db, array_replace($this->input, $input), $user));
    }
    public function test_accepts_remaining_payment_without_consuming_source(): void
    {
        $this->check(['payments' => [['payment' => '132.00']]]);
        self::assertSame(0, $this->db->table('documents')->count());
        self::assertSame('100.00', $this->db->table('sale_note_payments')->value('payment'));
    }
    private function groupedInput(): array
    {
        $source = (array) $this->db->table('sale_notes')->first();
        $this->db->table('sale_notes')->insert(array_replace($source, ['id' => 2]));
        $this->db->table('sale_note_items')->insert(['sale_note_id' => 2, 'item_id' => 1, 'quantity' => '2']);
        $this->db->table('sale_note_payments')->insert(['sale_note_id' => 2, 'payment' => '50.00']);
        return ['sale_note_id' => null, 'sale_notes_relateds' => [['id' => 2], ['id' => 1]],
            'items' => [['item_id' => 1, 'quantity' => 4]], 'total' => '464.00', 'payments' => [['payment' => '314.00']]];
    }
    public function test_grouped_conversion_sums_sources_and_receipts(): void
    {
        $this->check($this->groupedInput());
        self::assertSame([1, 2], FiscalSaleNoteConversion::sourceIds(['sale_notes_relateds' => [['id' => 2], ['id' => 1]]]));
        self::assertSame(0, $this->db->table('documents')->count());
    }
    /** @dataProvider invalidGroupedSource */
    public function test_grouped_conversion_rejects_any_incompatible_source(array $change): void
    {
        $input = $this->groupedInput();
        $this->db->table('sale_notes')->where('id', 2)->update($change);
        $this->expectException(\DomainException::class); $this->check($input);
    }
    public static function invalidGroupedSource(): array
    {
        return [[['customer_id' => 2]], [['user_id' => 9]], [['establishment_id' => 2]], [['currency_type_id' => 'USD']],
            [['fiscal_environment' => 'production']], [['changed' => 1]], [['document_id' => 8]], [['total' => '233']]];
    }
    /** @dataProvider invalidSourceIds */
    public function test_rejects_ambiguous_or_invalid_source_lists(array $input): void
    {
        $this->expectException(\DomainException::class); FiscalSaleNoteConversion::sourceIds($input);
    }
    public static function invalidSourceIds(): array
    {
        return [[[]], [['sale_note_id' => 1, 'sale_notes_relateds' => [['id' => 2]]]],
            [['sale_notes_relateds' => [['id' => 1], ['id' => '1']]]], [['sale_notes_relateds' => [['id' => 0]]]],
            [['sale_notes_relateds' => [['id' => '1.5']]]], [['sale_notes_relateds' => ['NV01-1']]]];
    }
    /** @dataProvider invalidInputs */
    public function test_rejects_invalid_conversion(array $input, array $actor = []): void
    {
        $this->expectException(\DomainException::class);
        $this->check($input, $actor);
    }
    public static function invalidInputs(): array
    {
        return [[['sale_note_id' => 99]], [['customer_id' => 2]], [['establishment_id' => 2]], [['total' => '233']],
            [['currency_type_id' => 'USD']], [['fiscal_environment' => 'production']], [[], ['id' => 6]],
            [['items' => [['item_id' => 1, 'quantity' => 3]]]], [['items' => [['item_id' => 2, 'quantity' => 2]]]],
            [['payments' => [['id' => 1, 'payment' => '100']]]], [['payments' => [['payment' => '133']]]],
            [['payments' => [['payment' => '-1']]]], [['payments' => [['payment' => '0']]]], [['payments' => [['payment' => 'invalid']]]]];
    }
    /** @dataProvider unavailableSources */
    public function test_rejects_unavailable_source(array $change): void
    {
        $this->db->table('sale_notes')->update($change);
        $this->expectException(\DomainException::class); $this->check();
    }
    public static function unavailableSources(): array { return [[['changed' => 1]], [['document_id' => 7]], [['state_type_id' => '09']], [['state_type_id' => '11']]]; }
    public function test_inverse_document_link_prevents_second_conversion(): void
    {
        $this->db->table('documents')->insert(['sale_note_id' => 1]);
        $this->expectException(\DomainException::class); $this->check();
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
