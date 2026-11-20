<?php

namespace App\Database;

// ########## INICIO CAMBIO POSTGRESQL 17
/** JSON numbers outside JavaScript's exact integer domain must travel as decimal text. */
final class IntegerTransport
{
    public static function value($value)
    {
        if (is_int($value) && ($value > 9007199254740991 || $value < -9007199254740991)) return (string) $value;
        return $value;
    }

    /** Validate a technical bigint key before casting; reject aliases and overflow. */
    public static function positiveKey($value): ?int
    {
        if (is_int($value)) return $value > 0 ? $value : null;
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,18}$/D',$value)) return null;
        // strcmp is deliberate: numeric-string comparison can coerce an overflow to float.
        if (strlen($value)===19 && strcmp($value,'9223372036854775807')>0) return null;
        return (int) $value;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
