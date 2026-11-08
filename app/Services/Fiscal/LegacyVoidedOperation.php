<?php

namespace App\Services\Fiscal;

/** The legacy communication no longer performs a fiscal or local cancellation. */
final class LegacyVoidedOperation
{
    public static function reject(): void
    {
        FiscalAmounts::error('documents','La anulación por esta vía no está disponible. Para corregir una factura, utilice las opciones de notas de crédito.');
    }
}
