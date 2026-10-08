<?php

namespace Tests\Unit;

use App\Services\Fiscal\{HkaA5Fonts, HkaA5Layout, HkaA5Text};
use setasign\Fpdi\Math\Matrix;
use setasign\Fpdi\PdfParser\{PdfParser, StreamReader};
use setasign\Fpdi\PdfParser\Type\{PdfArray, PdfDictionary, PdfHexString, PdfName, PdfNumeric, PdfStream, PdfString, PdfToken};
use Tests\TestCase;

class HkaA5LayoutTest extends TestCase
{
    private function fonts(): HkaA5Fonts
    {
        $resources = PdfDictionary::create(['Font' => PdfDictionary::create([
            'F1' => PdfDictionary::create(['Subtype' => PdfName::create('Type1'), 'BaseFont' => PdfName::create('Helvetica')]),
            'F2' => PdfDictionary::create(['Subtype' => PdfName::create('Type1'), 'BaseFont' => PdfName::create('Helvetica-Bold')]),
        ])]);
        return new HkaA5Fonts($resources, new PdfParser(StreamReader::createByString('')));
    }

    private function width(string $text, string $font = 'F1', float $size = 10): float
    {
        return $this->fonts()->measure($font, PdfString::create($text), $size, 0, 0, 100)['width'];
    }

    private function convert(string $source): string
    {
        $layout = new HkaA5Layout($this->fonts(), new Matrix(), .97, .53, [0, 0, 200, 300]);
        HkaA5Text::normalize($source, .97, .53, new Matrix(), null, null, [$layout, 'observe']);
        $layout->finish();
        return HkaA5Text::normalize($source, .97, .53, new Matrix(), null, $layout);
    }

    private function draws(string $content): array
    {
        $parser = new PdfParser(StreamReader::createByString($content));
        $operands = $draws = [];
        $matrix = [];
        while (($value = $parser->readValue()) !== false) {
            if (!$value instanceof PdfToken) { $operands[] = $value; continue; }
            if ($value->value === 'Tm') $matrix = array_map(fn ($value) => $value->value, $operands);
            if ($value->value === 'Tj') $draws[] = ['matrix' => $matrix, 'text' => $operands[0]->value];
            $operands = [];
        }
        return $draws;
    }

    public function test_original_cid_font_widths_unicode_and_kerning_determine_the_line_width(): void
    {
        $n = fn ($value) => PdfNumeric::create($value);
        $font = PdfDictionary::create([
            'Subtype' => PdfName::create('Type0'),
            'DescendantFonts' => PdfArray::create([PdfDictionary::create([
                'W' => PdfArray::create([$n(1), PdfArray::create([$n(500), $n(600)]), $n(3), $n(4), $n(700)]),
                'DW' => $n(800),
                'FontDescriptor' => PdfDictionary::create(['CapHeight' => $n(700), 'Descent' => $n(-220)]),
            ])]),
            'ToUnicode' => PdfStream::create(PdfDictionary::create([]),
                '2 beginbfchar <0001> <0042> <0002> <0073> endbfchar '
                .'2 beginbfrange <0003> <0004> <0030> <0005> <0006> [<00E1> <00A0>] endbfrange'),
        ]);
        $resources = PdfDictionary::create(['Font' => PdfDictionary::create(['F16' => $font])]);
        $fonts = new HkaA5Fonts($resources, new PdfParser(StreamReader::createByString('')));
        $text = PdfArray::create([PdfHexString::create('000100020003'), $n(100), PdfHexString::create('000400050006')]);
        $measurement = $fonts->measure('F16', $text, 10, .2, 5, 90);
        self::assertSame("Bs01á\u{00A0}", $measurement['text']);
        self::assertEqualsWithDelta((41 - 1 + 6 * .2) * .9, $measurement['width'], 1e-6);
        self::assertEqualsWithDelta(8.5, $measurement['cap'], 1e-6);
        self::assertEqualsWithDelta(2.2, $measurement['descent'], 1e-6);
    }

