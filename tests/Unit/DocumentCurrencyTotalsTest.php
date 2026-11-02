<?php

namespace Tests\Unit;

use App\Support\Exact\Decimal;
use Modules\Report\Services\DocumentCurrencyTotals;
use PHPUnit\Framework\TestCase;

class DocumentCurrencyTotalsTest extends TestCase
{
    public function test_totals_stay_separate_and_round_only_after_exact_sum(): void
    {
        $make = function ($currency, $amount, $type = '01', $state = '05') {
            return new class($currency, $amount, $type, $state) {
                public $currency_type_id, $total, $total_taxed, $total_igv, $total_charge, $total_exonerated, $state_type_id, $type;
                public function __construct($currency, $amount, $type, $state) {
                    $this->currency_type_id=$currency; $this->total=$amount; $this->total_taxed=$amount;
                    $this->total_igv='0'; $this->total_charge='0'; $this->total_exonerated='0';
                    $this->type=$type; $this->state_type_id=$state;
                }
                public function getDocumentType() { return (object) ['id'=>$this->type]; }
            };
        };
        $totals=DocumentCurrencyTotals::empty();
        foreach ([$make('VES','1.005'), $make('VES','1.005'), $make('VES','0.01','07'),
            $make('USD','2.50'), $make('USD','3.25'), $make('USD','1','07'),
            $make('VES','999','01','11'), $make('USD','999','07','09'), $make('VES','999','08','09')] as $document) {
            $totals=DocumentCurrencyTotals::add($totals,$document);
        }
        self::assertSame('2.00',Decimal::format($totals['VES']['total']));
        self::assertSame('4.75',Decimal::format($totals['USD']['total']));
        self::assertSame('2.00',Decimal::format($totals['VES']['total_taxed']));
    }
}
