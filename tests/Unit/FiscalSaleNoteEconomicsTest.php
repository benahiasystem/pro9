<?php
namespace Tests\Unit;
use App\Services\Fiscal\FiscalSaleNoteEconomics;
use PHPUnit\Framework\TestCase;

class FiscalSaleNoteEconomicsTest extends TestCase
{
    public function test_keeps_adjustments_without_reapplying_them_to_totals(): void
    {
        $discount = ['discount_type_id' => '03', 'description' => 'Descuento global', 'factor' => '0.10', 'base' => '100', 'amount' => '10'];
        $charge = ['charge_type_id' => '50', 'description' => 'Cargo global', 'factor' => '0.05', 'base' => '200', 'amount' => '10'];
        $snapshot = FiscalSaleNoteEconomics::capture([
            (object) ['id' => 2, 'total' => '90.00', 'total_discount' => '10', 'discounts' => json_encode([$discount])],
            ['id' => 1, 'total' => '210.00', 'total_charge' => '10', 'charges' => [$charge]],
        ]);
        self::assertSame([1, 2], $snapshot['source_ids']);
        self::assertSame('300.00', $snapshot['totals']['total']);
        self::assertSame([$discount], $snapshot['discounts']);
        self::assertSame([$charge], $snapshot['charges']);
        $input = FiscalSaleNoteEconomics::apply(['total' => 999, 'discounts' => [], 'date_of_issue' => '2026-09-20'], $snapshot);
        self::assertSame('300.00', $input['total']);
        self::assertSame('10.00', $input['total_discount']);
        self::assertSame('10.00', $input['total_charge']);
        self::assertSame('2026-09-20', $input['date_of_issue']);
    }
    public function test_distinct_global_discount_rates_keep_original_bases_and_factors(): void
    {
        $first = ['factor' => '0.10', 'base' => '100', 'amount' => '10'];
        $second = ['factor' => '0.20', 'base' => '200', 'amount' => '40'];
        $snapshot = FiscalSaleNoteEconomics::capture([['id' => 1, 'total' => '90', 'discounts' => [$first]], ['id' => 2, 'total' => '160', 'discounts' => [$second]]]);
        self::assertSame([$first, $second], $snapshot['discounts']);
        self::assertSame('250.00', $snapshot['totals']['total']);
    }
    public function test_sums_money_exactly(): void
    {
        $snapshot = FiscalSaleNoteEconomics::capture([['id' => 1, 'total' => '0.10'], ['id' => 2, 'total' => '0.20']]);
        self::assertSame('0.30', $snapshot['totals']['total']);
    }
    public function test_duplicate_sources_are_rejected(): void
    {
        $this->expectException(\DomainException::class);
        FiscalSaleNoteEconomics::capture([['id' => 1], ['id' => 1]]);
    }
}
