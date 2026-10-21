<?php

namespace Tests\Unit;

use App\Support\Exact\{Decimal as D, HeaderAllocation as H, LineCalculation};
use PHPUnit\Framework\TestCase;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class ExactHeaderAllocationTest extends TestCase
{
    public function test_an_adjustment_without_an_amount_cannot_silently_become_zero(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        \App\Support\Exact\HeaderAllocation::discounts([['discount_type_id'=>'02']],\App\Support\Exact\HeaderAllocation::stats());
    }
    private function line(string $price, string $type = '20', array $discounts = []): array
    {
        return LineCalculation::calculate(['quantity'=>'1', 'unit_price'=>$price,
            'affectation_igv_type_id'=>$type, 'discounts'=>$discounts], $type === '10' ? '16' : '0');
    }

    private function header(array $changes = []): array
    {
        return array_replace(['total'=>'200.4', 'total_value'=>'196', 'total_igv'=>'14.4', 'total_other_taxes'=>'0',
            'total_discount'=>'10', 'total_charge'=>'6', 'total_prepayment'=>'10',
            'discounts'=>[['discount_type_id'=>'02', 'amount'=>'10']]], $changes);
    }

    private function allocate(array $lines, array $header): array
    {
        $stats = H::stats(); foreach ($lines as $line) H::add($stats, $line);
        $context = H::context($header, $stats);
        return array_map(fn($line)=>H::line($line, $context), $lines);
    }

    public function test_taxed_discount_never_reduces_exempt_base_and_all_components_reconcile(): void
    {
        $rows = $this->allocate([$this->line('116', '10'), $this->line('100')], $this->header());
        self::assertTrue($rows[1]['header_discount']->isZero());
        self::assertTrue($rows[1]['header_iva_adjustment']->isZero());
        self::assertSame('10', (string)$rows[0]['header_discount']);
        self::assertSame('-8/5', (string)$rows[0]['header_iva_adjustment']);
        self::assertSame('10', (string)D::sum(array_column($rows, 'header_prepayment')));
        self::assertSame('1002/5', (string)D::sum(array_column($rows, 'report_total')));
        self::assertTrue($rows[0]['report_total']->isPositive());
    }

    public function test_distributed_unaffected_discount_does_not_consume_a_taxed_discount_twice(): void
    {
        $lines = [$this->line('116', '10', [
            ['discount_type_id'=>'00', 'is_amount'=>true, 'from_global_distribution'=>true, 'amount'=>'5'],
            ['discount_type_id'=>'01', 'is_amount'=>true, 'from_global_distribution'=>true, 'amount'=>'3'],
        ])];
        $stats = H::stats(); H::add($stats, $lines[0]);
        $parts = H::discounts([['discount_type_id'=>'02','amount'=>'10'], ['discount_type_id'=>'03','amount'=>'3']], $stats);
        self::assertSame('5', (string)$parts['global']);
        self::assertSame('5', (string)$parts['taxed']);
    }

    public function test_advance_already_in_discount_codes_is_not_subtracted_again(): void
    {
        $header = $this->header(['total'=>'204.4', 'total_value'=>'190', 'total_charge'=>'0',
            'discounts'=>[['discount_type_id'=>'04','amount'=>'10']]]);
        $rows = $this->allocate([$this->line('116', '10'), $this->line('100')], $header);
        self::assertTrue(D::sum(array_column($rows, 'header_prepayment'))->isZero());
        self::assertSame('1022/5', (string)D::sum(array_column($rows, 'report_total')));
    }

    public function test_one_unit_discount_keeps_exact_thirds_and_never_reuses_the_projection(): void
    {
        $header = $this->header(['total'=>'299', 'total_value'=>'299', 'total_igv'=>'0', 'total_charge'=>'0',
            'total_discount'=>'1', 'total_prepayment'=>'0', 'discounts'=>[['discount_type_id'=>'03', 'amount'=>'1']]]);
        $rows = $this->allocate(array_fill(0,3,$this->line('100')), $header);
        self::assertSame('1/3', (string)$rows[0]['header_discount']);
        self::assertSame('299/3', (string)$rows[0]['report_total']);
        self::assertSame('299', (string)D::sum(array_column($rows, 'report_total')));
    }

    public function test_header_inconsistency_is_explicit_instead_of_inventing_an_adjustment(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->allocate([$this->line('116','10'),$this->line('100')], $this->header(['total'=>'199']));
    }

    public function test_export_header_discount_is_allocated_without_domestic_iva_pools(): void
    {
        $header = $this->header(['total'=>'90','total_value'=>'90','total_igv'=>'0','total_charge'=>'0','total_prepayment'=>'0']);
        $rows = $this->allocate([$this->line('100','EXP')],$header);
        self::assertSame('10',(string)$rows[0]['header_discount']);
        self::assertSame('90',(string)$rows[0]['report_total']);
    }

    public function test_unmatched_eligibility_is_an_error_rather_than_an_unallocated_discount(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->allocate([$this->line('100','20')],$this->header(['total'=>'90','total_value'=>'90','total_igv'=>'0','total_charge'=>'0','total_prepayment'=>'0']));
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
