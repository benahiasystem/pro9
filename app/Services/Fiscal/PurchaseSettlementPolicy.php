<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Catalogs\DocumentType;

/** A retired settlement must never be registered as another fiscal document. */
final class PurchaseSettlementPolicy
{
    public static function assertAvailable($type): void
    {
        if ((string) $type !== '04' || !DocumentType::query()->whereKey('04')->where('active', true)->exists()) {
            FiscalAmounts::error('document_type_id', 'La liquidación de compra no está disponible en el catálogo fiscal.');
        }
    }
}
