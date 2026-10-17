<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use App\Support\Exact\SalesConsolidated;
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactSalesConsolidatedTest extends TestCase
{
    private function row(array $changes=[]): array
    {
        return array_replace(['id'=>1, 'item_id'=>5, 'source_type'=>'document', 'document_type_id'=>'01',
            'currency_type_id'=>'VES', 'exchange_rate_sale'=>'1.00000000', 'quantity'=>'1', 'total'=>'500',
            'item'=>['unit_type_id'=>'UND', 'description'=>'Precio original', 'unit_price'=>'999', 'internal_id'=>'A']], $changes);
    }

    public function test_group_sums_actual_lines_and_never_the_first_catalog_price(): void
    {
        $rows = [SalesConsolidated::line($this->row()), SalesConsolidated::line($this->row(['id'=>2, 'quantity'=>'2', 'total'=>'1400']))];
        $group = SalesConsolidated::group($rows)[0];
        self::assertSame('3', $group['quantity']);
        self::assertSame('1900', $group['total_sale']);
        self::assertSame('1900', SalesConsolidated::summary([$group])['total_sale']);
        self::assertSame('1900', SalesConsolidated::summary($rows)['total_sale']);
    }

    public function test_fractions_survive_grouping_historical_conversion_and_credit_notes(): void
    {
        $usd = $this->row(['currency_type_id'=>'USD', 'exchange_rate_sale'=>'3.00000000', 'quantity'=>'0.333333333333', 'total'=>'0.333333333333',
            'calculation_snapshot'=>Decimal::snapshot(['quantity'=>'1/3', 'total'=>'1/3'])]);
        $rows = [SalesConsolidated::line($usd), SalesConsolidated::line(array_replace($usd, ['id'=>2])),
            SalesConsolidated::line($this->row(['id'=>3, 'document_type_id'=>'07', 'total'=>'0.5', 'quantity'=>'0.5']))];
        $summary = SalesConsolidated::summary($rows);
        $groups = SalesConsolidated::group($rows);
        self::assertSame('3/2', (string)Decimal::readRequired($summary, 'total_sale'));
        self::assertSame('1/6', (string)Decimal::readRequired($groups[0], 'quantity'));
        self::assertSame('2/3', (string)Decimal::readRequired($groups[0], 'total_usd'));
        self::assertSame($summary, SalesConsolidated::summary($groups));
    }

    public function test_unit_groups_and_union_identity_do_not_collide(): void
    {
        $first = SalesConsolidated::line($this->row());
        $other = SalesConsolidated::line($this->row(['source_type'=>'sale_note', 'item'=>['unit_type_id'=>'KG', 'description'=>'Otra unidad']]));
        self::assertNotSame($first['report_key'], $other['report_key']);
        self::assertCount(2, SalesConsolidated::group([$first, $other]));
    }

    /** @dataProvider invalidRows */
    public function test_missing_operands_or_historical_rate_fail(array $changes): void
    {
        $this->expectException(\UnexpectedValueException::class);
        SalesConsolidated::line($this->row($changes));
    }

    public static function invalidRows(): array
    {
        return [[['total'=>null]], [['currency_type_id'=>'USD', 'exchange_rate_sale'=>null]],
            [['currency_type_id'=>'USD', 'exchange_rate_sale'=>'0']], [['currency_type_id'=>'PEN']]];
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
