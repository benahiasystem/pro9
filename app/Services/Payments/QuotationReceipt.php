<?php

namespace App\Services\Payments;

use App\Models\Tenant\{SaleNote, SaleNotePayment};
use Modules\Sale\Models\QuotationPayment;

/** Compatibility entry point for applying receipts of a quotation. */
final class QuotationReceipt
{
    public static function resolve($target, array $input): ?array
    {
        return CommercialSourceReceipt::resolve($target, $input, 'quotation');
    }

    public static function assertUnused(QuotationPayment $source): void
    {
        CommercialSourceReceipt::assertUnused($source, 'quotation');
    }

    public static function applyToSaleNote(SaleNote $note, array $input): SaleNotePayment
    {
        return CommercialSourceReceipt::applyToSaleNote($note, $input, 'quotation');
    }
}
