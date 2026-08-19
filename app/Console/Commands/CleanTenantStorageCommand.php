<?php

namespace App\Console\Commands;

use App\Http\Controllers\System\StorageManagementController;
use App\Models\System\StorageCleanupConfiguration;
use App\Traits\StorageManagementTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Ejecuta las programaciones de limpieza registradas por el superadmin.
 *
 * Corre sobre la conexion system: la configuracion vive ahi y el borrado es de
 * archivos, no necesita entrar a la base de cada tenant.
 */
class CleanTenantStorageCommand extends Command
{
    use StorageManagementTrait;

    protected $signature = 'storage:clean
                            {--dry-run : Solo muestra qué se eliminaría, sin borrar}
                            {--id= : Ejecutar solo la programación indicada}
                            {--force : Ignora la hora y la última corrida}';

    protected $description = 'Ejecuta las programaciones de limpieza de archivos por empresa';

    public function handle()
    {
        $dry_run = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // ########## INICIO CAMBIO IGV A IVA
        // El timezone de la app es America/Caracas; la hora configurada se compara
        // ######### FIN CAMBIO IGV A IVA
        // directamente contra esta
        $now = Carbon::now();

        $query = StorageCleanupConfiguration::active()->with('website');

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $configurations = $query->get();

        if ($dry_run) {
            $this->warn('[DRY-RUN] No se eliminará ningún archivo.');
        }

        $executed = 0;
        $total_files = 0;
        $total_space = 0;

        foreach ($configurations as $configuration) {

            $due_at = $this->resolveDueAt($configuration, $now);

            if (!$force && !$this->isDue($configuration, $due_at, $now)) continue;

            $uuid = optional($configuration->website)->uuid;

            if (!$uuid) {
                $this->warn("[#{$configuration->id}] Sin empresa asociada — omitido");
                continue;
            }

            $result = $this->runConfiguration($configuration, $uuid, $dry_run);

            $executed++;
            $total_files += $result['deleted_files'];
            $total_space += $result['freed_space'];

            $this->info(sprintf(
                '[%s] %d archivo(s), %s',
                $uuid,
                $result['deleted_files'],
                $this->formatBytes($result['freed_space'])
            ));

            // la marca solo avanza si realmente se borro, asi un dry-run no
            // saltea la corrida real
            if (!$dry_run) {
                $configuration->update(['last_run_at' => $now]);
            }

        }

        $this->line(sprintf(
            '%s: %d programación(es) ejecutada(s) de %d activa(s), %d archivo(s), %s',
            $dry_run ? 'Simulacion' : 'Limpieza',
            $executed,
            $configurations->count(),
            $total_files,
            $this->formatBytes($total_space)
        ));

        return self::SUCCESS;
    }

    /**
     * Momento en que corresponde ejecutar hoy, o null si hoy no toca
     */
    private function resolveDueAt(StorageCleanupConfiguration $configuration, Carbon $now): ?Carbon
    {
        switch ($configuration->frequency) {

            case StorageCleanupConfiguration::FREQUENCY_WEEKLY:
                // dayOfWeek de Carbon usa 0 = domingo, igual que la configuracion
                if ((int) $now->dayOfWeek !== (int) $configuration->day_of_week) return null;
                break;

            case StorageCleanupConfiguration::FREQUENCY_MONTHLY:
                if ((int) $now->day !== (int) $configuration->day_of_month) return null;
                break;

        }

        return Carbon::parse("{$now->toDateString()} {$configuration->time}");
    }

    /**
     * Si la programacion tiene que correr en este momento
     *
     * Comparar contra due_at en vez de contra la hora exacta permite recuperar
     * una corrida perdida: si el scheduler estuvo caido a las 03:00, al volver
     * a las 05:00 igual se ejecuta
     */
    private function isDue(StorageCleanupConfiguration $configuration, ?Carbon $due_at, Carbon $now): bool
    {
        if ($due_at === null) return false;

        if ($now->lt($due_at)) return false;

        return !($configuration->last_run_at && $configuration->last_run_at->gte($due_at));
    }

    /**
     * Limpia las carpetas de una programacion
     *
     * @return array{deleted_files:int, freed_space:int}
     */
    private function runConfiguration(StorageCleanupConfiguration $configuration, string $uuid, bool $dry_run): array
    {
        $tenant_path = $this->getTenantPath($uuid);

        // el listado se filtra de nuevo contra la lista blanca: una fila vieja
        // podria tener una carpeta que ya no se considera borrable
        $packages = array_intersect(
            $configuration->packages ?: [],
            StorageManagementController::PACKAGE_DELETE
        );

        $deleted_files = 0;
        $freed_space = 0;

        foreach ($packages as $package) {

            $path = $this->getPackagePath($tenant_path, $package);

            if ($dry_run) {
                $deleted_files += $this->countFiles($path);
                $freed_space += $this->getDirectorySize($path);
                continue;
            }

            $result = $this->deleteFilesFromPath($path);

            $deleted_files += $result['deleted_files'];
            $freed_space += $result['freed_space'];

        }

        return [
            'deleted_files' => $deleted_files,
            'freed_space' => $freed_space,
        ];
    }
}
