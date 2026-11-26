<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Services\Storage;

use App\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class StorageBackup
{
    public function create(string $uuid, string $snapshot, bool $includeFiles = true): array
    {
        TenantStorage::validateUuid($uuid);
        $this->validateSnapshot($snapshot);

        return $this->inWorkspace(function (string $directory) use ($uuid, $snapshot, $includeFiles) {
            $archive = $directory.'/archive.zip';
            $metadata = $this->buildTenantArchive($directory, $archive, $uuid, $snapshot, $includeFiles);
            return $this->publish($archive, $uuid, $snapshot, $metadata);
        });
    }

    public function createAll(string $snapshot, bool $includeFiles = true): array
    {
        $this->validateSnapshot($snapshot);

        return $this->inWorkspace(function (string $directory) use ($snapshot, $includeFiles) {
            $tenants = [];
            $system = config('tenancy.db.system-connection-name', 'system');
            // Capture the exact inventory of websites in the central dump.
            $this->dump($system, $directory.'/database.dump', static function ($table, $row) use (&$tenants) {
                if ($table === 'websites' && empty($row['deleted_at'])) {
                    $tenants[] = TenantStorage::validateUuid($row['uuid']);
                }
                return $row;
            });

            $archive = $directory.'/archive.zip';
            $metadata = $this->writeArchive($directory, $archive, 'all', $snapshot, $includeFiles,
                function (ZipArchive $zip, $manifest) use ($tenants, $directory, $snapshot, $includeFiles): array {
                    $bytes = 0;
                    $count = 0;
                    foreach ($tenants as $index => $uuid) {
                        if (!Website::where('uuid', $uuid)->exists()) {
                            throw new RuntimeException('Una empresa del respaldo dejó de estar disponible.');
                        }
                        $local = $directory.'/company-'.$index.'.zip';
                        $this->inWorkspace(fn($work) => $this->buildTenantArchive($work, $local, $uuid, $snapshot, $includeFiles));
                        $bytes += $this->addEntry($zip, $manifest, $local, $uuid.'/archive.zip');
                        $count++;
                    }
                    if ($includeFiles) {
                        $media = app(TenantStorage::class)->systemMedia();
                        foreach ($media->listing() as $entry) {
                            $local = $directory.'/system-media-'.$count++;
                            $this->copyObject($media, $entry['path'], $local, $entry['size']);
                            $bytes += $this->addEntry($zip, $manifest, $local, '_system/media/'.$entry['path']);
                        }
                    }
                    return [$count, $bytes];
                });
            return $this->publish($archive, 'all', $snapshot, $metadata);
        });
    }

    private function buildTenantArchive(string $directory, string $archive, string $uuid, string $snapshot, bool $includeFiles): array
    {
        // Prevent physical reclamation of versions included in the PostgreSQL snapshot. Other requests can publish new versions.
        return app(StorageLock::class)->run('storage-gc:'.$uuid, fn() => app(TenantContext::class)->run($uuid,
            function () use ($directory, $archive, $uuid, $snapshot, $includeFiles) {
                $selection = fopen($directory.'/versions.ndjson', 'w+b');
                if (!$selection) throw new RuntimeException('No se pudo registrar el catálogo del respaldo.');
                try {
                    $this->dump('tenant', $directory.'/database.dump', function ($table, $row) use ($selection, $includeFiles) {
                        if ($includeFiles && $table === 'storage_objects') {
                            $this->writeLine($selection, [
                                'path' => $row['object_key'],
                                'size' => (int) $row['size'],
                                'sha256' => $row['sha256'],
                            ]);
                        }
                        return $row;
                    });
                    rewind($selection);
                    $disk = app(TenantStorage::class)->physicalForUuid($uuid);
                    return $this->writeArchive($directory, $archive, $uuid, $snapshot, $includeFiles,
                        function (ZipArchive $zip, $manifest) use ($selection, $disk, $directory, $uuid): array {
                            $count = 0;
                            $bytes = 0;
                            while (($line = fgets($selection)) !== false) {
                                app(StorageLock::class)->assertOwned('storage-gc:'.$uuid);
                                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                                $path = TenantDisk::relative($entry['path']);
                                $local = $directory.'/object-'.$count++;
                                $this->copyObject($disk, $path, $local, $entry['size']);
                                $bytes += $this->addEntry($zip, $manifest, $local, $path, $entry['sha256']);
                            }
                            if (!feof($selection)) throw new RuntimeException('No se pudo leer el catálogo del respaldo.');
                            return [$count, $bytes];
                        });
                } finally {
                    fclose($selection);
                }
            }));
    }

    private function writeArchive(string $directory, string $archive, string $scope, string $snapshot, bool $includeFiles, callable $writeObjects): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el respaldo.');
        }
        $manifestPath = $directory.'/manifest.ndjson';
        $manifest = fopen($manifestPath, 'wb');
        if (!$manifest) throw new RuntimeException('No se pudo abrir el manifiesto.');

        try {
            [$count, $bytes] = $writeObjects($zip, $manifest);
        } finally {
            fclose($manifest);
        }
        $database = $directory.'/database.dump';
        $metadata = [
            'version' => 1,
            'engine' => 'postgresql',
            'includes_files' => $includeFiles,
            'tenant_uuid' => $scope,
            'snapshot' => $snapshot,
            'files' => $count,
            'bytes' => $bytes,
            'created_at' => now()->toIso8601String(),
            'database_sha256' => hash_file('sha256', $database),
        ];
        if (!$zip->addFile($database, 'database.dump') || !$zip->addFile($manifestPath, 'manifest.ndjson')
            || !$zip->addFromString('manifest.json', json_encode($metadata, JSON_THROW_ON_ERROR)) || !$zip->close()) {
            throw new RuntimeException('No se pudo cerrar el respaldo.');
        }
        $this->verifyLocal($archive);
        return $metadata;
    }

    private function copyObject(TenantDisk $disk, string $path, string $local, int $expectedSize): void
    {
        $input = $disk->readStream($path);
        $output = null;
        try {
            $output = fopen($local, 'wb');
            if (!is_resource($input) || !$output || stream_copy_to_stream($input, $output) !== $expectedSize) {
                throw new RuntimeException('Un archivo cambió o no se pudo respaldar.');
            }
        } finally {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
        }
    }

    private function addEntry(ZipArchive $zip, $manifest, string $local, string $path, ?string $expectedHash = null): int
    {
        $size = filesize($local);
        $hash = hash_file('sha256', $local);
        if ($size === false || !$hash || ($expectedHash !== null && !hash_equals($expectedHash, $hash))) {
            throw new RuntimeException('Una versión del respaldo no corresponde al catálogo.');
        }
        if (!$zip->addFile($local, 'files/'.$path)) {
            throw new RuntimeException('No se pudo agregar un archivo al respaldo.');
        }
        $this->writeLine($manifest, ['path'=>$path, 'size'=>$size, 'sha256'=>$hash]);
        return $size;
    }

    private function writeLine($manifest, array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        if (fwrite($manifest, $line) !== strlen($line)) {
            throw new RuntimeException('No se pudo escribir el manifiesto.');
        }
    }

    private function publish(string $archive, string $scope, string $snapshot, array $metadata): array
    {
        $stream = fopen($archive, 'rb');
        if (!$stream) throw new RuntimeException('No se pudo leer el respaldo.');
        // Each attempt writes immutable bytes. Only the completed central record selects the archive.
        $path = $scope.'/'.$snapshot.'/archive-'.Str::uuid().'.zip';
        $backups = app(TenantStorage::class)->backups();
        try {
            $backups->put($path, $stream, ['ContentType'=>'application/zip']);
        } finally {
            fclose($stream);
        }
        $hash = hash_file('sha256', $archive);
        $remote = $backups->readStream($path);
        try {
            if (!is_resource($remote)) throw new RuntimeException('No se pudo verificar el respaldo guardado.');
            $context = hash_init('sha256');
            $size = hash_update_stream($context, $remote);
            if ($size !== filesize($archive) || !hash_equals($hash, hash_final($context))) {
                throw new RuntimeException('El respaldo guardado no superó la verificación.');
            }
        } finally {
            if (is_resource($remote)) fclose($remote);
        }
        return ['path'=>$path, 'name'=>$scope.'-'.$snapshot.'.zip', 'sha256'=>$hash, 'size'=>$size,
            'files'=>$metadata['files'], 'bytes'=>$metadata['bytes']];
    }

    private function inWorkspace(callable $callback): mixed
    {
        $directory = sys_get_temp_dir().'/sab-backup-'.bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) throw new RuntimeException('No se pudo preparar el respaldo.');
        try {
            return $callback($directory);
        } finally {
            foreach (glob($directory.'/*') as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($directory);
        }
    }

    private function validateSnapshot(string $snapshot): void
    {
        if (!Str::isUuid($snapshot)) throw new \InvalidArgumentException('Identificador de respaldo inválido.');
    }

    private function dump(string $connection, string $destination, ?callable $rowHook = null): void
    {
        $db = DB::connection($connection);
        if ($db->getDriverName() !== 'pgsql' || $db->transactionLevel() !== 0) {
            throw new RuntimeException('El respaldo requiere una conexión PostgreSQL independiente.');
        }
        $table = $connection === 'tenant' ? 'storage_objects' : 'websites';
        $db->transaction(function () use ($db, $table, $destination, $rowHook) {
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $snapshot = $db->selectOne('SELECT pg_export_snapshot() AS snapshot')->snapshot;
            if ($rowHook) {
                foreach ($db->table($table)->cursor() as $row) {
                    $rowHook($table, (array) $row);
                }
            }
            app(\App\Database\PostgresBackup::class)->dump($db->getConfig(), $destination, $snapshot);
        });
    }

    public function verifyLocal(string $archive): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) throw new RuntimeException('El respaldo no es un ZIP válido.');
        try {
            $metadata = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            $manifest = $zip->getStream('manifest.ndjson');
            if (!is_resource($manifest)) throw new RuntimeException('Falta el manifiesto del respaldo.');
            $seen = [];
            $bytes = 0;
            try {
                while (($line = fgets($manifest)) !== false) {
                    $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $name = TenantDisk::relative($entry['path']);
                    if ($name !== $entry['path'] || isset($seen[$name])) throw new RuntimeException('El manifiesto contiene rutas inválidas.');
                    $seen[$name] = true;
                    [$size, $digest] = $this->hashEntry($zip, 'files/'.$name);
                    if ($size !== $entry['size'] || !hash_equals($entry['sha256'], $digest)) {
                        throw new RuntimeException('Un archivo del respaldo está dañado.');
                    }
                    $bytes += $size;
                }
                if (!feof($manifest)) throw new RuntimeException('El manifiesto está incompleto.');
            } finally {
                fclose($manifest);
            }
            [, $digest] = $this->hashEntry($zip, 'database.dump');
            if ($zip->numFiles !== count($seen) + 3 || count($seen) !== $metadata['files']
                || $bytes !== $metadata['bytes'] || !hash_equals($metadata['database_sha256'], $digest)) {
                throw new RuntimeException('El manifiesto del respaldo no coincide.');
            }
            return $metadata;
        } finally {
            $zip->close();
        }
    }

    private function hashEntry(ZipArchive $zip, string $name): array
    {
        $stream = $zip->getStream($name);
        if (!is_resource($stream)) throw new RuntimeException('El respaldo está incompleto.');
        try {
            $hash = hash_init('sha256');
            return [hash_update_stream($hash, $stream), hash_final($hash)];
        } finally {
            fclose($stream);
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
