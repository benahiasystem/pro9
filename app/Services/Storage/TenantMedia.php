<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

/** Request/job-local materialization for legacy PDF engines, never public storage. */
final class TenantMedia
{
    private array $files = [];

    public function localPath(string $reference): string
    {
        $storage = app(TenantStorage::class);
        $path = TenantDisk::relative($reference);
        if (!str_starts_with($path, 'uploads/')) throw new \InvalidArgumentException('Referencia de imagen inválida.');
        $key = $storage->uuid().'/'.$path;
        if (isset($this->files[$key])) return $this->files[$key];
        $temporary = tempnam(sys_get_temp_dir(), 'sab-media-');
        if ($temporary === false) throw new \RuntimeException('No se pudo preparar la imagen.');
        $stream = null; $output = null;
        try {
            chmod($temporary, 0600);
            $stream = $storage->disk()->readStream($path);
            $output = fopen($temporary, 'wb');
            if (!is_resource($stream) || !$output || stream_copy_to_stream($stream, $output) === false) throw new \RuntimeException('No se pudo leer la imagen.');
            $this->files[$key] = $temporary;
            return $temporary;
        } catch (\Throwable $error) {
            if (is_file($temporary)) unlink($temporary);
            throw $error;
        } finally {
            if (is_resource($stream)) fclose($stream);
            if (is_resource($output)) fclose($output);
        }
    }

    public function cleanup(): void
    {
        foreach ($this->files as $file) if (is_file($file)) unlink($file);
        $this->files = [];
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
