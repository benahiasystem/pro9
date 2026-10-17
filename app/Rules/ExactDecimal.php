<?php

namespace App\Rules;

use App\Support\Exact\Decimal;
use Illuminate\Contracts\Validation\Rule;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Validate a decimal input without PHP's binary numeric comparisons. */
final class ExactDecimal implements Rule
{
    public function __construct(private ?string $minimum = null, private bool $exclusive = false,
        private int $integerDigits = 18, private int $scale = 12) {}

    public function passes($attribute, $value): bool
    {
        try {
            $number=Decimal::of(Decimal::input($value,$attribute,$this->scale,$this->integerDigits));
            if ($this->minimum===null) return true;
            return $this->exclusive ? $number->isGreaterThan($this->minimum) : $number->isGreaterThanOrEqualTo($this->minimum);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function message(): string
    {
        $minimum=$this->minimum===null ? '' : ' y '.($this->exclusive ? 'mayor que ' : 'mayor o igual a ').$this->minimum;
        return "El campo :attribute debe ser un decimal válido de hasta {$this->integerDigits} enteros y {$this->scale} decimales$minimum, sin redondeo.";
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
