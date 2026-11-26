<?php

namespace App\Console\Commands;

use App\Models\Storage\StorageObject;
use App\Services\Storage\StorageBackup;
use App\Services\Storage\StorageLock;
use App\Services\Storage\TenantContext;
use App\Services\Storage\TenantDisk;
use App\Services\Storage\TenantStorage;
use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

final class RestoreStorageObjects extends Command
{
    protected $signature = 'sab:restore-storage {tenant} {archive} {--apply : Copiar al bucket de recuperación}';
    protected $description = 'Verifica y restaura los objetos de un tenant cuyo catálogo ya fue restaurado';

    public function handle(StorageBackup $backup, TenantContext $context, TenantStorage $storage, StorageLock $lock): int
    {
        $scope = $this->argument('tenant');
        $uuid = $scope === '_system' ? 'all' : TenantStorage::validateUuid($scope);
        $path = $this->argument('archive');
        $metadata = $backup->verifyLocal($path);
        if (($metadata['tenant_uuid'] ?? null) !== $uuid || empty($metadata['includes_files'])) {
            throw new RuntimeException('El respaldo no contiene objetos de la empresa indicada.');
        }
        $apply = $this->option('apply');
        if ($apply && !config('production.recovery_enabled')) {
            throw new RuntimeException('La restauración requiere un entorno de recuperación habilitado.');
        }
        if ($scope === '_system') {
            return $lock->run('storage:restore-system', fn () => $this->restoreSystem($path, $storage->systemMedia(), $apply));
        }
        $lock->run('tenant:'.$uuid, fn () => $context->run($uuid, function () use ($path, $uuid, $storage, $apply) {
            $disk = $storage->physicalForUuid($uuid);
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('No se pudo abrir el respaldo verificado.');
            }
            $manifest = $zip->getStream('manifest.ndjson');
            try {
                $count = 0;
                while (($line = fgets($manifest)) !== false) {
                    $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    $object = StorageObject::where('object_key', $entry['path'])->firstOrFail();
                    if ((int) $object->size !== $entry['size'] || !hash_equals($object->sha256, $entry['sha256'])) {
                        throw new RuntimeException('El catálogo restaurado no corresponde al respaldo.');
                    }
                    if ($apply) {
                        $this->restoreObject($disk, $zip, 'files/'.$entry['path'], $entry['path'], $entry, $object->mime);
                    }
                    $count++;
                }
                if (!feof($manifest) || StorageObject::count() !== $count) {
                    throw new RuntimeException('El respaldo no cubre el catálogo completo.');
                }
                $this->info($count.' objetos comprobados.');
            } finally {
                fclose($manifest);
                $zip->close();
            }
        }));
        return self::SUCCESS;
    }

    private function restoreSystem(string $path, TenantDisk $disk, bool $apply): int
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('No se pudo abrir el respaldo verificado.');
        }
        $manifest = $zip->getStream('manifest.ndjson');
        $prefix = '_system/media/';
        try {
            while (($line = fgets($manifest)) !== false) {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if ($apply && str_starts_with($entry['path'], $prefix)) {
                    $this->restoreObject($disk, $zip, 'files/'.$entry['path'], substr($entry['path'], strlen($prefix)), $entry);
                }
            }
            if (!feof($manifest)) {
                throw new RuntimeException('No se pudo leer el manifiesto.');
            }
        } finally {
            fclose($manifest);
            $zip->close();
        }
        return self::SUCCESS;
    }

    private function restoreObject(TenantDisk $disk, ZipArchive $zip, string $entryName, string $key, array $entry, ?string $mime = null): void
    {
        if (!$disk->exists($key)) {
            $stream = $zip->getStream($entryName);
            try {
                $disk->put($key, $stream, $mime ? ['ContentType' => $mime] : []);
            } finally {
                fclose($stream);
            }
        }

        $remote = $disk->readStream($key);
        try {
            $hash = hash_init('sha256');
            $size = hash_update_stream($hash, $remote);
            if ($size !== $entry['size'] || !hash_equals($entry['sha256'], hash_final($hash))) {
                throw new RuntimeException('El objeto destino no coincide con el respaldo.');
            }
        } finally {
            fclose($remote);
        }
    }
}
