<?php

namespace Modules\Report\Services;

/** Choose a printable landscape sheet wide enough for the selected columns. */
final class DocumentReportPaper
{
    private const OPTIONAL_COLUMNS = [
        'user_seller', 'doc_affect', 'guides', 'quote', 'case', 'district', 'department', 'province',
        'client_direction', 'ruc', 'currency_type_id', 'web_platforms', 'purchase_order', 'note_sale',
        'date_note', 'payment_form', 'payment_method', 'total_charge', 'total_exonerated', 'total_taxed',
        'total_igv', 'total', 'items',
    ];

    public static function landscape($columns): string
    {
        $count = 9; // Fixed document identifiers, dates, customer, status and discount.
        foreach (self::OPTIONAL_COLUMNS as $column) {
            if (data_get($columns, $column.'.visible', false)) $count++;
        }

        if ($count <= 15) return 'A4-L';
        if ($count <= 23) return 'A3-L';
        return 'A2-L';
    }
}
