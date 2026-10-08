<?php

namespace Tests\Unit;

use App\Services\Fiscal\HkaA5Text;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Math\Matrix;

class HkaA5TextTest extends TestCase
{
    public function test_character_positions_shrink_with_glyphs_and_new_lines_keep_their_anchors(): void
    {
        $result = HkaA5Text::normalize('BT /F1 12 Tf 10 30 Td (A) Tj 7 0 Td (B) Tj 14 TL (next) \' 1 2 (third) " ET', .97, .53, new Matrix());
        preg_match_all('/([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) Tm/', $result, $matrices, PREG_SET_ORDER);
        self::assertCount(5, $matrices);
        self::assertEqualsWithDelta(10, $matrices[1][5], 1e-6);
        self::assertEqualsWithDelta(10 + 7 * .53 / .97, $matrices[2][5], 1e-6);
        self::assertEqualsWithDelta(30, $matrices[2][6], 1e-6);
        self::assertEqualsWithDelta(17, $matrices[3][5], 1e-6);
        self::assertEqualsWithDelta(16, $matrices[3][6], 1e-6);
        self::assertEqualsWithDelta(2, $matrices[4][6], 1e-6);
        self::assertStringContainsString('1 Tw', $result);
        self::assertStringContainsString('2 Tc', $result);
    }

    public function test_graphics_and_page_rotations_keep_the_same_glyph_shape_as_the_original(): void
    {
        foreach ([new Matrix(), new Matrix(0, 1, -1, 0)] as $page) {
            foreach ([new Matrix(), new Matrix(.8, .6, -.6, .8), new Matrix(0, -1, 1, 0)] as $ctm) {
                $cm = implode(' ', $ctm->getValues()).' cm';
                $result = HkaA5Text::normalize('q '.$cm.' BT 1 0 0 1 20 30 Tm (Text) Tj ET Q', .97, .53, $page);
                preg_match_all('/([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) Tm/', $result, $matches, PREG_SET_ORDER);
                $values = array_map('floatval', array_slice(end($matches), 1));
                $glyph = new Matrix($values[0], $values[1], $values[2], $values[3]);
                $actual = $glyph->multiply($ctm)->multiply($page)->multiply(new Matrix(.97, 0, 0, .53));
                $expected = $ctm->multiply($page)->multiply(new Matrix(.53, 0, 0, .53));
                foreach (array_slice($expected->getValues(), 0, 4) as $index => $value) self::assertEqualsWithDelta($value, $actual->getValues()[$index], 1e-7);
                self::assertEqualsWithDelta(20, $values[4], 1e-7);
                self::assertEqualsWithDelta(30, $values[5], 1e-7);
            }
        }
    }

    public function test_literal_strings_arrays_existing_font_scaling_and_images_are_preserved(): void
    {
        $literal = '(BT ET Tm Td \(escaped\) \\007)';
        $inline = 'BI /W 6 /H 1 /BPC 8 /CS /RGB ID '.str_pad(' EI BT ET Tm ', 18, "\x00")." EI\n";
        $encoded = "BI /W 1 /H 1 /BPC 8 /CS /RGB /F /AHx ID 0080FF> EI\n";
        $content = 'q 10 0 0 20 5 6 cm '.$inline.$encoded.' Q BT /F1 12 Tf 80 Tz 2 Tc 3 Tw 10 20 Td '.$literal.' Tj [<4142> -20 (CD)] TJ ET';
        $result = HkaA5Text::normalize($content, .97, .53, new Matrix());
        self::assertStringContainsString($literal.' Tj', $result);
        self::assertStringContainsString('[<4142> -20 (CD)] TJ', $result);
        self::assertStringContainsString('/F1 12 Tf 80 Tz 2 Tc 3 Tw', $result);
        self::assertStringContainsString($inline, $result);
        self::assertStringContainsString($encoded, $result);
        self::assertStringContainsString('10 0 0 20 5 6 cm', $result);
    }
}
