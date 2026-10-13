<?php

namespace App\Services\Fiscal;

use App\Models\Tenant\Document;
use App\Services\ExchangeRates\ExchangeRateMath;

final class HkaIslrRetentionCapability
{
    public const BLOCK_REASON = 'El cobro neto por ISLR se conserva con saldo pendiente. Su emisión HKA está bloqueada hasta acreditar aceptación del circuito comercial en DEMO.';

    public static function blockedSnapshot(array $snapshot): bool
    {
        if (($snapshot['document_type_id'] ?? '') !== '01' || config('islr_retention.demo_net_collection_verified',false)) return false;
        $expected = !empty($snapshot['fiscal_data']['islr_retention_prevision']);
        foreach ($snapshot['received_retentions'] ?? [] as $row) {
            if (($row['tax_kind'] ?? '') === 'ISLR' && in_array($row['application_status'] ?? '',['pending_validation','applied'],true)) $expected=true;
        }
        if (!$expected) return false;
        $paid = ExchangeRateMath::rational(0);
        foreach ($snapshot['payments'] ?? [] as $row) if (empty($row['reversed_at'])) $paid=$paid->plus($row['payment'] ?? 0);
        return $paid->isLessThan(ExchangeRateMath::rational($snapshot['total'] ?? 0)->minus('0.005'));
    }

    public static function reason(Document $document): ?string
    {
        if (!$document->exists || $document->document_type_id !== '01' || $document->fiscal_emission_mode !== 'digital'
            || $document->fiscal_environment !== 'demo' || !IslrRetentionService::available($document)) return null;
        $document->loadMissing(['fiscal_data','payments','received_retentions']);
        return self::blockedSnapshot(['document_type_id'=>$document->document_type_id,'total'=>$document->total,
            'fiscal_data'=>$document->fiscal_data ? $document->fiscal_data->toArray() : [],
            'received_retentions'=>$document->received_retentions->toArray(),'payments'=>$document->payments->toArray()]) ? self::BLOCK_REASON : null;
    }

    public static function assertSnapshot(array $snapshot): void
    {
        if (self::blockedSnapshot($snapshot)) FiscalAmounts::error('islr_retention',self::BLOCK_REASON);
    }
}
