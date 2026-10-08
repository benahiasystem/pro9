<?php

namespace App\Services\Fiscal;

use setasign\Fpdi\Math\{Matrix, Vector};

/** Preserve each original line's left, centre or right anchor after normalising its glyphs. */
final class HkaA5Layout
{
    private Matrix $ctm;
    private Matrix $line;
    private Matrix $cursor;
    private array $state = ['font' => '', 'size' => 0, 'char' => 0, 'word' => 0, 'horizontal' => 100, 'leading' => 0, 'rise' => 0, 'white' => false, 'clip' => null];
    private array $stack = [];
    private array $groups = [];
    private array $lineGroups = [];
    private array $draws = [];
    private array $rectangles = [];
    private array $backgrounds = [];
    private array $replacements = [];
    private array $imageUses = [];
    private array $sourceBounds;
    private ?int $pendingRectangle = null;
    private bool $pendingClip = false;
    private int $block = 0;

    public function __construct(private HkaA5Fonts $fonts, private Matrix $page, private float $scaleX,
        private float $scaleY, private array $bounds, private array $images = [])
    {
        $this->ctm = $this->line = $this->cursor = new Matrix();
        $this->sourceBounds = $bounds;
        $this->bounds = self::box($bounds[0], $bounds[1], $bounds[2] - $bounds[0], $bounds[3] - $bounds[1], $page);
    }

    public function observe(string $operator, array $operands): void
    {
        $values = array_map(fn ($value) => $value->value ?? null, $operands);
        switch ($operator) {
            case 'q': $this->stack[] = [$this->ctm, $this->state]; break;
            case 'Q': [$this->ctm, $this->state] = array_pop($this->stack); break;
            case 'cm': $this->ctm = (new Matrix(...$values))->multiply($this->ctm); break;
            case 'g': $this->state['white'] = $values[0] >= .95; break;
            case 'rg': $this->state['white'] = min($values) >= .95; break;
            case 'k': $this->state['white'] = max($values) <= .05; break;
            case 'Tf': [$this->state['font'], $this->state['size']] = $values; break;
            case 'Tc': $this->state['char'] = $values[0]; break;
            case 'Tw': $this->state['word'] = $values[0]; break;
            case 'Tz': $this->state['horizontal'] = $values[0]; break;
            case 'TL': $this->state['leading'] = $values[0]; break;
            case 'Ts': $this->state['rise'] = $values[0]; break;
            case 'BT': $this->block++; $this->line = $this->cursor = new Matrix(); break;
            case 'Tm': $this->line = $this->cursor = new Matrix(...$values); break;
            case 'TD': $this->state['leading'] = -$values[1];
            case 'Td': $this->move($values[0], $values[1]); break;
            case 'T*': $this->move(0, -$this->state['leading']); break;
            case '"': $this->state['word'] = $values[0]; $this->state['char'] = $values[1];
            case "'": $this->move(0, -$this->state['leading']); $this->draw(end($operands)); break;
            case 'Tj': case 'TJ': $this->draw($operands[0]); break;
            case 'Do': $this->imageUses[$values[0]] = ($this->imageUses[$values[0]] ?? 0) + 1; break;
            case 're':
                $id = count($this->rectangles);
                $transform = $this->ctm->multiply($this->page);
                $this->rectangles[$id] = ['values' => $values, 'transform' => $transform,
                    'box' => self::box($values[0], $values[1], $values[2], $values[3], $transform)];
                $this->pendingRectangle = $id;
                break;
            case 'm': case 'l': case 'c': $this->pendingRectangle = null; break;
            case 'W': case 'W*': $this->pendingClip = true; break;
            case 'f': case 'f*': case 'B': case 'B*':
                if ($this->pendingRectangle !== null) $this->backgrounds[] = $this->rectangles[$this->pendingRectangle]['box'];
                $this->endPath(); break;
            case 'n': case 'S': case 's': $this->endPath(); break;
        }
    }

