<?php

namespace App\Support\Venezuela;

/** Drops obsolete input fields, including copies in the stored API payload. */
final class RetiredDetractionFields
{
    private const KEYS = [
        'detraction', 'detraccion', 'subject_to_detraction', 'detraction_account',
        'detraction_amount_rounded_int', 'available_detraction_for_amount_minor',
        'pending_amount_detraction', 'total_pendiente_detraccion',
        'image_detraction', 'upload_image_pay_constancy',
        'incluye_detraccion', 'porcentaje_detraccion', 'servicio_detraccion',
    ];

    public static function discard(array $inputs): array
    {
        foreach ($inputs as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                unset($inputs[$key]);
            } elseif (is_array($value)) {
                $inputs[$key] = self::discard($value);
            } elseif ($value instanceof \stdClass) {
                $inputs[$key] = (object) self::discard((array) $value);
            } elseif ($key === 'data_json' && is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $inputs[$key] = json_encode(self::discard($decoded));
                }
            }
        }

        return $inputs;
    }
}