    public function test_multiline_column_headings_keep_the_same_centres_and_normal_glyphs(): void
    {
        $source = 'q 0 240 200 40 re 0 .5 .8 rg f 1 g BT /F2 10 Tf ';
        foreach (['Price', 'Unit', 'Subtotal', 'Item'] as $index => $text) {
            $center = $index < 2 ? 50 : 150;
            $y = $index % 2 ? 250 : 265;
            $x = $center - $this->width($text, 'F2') / 2;
            $source .= "1 0 0 1 $x $y Tm ($text) Tj ";
        }
        $draws = $this->draws($this->convert($source.'ET Q'));
        self::assertCount(4, $draws);
        foreach ($draws as $index => $draw) {
            $matrix = $draw['matrix'];
            $center = $matrix[4] + $matrix[0] * $this->width($draw['text'], 'F2') / 2;
            self::assertEqualsWithDelta($index < 2 ? 50 : 150, $center, 1e-6);
            self::assertEqualsWithDelta($matrix[0] * .97, $matrix[3] * .53, 1e-6);
        }
    }

    public function test_amounts_of_different_lengths_keep_a_common_right_edge(): void
    {
        $source = 'BT /F1 10 Tf ';
        foreach (['100.00', '0.00', '1200000.00'] as $index => $text) {
            $x = 180 - $this->width($text);
            $y = 200 - $index * 15;
            $source .= "1 0 0 1 $x $y Tm ($text) Tj ";
        }
        foreach ($this->draws($this->convert($source.'ET')) as $draw) {
            self::assertEqualsWithDelta(180, $draw['matrix'][4] + $draw['matrix'][0] * $this->width($draw['text']), 1e-6);
        }
    }

    public function test_mixed_font_labels_and_values_stay_together_and_left_aligned(): void
    {
        $valueX = 10 + $this->width('Customer:', 'F2') + 3;
        $source = "BT /F2 10 Tf 1 0 0 1 10 200 Tm (Customer:) Tj /F1 10 Tf 1 0 0 1 $valueX 200 Tm (Jane) Tj ET";
        [$label, $value] = $this->draws($this->convert($source));
        self::assertSame('Customer:', $label['text']); self::assertSame('Jane', $value['text']);
        self::assertEqualsWithDelta(10, $label['matrix'][4], 1e-6);
        self::assertEqualsWithDelta(3 * .53 / .97, $value['matrix'][4] - $label['matrix'][4] - $label['matrix'][0] * $this->width('Customer:', 'F2'), 1e-6);
        self::assertEqualsWithDelta($label['matrix'][5], $value['matrix'][5], 1e-6);
        // A column label may happen to sit near the page centre without being centred.
        $source = 'q 1 0 0 1 80 0 cm BT /F1 10 Tf 1 0 0 1 0 150 Tm (Telephone:) Tj ET Q';
        self::assertEqualsWithDelta(0, $this->draws($this->convert($source))[0]['matrix'][4], 1e-6);
    }

    public function test_footer_lines_are_centred_and_page_bounds_do_not_clip_text(): void
    {
        $x = 100 - $this->width('Fiscal footer') / 2 + 2;
        $source = "BT /F1 10 Tf 1 0 0 1 $x 20 Tm (Fiscal footer) Tj 1 0 0 1 195 40 Tm (Overflow) Tj ET";
        [$footer, $edge] = $this->draws($this->convert($source));
        self::assertEqualsWithDelta(100, $footer['matrix'][4] + $footer['matrix'][0] * $this->width('Fiscal footer') / 2, 1e-6);
        self::assertLessThanOrEqual(199.5, $edge['matrix'][4] + $edge['matrix'][0] * $this->width('Overflow'));
    }

