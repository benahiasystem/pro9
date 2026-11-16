<?php

namespace Modules\Accounting\Support;

use Brick\Math\BigDecimal;

/** Versioned encoding, stable across MySQL JSON key order/number formatting. */
final class AuditHash
{
    private static function node(mixed $value): array
    {
        if ($value === null) { return ['null']; }
        if (is_bool($value)) { return ['boolean', $value]; }
        if (is_int($value) || is_float($value)) {
            $number = is_int($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
            return ['number', (string) BigDecimal::of($number)->stripTrailingZeros()];
        }
        if (is_string($value)) { return ['string', $value]; }
        if (is_array($value)) { return ['array', array_map(self::node(...), $value)]; }
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $pairs = [];
            foreach ($properties as $name => $property) { $pairs[] = [(string) $name, self::node($property)]; }
            return ['object', $pairs];
        }
        throw new \InvalidArgumentException('La huella de auditoría requiere valores JSON explícitos.');
    }

    public static function digest(array $event): string
    {
        if ((int) ($event['hash_version'] ?? 0) !== 2) {
            throw new \InvalidArgumentException('Versión de huella no verificable con este formato.');
        }
        $data = (object) [];
        foreach (['id', 'organization_id', 'company_id', 'action', 'resource_type', 'resource_id', 'previous_hash', 'created_at', 'updated_at', 'actor_name'] as $field) {
            $data->{$field} = $event[$field] ?? null;
        }
        $data->hash_version = 2;
        $data->sequence = (int) $event['sequence'];
        $data->user_id = $event['user_id'] === null ? null : (int) $event['user_id'];
        $data->payload = json_decode($event['payload'], false, 512, JSON_THROW_ON_ERROR);
        return self::contentDigest($data);
    }

    /** The same typed encoding can protect other immutable JSON evidence. */
    public static function contentDigest(mixed $value): string
    {
        return hash('sha256', json_encode(self::node($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
