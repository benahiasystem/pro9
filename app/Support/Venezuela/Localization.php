<?php

namespace App\Support\Venezuela;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class Localization
{
    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    public const COUNTRY_ID = 'VE';

    public static function countryId(): string
    {
        return (string) config('venezuela.country_id', self::COUNTRY_ID);
    }

    public static function locationId(string $level, int $legacyId): string
    {
        $lengths = [
            'department' => 2,
            'province' => 4,
            'district' => 6,
        ];

        if (!isset($lengths[$level])) {
            throw new InvalidArgumentException("Nivel geopolitico no soportado: {$level}");
        }

        return str_pad((string) $legacyId, $lengths[$level], '0', STR_PAD_LEFT);
    }

    public static function normalizeLocationName(?string $name): string
    {
        return (string) Str::of((string) $name)
            ->ascii()
            ->lower()
            ->squish();
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA
}
