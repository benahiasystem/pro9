<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalCommercialService;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalCommercialServiceTest extends FiscalDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['documents', 'dispatches'] as $table) {
            $this->db->statement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, series TEXT, number INTEGER, document_type_id TEXT, establishment_id INTEGER, fiscal_environment TEXT, fiscal_emission_mode TEXT)");
        }
        $this->db->statement('CREATE TABLE commercial_effects (id INTEGER PRIMARY KEY AUTOINCREMENT, subject_id INTEGER, kind TEXT)');
    }

    private function profile(string $type = '01'): int
    {
        return (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => 'digital', 'document_type_id' => $type, 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence($type, '', 1, 1),
            'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1)['id'];
    }

    private function write(array $identifiers, string $table = 'documents'): int
    {
        $id = $this->db->table($table)->insertGetId($identifiers);
        foreach (['payment', 'inventory'] as $kind) {
            $this->db->table('commercial_effects')->insert(['subject_id' => $id, 'kind' => $kind]);
        }
        return $id;
    }

    public function test_retry_does_not_repeat_commercial_writer(): void
    {
        $profile = $this->profile();
        $service = new FiscalCommercialService($this->db);
        $calls = 0;
        $writer = function ($identifiers) use (&$calls) { $calls++; return $this->write($identifiers); };
        $first = $service->register($profile, 'sale', hash('sha256', 'sale'), 1, 'digital', $writer);
        $retry = $service->register($profile, 'sale', hash('sha256', 'sale'), 1, 'digital', $writer);
        $this->assertSame($first->document_id, $retry->document_id);
        $this->assertSame(1, $calls);
        $this->assertSame(1, $this->db->table('documents')->count());
        $this->assertSame(2, $this->db->table('commercial_effects')->count());
        $this->assertSame(2, (int) $this->db->table('fiscal_sequences')->value('next_number'));
    }

    public function test_failed_writer_rolls_back_reservation_document_and_effects(): void
    {
        $profile = $this->profile();
        try {
            (new FiscalCommercialService($this->db))->register($profile, 'sale', hash('sha256', 'sale'), 1, 'digital', function ($identifiers) {
                $this->write($identifiers);
                throw new \RuntimeException('Commercial failure');
            });
            $this->fail('Expected writer failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Commercial failure', $exception->getMessage());
            $this->assertSame(0, $this->db->table('documents')->count());
            $this->assertSame(0, $this->db->table('commercial_effects')->count());
            $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
            $this->assertSame(1, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        }
    }

    public function test_changed_payload_cannot_repeat_writer(): void
    {
        $profile = $this->profile();
        $service = new FiscalCommercialService($this->db);
        $service->register($profile, 'sale', hash('sha256', 'sale'), 1, 'digital', fn ($ids) => $this->write($ids));
        try {
            $service->register($profile, 'sale', hash('sha256', 'changed'), 1, 'digital', function () { $this->fail('Writer must not run'); });
            $this->fail('Expected fingerprint rejection');
        } catch (\DomainException $exception) {
            $this->assertSame(1, $this->db->table('documents')->count());
            $this->assertSame(2, $this->db->table('commercial_effects')->count());
        }
    }

    public function test_mismatched_persisted_identifiers_roll_back_everything(): void
    {
        $profile = $this->profile();
        try {
            (new FiscalCommercialService($this->db))->register($profile, 'sale', hash('sha256', 'sale'), 1, 'digital', function ($ids) {
                $ids['establishment_id'] = 2;
                return $this->write($ids);
            });
            $this->fail('Expected subject mismatch');
        } catch (\DomainException $exception) {
            $this->assertSame(0, $this->db->table('documents')->count());
            $this->assertSame(0, $this->db->table('commercial_effects')->count());
            $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
        }
    }

    public function test_delivery_order_links_dispatch_instead_of_invoice(): void
    {
        $reservation = (new FiscalCommercialService($this->db))->register($this->profile('09'), 'dispatch', hash('sha256', 'dispatch'), 1, 'digital', fn ($ids) => $this->write($ids, 'dispatches'));
        $this->assertNotNull($reservation->dispatch_id);
        $this->assertNull($reservation->document_id);
        $this->assertSame(0, $this->db->table('documents')->count());
        $this->assertSame(1, $this->db->table('dispatches')->count());
    }

    public function test_failed_pdf_capacity_validation_rolls_back_control_document_and_commercial_effects(): void
    {
        $lot = $this->repository->createLot([
            'establishment_id' => 1, 'printer_name' => 'Test printer', 'printer_rif' => 'J000000000',
            'authorization' => 'TEST ONLY', 'authorization_date' => '2026-09-01', 'prepared_at' => '2026-09-01',
            'start' => '00-1', 'end' => '00-10',
        ]);
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Print test', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'free_form',
            'sequence_id' => $this->repository->createSequence('01', '', 1, 1), 'control_lot_id' => $lot,
            'provider' => 'none', 'configuration' => ['page_capacity' => 10], 'active' => true,
        ], 1)['id'];
        $service = new FiscalCommercialService($this->db);
        try {
            $service->register($profile, 'print-sale', hash('sha256', 'print-sale'), 1, 'presential', fn ($ids) => $this->write($ids), null, function ($reservation) {
                $this->assertGreaterThan(0, $this->db->transactionLevel());
                $this->assertNotNull($reservation->document_id);
                $pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
                $pdf->WriteHTML('<p>First page</p><pagebreak /><p>Second page</p>');
                $this->assertSame(2, $pdf->page);
                \App\Services\Fiscal\FiscalPdfData::assertPageCount(
                    \App\Services\Fiscal\FiscalPdfData::fromReservation($reservation), $pdf->page
                );
            });
            $this->fail('An oversized form must not commit.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('capacidad', $exception->getMessage());
        }
        $this->assertSame(0, $this->db->table('documents')->count());
        $this->assertSame(0, $this->db->table('commercial_effects')->count());
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
        $this->assertSame(1, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        $this->assertSame(1, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
    }

    public function test_successful_precommit_validation_is_not_repeated_on_idempotent_retry(): void
    {
        $profile = $this->profile();
        $service = new FiscalCommercialService($this->db);
        $calls = 0;
        $validate = function ($reservation) use (&$calls) {
            $calls++;
            $this->assertNotNull($reservation->document_id);
            $this->assertGreaterThan(0, $this->db->transactionLevel());
        };
        $first = $service->register($profile, 'validated', hash('sha256', 'validated'), 1, 'digital', fn ($ids) => $this->write($ids), null, $validate);
        $retry = $service->register($profile, 'validated', hash('sha256', 'validated'), 1, 'digital', fn ($ids) => $this->write($ids), null, $validate);
        $this->assertSame(1, $calls);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, $this->db->table('documents')->count());
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
