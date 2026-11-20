<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\{SpecialTaxRegimePolicy, ThirdPartyInvoicePolicy};
use App\Support\Exact\Decimal as D;

/** Shared scope and real settlement contract for third-party and special-regime sales. */
final class HkaDomesticInvoicePolicy
{
    public static function applies($document): bool
    {
        return !FiscalNote::ordinary($document) && (ThirdPartyInvoicePolicy::isThirdParty($document) || SpecialTaxRegimePolicy::applies($document));
    }

    public static function assertSnapshot(array $snapshot): void
    {
        if (($snapshot['document_type_id'] ?? '') !== '01' || ($snapshot['invoice']['operation_type_id'] ?? '0101') !== '0101'
            || ($snapshot['payment_condition_id'] ?? '') !== '01' || !in_array($snapshot['currency_type_id'] ?? '', ['VES', 'USD'], true)
            || !empty($snapshot['is_itinerant']) || !D::read($snapshot,'total_exportation')->isZero()) FiscalAmounts::error('fiscal_data', 'Terceros y regímenes especiales requieren una Factura interna al contado en VES o USD.');
        foreach (['fiscal_emission_mode' => 'digital', 'fiscal_environment' => 'demo', 'source_module' => 'WEB'] as $key => $value) {
            if (isset($snapshot[$key]) && $snapshot[$key] !== $value) FiscalAmounts::error('fiscal_data', 'Esta operación requiere Comprobantes web, Medios digitales y DEMO.');
        }
        if (!HkaSettlement::complete($snapshot)) FiscalAmounts::error('payments', 'La factura por terceros o con régimen especial debe estar completamente pagada con cobros reales.');
    }

    public static function assertPaid(Document $document, bool $required = false): void
    {
        $document = $document->fresh();
        if (!$required && !self::applies($document)) return;
        $document->load(['payments', 'invoice']);
        self::assertSnapshot($document->toArray());
        if ($document->exactBalance()->abs()->isGreaterThan('0.005')) FiscalAmounts::error('payments', 'La factura por terceros o con régimen especial requiere saldo cero.');
    }
}