    private function endPath(): void
    {
        if ($this->pendingClip && $this->pendingRectangle !== null) $this->state['clip'] = $this->pendingRectangle;
        $this->pendingRectangle = null;
        $this->pendingClip = false;
    }

    private function move(float $x, float $y): void
    {
        $this->line = $this->cursor = (new Matrix(1, 0, 0, 1, $x, $y))->multiply($this->line);
    }

    private function draw($operand): void
    {
        $metrics = $this->fonts->measure($this->state['font'], $operand, $this->state['size'],
            $this->state['char'], $this->state['word'], $this->state['horizontal']);
        $transform = $this->ctm->multiply($this->page);
        $text = $this->cursor->multiply($transform);
        [$a, $b, , , $x, $y] = $this->cursor->getValues();
        $baseline = hypot($a, $b) ? ($y * $a - $x * $b) / hypot($a, $b) : $y;
        $lineKey = $this->block.':'.round($baseline, 3);
        $box = self::box(0, -$metrics['descent'] + $this->state['rise'], $metrics['width'],
            $metrics['cap'] + $metrics['descent'], $text);
        $origin = self::point(0, 0, $text);
        $key = $this->lineGroups[$lineKey] ?? $lineKey;
        if (isset($this->groups[$key])) {
            $previous = $this->groups[$key]['end'];
            [$directionX, $directionY] = $text->getValues();
            $length = hypot($directionX, $directionY);
            $gap = $length ? (($origin[0] - $previous[0]) * $directionX + ($origin[1] - $previous[1]) * $directionY) / $length : 0;
            // Separate columns can share a BT; labels with adjacent font changes stay together.
            if ($gap > max(8, $this->state['size'] * .8) * $length || $gap < -1 * $length) $key = $lineKey.':'.count($this->groups);
        }
        $this->lineGroups[$lineKey] = $key;
        if (!isset($this->groups[$key])) {
            $this->groups[$key] = ['box' => $box, 'origin' => $origin, 'local' => $x, 'text' => '',
                'white' => $this->state['white'], 'clip' => $this->state['clip'], 'delta' => 0,
                'horizontal' => abs($text->getValues()[1]) < 1e-7, 'size' => $this->state['size']];
        } else {
            $this->groups[$key]['box'] = self::union($this->groups[$key]['box'], $box);
        }
        $this->groups[$key]['text'] .= $metrics['text'];
        $this->groups[$key]['end'] = self::point($metrics['width'], 0, $text);
        $this->draws[] = ['group' => $key, 'matrix' => $this->cursor, 'transform' => $transform, 'origin' => $origin];
        $this->cursor = (new Matrix(1, 0, 0, 1, $metrics['width'], 0))->multiply($this->cursor);
    }

