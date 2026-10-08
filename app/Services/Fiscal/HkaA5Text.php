<?php

namespace App\Services\Fiscal;

use setasign\Fpdi\Math\Matrix;
use setasign\Fpdi\PdfParser\{PdfParser, StreamReader};
use setasign\Fpdi\PdfParser\Type\{PdfName, PdfNumeric, PdfToken};

/** Adjust placement and text clips; preserve strings, fonts, image bytes and other paths. */
final class HkaA5Text
{
    public static function normalize(string $content, float $scaleX, float $scaleY, Matrix $page, ?callable $form = null,
        ?HkaA5Layout $layout = null, ?callable $observe = null): string
    {
        $reader = StreamReader::createByString($content);
        $parser = new PdfParser($reader);
        $tokenizer = $parser->getTokenizer();
        $ctm = new Matrix();
        $originalLine = $outputLine = new Matrix();
        $leading = 0;
        $stack = $operands = $rawOperands = [];
        $inText = $shown = false;
        $start = 0;
        $output = '';
        $drawIndex = $rectangleIndex = 0;
        while (($token = $tokenizer->getNextToken()) !== false) {
            // Avoid numeric look-ahead, so raw operand boundaries stay exact.
            $value = is_numeric($token) ? PdfNumeric::create((float) $token) : $parser->readValue($token);
            $end = $reader->getPosition() + $reader->getOffset();
            if (!$value instanceof PdfToken) {
                $operands[] = $value;
                $rawOperands[] = substr($content, $start, $end - $start);
                $start = $end;
                continue;
            }
            $operator = $value->value;
            if ($observe !== null) $observe($operator, $operands);
            $raw = implode('', $rawOperands).substr($content, $start, $end - $start);
            $replacement = null;
            if ($operator === 'BI') {
                // Copy binary image data untouched; never tokenize it as text.
                $image = [];
                while (($imageToken = $tokenizer->getNextToken()) !== false && $imageToken !== 'ID') {
                    $key = $parser->readValue($imageToken);
                    if (!$key instanceof PdfName) throw new \RuntimeException('Invalid inline image dictionary.');
                    $imageToken = $tokenizer->getNextToken();
                    $image[$key->value] = is_numeric($imageToken) ? PdfNumeric::create((float) $imageToken) : $parser->readValue($imageToken);
                }
                if ($imageToken === false) throw new \RuntimeException('Incomplete inline image.');
                $dataStart = $reader->getPosition() + $reader->getOffset();
                $color = $image['CS'] ?? $image['ColorSpace'] ?? null;
                $channels = $color instanceof PdfName
                    ? (['RGB' => 3, 'DeviceRGB' => 3, 'G' => 1, 'DeviceGray' => 1, 'CMYK' => 4, 'DeviceCMYK' => 4][$color->value] ?? null)
                    : null;
                $width = $image['W']->value ?? $image['Width']->value ?? 0;
                $height = $image['H']->value ?? $image['Height']->value ?? 0;
                $bits = $image['BPC']->value ?? $image['BitsPerComponent']->value ?? 0;
                if (!isset($image['F']) && !isset($image['Filter']) && $channels && $width > 0 && $height > 0 && $bits > 0) {
                    // Unfiltered pixels may themselves contain the bytes " EI ".
                    $dataStart += substr($content, $dataStart, 2) === "\r\n" ? 2 : 1;
                    $dataEnd = $dataStart + (int) (ceil($width * $channels * $bits / 8) * $height);
                    $found = preg_match('/\G[\x00\x09\x0A\x0C\x0D\x20]+EI(?=[\x00\x09\x0A\x0C\x0D\x20])/', $content, $imageEnd, PREG_OFFSET_CAPTURE, $dataEnd);
                } else {
                    $found = preg_match('/[\x00\x09\x0A\x0C\x0D\x20]EI(?=[\x00\x09\x0A\x0C\x0D\x20])/', $content, $imageEnd, PREG_OFFSET_CAPTURE, $dataStart);
                }
                if (!$found) {
                    throw new \RuntimeException('Incomplete inline image data.');
                }
                $imageEnd = $imageEnd[0][1] + strlen($imageEnd[0][0]);
                $output .= $raw.substr($content, $end, $imageEnd - $end);
                $reader->reset($imageEnd);
                $operands = $rawOperands = [];
                $start = $imageEnd;
                continue;
            } elseif ($operator === 'q') {
                $stack[] = [$ctm, $leading];
            } elseif ($operator === 'Q') {
                if (!$stack) throw new \RuntimeException('Unbalanced PDF graphics state.');
                [$ctm, $leading] = array_pop($stack);
            } elseif ($operator === 'cm') {
                $numbers = self::numbers($operands, 6);
                $ctm = (new Matrix(...$numbers))->multiply($ctm);
            } elseif ($operator === 're') {
                $rectangle = $layout ? $layout->rectangle($rectangleIndex) : null;
                if ($rectangle !== null) $replacement = implode(' ', array_map([self::class, 'number'], $rectangle)).' re';
                $rectangleIndex++;
            } elseif ($operator === 'Do' && $form !== null) {
                if (count($operands) !== 1 || !$operands[0] instanceof PdfName) {
                    throw new \RuntimeException('Invalid PDF form operand.');
                }
                $name = $form($operands[0]->value, $ctm->multiply($page));
                if ($name instanceof Matrix) {
                    $replacement = 'q '.implode(' ', array_map([self::class, 'number'], $name->getValues())).' cm '.$raw.' Q';
                } elseif ($name !== null) $replacement = '/'.$name.' Do';
            } elseif ($operator === 'BT') {
                if ($inText) throw new \RuntimeException('Nested PDF text object.');
                $inText = true;
                $shown = false;
                $originalLine = new Matrix();
                $outputLine = self::textMatrix($originalLine, $ctm, $page, $scaleX, $scaleY);
                $replacement = 'BT '.self::command($outputLine);
            } elseif ($operator === 'ET') {
                $inText = false;
            } elseif ($operator === 'TL') {
                $leading = self::numbers($operands, 1)[0];
            } elseif ($inText && $operator === 'Tm') {
                $originalLine = new Matrix(...self::numbers($operands, 6));
                $outputLine = self::textMatrix($originalLine, $ctm, $page, $scaleX, $scaleY);
                $replacement = self::command($outputLine);
                $shown = false;
            } elseif ($inText && ($operator === 'Td' || $operator === 'TD')) {
                [$x, $y] = self::numbers($operands, 2);
                $originalLine = (new Matrix(1, 0, 0, 1, $x, $y))->multiply($originalLine);
                if ($shown && $y == 0) {
                    // HKA often places each character with a separate Td.
                    $outputLine = (new Matrix(1, 0, 0, 1, $x, $y))->multiply($outputLine);
                } else {
                    $outputLine = self::textMatrix($originalLine, $ctm, $page, $scaleX, $scaleY);
                }
                if ($operator === 'TD') $leading = -$y;
                $replacement = ($operator === 'TD' ? self::number($leading).' TL ' : '').self::command($outputLine);
            } elseif ($inText && in_array($operator, ['T*', "'", '"'], true)) {
                $originalLine = (new Matrix(1, 0, 0, 1, 0, -$leading))->multiply($originalLine);
                $outputLine = self::textMatrix($originalLine, $ctm, $page, $scaleX, $scaleY);
                if ($operator !== 'T*') {
                    $placement = $layout ? $layout->placement($drawIndex) : null;
                    if ($placement !== null) $outputLine = self::textMatrix($placement, $ctm, $page, $scaleX, $scaleY);
                    $drawIndex++;
                }
                $replacement = self::command($outputLine);
                if ($operator === "'") {
                    $replacement .= ' '.implode('', $rawOperands).' Tj';
                } elseif ($operator === '"') {
                    if (count($operands) !== 3) throw new \RuntimeException('Invalid PDF text operands.');
                    $replacement = $rawOperands[0].' Tw '.$rawOperands[1].' Tc '.$replacement.' '.$rawOperands[2].' Tj';
                }
                $shown = $operator !== 'T*';
            } elseif ($inText && ($operator === 'Tj' || $operator === 'TJ')) {
                $shown = true;
                $placement = $layout ? $layout->placement($drawIndex) : null;
                if ($placement !== null) $replacement = self::command(self::textMatrix($placement, $ctm, $page, $scaleX, $scaleY)).' '.$raw;
                $drawIndex++;
            }
            $output .= $replacement === null ? $raw : "\n".$replacement."\n";
            $operands = $rawOperands = [];
            $start = $end;
        }
        if ($inText || $stack || $operands) throw new \RuntimeException('Incomplete PDF content stream.');
        return $output.substr($content, $start);
    }

