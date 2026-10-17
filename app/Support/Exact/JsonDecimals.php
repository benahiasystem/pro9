<?php

namespace App\Support\Exact;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** Preserve JSON decimal lexemes; integer identifiers and booleans keep their types. */
final class JsonDecimals
{
    public static function decode(string $json): array
    {
        // Validate the original, including UTF-8, without trusting its numeric conversion.
        json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        $quoted = preg_replace_callback(
            '~"(?:[^"\\\\\x00-\x1f]|\\\\.)*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?~s',
            static function (array $match): string {
                $token = $match[0];
                return $token[0] !== '"' && strpbrk($token, '.eE') !== false
                    ? json_encode($token, JSON_THROW_ON_ERROR) : $token;
            },
            $json
        );
        $decoded = json_decode($quoted, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($decoded)) throw new \JsonException('La entrada JSON debe ser un objeto o una lista.');
        return $decoded;
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
