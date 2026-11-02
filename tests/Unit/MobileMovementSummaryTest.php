<?php

namespace Tests\Unit;

use App\Services\Payments\MobileMovementSummary;
use PHPUnit\Framework\TestCase;

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
final class MobileMovementSummaryTest extends TestCase
{
    private function row(string $currency, string $direction, string $n, string $d='1'): array
    {
        return ['currency_type_id'=>$currency,'direction'=>$direction,'calculation_snapshot'=>['values'=>['amount'=>['n'=>$n,'d'=>$d]]]];
    }

    public function test_separates_received_money_and_keeps_exact_fractions(): void
    {
        $summary=new MobileMovementSummary();
        $summary->add($this->row('VES','income','4000'));
        $summary->add($this->row('USD','income','10'));
        $summary->add($this->row('USD','egress','1','3'));
        $data=$summary->result();
        self::assertSame(3,$data['count']);
        self::assertNull($data['total_input']);
        self::assertSame('4000',$data['totals_by_currency']['VES']['balance']);
        self::assertSame(['n'=>'29','d'=>'3'],$data['totals_by_currency']['USD']['calculation_snapshot']['values']['balance']);
    }

    public function test_legacy_scalar_totals_exist_only_for_unambiguous_ves(): void
    {
        $summary=new MobileMovementSummary();
        $summary->add($this->row('VES','income','5'));
        self::assertSame(5.0,$summary->result()['total_input']);
        $summary->add(null);
        self::assertNull($summary->result()['total_input']);
        self::assertSame(1,$summary->result()['unclassified_count']);
    }
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