    private static function textMatrix(Matrix $text, Matrix $ctm, Matrix $page, float $scaleX, float $scaleY): Matrix
    {
        $original = $ctm->multiply($page);
        $device = $original->multiply(new Matrix($scaleX, 0, 0, $scaleY));
        [$a, $b, $c, $d] = $device->getValues();
        $determinant = $a * $d - $b * $c;
        if (abs($determinant) < 1e-12) throw new \RuntimeException('Invalid PDF text transform.');
        $inverse = new Matrix($d / $determinant, -$b / $determinant, -$c / $determinant, $a / $determinant);
        $scale = min($scaleX, $scaleY);
        $correction = $original->multiply(new Matrix($scale, 0, 0, $scale))->multiply($inverse);
        [$a, $b, $c, $d, $e, $f] = $text->getValues();
        [$a, $b, $c, $d] = (new Matrix($a, $b, $c, $d))->multiply($correction)->getValues();
        return new Matrix($a, $b, $c, $d, $e, $f);
    }

    private static function numbers(array $operands, int $count): array
    {
        if (count($operands) !== $count) throw new \RuntimeException('Invalid PDF transform operands.');
        return array_map(function ($operand) {
            if (!$operand instanceof PdfNumeric || !is_finite((float) $operand->value)) throw new \RuntimeException('Invalid PDF numeric operand.');
            return (float) $operand->value;
        }, $operands);
    }

    private static function command(Matrix $matrix): string
    {
        return implode(' ', array_map([self::class, 'number'], $matrix->getValues())).' Tm';
    }

    private static function number(float $number): string
    {
        return rtrim(rtrim(sprintf('%.8F', $number), '0'), '.') ?: '0';
    }
}
