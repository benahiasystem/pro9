<?php

namespace Tests\Unit;

require_once __DIR__.'/../../app/Support/Exact/Decimal.php';
require_once __DIR__.'/../../app/Services/Payments/BankCommission.php';

use App\Services\Payments\BankCommission;
use PHPUnit\Framework\TestCase;

final class BankCommissionTest extends TestCase
{
    public function test_percentage_minimum_and_rounding_use_exact_received_amount(): void
    {
        self::assertSame('2.00', BankCommission::calculate('100', '2', '1')['amount']);
        self::assertSame('1.00', BankCommission::calculate('10', '2', '1')['amount']);
        self::assertSame('0.00', BankCommission::calculate('10')['amount']);
        $result = BankCommission::calculate('10.05', '10');
        self::assertSame('1.01', $result['amount']);
        self::assertSame(['n'=>'201', 'd'=>'200'], $result['calculation_snapshot']['values']['exact']);
        self::assertSame('0.50', BankCommission::calculate('0.50', null, '0.50')['amount']);
    }
}
