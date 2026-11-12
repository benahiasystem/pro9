<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Domain\ApiProblem;

/** ClamD INSTREAM: no source paths or credentials leave the PRO9 process. */
class AttachmentScanner
{
    private function socket(): mixed
    {
        $endpoint = config('accounting.attachments.scanner_endpoint', '');
        if (!is_string($endpoint) || !preg_match('#^(?:tcp://[a-zA-Z0-9.\-]+:[0-9]{1,5}|unix:///[^\r\n\0]+)$#D', $endpoint)) {
            throw new ApiProblem('ATTACHMENT_SCANNER_UNAVAILABLE', 'El análisis de archivos no está configurado en PRO9. El archivo no se conserva sin verificar.', 503);
        }
        $socket = @stream_socket_client($endpoint, $error, $message, 3);
        if (!$socket) { throw new ApiProblem('ATTACHMENT_SCANNER_UNAVAILABLE', 'El servicio de análisis no está disponible. Conserva el archivo y vuelve a intentar cuando se restablezca.', 503); }
        stream_set_timeout($socket, 15);
        return $socket;
    }

    private function write(mixed $socket, string $bytes): void
    {
        while ($bytes !== '') {
            $length = @fwrite($socket, $bytes);
            if (!$length) { throw new ApiProblem('ATTACHMENT_SCAN_INTERRUPTED', 'El análisis no pudo recibir el archivo completo. No se adjuntó una copia parcial.', 503); }
            $bytes = substr($bytes, $length);
        }
    }

    private function response(mixed $socket): string
    {
        $response = '';
        while (!feof($socket) && !str_contains($response, "\0") && strlen($response) < 2048) {
            $part = @fread($socket, 512);
            if ($part === false || $part === '' && (stream_get_meta_data($socket)['timed_out'] || feof($socket))) { break; }
            $response .= $part;
        }
        if (!str_ends_with($response, "\0")) { throw new ApiProblem('ATTACHMENT_SCAN_INTERRUPTED', 'El análisis no entregó una confirmación completa. El archivo no se adjuntó.', 503); }
        return trim($response, "\0\r\n");
    }

    public function scan(string $path): array
    {
        $socket = $this->socket();
        try { $this->write($socket, "zVERSION\0"); $version = $this->response($socket); }
        finally { fclose($socket); }
        if (!preg_match('#^ClamAV ([^/\r\n]{1,40})/([0-9]{1,12})/(.{1,100})$#D', $version, $parts)) {
            throw new ApiProblem('ATTACHMENT_SCANNER_UNAVAILABLE', 'No se pudo comprobar la versión del analizador.', 503);
        }
        try { $databaseDate = new \DateTimeImmutable($parts[3], new \DateTimeZone('UTC')); }
        catch (\Throwable) { throw new ApiProblem('ATTACHMENT_SCANNER_UNAVAILABLE', 'No se pudo comprobar la vigencia del análisis.', 503); }
        $age = time() - $databaseDate->getTimestamp();
        if ($age < -86400 || $age > (int) config('accounting.attachments.signature_hours', 168) * 3600) {
            throw new ApiProblem('ATTACHMENT_SCANNER_OUTDATED', 'El servicio de análisis requiere actualizar sus firmas antes de adjuntar archivos.', 503);
        }
        $file = fopen($path, 'rb');
        if (!$file) { throw new \RuntimeException('No se pudo abrir el archivo recibido para analizarlo.'); }
        $socket = $this->socket();
        $sent = 0;
        try {
            $this->write($socket, "zINSTREAM\0");
            while (!feof($file)) {
                $chunk = fread($file, 65536);
                if ($chunk === false) { throw new \RuntimeException('No se pudo leer el archivo completo.'); }
                $sent += strlen($chunk);
                if ($sent > 5 * 1024 * 1024) { throw new ApiProblem('ATTACHMENT_TOO_LARGE', 'El archivo supera 5 MiB.', 413); }
                if ($chunk !== '') { $this->write($socket, pack('N', strlen($chunk)) . $chunk); }
            }
            $this->write($socket, pack('N', 0));
            $answer = $this->response($socket);
            if ($answer !== 'stream: OK') {
                if (str_ends_with($answer, ' FOUND')) { throw new ApiProblem('ATTACHMENT_SCAN_REJECTED', 'El archivo fue rechazado por el análisis de seguridad. No se adjuntó; revisa su contenido antes de volver a enviarlo.', 422); }
                throw new ApiProblem('ATTACHMENT_SCAN_INTERRUPTED', 'El analizador no confirmó este archivo. No se adjuntó.', 503);
            }
        } finally { fclose($file); fclose($socket); }
        return ['engine' => 'ClamAV ' . $parts[1], 'database' => $parts[2], 'database_date' => $databaseDate->format('c'), 'scanned_at' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Caracas')))->format('Y-m-d H:i:s')];
    }
}
