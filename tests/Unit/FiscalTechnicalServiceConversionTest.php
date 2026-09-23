<?php
namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalTechnicalServiceConversion;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalTechnicalServiceConversionTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->statement('CREATE TABLE technical_services (id INTEGER PRIMARY KEY, user_id INTEGER, establishment_id INTEGER, fiscal_environment TEXT)');
        foreach (['documents', 'sale_notes'] as $table) $this->db->statement("CREATE TABLE $table (id INTEGER PRIMARY KEY, technical_service_id INTEGER)");
        $this->db->table('technical_services')->insert(['id' => 1, 'user_id' => 5, 'establishment_id' => 1, 'fiscal_environment' => 'demo']);
    }

    private function check(array $changes = [], array $actorChanges = []): void
    {
        $actor = new User();
        $actor->setRawAttributes(array_replace(['id' => 5, 'type' => 'seller', 'establishment_id' => 1], $actorChanges));
        $this->db->transaction(fn () => FiscalTechnicalServiceConversion::assertAvailable($this->db,
            array_replace(['technical_service_id' => 1, 'establishment_id' => 1, 'fiscal_environment' => 'demo'], $changes), $actor));
    }

    public function test_authorized_source_remains_available_after_writer_rollback(): void
    {
        $this->check();
        try {
            $this->db->transaction(function () { $this->check(); $this->db->table('documents')->insert(['technical_service_id' => 1]); throw new \RuntimeException('writer failed'); });
        } catch (\RuntimeException $e) { self::assertSame('writer failed', $e->getMessage()); }
        $this->check();
        self::assertSame(0, $this->db->table('documents')->count());
    }

    /** @dataProvider linkedTables */
    public function test_existing_commercial_document_prevents_second_conversion(string $table): void
    {
        $this->db->table($table)->insert(['technical_service_id' => 1]);
        $this->expectException(\DomainException::class);
        $this->check();
    }
    public static function linkedTables(): array { return [['documents'], ['sale_notes']]; }

    /** @dataProvider incompatibleContexts */
    public function test_rejects_incompatible_context(array $input, array $actor): void
    {
        $this->expectException(\DomainException::class);
        $this->check($input, $actor);
    }
    public static function incompatibleContexts(): array
    {
        return [[['technical_service_id' => 99], []], [['establishment_id' => 2], []], [['fiscal_environment' => 'production'], []],
            [[], ['id' => 6]], [[], ['establishment_id' => 2]], [[], ['type' => 'integrator']]];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
