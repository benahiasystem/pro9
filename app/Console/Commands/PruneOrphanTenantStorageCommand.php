<?php

namespace App\Console\Commands;

use App\Traits\StorageManagementTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Elimina las carpetas de tenants que ya no tienen un website registrado.
 *
 * hyn no borra la carpeta al eliminar un tenant porque auto-delete-tenant-directory
 * esta en false, asi que storage/app/tenancy/tenants va acumulando carpetas de
 * empresas que ya no existen.
 *
 * Ver App\Traits\StorageManagementTrait, que resuelve las rutas y hace el borrado.
 */
class PruneOrphanTenantStorageCommand extends Command
{
    use StorageManagementTrait;

    protected $signature = 'storage:prune-tenants
                            {--dry-run : Solo lista las carpetas huérfanas, sin borrar}
                            {--force : No pedir confirmación antes de borrar}';

    protected $description = 'Elimina el storage de los tenants que ya no existen en la tabla websites';

    public function handle()
    {
        $tenants_path = $this->getTenantsPath();

        if (!is_dir($tenants_path)) {
            $this->error("No existe la carpeta {$tenants_path}");
            return self::FAILURE;
        }

        $registered = DB::table('websites')->pluck('uuid');

        // una consulta vacia por un fallo de conexion dejaria a todas las carpetas
        // como huerfanas: sin websites no hay con que comparar, se corta aca
        if ($registered->isEmpty()) {
            $this->error('La tabla websites no devolvió registros. Se aborta para no borrar todo.');
            return self::FAILURE;
        }

        $orphans = $this->findOrphans($tenants_path, $registered);

        $this->reportMissingDirectories($tenants_path, $registered);

        if ($orphans->isEmpty()) {
            $this->info('No hay carpetas huérfanas: todas corresponden a un website registrado.');
            return self::SUCCESS;
        }

        $this->listOrphans($orphans);

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No se eliminó nada.');
            return self::SUCCESS;
        }

        // el borrado es irreversible: en modo interactivo se confirma siempre,
        // y desde un cron hay que pasar --force a proposito
        if (!$this->option('force') && !$this->confirm("¿Eliminar {$orphans->count()} carpeta(s)?", false)) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        return $this->deleteOrphans($orphans);
    }

    /**
     * Carpetas de tenants sin website asociado
     *
     * @param \Illuminate\Support\Collection $registered uuids de la tabla websites
     * @return \Illuminate\Support\Collection de ['uuid', 'path', 'files', 'space']
     */
    private function findOrphans(string $tenants_path, $registered)
    {
        // flip para no recorrer la lista completa por cada carpeta
        $lookup = $registered->flip();

        return collect(File::directories($tenants_path))
            ->reject(function ($path) use ($tenants_path) {
                // una carpeta enlazada podria apuntar fuera del arbol de tenants
                return dirname((string) realpath($path)) !== realpath($tenants_path);
            })
            ->map(function ($path) {
                return [
                    'uuid' => basename($path),
                    'path' => $path,
                    'files' => $this->countFiles($path),
                    'space' => $this->getDirectorySize($path),
                ];
            })
            ->reject(function ($orphan) use ($lookup) {
                return $lookup->has($orphan['uuid']);
            })
            ->sortByDesc('space')
            ->values();
    }

    /**
     * Websites registrados que todavia no tienen carpeta
     *
     * No es un error —un tenant recien creado aun no escribio nada— pero se
     * informa porque ayuda a distinguir un storage incompleto de uno sucio
     */
    private function reportMissingDirectories(string $tenants_path, $registered): void
    {
        $missing = $registered->reject(function ($uuid) use ($tenants_path) {
            return is_dir($tenants_path . DIRECTORY_SEPARATOR . $uuid);
        });

        if ($missing->isNotEmpty()) {
            $this->line('Websites sin carpeta (no se toca nada): ' . $missing->implode(', '));
        }
    }

    private function listOrphans($orphans): void
    {
        $this->warn("Carpetas sin website registrado: {$orphans->count()}");

        $this->table(
            ['UUID', 'Archivos', 'Tamaño'],
            $orphans->map(function ($orphan) {
                return [
                    $orphan['uuid'],
                    $orphan['files'],
                    $this->formatBytes($orphan['space']),
                ];
            })
        );

        $this->line('Total: ' . $this->formatBytes($orphans->sum('space')));
    }

    /**
     * @return int el codigo de salida del comando
     */
    private function deleteOrphans($orphans): int
    {
        $deleted_files = 0;
        $freed_space = 0;
        $failed = 0;

        foreach ($orphans as $orphan) {

            $result = $this->deleteDirectory($orphan['path']);

            $deleted_files += $result['deleted_files'];
            $freed_space += $result['freed_space'];

            if ($result['removed']) {
                $this->info(sprintf(
                    '[%s] eliminada — %d archivo(s), %s',
                    $orphan['uuid'],
                    $result['deleted_files'],
                    $this->formatBytes($result['freed_space'])
                ));
                continue;
            }

            // deleteDirectory no lanza: si quedaron archivos por permisos o por
            // estar en uso, la carpeta sigue ahi y hay que reportarlo
            $failed++;
            $this->error("[{$orphan['uuid']}] no se pudo eliminar por completo, revisar permisos");

        }

        $this->line(sprintf(
            'Eliminadas %d de %d carpeta(s), %d archivo(s), %s liberados',
            $orphans->count() - $failed,
            $orphans->count(),
            $deleted_files,
            $this->formatBytes($freed_space)
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
