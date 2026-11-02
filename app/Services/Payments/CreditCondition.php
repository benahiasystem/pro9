<?php

namespace App\Services\Payments;

use App\Models\Tenant\SaleNote;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
final class CreditCondition
{
    public static function isCredit($document): bool
    {
        $condition = $document->payment_condition_id;
        if ($condition !== null && $condition !== '') return in_array((string) $condition, ['02', '03'], true);
        // Older sale notes may not have stored a payment condition.
        return $document instanceof SaleNote && $document->payment_method_type?->collection_kind === 'credit';
    }
}
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
