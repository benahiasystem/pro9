<?php

namespace App\Services\Sellnow;

/** Use the existing idempotent document/payment link writer with explicit cash context. */
final class CashDocumentLinks
{
    use \Modules\Finance\Traits\FinanceTrait;

    public function attach($document): void
    {
        $this->finance_cash_document($document, $document->id);
    }
}
