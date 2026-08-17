<?php

namespace App\Support\Venezuela;

final class Localization
{
    // ########### INICIO CAMBIO CLIENTES VENEZUELA
    public const COUNTRY_ID = 'VE';

    public static function countryId(): string
    {
        return (string) config('venezuela.country_id', self::COUNTRY_ID);
    }
    // ########### FIN CAMBIO CLIENTES VENEZUELA
}
