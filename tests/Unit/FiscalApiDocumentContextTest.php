<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalApiDocumentContext;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalApiDocumentContextTest extends FiscalDatabaseTestCase
{
    private function actor(string $type): User
    {
        $actor = new User();
        $actor->setRawAttributes(['id' => 1, 'type' => $type, 'establishment_id' => 1]);
        return $actor;
    }

    private function input(): array
    {
        return ['operation_key' => 'api-sale', 'document_type_id' => '01', 'items' => [['quantity' => 1]], 'customer' => ['number' => 'V1234567']];
    }

    private function profile(string $channel): int
    {
        return (new FiscalProfileService($this->db))->save(1, [
            'name' => 'API test', 'channel' => $channel, 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', strtoupper($channel), 1, 1), 'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1)['id'];
    }

    /** @dataProvider accountChannels */
    public function test_authenticated_account_selects_channel_and_ignores_client_overrides(string $type, string $channel): void
    {
        $profile = $this->profile($channel);
        $input = array_replace($this->input(), ['fiscal_channel' => 'contingency', 'fiscal_profile_id' => 999, 'series' => 'FF01', 'number' => 900]);
        $result = FiscalApiDocumentContext::prepareFor($input, $this->actor($type), $this->db, null);
        $this->assertSame($channel, $result['fiscal_channel']);
        $this->assertSame($profile, $result['fiscal_profile_id']);
        $this->assertSame(1, $result['establishment_id']);
        $this->assertSame('#', $result['number']);
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
    }

    public static function accountChannels(): array
    {
        return [['admin', 'presential'], ['seller', 'presential'], ['integrator', 'digital']];
    }

    public function test_another_establishment_is_rejected_instead_of_silently_reassigned(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        FiscalApiDocumentContext::prepareFor(array_replace($this->input(), ['establishment_id' => 2]), $this->actor('integrator'), $this->db, null);
    }

    public function test_customer_account_cannot_issue_fiscal_documents(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        FiscalApiDocumentContext::prepareFor($this->input(), $this->actor('client'), $this->db, null);
    }

    public function test_unauthenticated_context_cannot_fall_back_to_first_establishment(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        FiscalApiDocumentContext::prepareFor($this->input(), null, $this->db, null);
    }

    public function test_existing_api_operation_survives_profile_archival(): void
    {
        $profile = $this->profile('digital');
        $actor = $this->actor('integrator');
        $prepared = FiscalApiDocumentContext::prepareFor($this->input(), $actor, $this->db, null);
        $this->repository->reserveForProfile($profile, 'api-sale', $prepared['fiscal_fingerprint'], 1, 'digital');
        $this->db->table('fiscal_profiles')->where('id', $profile)->update(['active' => false]);
        $retry = FiscalApiDocumentContext::prepareFor($this->input(), $actor, $this->db, null);
        $this->assertSame($profile, $retry['fiscal_profile_id']);
        $this->assertSame($prepared['fiscal_fingerprint'], $retry['fiscal_fingerprint']);
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
