<?php

namespace App\Support\Venezuela;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class PersonLocation
{
    public static function resolve(ConnectionInterface $db, $countryId, $districtId): array
    {
        if ($districtId === null || $districtId === '') {
            return ['department_id' => null, 'province_id' => null, 'district_id' => null];
        }

        if ($countryId !== Localization::COUNTRY_ID || !is_string($districtId)
            || !preg_match('/^[0-9]{6}$/D', $districtId)) {
            throw ValidationException::withMessages(['district_id' => 'Indique un código de parroquia de seis dígitos para Venezuela.']);
        }

        // Los códigos territoriales no codifican a sus padres mediante prefijos.
        $locations = $db->table('districts as d')
            ->join('provinces as p', 'p.id', '=', 'd.province_id')
            ->join('departments as e', 'e.id', '=', 'p.department_id')
            ->where('d.id', $districtId)
            ->where('d.active', true)->where('p.active', true)->where('e.active', true)
            ->select('d.id as district_id', 'p.id as province_id', 'e.id as department_id')
            ->limit(2)->get();

        if ($locations->count() !== 1) {
            throw ValidationException::withMessages(['district_id' => 'La parroquia no existe, es ambigua o su jerarquía no está activa.']);
        }

        $location = $locations->first();
        return ['department_id' => (string) $location->department_id,
            'province_id' => (string) $location->province_id, 'district_id' => (string) $location->district_id];
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
