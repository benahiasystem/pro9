<?php

namespace App\Support\Venezuela;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class Localization
{
    // ########### INICIO CAMBIO LOCALIZACIÓN VENEZUELA
    public const COUNTRY_ID = 'VE';
    public const DIAL_CODE = '+58';

    public static function countryId(): string
    {
        return (string) config('venezuela.country_id', self::COUNTRY_ID);
    }

    public static function dialCode(): string
    {
        return (string) config('venezuela.dial_code', self::DIAL_CODE);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '51')) {
            $digits = substr($digits, 2);
        }

        return self::dialCode() . ltrim($digits, '0');
    }

    public static function whatsappNumber(?string $phone): ?string
    {
        $normalized = self::normalizePhone($phone);

        return $normalized === null ? null : ltrim($normalized, '+');
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
    // ########### FIN CAMBIO LOCALIZACIÓN VENEZUELA
}