    public function test_a_tall_paragraph_expands_its_clip_upward_and_keeps_both_lines_visible(): void
    {
        $source = 'q 0 10 100 10 re W n BT /F1 8 Tf 1 0 0 1 5 12 Tm (Exchange rate) Tj 1 0 0 1 5 3 Tm (Bs. 874.7321) Tj ET Q';
        $result = $this->convert($source);
        preg_match('/([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) re/', $result, $clip);
        $draws = $this->draws($result);
        self::assertCount(2, $draws);
        self::assertEqualsWithDelta(10, (float) $clip[2], 1e-6);
        self::assertGreaterThan(10, (float) $clip[4]);
        foreach ($draws as $draw) {
            self::assertGreaterThanOrEqual((float) $clip[2], $draw['matrix'][5] - 8 * .22);
            self::assertLessThanOrEqual((float) $clip[2] + (float) $clip[4], $draw['matrix'][5] + 8 * .85);
        }
        self::assertEqualsWithDelta(9, $draws[0]['matrix'][5] - $draws[1]['matrix'][5], 1e-6);
    }

    public function test_fiscal_qr_keeps_its_original_physical_size_above_the_item_table(): void
    {
        $source = 'q 0 580 612 30 re 0 .5 .8 rg f Q q 48 0 0 48 500 620 cm /QR Do Q';
        $layout = new HkaA5Layout($this->fonts(), new Matrix(), .97, .53, [0, 0, 612, 792], ['QR' => [245, 245]]);
        HkaA5Text::normalize($source, .97, .53, new Matrix(), null, null, [$layout, 'observe']);
        $layout->finish();
        $original = new Matrix(48, 0, 0, 48, 500, 620);
        $correction = $layout->imagePlacement('QR', $original);
        self::assertInstanceOf(Matrix::class, $correction);
        $actual = $correction->multiply($original)->multiply(new Matrix(.97, 0, 0, .53));
        [$a, $b, $c, $d, $x, $y] = $actual->getValues();
        self::assertEqualsWithDelta(48, hypot($a, $b), 1e-6);
        self::assertEqualsWithDelta(48, hypot($c, $d), 1e-6);
        self::assertGreaterThanOrEqual(610 * .53 + 1.5, $y);
        self::assertLessThanOrEqual(612 * .97, $x + $a);
        $result = HkaA5Text::normalize($source, .97, .53, new Matrix(), fn ($name, $ctm) => $layout->imagePlacement($name, $ctm));
        self::assertStringContainsString('/QR Do Q', $result);
        self::assertStringContainsString('0 580 612 30 re', $result);
    }

    public function test_logo_is_scaled_uniformly_and_repeated_watermarks_are_preserved(): void
    {
        $source = 'q 150 0 0 75 6 710 cm /Logo Do Q q /Demo Do /Demo Do Q';
        $layout = new HkaA5Layout($this->fonts(), new Matrix(), .97, .53, [0, 0, 612, 792], ['Logo' => [960, 480], 'Demo' => [340, 135]]);
        HkaA5Text::normalize($source, .97, .53, new Matrix(), null, null, [$layout, 'observe']);
        $layout->finish();
        $original = new Matrix(150, 0, 0, 75, 6, 710);
        $actual = $layout->imagePlacement('Logo', $original)->multiply($original)->multiply(new Matrix(.97, 0, 0, .53));
        [$a, $b, $c, $d] = $actual->getValues();
        self::assertEqualsWithDelta(150 * .53, hypot($a, $b), 1e-6);
        self::assertEqualsWithDelta(75 * .53, hypot($c, $d), 1e-6);
        self::assertNull($layout->imagePlacement('Demo', new Matrix(255, 0, 0, 101, 0, 690)));
    }

    public function test_rotated_nested_qr_keeps_the_original_size_after_page_conversion(): void
    {
        $page = new Matrix(0, .8, -.8, 0, 680, 20);
        $original = (new Matrix(48, 0, 0, 48, 500, 620))->multiply($page);
        $layout = new HkaA5Layout($this->fonts(), $page, .75, .68, [0, 0, 612, 792], ['QR' => [245, 245]]);
        $layout->observe('Do', [PdfName::create('QR')]);
        $correction = $layout->imagePlacement('QR', $original);
        self::assertInstanceOf(Matrix::class, $correction);
        $actual = $correction->multiply($original)->multiply(new Matrix(.75, 0, 0, .68));
        foreach (array_slice($original->getValues(), 0, 4) as $index => $value) {
            self::assertEqualsWithDelta($value, $actual->getValues()[$index], 1e-6);
        }
    }
}
