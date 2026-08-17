<?php

namespace App\Models\Tenant\Catalogs;

// ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
use App\Support\Venezuela\Localization;
use InvalidArgumentException;
// ######## FIN CAMBIO GEOPOLITICO VENEZUELA
use Hyn\Tenancy\Traits\UsesTenantConnection;

class Department extends ModelCatalog
{
    use UsesTenantConnection;

//    protected $with = ['provinces'];
    public $incrementing = false;
    public $timestamps = false;

    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    public static function idByDescription($description): string
    {
        $normalized = Localization::normalizeLocationName($description);
        $matches = self::query()
            ->where('active', true)
            ->get()
            ->filter(static fn (Department $department): bool =>
                Localization::normalizeLocationName($department->description) === $normalized
            );

        if ($normalized === '' || $matches->count() !== 1) {
            throw new InvalidArgumentException('El Estado indicado no existe o es ambiguo.');
        }

        return (string) $matches->first()->id;
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

    public function provinces()
    {
        return $this->hasMany(Province::class);
    }
}
