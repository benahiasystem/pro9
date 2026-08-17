<?php

namespace App\Support\Venezuela;

use Illuminate\Support\Str;

final class Localization
{
    // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
    public const COUNTRY_ID = 'VE';
    public const DIAL_CODE = '+58';
    public const NATIONAL_CURRENCY_ID = 'VES';
    public const NATIONAL_CURRENCY_SYMBOL = 'Bs.';
    public const NATIONAL_CURRENCY_DESCRIPTION = 'Bolívares';
    public const SECONDARY_CURRENCY_ID = 'USD';

    public static function countryId(): string
    {
        return (string) config('venezuela.country_id', self::COUNTRY_ID);
    }

    public static function dialCode(): string
    {
        return (string) config('venezuela.dial_code', self::DIAL_CODE);
    }

    public static function nationalCurrencyId(): string
    {
        return (string) config('venezuela.currency.id', self::NATIONAL_CURRENCY_ID);
    }

    public static function secondaryCurrencyId(): string
    {
        return (string) config('venezuela.currency.secondary_id', self::SECONDARY_CURRENCY_ID);
    }

    public static function currencySymbol(?string $currencyId): string
    {
        if ($currencyId === self::SECONDARY_CURRENCY_ID) {
            return '$';
        }

        return self::NATIONAL_CURRENCY_SYMBOL;
    }

    public static function isNationalCurrency(?string $currencyId): bool
    {
        return $currencyId === self::nationalCurrencyId();
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

        $digits = ltrim($digits, '0');

        return self::dialCode() . $digits;
    }

    public static function locationId(string $level, int $legacyId): string
    {
        $lengths = [
            'department' => 2,
            'province' => 4,
            'district' => 6,
        ];

        if (!isset($lengths[$level])) {
            throw new \InvalidArgumentException("Nivel geopolítico no soportado: {$level}");
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
    // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
}
