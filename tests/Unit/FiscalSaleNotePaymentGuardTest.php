<?php
namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalSaleNotePaymentGuard;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalSaleNotePaymentGuardTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE sale_notes (id INTEGER PRIMARY KEY, establishment_id INTEGER, user_id INTEGER, document_id INTEGER, changed INTEGER DEFAULT 0, state_type_id TEXT)');
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, sale_note_id INTEGER)');
        $this->db->table('sale_notes')->insert(['id' => 1, 'establishment_id' => 1, 'user_id' => 5]);
    }
    private function check(array $actorChanges = []): object
    {
        $actor = new User();
        $actor->setRawAttributes(array_replace(['id' => 5, 'type' => 'seller', 'establishment_id' => 1], $actorChanges));
        return $this->db->transaction(fn () => FiscalSaleNotePaymentGuard::lockEditable($this->db, 1, $actor));
    }
    public function test_unconverted_owned_note_remains_payable(): void
    {
        self::assertSame(1, (int) $this->check()->id);
        self::assertSame(1, (int) $this->check(['id' => 6, 'type' => 'admin'])->id);
    }
    /** @dataProvider terminalStates */
    public function test_voided_and_rejected_notes_cannot_be_changed_again(string $state): void
    {
        $this->db->table('sale_notes')->update(['state_type_id' => $state]);
        $this->expectException(ValidationException::class);
        $this->check();
    }
    public static function terminalStates(): array { return [['09'], ['11']]; }
    /** @dataProvider ownership */
    public function test_other_owner_or_establishment_is_rejected(array $actor): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->check($actor);
    }
    public static function ownership(): array { return [[['id' => 6]], [['establishment_id' => 2]], [['type' => 'unknown']]]; }
    /** @dataProvider bindings */
    public function test_any_existing_conversion_rejects_payment_changes(string $binding): void
    {
        if ($binding === 'invoice') $this->db->table('documents')->insert(['sale_note_id' => 1]);
        else $this->db->table('sale_notes')->update([$binding => 1]);
        $this->expectException(ValidationException::class);
        $this->check();
    }
    public static function bindings(): array { return [['document_id'], ['changed'], ['invoice']]; }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
