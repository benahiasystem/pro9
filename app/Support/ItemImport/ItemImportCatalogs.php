<?php

// ########### INICIO CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
namespace App\Support\ItemImport;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\UnitType;
use App\Models\Tenant\Item;

class ItemImportCatalogs
{
    public function snapshot(): array
    {
        return [
            'unit_type_ids' => $this->activeIds(UnitType::query()),
            'currency_type_ids' => $this->activeIds(CurrencyType::query()),
            'affectation_igv_type_ids' => $this->activeIds(AffectationIgvType::query()),
            'existing_internal_ids' => Item::query()
                ->whereNotNull('internal_id')
                ->pluck('internal_id')
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function activeIds($query): array
    {
        return $query
            ->where('active', 1)
            ->pluck('id')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->values()
            ->all();
    }
}
// ########### FIN CAMBIO VALIDACIÓN PREVIA IMPORTACIÓN ITEMS
