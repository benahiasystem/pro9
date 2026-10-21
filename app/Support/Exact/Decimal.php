<?php

namespace App\Support\Exact;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Fractions are authoritative. Decimal strings are boundary representations only. */
final class Decimal
{
    public const SCALE = 12;
    public const VERSION = 1;
    private static ?\WeakMap $readCache = null;

    public static function of($value): BigRational
    {
        if ($value instanceof BigRational) return $value;
        if ($value instanceof \Brick\Math\BigNumber) return $value->toBigRational()->simplified();
        if ($value instanceof \stdClass) $value = (array) $value;
        if (is_array($value) && isset($value['n'], $value['d'])) {
            foreach (['n','d'] as $part) {
                if (!is_string($value[$part]) || !preg_match('/^-?[0-9]+$/D', $value[$part])) {
                    throw ValidationException::withMessages(['amount' => 'Fracción decimal inválida.']);
                }
            }
            if (!preg_match('/^[0-9]*[1-9][0-9]*$/D', $value['d'])) throw ValidationException::withMessages(['amount' => 'Denominador inválido.']);
            return BigRational::nd($value['n'], $value['d'])->simplified();
        }
        if (!is_scalar($value) || is_bool($value) || $value === '') {
            throw ValidationException::withMessages(['amount' => 'Indique un valor decimal válido.']);
        }
        return BigRational::of((string) $value)->simplified();
    }

    public static function input($value, string $field = 'amount', int $scale = self::SCALE, int $integerDigits = 18): string
    {
        try {
            if (!is_scalar($value) || is_bool($value) || $value==='') throw new \InvalidArgumentException();
            $decimal = BigDecimal::of((string) $value)->toScale($scale, RoundingMode::UNNECESSARY);
            if ($integerDigits < 1 || $integerDigits + $scale > 65) throw new \InvalidArgumentException();
            $limit=str_repeat('9',$integerDigits).($scale ? '.'.str_repeat('9',$scale) : '');
            if ($decimal->abs()->isGreaterThan($limit)) throw new \InvalidArgumentException();
            return (string) $decimal;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([$field => "Indique un decimal válido con hasta $scale posiciones, sin redondeo."]);
        }
    }

    public static function fraction($value): array
    {
        $value = self::of($value)->simplified();
        return ['n' => (string) $value->getNumerator(), 'd' => (string) $value->getDenominator()];
    }

    public static function inputRational($value, string $field = 'amount'): BigRational
    {
        return $value instanceof BigRational || is_array($value) || $value instanceof \stdClass
            ? self::of($value) : self::of(self::input($value,$field));
    }

    public static function represent($value, int $scale = self::SCALE, bool $fixed = false): string
    {
        if ($scale < 0 || $scale > self::SCALE) throw new \InvalidArgumentException('Precisión fuera de rango.');
        $text = (string) self::of($value)->toScale($scale, RoundingMode::HALF_UP);
        return $fixed || $scale === 0 ? $text : (rtrim(rtrim($text, '0'), '.') ?: '0');
    }

    /** Locale punctuation at the presentation boundary, without a float conversion. */
    public static function format($value, int $scale = 2, string $decimal = '.', string $thousands = ','): string
    {
        $parts=explode('.',self::represent($value,$scale,true),2);
        $integer=$thousands === '' ? $parts[0] : preg_replace('/\B(?=(\d{3})+(?!\d))/',$thousands,$parts[0]);
        return $scale ? $integer.$decimal.$parts[1] : $integer;
    }

    public static function sum(iterable $values): BigRational
    {
        $sum = self::of(0);
        foreach ($values as $value) {
            $value=self::of($value);
            $sum=$sum->getDenominator()->isEqualTo($value->getDenominator())
                ? BigRational::nd($sum->getNumerator()->plus($value->getNumerator()),$sum->getDenominator())
                : $sum->plus($value)->simplified();
        }
        return $sum->simplified();
    }

