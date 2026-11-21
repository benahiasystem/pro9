<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// ######## INICIO MONEDA DOCUMENTAL DE VENTAS ########
final class SalesDocumentCurrency
{
    private const TABLES = [
        'documents', 'sale_notes', 'quotations', 'sale_opportunities',
        'contracts', 'order_notes', 'technical_services',
    ];

    public static function usesBolivars(): bool
    {
        // The column is installed with the initial schema and cannot be toggled
        // by changing user settings. Older tenant schemas have no marker.
        return DB::connection('tenant')->getSchemaBuilder()->hasColumn('configurations', 'sales_documents_ves');
    }

    public static function validate($currency): void
    {
        if ($currency !== 'VES' && self::usesBolivars()) {
            throw ValidationException::withMessages([
                'currency_type_id' => 'La moneda del documento debe ser bolívares (VES).',
            ]);
        }
    }

    public static function validateModel(Model $model): void
    {
        if (in_array($model->getTable(), self::TABLES, true)
            && (!$model->exists || $model->isDirty('currency_type_id'))) {
            self::validate($model->getAttribute('currency_type_id'));
        }
    }
}
// ######## FIN MONEDA DOCUMENTAL DE VENTAS ########
