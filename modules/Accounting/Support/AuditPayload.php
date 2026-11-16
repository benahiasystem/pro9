<?php

namespace Modules\Accounting\Support;

final class AuditPayload
{
    /** Credentials and internal file paths never belong in audit presentation. */
    public static function redact(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) { return self::redact($value->jsonSerialize()); }
        if ($value instanceof \stdClass) {
            $out = new \stdClass();
            foreach (get_object_vars($value) as $key => $item) { $out->{$key} = self::privateKey((string) $key) ? '[Dato privado omitido]' : self::redact($item); }
            return $out;
        }
        if (!is_array($value)) { return $value; }
        $out = [];
        foreach ($value as $key => $item) { $out[$key] = self::privateKey((string) $key) ? '[Dato privado omitido]' : self::redact($item); }
        return $out;
    }

    private static function privateKey(string $key): bool
    {
        $key = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key);
        return (bool) preg_match('/(?:^|_)(?:password|passwd|secret|token|jwt|credential|credentials|authorization|cookie|cookies|headers|path|private)(?:_|$)|(?:^|_)(?:api|access|encryption|signing)_key$|^(?:raw_response|raw_request|connection_context)$/i', $key);
    }

    public static function text(string $json): string
    {
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        return json_encode(self::redact($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
    }
}
