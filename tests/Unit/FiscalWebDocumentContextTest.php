<?php

namespace Tests\Unit;

use App\Models\Tenant\User;
use App\Services\Fiscal\FiscalWebDocumentContext;
use App\Services\FiscalProfileService;
use Tests\Support\FiscalDatabaseTestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalWebDocumentContextTest extends FiscalDatabaseTestCase
{
    private function user(): User
    {
        $user = new User();
        $user->setRawAttributes(['id' => 1, 'establishment_id' => 1]);
        return $user;
    }

    private function input(): array
    {
        return ['operation_key' => 'sale-1', 'establishment_id' => 1, 'document_type_id' => '01', 'customer_id' => 20, 'items' => [['quantity' => 1, 'item_id' => 2]]];
    }

    private function profile(): int
    {
        return (new FiscalProfileService($this->db))->save(1, [
            'name' => 'Demo', 'channel' => 'presential', 'document_type_id' => '01', 'mode' => 'digital',
            'sequence_id' => $this->repository->createSequence('01', 'A', 25, 1),
            'provider' => 'simulator', 'configuration' => [], 'active' => true,
        ], 1)['id'];
    }

    public function test_untrusted_fiscal_overrides_are_replaced_without_reserving(): void
    {
        $profile = $this->profile();
        $input = array_merge($this->input(), ['series' => 'FF01', 'number' => 900, 'fiscal_profile_id' => 999, 'fiscal_channel' => 'digital', 'fiscal_group_id' => 10, 'fiscal_fingerprint' => 'spoof']);
        $result = FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
        $this->assertSame($profile, $result['fiscal_profile_id']);
        $this->assertSame('presential', $result['fiscal_channel']);
        $this->assertNull($result['fiscal_group_id']);
        $this->assertSame('A', $result['series']);
        $this->assertSame('#', $result['number']);
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
    }

    public function test_archived_profile_retry_preserves_original_context(): void
    {
        $profile = $this->profile();
        $input = $this->input();
        $first = FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
        $this->repository->reserveForProfile($profile, $input['operation_key'], $first['fiscal_fingerprint'], 1, 'presential');
        $this->db->table('fiscal_profiles')->update(['active' => false]);
        $retry = FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
        $this->assertSame($first, $retry);
    }

    public function test_changed_items_reject_existing_operation_key(): void
    {
        $profile = $this->profile();
        $input = $this->input();
        $first = FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
        $this->repository->reserveForProfile($profile, $input['operation_key'], $first['fiscal_fingerprint'], 1, 'presential');
        $input['items'][0]['quantity'] = 2;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
    }

    public function test_other_establishment_is_rejected_before_profile_lookup(): void
    {
        $input = $this->input();
        $input['establishment_id'] = 2;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
    }

    public function test_missing_operation_key_is_rejected(): void
    {
        $input = $this->input();
        unset($input['operation_key']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
    }

    /** @dataProvider reservedKeys */
    public function test_public_payload_cannot_claim_internal_operation(string $key): void
    {
        $input = array_replace($this->input(), ['operation_key' => $key, 'internal_order_id' => 25]);
        try {
            FiscalWebDocumentContext::prepareFor($input, $this->user(), $this->db, null);
            $this->fail('Internal key accepted from public payload');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('operation_key', $exception->errors());
        }
        $this->assertSame(0, $this->db->table('fiscal_number_reservations')->count());
    }

    public static function reservedKeys(): array
    {
        return [['ecommerce-order-25-invoice'], ['ECOMMERCE-ORDER-25-INVOICE'], ['contingency-1'], ['CONTINGENCY-1'],
            ['print-replacement-1'], ['PRINT-REPLACEMENT-1']];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