    public function finish(): void
    {
        $ratioX = min($this->scaleX, $this->scaleY) / $this->scaleX;
        $ratioY = min($this->scaleX, $this->scaleY) / $this->scaleY;
        foreach ($this->groups as &$group) {
            [$left, $bottom, $right, $top] = $group['box'];
            $width = ($right - $left) * $ratioX;
            $center = ($left + $right) / 2;
            $alignment = 'left';
            if ($group['horizontal']) {
                $header = false;
                foreach ($this->backgrounds as $background) {
                    if ($group['white'] && $background[2] - $background[0] > ($this->bounds[2] - $this->bounds[0]) * .7
                        && $group['origin'][1] >= $background[1] && $group['origin'][1] <= $background[3]) $header = true;
                }
                $pageCenter = ($this->bounds[0] + $this->bounds[2]) / 2;
                $numeric = preg_match('/^(?:[A-Z]{1,4}\.?\s*)?[-+]?\d[\d.,]*(?:\s*%)?$/ui', trim($group['text']));
                $pageCentered = $group['local'] > 8 && abs($center - $pageCenter) < max(4, $group['size'] * .6);
                if ($header || $pageCentered || preg_match('/^\([A-Z]\)$/', trim($group['text']))) {
                    $alignment = 'center';
                    if ($pageCentered && !$header) $center = $pageCenter;
                } elseif ($numeric && $left > $this->bounds[0] + ($this->bounds[2] - $this->bounds[0]) * .1) {
                    $alignment = $group['local'] > 8 ? 'right' : 'center';
                }
            }
            $newLeft = $alignment === 'center' ? $center - $width / 2 : ($alignment === 'right' ? $right - $width : $left);
            $boundary = $group['clip'] !== null ? $this->rectangles[$group['clip']]['box'] : $this->bounds;
            $newLeft = max($boundary[0] + .5, min($newLeft, $boundary[2] - .5 - $width));
            $newBottom = $group['origin'][1] + ($bottom - $group['origin'][1]) * $ratioY;
            $newTop = $group['origin'][1] + ($top - $group['origin'][1]) * $ratioY;
            $group['left'] = $newLeft;
            $group['newBox'] = [$newLeft, $newBottom, $newLeft + $width, $newTop];
            $group['alignment'] = $alignment;
        }
        unset($group);
        // Keep every line visible, including provider paragraphs taller than their clipping box.
        foreach ($this->rectangles as $id => $rectangle) {
            $keys = array_keys(array_filter($this->groups, fn ($group) => $group['clip'] === $id));
            if (!$keys) continue;
            $ink = $this->groups[$keys[0]]['newBox'];
            foreach ($keys as $key) $ink = self::union($ink, $this->groups[$key]['newBox']);
            $box = $rectangle['box'];
            if ($ink[1] < $box[1] - .2 || $ink[3] > $box[3] + .2) {
                $delta = max(0, $box[1] + .8 - $ink[1]);
                $box[3] = max($box[3], $ink[3] + $delta + .8);
                foreach ($keys as $key) $this->groups[$key]['delta'] += $delta;
                $inverse = self::inverse($rectangle['transform']);
                $local = self::box($box[0], $box[1], $box[2] - $box[0], $box[3] - $box[1], $inverse);
                $this->replacements[$id] = [$local[0], $local[1], $local[2] - $local[0], $local[3] - $local[1]];
            }
        }
        foreach ($this->groups as &$group) {
            $bottom = $group['newBox'][1] + $group['delta'];
            $top = $group['newBox'][3] + $group['delta'];
            if ($bottom < $this->bounds[1] + .5) $group['delta'] += $this->bounds[1] + .5 - $bottom;
            if ($top > $this->bounds[3] - .5) $group['delta'] -= $top - $this->bounds[3] + .5;
        }
        unset($group);
    }

    public function placement(int $index): ?Matrix
    {
        if (!isset($this->draws[$index])) return null;
        $draw = $this->draws[$index];
        $group = $this->groups[$draw['group']];
        $ratioX = min($this->scaleX, $this->scaleY) / $this->scaleX;
        $ratioY = min($this->scaleX, $this->scaleY) / $this->scaleY;
        $x = $group['left'] + ($draw['origin'][0] - $group['box'][0]) * $ratioX;
        $y = $group['origin'][1] + ($draw['origin'][1] - $group['origin'][1]) * $ratioY + $group['delta'];
        [$x, $y] = self::point($x, $y, self::inverse($draw['transform']));
        [$a, $b, $c, $d] = $draw['matrix']->getValues();
        return new Matrix($a, $b, $c, $d, $x, $y);
    }

    public function rectangle(int $index): ?array
    {
        return $this->replacements[$index] ?? null;
    }

