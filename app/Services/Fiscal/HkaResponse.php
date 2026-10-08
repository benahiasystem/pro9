<?php

namespace App\Services\Fiscal;

/** Only explicitly understood responses can confirm an emission. Never persist raw provider text. */
final class HkaResponse
{
    public static function interpret(int $http, $body, array $identity, bool $query = false): array
    {
        $code = is_array($body) && (is_int($body['codigo'] ?? null) || is_string($body['codigo'] ?? null))
            ? (string) $body['codigo'] : null;
        if ($code !== null && !preg_match('/\A[0-9]{3,4}\z/', $code)) $code = null;
        $result = ['status' => 'uncertain', 'http_status' => $http, 'code' => $code,
            'retry_allowed' => false, 'diagnostic' => 'No se pudo confirmar la emisión. Consulte el estado HKA antes de reenviar.'];
        if (!is_array($body) || $http < 200 || $http >= 300) return $result;
        $validations = $body['validaciones'] ?? [];
        if (!is_array($validations)) return $result;
        // Exact absence envelope verified against DEMO on 2026-10-07 using a fresh UUID.
        if ($query && $code === '203' && ($body['estado'] ?? null) === null
            && ($body['mensaje'] ?? null) === 'Consulta no procesada'
            && $validations === ['Documento no encontrado en nuestra base de datos']) {
            return array_replace($result, ['retry_allowed' => true,
                'diagnostic' => 'HKA no encontró la operación. Puede reintentar después de 30 segundos del envío anterior.']);
        }
        if (!$query && in_array($code, ['202', '203', '205', '400', '401'], true)) {
            return array_replace($result, ['status' => 'rejected', 'diagnostic' => ($code === '203' && in_array('No posee rango de numeración disponible', $validations, true))
                ? 'HKA no tiene un rango de numeración disponible para esta factura. Configure el rango DEMO en HKA.' : self::rejection($code)]);
        }
        if ($code !== '200' || count($validations)) return $result;
        $data = $body[$query ? 'estado' : 'resultado'] ?? null;
        if (!is_array($data)) return $result;
        foreach (['tipoDocumento', 'numeroDocumento'] as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] !== $identity[$field]) return $result;
        }
        if (!$query && (!array_key_exists('serie', $data) || (string) $data['serie'] !== $identity['serie'])) return $result;
        if ($query && ($data['transaccionId'] ?? null) !== $identity['transaccionId']) return $result;
        if (!$query && !empty($data['transaccionId']) && $data['transaccionId'] !== $identity['transaccionId']) return $result;
        // "Enviada" with code 200, matching transaction and control was verified in DEMO on 2026-10-07.
        if ($query && !in_array($data['estadoDocumento'] ?? null, ['Enviada', 'Procesado', 'PROCESADO', 'Documento Procesado exitosamente'], true)) return $result;
        try {
            $control = (string) new \App\Services\FiscalControlNumber($data['numeroControl'] ?? '');
        } catch (\Throwable $exception) { return $result; }
        $url = $data['urlConsulta'] ?? null;
        if (!is_string($url) || strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https') $url = null;
        return array_replace($result, ['status' => 'confirmed', 'diagnostic' => 'Factura confirmada por HKA.',
            'control_number' => $control, 'authorization' => is_string($data['autorizado'] ?? null) ? mb_substr($data['autorizado'], 0, 255) : null,
            'assigned_at' => self::assignmentDate($data['fechaAsignacion'] ?? null, $data['horaAsignacion'] ?? null),
            'control_assigned_at' => self::assignmentDate($data['fechaAsignacionNumeroControl'] ?? null, $data['horaAsignacionNumeroControl'] ?? null),
            'consulta_url' => $url]);
    }

    private static function assignmentDate($date, $time): ?string
    {
        if (!is_string($date) || !is_string($time)) return null;
        $value = $date.' '.strtoupper($time);
        $parsed = \DateTimeImmutable::createFromFormat('!d/m/Y h:i:s A', $value, new \DateTimeZone('America/Caracas'));
        return $parsed && $parsed->format('d/m/Y h:i:s A') === $value ? $parsed->format('Y-m-d H:i:s') : null;
    }

    private static function rejection(string $code): string
    {
        return [
            '202' => 'HKA rechazó la asignación. Verifique el punto de facturación.',
            '203' => 'HKA rechazó los datos obligatorios o su formato.',
            '205' => 'HKA rechazó las validaciones mínimas de la factura.',
            '400' => 'HKA rechazó la estructura de la solicitud.',
            '401' => 'HKA rechazó la autorización de la solicitud.',
        ][$code];
    }
}
