<?php

namespace App\Services\Fiscal;

// ######## INICIO NUMERACIÓN FISCAL VENEZUELA ########
final class FiscalInventoryPolicy
{
    public static function affectsStock(string $documentType, ?string $creditReason = null): bool
    {
        if ($documentType === '08') return false;
        if ($documentType === '07') return in_array($creditReason, ['01', '07'], true);
        return true;
    }
}
// ######## FIN NUMERACIÓN FISCAL VENEZUELA ########
