<?php

namespace App\Models\Tenant\Catalogs;

// ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
use App\Support\Venezuela\Localization;
use InvalidArgumentException;
// ######## FIN CAMBIO GEOPOLITICO VENEZUELA
use Hyn\Tenancy\Traits\UsesTenantConnection;

class Province extends ModelCatalog
{
    use UsesTenantConnection;

//    protected $with = ['districts'];
    public $incrementing = false;
    public $timestamps = false;

    // ######## INICIO CAMBIO GEOPOLITICO VENEZUELA
    public static function idByDescription($description, string $departmentId): string
    {
        $normalized = Localization::normalizeLocationName($description);
        $matches = self::query()
            ->where('active', true)
            ->where('department_id', $departmentId)
            ->get()
            ->filter(static fn (Province $province): bool =>
                Localization::normalizeLocationName($province->description) === $normalized
            );

        if ($normalized === '' || $matches->count() !== 1) {
            throw new InvalidArgumentException(
                'El Municipio indicado no existe, es ambiguo o no pertenece al Estado.'
            );
        }

        return (string) $matches->first()->id;
    }
    // ######## FIN CAMBIO GEOPOLITICO VENEZUELA

    public function districts()
    {
        return $this->hasMany(District::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

}
