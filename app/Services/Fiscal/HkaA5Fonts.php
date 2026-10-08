<?php

namespace App\Services\Fiscal;

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\Type\{PdfArray, PdfDictionary, PdfHexString, PdfNumeric, PdfStream, PdfString, PdfType};

/** Measurements come from the original PDF fonts, including HKA's CID subsets. */
final class HkaA5Fonts
{
    private array $fonts = [];

    public function __construct(PdfDictionary $resources, PdfParser $parser)
    {
        $fonts = PdfType::resolve(PdfDictionary::get($resources, 'Font'), $parser);
        if (!$fonts instanceof PdfDictionary) return;
        foreach ($fonts->value as $name => $reference) {
            $font = PdfType::resolve($reference, $parser);
            $resolve = fn ($key) => PdfType::resolve(PdfDictionary::get($font, $key), $parser);
            $cid = ($resolve('Subtype')->value ?? null) === 'Type0';
            $widths = [];
            $default = 0;
            if ($cid) {
                $children = $resolve('DescendantFonts');
                $child = PdfType::resolve($children->value[0], $parser);
                $widthArray = PdfType::resolve(PdfDictionary::get($child, 'W'), $parser);
                $default = PdfType::resolve(PdfDictionary::get($child, 'DW'), $parser)->value ?? 1000;
                if ($widthArray instanceof PdfArray) {
                    $entries = PdfType::flatten($widthArray, $parser)->value;
                    for ($i = 0; $i < count($entries);) {
                        $first = $entries[$i++]->value;
                        $next = $entries[$i++];
                        if ($next instanceof PdfArray) {
                            foreach ($next->value as $offset => $width) $widths[$first + $offset] = $width->value;
                        } else {
                            $width = $entries[$i++]->value;
                            for ($code = $first; $code <= $next->value; $code++) $widths[$code] = $width;
                        }
                    }
                }
                $descriptor = PdfType::resolve(PdfDictionary::get($child, 'FontDescriptor'), $parser);
            } else {
                $values = $resolve('Widths');
                $first = $resolve('FirstChar')->value ?? 0;
                if ($values instanceof PdfArray) {
                    foreach ($values->value as $offset => $width) $widths[$first + $offset] = PdfType::resolve($width, $parser)->value;
                } else {
                    $base = preg_replace('/^[A-Z]{6}\+/', '', $resolve('BaseFont')->value ?? '');
                    $core = ['Helvetica' => 'helvetica', 'Helvetica-Bold' => 'helveticab', 'Helvetica-Oblique' => 'helveticai',
                        'Helvetica-BoldOblique' => 'helveticabi', 'Times-Roman' => 'times', 'Times-Bold' => 'timesb',
                        'Times-Italic' => 'timesi', 'Times-BoldItalic' => 'timesbi', 'Courier' => 'courier',
                        'Courier-Bold' => 'courierb', 'Courier-Oblique' => 'courieri', 'Courier-BoldOblique' => 'courierbi',
                        'Symbol' => 'symbol', 'ZapfDingbats' => 'zapfdingbats'];
                    if (isset($core[$base])) {
                        $metrics = (static function ($path) { require $path; return $cw; })(base_path('vendor/setasign/fpdf/font/'.$core[$base].'.php'));
                        foreach ($metrics as $character => $width) $widths[ord($character)] = $width;
                    }
                }
                $descriptor = $resolve('FontDescriptor');
            }
            $cap = $descriptor instanceof PdfDictionary ? (PdfType::resolve(PdfDictionary::get($descriptor, 'CapHeight'), $parser)->value ?? 700) : 700;
            $descent = $descriptor instanceof PdfDictionary ? (PdfType::resolve(PdfDictionary::get($descriptor, 'Descent'), $parser)->value ?? -220) : -220;
            $unicode = $resolve('ToUnicode');
            $map = $unicode instanceof PdfStream ? self::unicodeMap($unicode->getUnfilteredStream()) : [];
            $this->fonts[$name] = ['cid' => $cid, 'widths' => $widths, 'default' => $default, 'unicode' => $map,
                'cap' => ($cap ?: 700) / 1000 + .15, 'descent' => abs($descent ?: -220) / 1000];
        }
    }

    public function measure(string $font, $operand, float $size, float $charSpace, float $wordSpace, float $horizontal): array
    {
        if (!isset($this->fonts[$font])) throw new \RuntimeException('Missing original PDF font metrics.');
        $metrics = $this->fonts[$font];
        $width = 0;
        $text = '';
        $entries = $operand instanceof PdfArray ? $operand->value : [$operand];
        foreach ($entries as $entry) {
            if ($entry instanceof PdfNumeric) { $width -= $entry->value / 1000 * $size; continue; }
            if ($entry instanceof PdfHexString) {
                $hex = preg_replace('/\s/', '', $entry->value);
                $bytes = hex2bin(strlen($hex) % 2 ? $hex.'0' : $hex);
            } else {
                $bytes = $entry instanceof PdfString ? PdfString::unescape($entry->value) : null;
            }
            if (!is_string($bytes)) throw new \RuntimeException('Invalid PDF text string.');
            $codes = $metrics['cid'] ? array_values(unpack('n*', $bytes)) : array_values(unpack('C*', $bytes));
            foreach ($codes as $code) {
                $width += ($metrics['widths'][$code] ?? $metrics['default']) / 1000 * $size + $charSpace;
                if (!$metrics['cid'] && $code === 32) $width += $wordSpace;
                $text .= $metrics['unicode'][$code] ?? ($metrics['cid'] ? '' : mb_convert_encoding(chr($code), 'UTF-8', 'Windows-1252'));
            }
        }
        return ['width' => $width * $horizontal / 100, 'text' => $text, 'cap' => $metrics['cap'] * $size, 'descent' => $metrics['descent'] * $size];
    }

    private static function unicodeMap(string $cmap): array
    {
        $map = [];
        preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $sections);
        foreach ($sections[1] as $section) {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $section, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair) $map[hexdec($pair[1])] = mb_convert_encoding(hex2bin($pair[2]), 'UTF-8', 'UTF-16BE');
        }
        preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $sections);
        foreach ($sections[1] as $section) {
            preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(\[[^]]*\]|<[0-9A-Fa-f]+>)/', $section, $ranges, PREG_SET_ORDER);
            foreach ($ranges as $range) {
                preg_match_all('/<([0-9A-Fa-f]+)>/', $range[3], $destinations);
                for ($code = hexdec($range[1]); $code <= hexdec($range[2]); $code++) {
                    $offset = $code - hexdec($range[1]);
                    $hex = $range[3][0] === '[' ? ($destinations[1][$offset] ?? 'FFFD')
                        : str_pad(dechex(hexdec($destinations[1][0]) + $offset), strlen($destinations[1][0]), '0', STR_PAD_LEFT);
                    $map[$code] = mb_convert_encoding(hex2bin($hex), 'UTF-8', 'UTF-16BE');
                }
            }
        }
        return $map;
    }
}
