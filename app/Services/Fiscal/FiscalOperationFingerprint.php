<?php

namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalOperationFingerprint
{
    public static function forWebDocument(array $input, int $actorId): string
    {
        // Exclude only server identifiers and transport/presentation data.
        foreach (['_token', 'operation_key', 'series', 'series_id', 'number', 'external_id', 'actions', 'fiscal_profile_id', 'fiscal_channel', 'fiscal_group_id', 'fiscal_fingerprint', 'fiscal_environment', 'fiscal_emission_mode'] as $key) {
            unset($input[$key]);
        }
        return hash('sha256', json_encode(self::canonical(['actor_id' => $actorId, 'document' => $input]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function canonical(array $value): array
    {
        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = self::canonical($child);
            }
        }
        return $value;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