    /** HKA's header logo keeps its aspect ratio; its fiscal QR keeps its original physical size. */
    public function imagePlacement(string $name, Matrix $transform): ?Matrix
    {
        if (!isset($this->images[$name]) || ($this->imageUses[$name] ?? 0) !== 1) return null;
        [$pixelsX, $pixelsY] = $this->images[$name];
        [$a, $b, $c, $d] = $transform->getValues();
        $width = hypot($a, $b); $height = hypot($c, $d);
        $box = self::box(0, 0, 1, 1, $transform);
        $pageWidth = $this->bounds[2] - $this->bounds[0];
        $center = self::point(.5, .5, $transform);
        $source = $transform->multiply(self::inverse($this->page));
        $sourceBox = self::box(0, 0, 1, 1, $source);
        $sourceCenter = self::point(.5, .5, $source);
        $sourceWidth = $this->sourceBounds[2] - $this->sourceBounds[0];
        $sourceHeight = $this->sourceBounds[3] - $this->sourceBounds[1];
        // Tiled DEMO watermarks are deliberately excluded by the per-resource use count.
        $qr = $pixelsX === $pixelsY && $width > 14 && $width < 86 && abs($width - $height) < .01 * $width
            && $sourceCenter[0] > $this->sourceBounds[0] + $sourceWidth * .6 && $sourceBox[1] > $this->sourceBounds[1] + $sourceHeight * .6;
        $logo = $sourceCenter[0] < $this->sourceBounds[0] + $sourceWidth * .35 && $sourceBox[1] > $this->sourceBounds[1] + $sourceHeight * .75;
        if (!$qr && !$logo) return null;
        $scale = $qr ? 1 : min($this->scaleX, $this->scaleY);
        $halfWidth = (abs($a) + abs($c)) * $scale / 2;
        $halfHeight = (abs($b) + abs($d)) * $scale / 2;
        [$x, $y] = [$center[0] * $this->scaleX, $center[1] * $this->scaleY];
        if ($qr) {
            $bodyTop = $this->bounds[1];
            foreach ($this->backgrounds as $background) {
                if ($background[2] - $background[0] > $pageWidth * .7 && $background[3] < $box[1]) {
                    $bodyTop = max($bodyTop, $background[3]);
                }
            }
            // Preserve a small gap above the item table after restoring the QR's height.
            $y = max($y, $bodyTop * $this->scaleY + $halfHeight + 1.5);
        }
        $x = max($this->bounds[0] * $this->scaleX + $halfWidth + .5,
            min($x, $this->bounds[2] * $this->scaleX - $halfWidth - .5));
        $y = max($this->bounds[1] * $this->scaleY + $halfHeight + .5,
            min($y, $this->bounds[3] * $this->scaleY - $halfHeight - .5));
        $desired = new Matrix($a * $scale, $b * $scale, $c * $scale, $d * $scale,
            $x - ($a + $c) * $scale / 2, $y - ($b + $d) * $scale / 2);
        return $desired->multiply(new Matrix(1 / $this->scaleX, 0, 0, 1 / $this->scaleY))
            ->multiply(self::inverse($transform));
    }

    private static function union(array $a, array $b): array
    {
        return [min($a[0], $b[0]), min($a[1], $b[1]), max($a[2], $b[2]), max($a[3], $b[3])];
    }

    private static function point(float $x, float $y, Matrix $matrix): array
    {
        $point = (new Vector($x, $y))->multiplyWithMatrix($matrix);
        return [$point->getX(), $point->getY()];
    }

    private static function box(float $x, float $y, float $width, float $height, Matrix $matrix): array
    {
        $points = [self::point($x, $y, $matrix), self::point($x + $width, $y, $matrix),
            self::point($x, $y + $height, $matrix), self::point($x + $width, $y + $height, $matrix)];
        return [min(array_column($points, 0)), min(array_column($points, 1)), max(array_column($points, 0)), max(array_column($points, 1))];
    }

    private static function inverse(Matrix $matrix): Matrix
    {
        [$a, $b, $c, $d, $e, $f] = $matrix->getValues();
        $determinant = $a * $d - $b * $c;
        if (abs($determinant) < 1e-12) throw new \RuntimeException('Invalid PDF layout transform.');
        return new Matrix($d / $determinant, -$b / $determinant, -$c / $determinant, $a / $determinant,
            ($c * $f - $d * $e) / $determinant, ($b * $e - $a * $f) / $determinant);
    }
}
