<?php

namespace Tests\Unit;

use App\Models\Tenant\QuotationItem;
use App\Support\Exact\{Decimal, PdfAmounts};
use PHPUnit\Framework\TestCase;

final class QuotationPdfAdjustmentsTest extends TestCase
{
    public function test_discount_reads_decimal_tokens_from_stored_json(): void
    {
        $line=new QuotationItem();
        $line->setRawAttributes(['percentage_igv'=>'0','discounts'=>'[{"discount_type_id":"01","amount":9007199254740993.01}]']);
        self::assertSame('9,007,199,254,740,993.01',Decimal::format(PdfAmounts::lineDiscount($line,false)));
    }

    public function test_percentage_and_amount_use_stored_exact_values(): void
    {
        $line=new QuotationItem();
        $line->setRawAttributes(['percentage_igv'=>'0','discounts'=>'[{"factor":0.1,"percentage":20,"amount":0.333333333333,"amount_exact":{"n":"1","d":"3"},"description":"Oferta"}]']);
        $entry=PdfAmounts::adjustments($line,'discounts')[0];
        self::assertSame('20',PdfAmounts::adjustmentPercent($entry));
        self::assertSame('0.33',PdfAmounts::adjustmentAmount($entry));
        $legacy=(object)['factor'=>'0.123456789012','amount'=>'9007199254740993.01'];
        self::assertSame('12.3456789012',PdfAmounts::adjustmentPercent($legacy));
        self::assertSame('9,007,199,254,740,993.01',PdfAmounts::adjustmentAmount($legacy));
    }

    public function test_discount_amount_fraction_and_iva_remain_exact(): void
    {
        $line=new QuotationItem();
        $line->setRawAttributes(['percentage_igv'=>'16','discounts'=>'[{"discount_type_id":"00","amount":0.333333333333,"amount_exact":{"n":"1","d":"3"}}]']);
        self::assertSame(['n'=>'29','d'=>'75'],Decimal::fraction(PdfAmounts::lineDiscount($line,false)));
        self::assertSame('0.39',Decimal::format(PdfAmounts::lineDiscount($line,false)));
    }

    public function test_ticket_tax_rate_does_not_claim_one_rate_when_there_are_several(): void
    {
        self::assertSame('16',PdfAmounts::singleTaxRate([(object)['percentage_igv'=>'16'],(object)['percentage_igv'=>'0']]));
        self::assertSame('16.123456789012',PdfAmounts::singleTaxRate([(object)['percentage_igv'=>'16.123456789012']]));
        self::assertNull(PdfAmounts::singleTaxRate([(object)['percentage_igv'=>'16'],(object)['percentage_igv'=>'8']]));
        self::assertNull(PdfAmounts::singleTaxRate([(object)['percentage_igv'=>'0']]));
    }
}
