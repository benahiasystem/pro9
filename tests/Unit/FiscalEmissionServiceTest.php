<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalAdapter;
use App\Services\Fiscal\FiscalEmissionService;
use App\Services\Fiscal\SimulatedFiscalAdapter;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalEmissionServiceTest extends FiscalDatabaseTestCase
{
    private function reserve(string $mode = 'digital'): object
    {
        $lot = $mode === 'free_form' ? $this->repository->createLot([
            'establishment_id' => 1, 'start' => '00-1', 'end' => '00-10',
            'printer_name' => 'Imprenta de prueba', 'printer_rif' => 'J-00000000-0',
            'authorization' => 'DEMO', 'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01',
        ]) : null;
        $channel = $mode === 'digital' ? 'digital' : 'presential';
        $profile = (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => $channel, 'document_type_id' => '01', 'mode' => $mode,
            'sequence_id' => $this->repository->createSequence('01', '', 1, 1),
            'provider' => $mode === 'free_form' ? 'none' : 'simulator',
            'control_lot_id' => $lot, 'configuration' => $lot ? ['page_capacity' => 10] : [], 'active' => true,
        ], 1);
        $reservation = $this->repository->reserveForProfile($profile['id'], 'sale-1', hash('sha256', 'sale'), 1, $channel);
        $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update(['document_id' => 1]);
        return $reservation;
    }

    public function test_repeated_processing_keeps_one_receipt_and_one_attempt(): void
    {
        $reservation = $this->reserve();
        $service = new FiscalEmissionService($this->db);
        $this->assertSame('issued', $service->process($reservation->id)->status);
        $this->assertSame('issued', $service->process($reservation->id)->status);
        $this->assertSame(1, $this->db->table('fiscal_demo_receipts')->count());
        $this->assertSame(1, $this->db->table('fiscal_emission_attempts')->count());
        $this->assertNull($this->db->table('fiscal_number_reservations')->value('control_number'));
    }

    public function test_print_confirmation_is_explicit_idempotent_and_audited(): void
    {
        $reservation = $this->reserve('free_form');
        $service = new FiscalEmissionService($this->db);
        $this->assertSame('awaiting_print', $service->process($reservation->id)->status);
        $this->assertSame('issued', $service->confirmPrinted($reservation->id, 1)->status);
        $this->assertSame('issued', $service->confirmPrinted($reservation->id, 1)->status);
        $this->assertSame(1, $this->db->table('fiscal_numbering_audits')->where('action', 'confirm_print')->count());
        $this->assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        $this->assertSame(0, $this->db->table('fiscal_demo_receipts')->count());
    }

    public function test_inutilized_print_keeps_control_and_cannot_be_confirmed_later(): void
    {
        $reservation = $this->reserve('free_form');
        $service = new FiscalEmissionService($this->db);
        $service->process($reservation->id);
        $result = $service->invalidatePrint($reservation->id, 1, 'Papel dañado');
        $this->assertSame('inutilized', $result->status);
        $this->assertSame('00-00000001', $result->control_number);
        $this->assertSame('Papel dañado', $result->invalidation_reason);
        $this->assertSame(2, (int) $this->db->table('fiscal_control_lots')->value('next_ordinal'));
        $this->expectException(\DomainException::class);
        $service->confirmPrinted($reservation->id, 1);
    }

    public function test_lost_response_is_queried_without_emitting_again_or_logging_exception_secrets(): void
    {
        $reservation = $this->reserve();
        $adapter = new class(new SimulatedFiscalAdapter($this->db)) implements FiscalAdapter {
            public int $emits = 0;
            private FiscalAdapter $inner;
            public function __construct(FiscalAdapter $inner) { $this->inner = $inner; }
            public function emit(object $reservation, array $snapshot): array {
                $this->emits++;
                $this->inner->emit($reservation, $snapshot);
                throw new \RuntimeException('secret-provider-token');
            }
            public function lookup(object $reservation, array $snapshot): array { return $this->inner->lookup($reservation, $snapshot); }
        };
        $service = new FiscalEmissionService($this->db, $adapter);
        $this->assertSame('uncertain', $service->process($reservation->id)->status);
        $this->assertSame('issued', $service->process($reservation->id)->status);
        $this->assertSame(1, $adapter->emits);
        $attempts = $this->db->table('fiscal_emission_attempts')->orderBy('id')->get();
        $this->assertSame(['emit', 'lookup'], $attempts->pluck('action')->all());
        $this->assertStringNotContainsString('secret-provider-token', json_encode($attempts));
    }

    public function test_active_attempt_is_not_repeated_and_expired_attempt_queries_first(): void
    {
        $reservation = $this->reserve();
        $attempt = $this->db->table('fiscal_emission_attempts')->insertGetId([
            'reservation_id' => $reservation->id, 'action' => 'emit', 'status' => 'processing', 'created_at' => now(),
        ]);
        $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update(['status' => 'processing', 'current_attempt_id' => $attempt]);
        $service = new FiscalEmissionService($this->db);
        $this->assertSame('processing', $service->process($reservation->id)->status);
        $this->assertSame(0, $this->db->table('fiscal_demo_receipts')->count());
        $this->db->table('fiscal_emission_attempts')->where('id', $attempt)->update(['created_at' => now()->subMinutes(3)]);
        $this->assertSame('reserved', $service->process($reservation->id)->status);
        $this->assertSame('lookup', $this->db->table('fiscal_emission_attempts')->orderByDesc('id')->value('action'));
        $this->assertSame(0, $this->db->table('fiscal_demo_receipts')->count());
        $this->assertSame('issued', $service->process($reservation->id)->status);
    }

    public function test_cannot_emit_inside_commercial_transaction(): void
    {
        $reservation = $this->reserve();
        $this->expectException(\DomainException::class);
        $this->db->transaction(function () use ($reservation) { (new FiscalEmissionService($this->db))->process($reservation->id); });
    }

    public function test_late_response_does_not_overwrite_newer_reconciliation(): void
    {
        $reservation = $this->reserve();
        $adapter = new class($this->db) implements FiscalAdapter {
            private $db;
            public function __construct($db) { $this->db = $db; }
            public function emit(object $reservation, array $snapshot): array {
                $newer = $this->db->table('fiscal_emission_attempts')->insertGetId([
                    'reservation_id' => $reservation->id, 'action' => 'lookup', 'status' => 'issued', 'created_at' => now(), 'finished_at' => now(),
                ]);
                $this->db->table('fiscal_number_reservations')->where('id', $reservation->id)->update([
                    'current_attempt_id' => $newer, 'status' => 'issued', 'provider_result' => json_encode(['provider_reference' => 'newer']),
                ]);
                return ['status' => 'uncertain', 'simulated' => true];
            }
            public function lookup(object $reservation, array $snapshot): array { throw new \LogicException('Unexpected lookup'); }
        };
        $result = (new FiscalEmissionService($this->db, $adapter))->process($reservation->id);
        $this->assertSame('issued', $result->status);
        $this->assertSame('newer', json_decode($result->provider_result, true)['provider_reference']);
        $this->assertSame('uncertain', $this->db->table('fiscal_emission_attempts')->orderBy('id')->value('status'));
    }

    public function test_unlinked_reservation_cannot_emit(): void
    {
        $reservation = $this->reserve();
        $this->db->table('fiscal_number_reservations')->update(['document_id' => null]);
        $this->expectException(\DomainException::class);
        (new FiscalEmissionService($this->db))->process($reservation->id);
    }

    public function test_production_snapshot_cannot_invoke_simulator(): void
    {
        $reservation = $this->reserve();
        $snapshot = json_decode($reservation->fiscal_snapshot, true);
        $snapshot['environment'] = 'production';
        $this->db->table('fiscal_number_reservations')->update(['fiscal_snapshot' => json_encode($snapshot)]);
        try {
            (new FiscalEmissionService($this->db))->process($reservation->id);
            $this->fail('Producción debe bloquear simulación');
        } catch (\DomainException $e) {
            $this->assertSame(0, $this->db->table('fiscal_demo_receipts')->count());
            $this->assertSame(0, $this->db->table('fiscal_emission_attempts')->count());
        }
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
