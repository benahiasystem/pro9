<?php

namespace Tests\Unit;

use App\Services\FiscalProfileService;
use Illuminate\Validation\ValidationException;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalProfileServiceTest extends FiscalDatabaseTestCase
{
    private function input(string $type = '01', string $mode = 'digital'): array
    {
        return [
            'name' => 'Perfil de prueba', 'channel' => $mode === 'digital' ? 'digital' : 'presential',
            'document_type_id' => $type, 'mode' => $mode,
            'sequence_id' => $this->repository->createSequence($type, '', 1, 1),
            'provider' => $mode === 'free_form' ? 'none' : 'simulator', 'configuration' => [], 'active' => true,
        ];
    }

    public function test_credentials_are_encrypted_hidden_and_preserved_by_blank_edits(): void
    {
        $service = new FiscalProfileService($this->db);
        $input = $this->input();
        $input['credentials'] = 'secret-test';
        $result = $service->save(1, $input, 1);
        $this->assertArrayNotHasKey('credentials', $result);
        $this->assertTrue($result['credentials_configured']);
        $stored = $this->db->table('fiscal_profiles')->value('credentials');
        $this->assertNotSame('secret-test', $stored);
        $this->assertSame('secret-test', app('encrypter')->decryptString($stored));
        $input['id'] = $result['id'];
        $input['credentials'] = '';
        $service->save(1, $input, 1);
        $this->assertSame($stored, $this->db->table('fiscal_profiles')->value('credentials'));
        $this->assertStringNotContainsString('secret-test', json_encode($this->db->table('fiscal_numbering_audits')->get()));
        $input['clear_credentials'] = true;
        $service->save(1, $input, 1);
        $this->assertNull($this->db->table('fiscal_profiles')->value('credentials'));
    }

    public function test_same_company_can_resolve_different_modes_by_channel(): void
    {
        $service = new FiscalProfileService($this->db);
        $input = $this->input();
        $service->save(1, $input, 1);
        $input['channel'] = 'presential';
        $input['mode'] = 'fiscal_machine';
        $service->save(1, $input, 1);
        $this->assertSame('digital', $service->resolve(1, 'digital', '01')->mode);
        $this->assertSame('fiscal_machine', $service->resolve(1, 'presential', '01')->mode);
    }

    public function test_cannot_bind_another_establishments_device_group(): void
    {
        $input = $this->input();
        $input['device_group_id'] = 2;
        $this->expectException(ValidationException::class);
        (new FiscalProfileService($this->db))->save(1, $input, 1);
    }

    public function test_duplicate_active_resolution_is_rejected(): void
    {
        $input = $this->input();
        $service = new FiscalProfileService($this->db);
        $service->save(1, $input, 1);
        $this->expectException(ValidationException::class);
        $service->save(1, $input, 1);
    }

    public function test_machine_cannot_offer_an_order_of_delivery(): void
    {
        $input = $this->input('09', 'fiscal_machine');
        $this->expectException(ValidationException::class);
        (new FiscalProfileService($this->db))->save(1, $input, 1);
    }

    public function test_simulator_is_blocked_in_production_before_reservation(): void
    {
        $input = $this->input();
        $profile = (new FiscalProfileService($this->db))->save(1, $input, 1);
        $this->db->table('companies')->update(['fiscal_environment' => 'production']);
        try {
            $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'digital');
            $this->fail('No debe reservar en producción con simulador');
        } catch (\DomainException $e) {
            $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
            $this->assertSame(1, (int) $this->db->table('fiscal_sequences')->value('next_number'));
        }
    }

    public function test_used_profile_is_immutable_and_retry_works_after_archival(): void
    {
        $input = $this->input();
        $service = new FiscalProfileService($this->db);
        $profile = $service->save(1, $input, 1);
        $first = $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'digital');
        $input['id'] = $profile['id'];
        $input['active'] = false;
        $service->save(1, $input, 1);
        $retry = $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'digital');
        $this->assertSame($first->id, $retry->id);
        $input['provider'] = 'another';
        $this->expectException(ValidationException::class);
        $service->save(1, $input, 1);
    }

    public function test_cannot_reserve_with_untrusted_channel_override(): void
    {
        $profile = (new FiscalProfileService($this->db))->save(1, $this->input(), 1);
        $this->expectException(\DomainException::class);
        $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'presential');
    }

    public function test_dedicated_profile_does_not_fall_back_to_general_profile(): void
    {
        $service = new FiscalProfileService($this->db);
        $service->save(1, $this->input(), 1);
        $this->expectException(\DomainException::class);
        $service->resolve(1, 'digital', '01', 1);
    }

    public function test_other_establishment_cannot_edit_profile_even_with_valid_id(): void
    {
        $service = new FiscalProfileService($this->db);
        $input = $this->input();
        $profile = $service->save(1, $input, 1);
        $input['id'] = $profile['id'];
        $input['sequence_id'] = $this->repository->createSequence('01', 'SUC2', 1, 2);
        $this->expectException(ValidationException::class);
        $service->save(2, $input, 1);
    }

    public function test_free_form_requires_lot_and_page_capacity_before_consuming_numbers(): void
    {
        $service = new FiscalProfileService($this->db);
        $profile = $service->save(1, $this->input('01', 'free_form'), 1);
        try {
            $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'presential');
            $this->fail('No debe emitir sin lote');
        } catch (\DomainException $e) {
            $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
        }
    }

    public function test_profile_snapshot_does_not_include_encrypted_or_plain_credentials(): void
    {
        $input = $this->input();
        $input['credentials'] = 'secret-test';
        $profile = (new FiscalProfileService($this->db))->save(1, $input, 1);
        $reservation = $this->repository->reserveForProfile($profile['id'], 'sale', hash('sha256', 'sale'), 1, 'digital');
        $this->assertStringNotContainsString('secret-test', $reservation->fiscal_snapshot);
        $snapshot = json_decode($reservation->fiscal_snapshot, true);
        $this->assertArrayNotHasKey('credentials', $snapshot['profile']);
        $this->assertSame('digital', $snapshot['mode']);
    }

    public function test_selection_catalog_respects_channel_and_device_group_without_secrets(): void
    {
        $input = $this->input();
        $input['channel'] = 'presential';
        $input['credentials'] = 'private-provider-token';
        $service = new FiscalProfileService($this->db);
        $general = $service->save(1, $input, 1);
        $input['device_group_id'] = 1;
        $dedicated = $service->save(1, $input, 1);
        $this->assertSame([$general['id']], $service->forSelection(1, 'presential', null)->pluck('id')->all());
        $this->assertSame([$dedicated['id']], $service->forSelection(1, 'presential', 1)->pluck('id')->all());
        $this->assertCount(0, $service->forSelection(2, 'presential', 1));
        $this->assertCount(0, $service->forSelection(1, 'digital', null));
        $this->assertStringNotContainsString('credentials', json_encode($service->forSelection(1, 'presential', null)));
    }

    public function test_selection_catalog_excludes_archived_sequences(): void
    {
        $service = new FiscalProfileService($this->db);
        $service->save(1, $this->input(), 1);
        $this->db->table('fiscal_sequences')->update(['active' => false]);
        $this->assertCount(0, $service->forSelection(1, 'digital', null));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
