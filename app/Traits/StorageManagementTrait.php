<?php

namespace App\Traits;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait StorageManagementTrait
{

    /**
     * Carpeta que contiene el storage de todos los tenants
     */
    protected function getTenantsPath(): string
    {
        return storage_path('app/tenancy/tenants');
    }

    /**
     * Ruta del storage de un tenant
     */
    protected function getTenantPath(string $uuid): string
    {
        return $this->getTenantsPath() . DIRECTORY_SEPARATOR . $uuid;
    }

    /**
     * Ruta del storage de un tenant, validada contra los registros existentes
     *
     * El uuid llega del request: sin comprobarlo contra la tabla un valor como
     * ../../ apuntaria fuera de la carpeta de tenants
     */
    protected function getValidatedTenantPath(string $uuid): string
    {
        abort_unless(DB::table('websites')->where('uuid', $uuid)->exists(), 404, 'La empresa no existe');

        return $this->getTenantPath($uuid);
    }

    /**
     * Ruta de una carpeta de paquete dentro de un tenant
     */
    protected function getPackagePath(string $tenant_path, string $package): string
    {
        return $tenant_path . DIRECTORY_SEPARATOR . $package;
    }

    /**
     * Peso en bytes de un directorio, recorriendo sus archivos
     *
     * @return int 0 cuando la carpeta no existe, un tenant recien creado aun no tiene archivos
     */
    protected function getDirectorySize(string $path): int
    {
        return $this->eachFile($path, function ($file, &$size) {
            $size += $file->getSize();
        });
    }

    /**
     * Cantidad de archivos de un directorio
     */
    protected function countFiles(string $path): int
    {
        return $this->eachFile($path, function ($file, &$count) {
            $count++;
        });
    }

    /**
     * Recorre los archivos de un directorio acumulando un total
     *
     * Medir y eliminar necesitan el mismo recorrido, la unica diferencia es
     * que hace el callback con cada archivo
     *
     * @return int el acumulado que haya construido el callback
     */
    protected function eachFile(string $path, callable $callback): int
    {
        if (!is_dir($path)) return 0;

        $total = 0;

        // SKIP_DOTS evita que el recorrido entre en . y ..
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) $callback($file, $total);
        }

        return $total;
    }

    /**
     * Elimina los archivos de una carpeta, dejando el directorio en su sitio
     *
     * Las carpetas se conservan para que la aplicacion siga escribiendo sin
     * tener que recrearlas
     *
     * @return array{deleted_files:int, freed_space:int} solo cuenta lo que realmente se borro
     */
    protected function deleteFilesFromPath(string $path): array
    {
        $deleted_files = 0;

        $freed_space = $this->eachFile($path, function ($file, &$size) use (&$deleted_files) {

            // el tamaño se lee antes de borrar, despues el archivo ya no responde
            $file_size = $file->getSize();

            // unlink devuelve false ante permisos o archivos en uso, se omite y se sigue
            if (@unlink($file->getPathname())) {
                $deleted_files++;
                $size += $file_size;
            }

        });

        return [
            'deleted_files' => $deleted_files,
            'freed_space' => $freed_space,
        ];
    }

    /**
     * Elimina una carpeta completa con todo su contenido
     *
     * A diferencia de deleteFilesFromPath, aca desaparece tambien el directorio.
     * Pensado para el tenant que ya no existe: hyn no borra su carpeta porque
     * auto-delete-tenant-directory esta en false
     *
     * @return array{deleted_files:int, freed_space:int, removed:bool} removed indica
     *         que la carpeta ya no esta, no que la haya borrado este metodo: con
     *         auto-delete-tenant-directory activo hyn pudo haberla borrado antes.
     *         Los totales son lo medido antes menos lo que haya quedado, asi un
     *         borrado parcial no reporta de mas
     */
    protected function deleteDirectory(string $path): array
    {
        if (!is_dir($path)) {
            return ['deleted_files' => 0, 'freed_space' => 0, 'removed' => true];
        }

        $files_before = $this->countFiles($path);
        $size_before = $this->getDirectorySize($path);

        File::deleteDirectory($path);

        // si algo no se pudo borrar (permisos, archivo en uso) sigue estando aca
        $files_after = $this->countFiles($path);
        $size_after = $this->getDirectorySize($path);

        return [
            'deleted_files' => $files_before - $files_after,
            'freed_space' => $size_before - $size_after,
            'removed' => !is_dir($path),
        ];
    }

    /**
     * Bytes a la unidad legible mas cercana
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exponent = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / pow(1024, $exponent), 2) . ' ' . $units[$exponent];
    }

}
