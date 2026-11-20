<?php

namespace App\Rules;

use App\Database\IntegerTransport;
use Illuminate\Contracts\Validation\Rule;

final class PositiveBigint implements Rule
{
    public function passes($attribute, $value): bool
    {
        return IntegerTransport::positiveKey($value) !== null;
    }

    public function message(): string
    {
        return 'El campo :attribute debe contener un identificador entero positivo válido.';
    }
}
