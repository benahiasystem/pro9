<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalContingencyService;
use App\Services\Fiscal\FiscalEmissionService;
use App\Services\Fiscal\FiscalReservation;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalContingencyServiceTest extends FiscalDatabaseTestCase
{
    private object $original;
    private int $physicalProfile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->table('users')->insert(['id' => 1, 'name' => 'Admin', 'type' => 'admin', 'active' => 1, 'establishment_id' => 1]);
        $this->db->statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, establishment_id INTEGER, document_type_id TEXT, fiscal_environment TEXT, state_type_id TEXT)');
        $this->db->statement('CREATE TABLE document_items (id INTEGER PRIMARY KEY, document_id INTEGER)');
        $this->db->table('documents')->insert(['id' => 1, 'establishment_id' => 1, 'document_type_id' => '01', 'fiscal_environment' => 'demo', 'state_type_id' => '01']);
        $this->db->table('document_items')->insert(['id' => 1, 'document_id' => 1]);
        $profiles = new FiscalProfileService($this->db);
        $profile = $profiles->save(1, ['name' => 'Original', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', 'D', 1, 1), 'provider' => 'simulator', 'configuration' => [], 'active' => true], 1);
        $this->original = $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'presential');
        $this->db->table('fiscal_number_reservations')->where('id', $this->original->id)->update(['document_id' => 1]);
        $lot = $this->repository->createLot(['establishment_id' => 1, 'start' => '00-1', 'end' => '00-10', 'printer_name' => 'Test',
            'printer_rif' => 'J-00000000-0', 'authorization' => 'DEMO', 'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01']);
        $physical = $profiles->save(1, ['name' => 'Contingencia', 'channel' => 'contingency', 'document_type_id' => '01', 'mode' => 'free_form',
            'sequence_id' => $this->repository->createSequence('01', 'C', 10, 1), 'provider' => 'none', 'control_lot_id' => $lot,
            'configuration' => ['page_capacity' => 10], 'active' => true], 1);
        $this->physicalProfile = $physical['id'];
    }

    private function start(?callable $validator = null): object
    {
        return (new FiscalContingencyService($this->db))->start($this->original->id, $this->physicalProfile, 'Interrupción de conexión', 1, $validator ?? static function () {});
    }

    public function test_replacement_is_linked_without_creating_a_second_sale_and_retry_does_not_consume_numbers(): void
    {
        $checks = 0;
        $first = $this->start(function ($physical, $subject) use (&$checks) {
            $checks++;
            self::assertSame(1, (int) $subject->id);
            self::assertSame('awaiting_print', $physical->status);
            $original = $this->db->table('fiscal_number_reservations')->find($this->original->id);
            self::assertSame((int) $physical->id, (int) FiscalReservation::effective($this->db, $original)->id);
        });
        $second = $this->start();
        self::assertSame($first->id, $second->id);
        self::assertSame(1, $checks);
        self::assertNull($first->document_id);
        self::assertSame(1, $this->db->table('documents')->count());
        self::assertSame(1, $this->db->table('document_items')->count());
        self::assertSame(2, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame(11, (int) $this->db->table('fiscal_sequences')->where('series_code', 'C')->value('next_number'));
        $original = $this->db->table('fiscal_number_reservations')->find($this->original->id);
        self::assertSame($this->original->fiscal_snapshot, $original->fiscal_snapshot);
        self::assertSame('contingency', $original->status);
        self::assertSame(1, (int) $original->document_id);
        self::assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'start_contingency')->count());
        $emission = new FiscalEmissionService($this->db);
        self::assertSame('awaiting_print', $emission->process($original->id)->status);
        self::assertSame('issued', $emission->confirmPrinted($original->id, 1)->status);
        self::assertSame('issued', $emission->confirmPrinted($original->id, 1)->status);
        self::assertSame('issued', $emission->process($original->id)->status);
        self::assertSame(0, $this->db->table('fiscal_demo_receipts')->count());
    }

    /** @dataProvider forbiddenStatuses */
    public function test_requires_reconciliation_and_never_replaces_confirmed_emission(string $status): void
    {
        $this->db->table('fiscal_number_reservations')->where('id', $this->original->id)->update(['status' => $status]);
        $this->expectException(\DomainException::class);
        $this->start();
    }

    public static function forbiddenStatuses(): array
    {
        return [['issued'], ['uncertain'], ['processing'], ['inutilized'], ['awaiting_print'], ['rejected']];
    }

    public function test_verified_absence_after_lookup_allows_contingency(): void
    {
        $this->db->table('fiscal_emission_attempts')->insert(['reservation_id' => $this->original->id, 'action' => 'lookup', 'status' => 'not_found', 'created_at' => now()]);
        $this->db->table('fiscal_number_reservations')->where('id', $this->original->id)->update(['provider_result' => '{"status":"not_found"}']);
        self::assertSame('awaiting_print', $this->start()->status);
    }

    public function test_attempt_without_verified_absence_cannot_be_bypassed(): void
    {
        $this->db->table('fiscal_emission_attempts')->insert(['reservation_id' => $this->original->id, 'action' => 'emit', 'status' => 'uncertain', 'created_at' => now()]);
        $this->expectException(\DomainException::class);
        $this->start();
    }

    public function test_print_failure_rolls_back_replacement_and_counters_but_keeps_original_sale(): void
    {
        try {
            $this->start(static function () { throw new \DomainException('Dos páginas'); });
            self::fail('Debe rechazar el formato.');
        } catch (\DomainException $e) { self::assertSame('Dos páginas', $e->getMessage()); }
        self::assertSame('reserved', $this->db->table('fiscal_number_reservations')->value('status'));
        self::assertSame(1, $this->db->table('fiscal_number_reservations')->count());
        self::assertSame(1, $this->db->table('documents')->count());
        self::assertSame(1, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        self::assertSame(10, (int) $this->db->table('fiscal_sequences')->where('series_code', 'C')->value('next_number'));
    }

    public function test_confirmed_provider_rejection_allows_contingency(): void
    {
        $this->db->table('fiscal_number_reservations')->where('id', $this->original->id)->update(['status' => 'rejected', 'provider_result' => '{"status":"rejected"}']);
        self::assertSame('awaiting_print', $this->start()->status);
    }

    public function test_exhausted_lot_does_not_change_original(): void
    {
        $this->db->table('fiscal_control_lots')->update(['next_ordinal' => 11]);
        try { $this->start(); self::fail('Debe rechazar un lote agotado.'); }
        catch (\DomainException $e) { self::assertStringContainsString('lote de controles disponible', $e->getMessage()); }
        self::assertSame('reserved', $this->db->table('fiscal_number_reservations')->value('status'));
        self::assertSame(1, $this->db->table('fiscal_number_reservations')->count());
    }

    public function test_other_establishment_profile_cannot_consume_a_control(): void
    {
        $this->db->table('fiscal_profiles')->where('id', $this->physicalProfile)->update(['establishment_id' => 2]);
        $this->expectException(\DomainException::class);
        $this->start();
    }

    public function test_changed_cause_does_not_replace_existing_contingency(): void
    {
        $this->start();
        $this->expectException(\DomainException::class);
        (new FiscalContingencyService($this->db))->start($this->original->id, $this->physicalProfile, 'Otra causa', 1, static function () {});
    }

    /** @dataProvider unauthorizedActors */
    public function test_requires_active_administrator_from_original_establishment(array $changes): void
    {
        $this->db->table('users')->where('id', 1)->update($changes);
        $this->expectException(\DomainException::class);
        $this->start();
    }

    public static function unauthorizedActors(): array
    {
        return [[['type' => 'seller']], [['active' => 0]], [['establishment_id' => 2]]];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
