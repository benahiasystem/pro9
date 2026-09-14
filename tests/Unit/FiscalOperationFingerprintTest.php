<?php

namespace Tests\Unit;

use App\Services\Fiscal\FiscalOperationFingerprint;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalOperationFingerprintTest extends TestCase
{
    public function test_object_key_order_and_generated_identifiers_do_not_change_fingerprint(): void
    {
        $a = ['items' => [['id' => 2, 'quantity' => 1]], 'customer_id' => 4, 'external_id' => 'first'];
        $b = ['external_id' => 'retry', 'customer_id' => 4, 'items' => [['quantity' => 1, 'id' => 2]]];
        $this->assertSame(FiscalOperationFingerprint::forWebDocument($a, 1), FiscalOperationFingerprint::forWebDocument($b, 1));
    }

    public function test_actor_payment_amount_and_item_order_are_part_of_operation(): void
    {
        $input = ['items' => [['id' => 1], ['id' => 2]], 'payments' => [['amount' => 5]]];
        $hash = FiscalOperationFingerprint::forWebDocument($input, 1);
        $this->assertNotSame($hash, FiscalOperationFingerprint::forWebDocument($input, 2));
        $input['payments'][0]['amount'] = 6;
        $this->assertNotSame($hash, FiscalOperationFingerprint::forWebDocument($input, 1));
        $input['payments'][0]['amount'] = 5;
        $input['items'] = array_reverse($input['items']);
        $this->assertNotSame($hash, FiscalOperationFingerprint::forWebDocument($input, 1));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
