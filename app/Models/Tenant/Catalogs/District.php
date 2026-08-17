<?php

namespace App\Models\Tenant\Catalogs;

// ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
use App\Support\Venezuela\Localization;
use InvalidArgumentException;
// ######## FIN CAMBIO GEOPOLITICO VENEZUELA
use Hyn\Tenancy\Traits\UsesTenantConnection;

class District extends ModelCatalog
{
    use UsesTenantConnection;

    public $incrementing = false;
    public $timestamps = false;

    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    public static function idByDescription($description, string $provinceId): string
    {
        $normalized = Localization::normalizeLocationName($description);
        $matches = self::query()
            ->where('active', true)
            ->where('province_id', $provinceId)
            ->get()
            ->filter(static fn (District $district): bool =>
                Localization::normalizeLocationName($district->description) === $normalized
            );

        if ($normalized === '' || $matches->count() !== 1) {
            throw new InvalidArgumentException(
                'La Parroquia indicada no existe, es ambigua o no pertenece al Municipio.'
            );
        }

        return (string) $matches->first()->id;
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

    public function province()
    {
        return $this->belongsTo(Province::class)->with('department');
    }

}