    /** Canonical stored decimal, without reparsing a fraction for each model read. */
    public static function canonical($value): string
    {
        $text=(string)$value;
        if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,12}))?$/D',$text,$parts)) return self::represent($value);
        $integer=ltrim($parts[2],'0') ?: '0';
        $fraction=rtrim($parts[3] ?? '','0');
        return ($parts[1] && ($integer!=='0' || $fraction!=='') ? '-' : '').$integer.($fraction!=='' ? '.'.$fraction : '');
    }

    public static function min($a, $b): BigRational
    {
        $a = self::of($a); $b = self::of($b);
        return $a->isLessThan($b) ? $a : $b;
    }

    public static function max($a, $b): BigRational
    {
        $a = self::of($a); $b = self::of($b);
        return $a->isGreaterThan($b) ? $a : $b;
    }

    public static function snapshot(array $values, array $inputs = []): array
    {
        return ['version' => self::VERSION, 'inputs' => $inputs,
            'values' => array_map([self::class, 'fraction'], $values)];
    }

    public static function read($record, string $field): BigRational
    {
        $snapshot = is_array($record) ? ($record['calculation_snapshot'] ?? null)
            : (method_exists($record,'getAttributes') ? ($record->getAttributes()['calculation_snapshot'] ?? null) : ($record->calculation_snapshot ?? null));
        if (is_object($record)) {
            self::$readCache ??= new \WeakMap();
            $entry=self::$readCache[$record] ?? null;
            if ($entry && !is_object($snapshot) && $entry['source']===$snapshot) {
                if (isset($entry['values'][$field])) return $entry['values'][$field];
                $snapshot=$entry['decoded'];
            } else $entry=null;
            $source=$snapshot;
        }
        if (is_string($snapshot)) $snapshot = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        if (is_object($snapshot)) $snapshot = json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        if (is_object($record)) {
            $entry ??= ['source'=>$source,'decoded'=>$snapshot,'values'=>[]];
            if (isset($snapshot['values'][$field])) $entry['values'][$field]=self::of($snapshot['values'][$field]);
            self::$readCache[$record]=$entry;
            if (isset($entry['values'][$field])) return $entry['values'][$field];
        }
        $value = is_array($record) ? ($record[$field] ?? 0) : ($record->$field ?? 0);
        return self::of($snapshot['values'][$field] ?? $value);
    }

    /** A partial SQL projection must never invent a zero-valued business operand. */
    public static function readRequired($record, string $field): BigRational
    {
        $attributes = is_array($record) ? $record
            : (method_exists($record, 'getAttributes') ? $record->getAttributes() : get_object_vars($record));
        $snapshot = $attributes['calculation_snapshot'] ?? null;
        $cached = is_object($record) ? (self::$readCache[$record] ?? null) : null;
        if ($cached && !is_object($snapshot) && $cached['source'] === $snapshot) $snapshot = $cached['decoded'];
        else {
            if (is_string($snapshot)) $snapshot = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (is_object($snapshot)) $snapshot = json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }
        if ($snapshot !== null && (!is_array($snapshot) || ($snapshot['version'] ?? null) !== self::VERSION
            || !is_array($snapshot['values'] ?? null))) {
            throw new \UnexpectedValueException('Snapshot de cálculo inválido.');
        }
        if (!array_key_exists($field, $attributes) && !isset($snapshot['values'][$field])) {
            throw new \UnexpectedValueException("Falta el operando exacto $field en la consulta.");
        }
        if (!isset($snapshot['values'][$field]) && ($attributes[$field] ?? null) === null) {
            throw new \UnexpectedValueException("El operando $field no puede ser nulo.");
        }
        return self::read($record, $field);
    }

    public static function values(array $values): array
    {
        return array_map([self::class, 'represent'], $values);
    }

    public static function convert($amount, string $from, string $to, $rate): BigRational
    {
        if (!in_array($from, ['VES', 'USD'], true) || !in_array($to, ['VES', 'USD'], true)) {
            throw ValidationException::withMessages(['currency_type_id' => 'Sólo se admiten VES y USD.']);
        }
        $amount = self::of($amount);
        if ($from === $to) return $amount;
        $rate = self::of(\App\Services\ExchangeRates\ExchangeRateMath::rate($rate));
        return ($from === 'USD' ? $amount->multipliedBy($rate) : $amount->dividedBy($rate))->simplified();
    }

    /** Largest remainder, deterministic by original position. No binary arithmetic. */
    public static function allocate($total, array $weights, int $scale = 2): array
    {
        if (!$weights) return [];
        $total = self::of($total);
        if ($total->isNegative()) return array_map(fn ($v) => self::represent(self::of($v)->negated(), $scale, true), self::allocate($total->negated(), $weights, $scale));
        $weights = array_map([self::class, 'of'], array_values($weights));
        foreach ($weights as $weight) if ($weight->isNegative()) throw new \InvalidArgumentException('Peso negativo.');
        $sum = self::sum($weights);
        if ($sum->isZero()) {
            if (!$total->isZero()) throw new \InvalidArgumentException('El reparto necesita una base positiva.');
            return array_fill(0, count($weights), self::represent(0, $scale, true));
        }
        $unit = BigInteger::of(10)->power($scale);
        $target = $total->multipliedBy($unit)->toScale(0, RoundingMode::HALF_UP)->toBigInteger();
        $floors = $remainders = [];
        foreach ($weights as $i => $weight) {
            $share = $weight->multipliedBy($target)->dividedBy($sum);
            $floors[$i] = $share->toScale(0, RoundingMode::DOWN)->toBigInteger();
            $remainders[$i] = $share->minus($floors[$i]);
        }
        $left = $target->minus(self::sum($floors)->toBigInteger())->toInt();
        $order = array_keys($weights);
        usort($order, fn ($a, $b) => $remainders[$b]->compareTo($remainders[$a]) ?: $a <=> $b);
        for ($i = 0; $i < $left; $i++) $floors[$order[$i]] = $floors[$order[$i]]->plus(1);
        return array_map(fn ($v) => self::represent(self::of($v)->dividedBy($unit), $scale, true), $floors);
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
