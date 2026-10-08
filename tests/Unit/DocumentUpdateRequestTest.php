<?php
namespace Tests\Unit;

use App\Http\Requests\Tenant\DocumentUpdateRequest;
use Tests\TestCase;

class DocumentUpdateRequestTest extends TestCase
{
    public function test_update_accepts_the_configured_empty_series(): void
    {
        $input = ['id' => 16, 'customer_id' => 2, 'establishment_id' => 1,
            'date_of_issue' => '2026-10-07', 'exchange_rate_sale' => '1.00000000'];
        $rules = (new DocumentUpdateRequest)->rules();
        foreach ([[], ['series' => null], ['series' => ''], ['series' => '   '], ['series' => 'FF01']] as $series) {
            $validator = app('validator')->make(array_replace($input, $series), $rules);
            self::assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
        }
        foreach ([['series' => []], ['series' => str_repeat('A', 21)], ['issuer' => ['name' => 'Untrusted']], ['control_number' => '00-1']] as $invalid) {
            self::assertTrue(app('validator')->make(array_replace($input, $invalid), $rules)->fails());
        }
    }
}
