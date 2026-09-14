<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\DocumentController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalPaymentDestinationTest extends TestCase
{
    /** @dataProvider nonCashPayments */
    public function test_payments_without_cash_destination_do_not_require_a_cash_session(array $payments): void
    {
        // No auth/database container: a false search result must not enter the cash lookup.
        $this->assertTrue((new DocumentController())->validationOpenCash(new Request(['payments' => $payments])));
    }

    public static function nonCashPayments(): array
    {
        return [[[]], [[['payment_destination_id' => 15, 'payment' => 232]]], [[['payment' => 232]]]];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
