<?php

namespace App\Models\Tenant\Catalogs;

use App\Models\Tenant\Item;
use Hyn\Tenancy\Traits\UsesTenantConnection;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class UnitType extends ModelCatalog
{
    use UsesTenantConnection;

    // ######## INICIO CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
    public const DEFAULT_UNIT_TYPE = 'UND';
    public const SERVICE_UNIT_TYPE = 'SERV';
    public const LEGACY_UNIT_TYPES = ['NIU', 'ZZ'];
    public const RESERVED_UNIT_TYPES = [self::DEFAULT_UNIT_TYPE, self::SERVICE_UNIT_TYPE];

    public static function activeValidationRule()
    {
        return Rule::exists('tenant.cat_unit_types', 'id')
            ->where(static function ($query): void {
                $query->where('active', 1);
            });
    }

    public static function isReserved(string $id): bool
    {
        return in_array(strtoupper(trim($id)), self::RESERVED_UNIT_TYPES, true);
    }

    public static function requireActiveCode($id): string
    {
        $code = strtoupper(trim((string) $id));
        if ($code === '' || in_array($code, self::LEGACY_UNIT_TYPES, true)) {
            throw new InvalidArgumentException('El código de unidad de medida no es válido.');
        }

        $exists = static::query()
            ->where('id', $code)
            ->where('active', 1)
            ->exists();

        if (!$exists) {
            throw new InvalidArgumentException("La unidad de medida {$code} no existe o está inactiva.");
        }

        return $code;
    }
    // ######## FIN CONTRATO UNIDADES DE MEDIDA VENEZUELA ########
    
    protected $table = "cat_unit_types";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'active',
        'symbol',
        'description',
    ];

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::addGlobalScope('active', function (Builder $builder) {
    //         $builder->where('active', 1);
    //     });
    // }
    public function items()
    {
        return $this->hasMany(Item::class, 'unit_type_id');
    }

    public function item_unit_types()
    {
        return $this->hasMany(ItemUnitType::class);
    }
}
