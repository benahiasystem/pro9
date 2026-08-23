<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

// ########## INICIO CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
final class SalesDocumentTypePolicy
{
    public const INVOICE = '01';
    public const RECEIPT = '03';
    public const SALE_NOTE = '80';
    public const SALE_NOTE_ALIAS = 'nv';

    public const PRIMARY_DOCUMENT_TYPE_IDS = [self::INVOICE, self::SALE_NOTE];
    public const TECHNICAL_SERVICE_DOCUMENT_TYPE_IDS = [self::INVOICE, self::SALE_NOTE_ALIAS];

    /**
     * Impide nuevas boletas sin afectar lectura, impresión o auditoría histórica.
     */
    public static function assertNewFiscalDocumentAllowed(?string $documentTypeId): void
    {
        if ($documentTypeId === self::RECEIPT) {
            throw ValidationException::withMessages([
                'document_type_id' => 'La emisión de Boletas está deshabilitada. Use Factura o Nota de venta.',
            ]);
        }
    }

    /**
     * Valida la lista específica de un flujo antes de producir efectos laterales.
     *
     * @param array<int, string> $allowedDocumentTypeIds
     */
    public static function assertAllowedForFlow(?string $documentTypeId, array $allowedDocumentTypeIds): void
    {
        if (! in_array($documentTypeId, $allowedDocumentTypeIds, true)) {
            throw ValidationException::withMessages([
                'document_type_id' => 'El tipo de comprobante seleccionado no está permitido en este flujo.',
            ]);
        }
    }

    /**
     * Conserva las series históricas, pero evita crear nuevas series de Boleta y sus NC/ND.
     */
    public static function isProhibitedNewSeries(string $documentTypeId, string $number): bool
    {
        if ($documentTypeId === self::RECEIPT) {
            return true;
        }

        $prefix = strtoupper(substr(trim($number), 0, 2));

        return ($documentTypeId === '07' && $prefix === 'BC')
            || ($documentTypeId === '08' && $prefix === 'BD');
    }
}
// ######### FIN CAMBIO SOLO FACTURAS Y NOTAS DE VENTA
