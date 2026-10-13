<?php
namespace App\Services\Fiscal;
/** IVA keeps its published contract and independently verified absence envelope. */
final class HkaIvaRetention extends HkaReceivedRetention
{
    protected const KIND = 'IVA';
    protected const OPERATION_MODEL = \App\Models\Tenant\DocumentIvaRetentionOperation::class;
    protected const SERVICE = IvaRetentionService::class;
    protected const PAYLOAD = HkaIvaRetentionPayload::class;
    protected const RELATION = 'operations';
    protected const APPLY = 'applyIvaRetention';
    protected const QUERY = 'queryIvaRetention';
    protected const REMOVE = 'removeIvaRetention';
    protected function interpretRead(\Illuminate\Http\Client\Response $response, array $payload): string
    {
        $body = $response->json();
        // DEMO verified 2026-10-12: absence is a business 203, not HTTP 404.
        if ($response->successful() && is_array($body) && (string)($body['codigo'] ?? '') === '203'
            && ($body['mensaje'] ?? '') === 'Retención no encontrada'
            && ($body['validaciones'] ?? []) === ['No se ha aplicado retención previamente por lo que no se puede consultar nada']) return 'absent';
        if (!$response->successful() || !is_array($body) || (string)($body['codigo'] ?? '') !== '200' || !empty($body['validaciones'])) return 'unknown';
        // Only an explicit empty result on a successful query accredits absence.
        if (!array_key_exists('infoRetencion',$body)) return 'unknown';
        if ($body['infoRetencion'] === null) return 'absent';
        $remote = $body['infoRetencion'];
        if (!is_array($remote)) return 'unknown';
        foreach (HkaIvaRetentionPayload::identity($payload) as $key=>$value) {
            if (array_key_exists($key,$remote) && (string)($remote[$key] ?? '') !== (string)$value) return 'conflict';
        }
        foreach (HkaIvaRetentionPayload::identity($payload) as $key=>$value) if (!array_key_exists($key,$remote)) return 'unknown';
        if (array_key_exists('totalesRetencion',$remote) && $remote['totalesRetencion'] === null) return 'absent';
        if (!is_array($remote['totalesRetencion'] ?? null)) return 'unknown';
        // A different voucher is a positive conflict even if the provider omits other totals.
        foreach (['numeroCompRetencion','fechaEmisionCR'] as $key) {
            if (isset($remote['totalesRetencion'][$key]) && (string)$remote['totalesRetencion'][$key] !== (string)$payload['totalRetencion'][$key]) return 'conflict';
        }
        foreach ($payload['totalRetencion'] as $key=>$value) if (!isset($remote['totalesRetencion'][$key])) return 'unknown';
        foreach (['totalBaseImponible','totalIVA','totalRetenido'] as $key) if (!is_numeric($remote['totalesRetencion'][$key])) return 'unknown';
        return HkaIvaRetentionPayload::matches($remote,$payload) ? 'match' : 'conflict';
    }

}
