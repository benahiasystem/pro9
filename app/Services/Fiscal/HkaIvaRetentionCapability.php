<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\ExchangeRates\ExchangeRateMath;

final class HkaIvaRetentionCapability
{
    public const BLOCK_REASON = 'HKA DEMO exige que las formas de pago sumen el total de la factura (1016). El cobro neto por retención queda guardado con saldo pendiente, pero su emisión HKA está bloqueada hasta validar este contrato con el proveedor.';

    public static function blockedSnapshot(array $snapshot): bool
    {
        if (($snapshot['document_type_id'] ?? '') !== '01' || config('iva_retention.demo_net_collection_verified',false)) return false;
        $expected = !empty($snapshot['fiscal_data']['iva_retention_prevision']);
        foreach ($snapshot['received_retentions'] ?? [] as $row) {
            if (($row['tax_kind'] ?? '') === 'IVA' && in_array($row['application_status'] ?? '',['pending_validation','applied'],true)) $expected=true;
        }
        if (!$expected) return false;
        $paid = ExchangeRateMath::rational(0);
        foreach ($snapshot['payments'] ?? [] as $row) if (empty($row['reversed_at'])) $paid=$paid->plus($row['payment'] ?? 0);
        return $paid->isLessThan(ExchangeRateMath::rational($snapshot['total'] ?? 0)->minus('0.005'));
    }

    public static function reason(Document $document): ?string
    {
        if (!$document->exists || $document->document_type_id !== '01' || $document->fiscal_emission_mode !== 'digital'
            || $document->fiscal_environment !== 'demo' || !IvaRetentionService::available($document)) return null;
        $document->loadMissing(['fiscal_data','payments','received_retentions']);
        return self::blockedSnapshot(['document_type_id'=>$document->document_type_id,'total'=>$document->total,
            'fiscal_data'=>$document->fiscal_data ? $document->fiscal_data->toArray() : [],
            'received_retentions'=>$document->received_retentions->toArray(),'payments'=>$document->payments->toArray()]) ? self::BLOCK_REASON : null;
    }

    public static function assertSnapshot(array $snapshot): void
    {
        if (self::blockedSnapshot($snapshot)) FiscalAmounts::error('iva_retention',self::BLOCK_REASON);
    }
}
