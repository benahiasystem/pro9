<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\{Document, DocumentReceivedRetention};
use App\Services\ExchangeRates\ExchangeRateMath;

/** Applies a received voucher to an existing invoice; this never emits document type 05. */
final class HkaIvaRetentionPayload
{
    public static function build(Document $document, DocumentReceivedRetention $retention): array
    {
        $id = $document->emission->payload['documentoElectronico']['encabezado']['identificacionDocumento'] ?? [];
        if (($id['tipoDocumento'] ?? '') !== '01' || !$document->control_number
            || $document->emission->status !== 'confirmed') {
            FiscalAmounts::error('document', 'La retención espera una factura HKA confirmada con control real.');
        }
        $currency = $id['moneda'] ?? null;
        if (!in_array($currency, ['VES', 'USD'], true)) FiscalAmounts::error('currency_type_id', 'Falta la moneda fiscal conservada en HKA.');
        $money = static function ($amount) use ($retention, $document, $currency): string {
            // Voucher money and settlement money are independent. VES -> VES stays exact,
            // even when the invoice is USD and the collection rate differs from its issue rate.
            return ExchangeRateMath::finalAmount(FiscalAmounts::convert($amount, $retention->currency_type_id, $currency, $retention->exchange_rate));
        };
        return ['serie' => $id['serie'] ?? '', 'tipoDocumento' => '01',
            'numeroDocumento' => (string) $id['numeroDocumento'], 'numeroControl' => $document->control_number,
            'totalRetencion' => ['numeroCompRetencion' => $retention->voucher_number,
                'fechaEmisionCR' => $retention->voucher_date->format('d/m/Y'),
                'totalBaseImponible' => $money($retention->taxable_base),
                'totalIVA' => $money($retention->base), 'totalRetenido' => $money($retention->amount)]];
    }

    public static function identity(array $payload): array
    {
        return array_intersect_key($payload, array_flip(['serie', 'tipoDocumento', 'numeroDocumento', 'numeroControl']));
    }

    public static function matches(array $remote, array $payload): bool
    {
        foreach (self::identity($payload) as $key => $value) {
            if ((string) ($remote[$key] ?? '') !== (string) $value) return false;
        }
        $totals = $remote['totalesRetencion'] ?? [];
        foreach ($payload['totalRetencion'] as $key => $value) {
            if (!array_key_exists($key, $totals)) return false;
            if (in_array($key, ['totalBaseImponible', 'totalIVA', 'totalRetenido'], true)) {
                if (!is_numeric($totals[$key]) || !ExchangeRateMath::rational($totals[$key])->isEqualTo(ExchangeRateMath::rational($value))) return false;
            } elseif ((string) $totals[$key] !== (string) $value) return false;
        }
        return true;
    }
}
