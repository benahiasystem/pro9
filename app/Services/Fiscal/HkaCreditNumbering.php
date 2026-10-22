<?php
namespace App\Services\Fiscal;

use App\Models\Tenant\{Document, DocumentEmission};

// ######## INICIO CONFLICTO NUMERACION NC HKA ########
/** A number lookup is evidence about identity, never authority to adopt another operation. */
final class HkaCreditNumbering
{
    public static function applies(Document $document): bool
    {
        return $document->document_type_id === '07' && FiscalNote::digital($document)
            && $document->fiscal_environment === 'demo';
    }

    public static function interpret(int $http, $body, array $identity): array
    {
        $result = HkaResponse::interpret($http, $body, $identity, true);
        if ($result['retry_allowed']) return $result;
        $result['retry_allowed'] = false;
        if ($result['status'] === 'confirmed') {
            // Unlike the original UUID lookup, the secondary lookup must also validate a returned series.
            if (!array_key_exists('serie', $body['estado']) || $body['estado']['serie'] === $identity['serie']) return $result;
            $result['status'] = 'uncertain';
            unset($result['control_number'], $result['authorization'], $result['consulta_url']);
        }
        $result['diagnostic'] = 'No se pudo verificar si el número de la nota de crédito está disponible en HKA. Consulte nuevamente antes de reenviar.';
        $state = is_array($body) ? ($body['estado'] ?? null) : null;
        if ($http < 200 || $http >= 300 || $result['code'] !== '200'
            || !is_array($body['validaciones'] ?? []) || !empty($body['validaciones']) || !is_array($state)) return $result;
        if (($state['tipoDocumento'] ?? null) !== '02' || $identity['tipoDocumento'] !== '02'
            || ($state['numeroDocumento'] ?? null) !== $identity['numeroDocumento']
            || (array_key_exists('serie', $state) && $state['serie'] !== $identity['serie'])
            || !is_string($state['transaccionId'] ?? null) || trim($state['transaccionId']) === ''
            || $state['transaccionId'] === $identity['transaccionId']) return $result;
        try { $control = (string)new \App\Services\FiscalControlNumber($state['numeroControl'] ?? ''); }
        catch (\Throwable $exception) { return $result; }
        $remoteStatus = $state['estadoDocumento'] ?? null;
        $status = is_string($remoteStatus) ? (['Anulada'=>'Anulada', 'Anulado'=>'Anulada', 'Enviada'=>'Enviada', 'Procesado'=>'Procesado',
            'PROCESADO'=>'Procesado', 'Documento Procesado exitosamente'=>'Procesado'][$remoteStatus] ?? 'Registrada') : 'Registrada';
        $result['numbering_conflict'] = ['control_number'=>$control, 'remote_status'=>$status,
            'transaction_hash'=>hash('sha256', $state['transaccionId'])];
        $result['diagnostic'] = self::diagnostic($identity, $result['numbering_conflict']);
        return $result;
    }

    public static function diagnostic(array $identity, array $conflict): string
    {
        return 'HKA ya tiene registrada la nota de crédito '.FiscalIdentity::numberFull($identity['serie'], $identity['numeroDocumento'])
            .' en otra operación, con control '.$conflict['control_number'].' y estado '.$conflict['remote_status']
            .'. Resuelva el conflicto de numeración antes de emitir una nueva nota.';
    }

    public static function conflict(?DocumentEmission $emission): ?array
    {
        if (!$emission || $emission->status !== 'uncertain') return null;
        $response = $emission->response ?? [];
        $conflict = $response['numbering_conflict'] ?? null;
        return is_array($conflict) && ($conflict['operation_key'] ?? null) === $emission->operation_key
            && ($conflict['attempt_id'] ?? null) === ($response['attempt_id'] ?? null) ? $conflict : null;
    }

    public static function canCancelLocally(DocumentEmission $emission): bool
    {
        if (!self::conflict($emission)) return false;
        $response = $emission->response ?? [];
        $absence = $response['transaction_absence'] ?? [];
        return ($absence['verified'] ?? false) === true && ($absence['operation_key'] ?? null) === $emission->operation_key
            && ($absence['attempt_id'] ?? null) === ($response['attempt_id'] ?? null);
    }
}
// ######## FIN CONFLICTO NUMERACION NC HKA ########
