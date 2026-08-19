<?php

namespace App\Support\System;

final class Rif
{
    // ########## INICIO CAMBIO RIF SUPER ADMIN
    public const PATTERN = '/^[VEJPG][0-9]{9}$/';

    public static function normalize(?string $value): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/', '', trim((string) $value)));
    }

    public static function isValid(?string $value): bool
    {
        return preg_match(self::PATTERN, self::normalize($value)) === 1;
    }
    // ######### FIN CAMBIO RIF SUPER ADMIN
}
