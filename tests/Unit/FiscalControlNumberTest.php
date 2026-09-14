<?php

namespace Tests\Unit;

use App\Services\FiscalControlNumber;
use PHPUnit\Framework\TestCase;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
class FiscalControlNumberTest extends TestCase
{
    public function test_normalizes_equivalent_controls_without_losing_identifier(): void
    {
        $short = new FiscalControlNumber('00-1');
        $padded = new FiscalControlNumber('00-00000001');
        $this->assertSame((string) $short, (string) $padded);
        $this->assertSame('00-00000001', (string) $short);
        $this->assertSame('00', $short->identifier());
        $this->assertSame(1, $short->sequence());
        $this->assertNotSame($short->ordinal(), (new FiscalControlNumber('01-1'))->ordinal());
    }

    /** @dataProvider invalidNumbers */
    public function test_rejects_document_series_and_malformed_controls(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FiscalControlNumber($value);
    }

    public static function invalidNumbers(): array
    {
        return array_map(fn ($value) => [$value], ['', 'FF01', 'P222462', '0-1', '000-1', '00-0', '00-00000000', '00-100000000', '00--1', '00-1.5', '00-1e2', '00-1\n', ' 00-1', '00-1 ']);
    }

    public function test_checks_both_range_boundaries_and_reference_invoice(): void
    {
        $start = new FiscalControlNumber('00-00360196');
        $end = new FiscalControlNumber('00-00373195');
        $this->assertTrue($start->isWithin($start, $end));
        $this->assertTrue($end->isWithin($start, $end));
        $this->assertTrue((new FiscalControlNumber('00-00364979'))->isWithin($start, $end));
        $this->assertFalse((new FiscalControlNumber('00-00360195'))->isWithin($start, $end));
        $this->assertFalse((new FiscalControlNumber('01-00364979'))->isWithin($start, $end));
    }

    public function test_rejects_inverted_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FiscalControlNumber('00-1'))->isWithin(new FiscalControlNumber('00-2'), new FiscalControlNumber('00-1'));
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
