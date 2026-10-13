<?php
namespace App\Services\Fiscal;
/** Received ISLR has an independent adapter, outbox and absence contract. */
final class HkaIslrRetention extends HkaReceivedRetention
{
    protected const KIND = 'ISLR';
    protected const OPERATION_MODEL = \App\Models\Tenant\DocumentIslrRetentionOperation::class;
    protected const SERVICE = IslrRetentionService::class;
    protected const PAYLOAD = HkaIslrRetentionPayload::class;
    protected const RELATION = 'islr_operations';
    protected const APPLY = 'applyIslrRetention';
    protected const QUERY = 'queryIslrRetention';
    protected const REMOVE = 'removeIslrRetention';
    protected function interpretRead(\Illuminate\Http\Client\Response $response, array $payload): string
    {
        $body = $response->json();
        // ISLR-specific absence verified in DEMO (VES/USD) on 2026-10-12; IVA's 203 is unrelated.
        if ($response->successful() && is_array($body) && (string)($body['codigo'] ?? '') === '204'
            && ($body['mensaje'] ?? '') === 'No se ha registrado aún ninguna Retención de ISLR a este documento'
            && ($body['validaciones'] ?? []) === [] && !isset($body['infoRetencionISLR'])) return 'absent';
        if (!$response->successful() || !is_array($body) || (string)($body['codigo'] ?? '') !== '200' || !empty($body['validaciones'])) return 'unknown';
        if (!array_key_exists('infoRetencionISLR',$body)) return 'unknown';
        if ($body['infoRetencionISLR'] === null) return 'absent';
        $remote = $body['infoRetencionISLR'];
        if (!is_array($remote)) return 'unknown';
        $expected = HkaIslrRetentionPayload::identity($payload) + array_intersect_key($payload['detallesRetencionISLR'],array_flip(['numeroCompRetencion','fechaEmisionCR','impuestoRetenido']));
        foreach ($expected as $key=>$value) {
            if (!array_key_exists($key,$remote) || $remote[$key] === null) continue;
            if ($key === 'impuestoRetenido') {
                if (!is_numeric($remote[$key])) continue;
                if (!\App\Services\ExchangeRates\ExchangeRateMath::rational($remote[$key])->isEqualTo(\App\Services\ExchangeRates\ExchangeRateMath::rational($value))) return 'conflict';
            } elseif ((string)$remote[$key] !== (string)$value) return 'conflict';
        }
        foreach ($expected as $key=>$value) if (!isset($remote[$key]) || ($key === 'impuestoRetenido' && !is_numeric($remote[$key]))) return 'unknown';
        return 'match';
    }
}
