<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace Modules\Accounting\Support;

use App\Services\Storage\{TenantDisk,TenantStorage};
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Accounting\Domain\CompanyContext;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PrivateStorage
{
    private ?string $temporary = null;

    public function key(string $relative): string
    {
        $connection = DB::connection('tenant');
        $database = $connection->getDatabaseName();
        $selected = $connection->selectOne('SELECT current_database() AS name')->name;
        if (!$database || $database !== $selected) { throw new \LogicException('La conexión no acredita el tenant de almacenamiento.'); }
        $key = hash('sha256', 'accounting-storage-v1:' . $database);
        $context = app(CompanyContext::class);
        if (!Str::isUuid($context->organizationId()) || !Str::isUuid($context->id())) {
            throw new LogicException('La empresa no acredita un identificador de almacenamiento válido.');
        }
        return 'accounting/'.$context->organizationId().'/'.$context->id().'/'.TenantDisk::relative($relative);
    }

    public function put(string $relative, string $bytes): void
    {
        app(TenantStorage::class)->disk()->put($this->key($relative), $bytes);
    }

    public function putFile(string $relative, string $local): void
    {
        $stream = fopen($local, 'rb');
        if (!$stream) throw new RuntimeException('No se pudo abrir el archivo contable.');
        try {
            app(TenantStorage::class)->disk()->put($this->key($relative), $stream);
        } finally {
            fclose($stream);
        }
    }

    public function workspace(): string
    {
        if ($this->temporary === null) {
            $path = sys_get_temp_dir().'/sab-accounting-'.bin2hex(random_bytes(12));
            if (!mkdir($path, 0700)) throw new RuntimeException('No se pudo preparar el directorio privado de trabajo.');
            $this->temporary = $path;
        }
        return $this->temporary;
    }

    /** Copies a bounded immutable object for a consumer which requires a filesystem path. */
    public function materialize(string $relative, int $maxBytes): ?string
    {
        $disk = app(TenantStorage::class)->disk();
        $key = $this->key($relative);
        try {
            $input = $disk->readStream($key);
        } catch (NotFoundHttpException $missing) {
            return null;
        }
        $path = $this->workspace().'/'.Str::uuid();
        $output = null;
        $valid = false;
        try {
            $output = fopen($path, 'xb');
            if (!is_resource($input) || !$output) throw new RuntimeException('No se pudo leer el archivo contable.');
            chmod($path, 0600);
            $bytes = stream_copy_to_stream($input, $output, $maxBytes + 1);
            if ($bytes === false || $bytes > $maxBytes) return null;
            $valid = true;
            return $path;
        } finally {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            if (!$valid && is_file($path)) unlink($path);
        }
    }

    public function cleanup(): void
    {
        if ($this->temporary === null) return;
        foreach (glob($this->temporary.'/*') as $file) {
            if (is_file($file)) unlink($file);
        }
        rmdir($this->temporary);
        $this->temporary = null;
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
