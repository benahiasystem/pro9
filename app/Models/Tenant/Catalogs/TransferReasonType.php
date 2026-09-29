<?php

namespace App\Models\Tenant\Catalogs;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Validation\Rule;

class TransferReasonType extends ModelCatalog
{
    use UsesTenantConnection;

    // ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
    public const OWN_WAREHOUSES = '04';
    public const REPAIR = '21';
    public const THIRD_PARTY_WAREHOUSES = '22';
    public const CUSTOMS_TRANSIT = '23';
    public const OTHER = '24';
    public const CONTRACT_IDS = [
        self::OWN_WAREHOUSES,
        self::REPAIR,
        self::THIRD_PARTY_WAREHOUSES,
        self::CUSTOMS_TRANSIT,
        self::OTHER,
    ];

    public static function activeValidationRule()
    {
        return Rule::exists('tenant.cat_transfer_reason_types', 'id')
            ->where(static function ($query): void {
                $query->where('active', 1)->whereIn('id', self::CONTRACT_IDS);
            });
    }

    public static function isContractId($id): bool
    {
        return in_array((string) $id, self::CONTRACT_IDS, true);
    }

    public function scopeWhereContractActive($query)
    {
        return $query->where('active', true)
            ->whereIn('id', self::CONTRACT_IDS)
            ->orderByRaw("CASE id WHEN '04' THEN 1 WHEN '21' THEN 2 WHEN '22' THEN 3 WHEN '23' THEN 4 WHEN '24' THEN 5 ELSE 6 END");
    }

    public function displayDescription(?string $detail = null): string
    {
        $description = (string) $this->description;
        $detail = trim((string) $detail);

        return $this->id === self::OTHER && $detail !== ''
            ? "{$description}: {$detail}"
            : $description;
    }
    // ######### FIN CAMBIO CATÁLOGOS DE NOMBRES

    protected $table = "cat_transfer_reason_types";
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'active',
        'description',
        'discount_stock',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount_stock' => 'boolean',
    ];
}
