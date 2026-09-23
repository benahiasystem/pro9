<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOrderContext;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOrderContextTest extends FiscalDatabaseTestCase
{
    private function input(): array
    {
        return ['document_type_id' => '01', 'operation_key' => 'untrusted', 'customer' => ['number' => 'V1234567'], 'items' => [['quantity' => 2]]];
    }

    private function profile(int $establishment = 1, bool $withEmitter = true, string $mode = 'digital'): int
    {
        $this->db->table('users')->updateOrInsert(['id' => 10 + $establishment], ['establishment_id' => $establishment, 'type' => 'integrator', 'name' => 'Configured emitter', 'active' => 1]);
        $lot = $mode === 'free_form' ? $this->repository->createLot(['establishment_id' => $establishment,
            'start' => sprintf('%02d-1', $establishment - 1), 'end' => sprintf('%02d-10', $establishment - 1),
            'printer_name' => 'Test', 'printer_rif' => 'J-00000000-0', 'authorization' => 'TEST',
            'authorization_date' => '2026-01-01', 'prepared_at' => '2026-02-01']) : null;
        return (new FiscalProfileService($this->db))->save($establishment, [
            'name' => 'Order profile', 'channel' => 'digital', 'document_type_id' => '01', 'mode' => $mode,
            'sequence_id' => $this->repository->createSequence('01', 'S' . $establishment, 1, $establishment),
            'provider' => $mode === 'free_form' ? 'none' : 'simulator', 'control_lot_id' => $lot,
            'configuration' => array_filter(['page_capacity' => $mode === 'free_form' ? 10 : null,
                'emitter_user_id' => $withEmitter ? 10 + $establishment : null], fn ($value) => $value !== null), 'active' => true,
        ], 1)['id'];
    }

    public function test_order_uses_configured_emitter_and_stable_server_key(): void
    {
        $this->db->table('users')->insert(['id' => 1, 'establishment_id' => 2, 'name' => 'Unrelated first admin', 'type' => 'admin', 'active' => 1]);
        $profile = $this->profile();
        $result = FiscalOrderContext::prepare($this->input(), 25, null, $this->db);
        $this->assertSame(11, $result['user_id']);
        $this->assertSame(1, $result['establishment_id']);
        $this->assertSame($profile, $result['fiscal_profile_id']);
        $this->assertSame('digital', $result['fiscal_channel']);
        $this->assertSame('ecommerce-order-25-invoice', $result['operation_key']);
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
    }

    public function test_multiple_digital_branches_require_explicit_order_branch(): void
    {
        $this->profile(1);
        $this->profile(2);
        $this->expectException(\DomainException::class);
        FiscalOrderContext::prepare($this->input(), 25, null, $this->db);
    }

    public function test_missing_emitter_does_not_select_first_user(): void
    {
        $this->profile(1, false);
        $this->expectException(\DomainException::class);
        FiscalOrderContext::prepare($this->input(), 25, 1, $this->db);
    }

    public function test_disabled_emitter_blocks_new_order(): void
    {
        $this->profile();
        $this->db->table('users')->update(['active' => 0]);
        $this->expectException(\DomainException::class);
        FiscalOrderContext::prepare($this->input(), 25, 1, $this->db);
    }

    public function test_retry_recovers_snapshot_after_profile_archival_and_emitter_deactivation(): void
    {
        $profile = $this->profile();
        $first = FiscalOrderContext::prepare($this->input(), 25, 1, $this->db);
        $this->repository->reserveForProfile($profile, $first['operation_key'], $first['fiscal_fingerprint'], 1, 'digital');
        $this->db->table('fiscal_profiles')->update(['active' => false]);
        $this->db->table('users')->update(['active' => false]);
        $retry = FiscalOrderContext::prepare($this->input(), 25, null, $this->db);
        $this->assertSame($first['fiscal_profile_id'], $retry['fiscal_profile_id']);
        $this->assertSame($first['fiscal_fingerprint'], $retry['fiscal_fingerprint']);
        $this->assertSame(11, $retry['user_id']);
    }

    public function test_automatic_order_can_use_preprinted_free_form_profile(): void
    {
        $profile = $this->profile(1, true, 'free_form');
        $prepared = FiscalOrderContext::prepare($this->input(), 25, 1, $this->db);
        $this->assertSame($profile, $prepared['fiscal_profile_id']);
        $this->assertSame('digital', $prepared['fiscal_channel']);
        $reservation = $this->repository->reserveForProfile($profile, $prepared['operation_key'], $prepared['fiscal_fingerprint'], 1, 'digital');
        $this->assertSame('00-00000001', $reservation->control_number);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
