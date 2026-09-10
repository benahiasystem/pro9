<?php

namespace App\Console\Commands;

use App\Models\System\DownloadTray;
use App\Models\System\JobBatchingTray;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Limpieza de la bandeja de backups del central.
 *
 * Sin esto los zip se acumulan indefinidamente en storage: es lo que pasaba con
 * el flujo anterior, donde nada borraba los .sql viejos.
 */
class PruneBackups extends Command
{
    protected $signature = 'backup:prune {--dry-run : Muestra lo que se borraria sin tocar nada}';

    protected $description = 'Elimina backups vencidos, destraba procesos colgados y borra parciales huerfanos';

    protected $dry_run = false;

    public function handle()
    {
        $this->dry_run = $this->option('dry-run');

        if ($this->dry_run) $this->warn('Modo simulacion: no se borra nada.');

        $expired = $this->pruneExpired();
        $stale = $this->releaseStale();
        $failed = $this->pruneOldFailed();
        $orphans = $this->pruneOrphanPartials();

        $summary = "Backups vencidos: {$expired} | Colgados liberados: {$stale} | "
                 . "Fallidos antiguos: {$failed} | Parciales huerfanos: {$orphans}";

        $this->info($summary);
        if (!$this->dry_run) Log::info("backup:prune -> {$summary}");

        return 0;
    }

    /**
     * Backups que ya pasaron su fecha de vencimiento: se borra el archivo y la fila.
     * El detalle de backup_tray_details se va solo por la FK en cascada.
     */
    protected function pruneExpired()
    {
        $records = DownloadTray::module(DownloadTray::MODULE_BACKUP)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($records as $tray) {
            $this->line("  vencido #{$tray->id} {$tray->file_name}");

            if (!$this->dry_run) {
                $this->deleteFile($tray);
                $tray->delete();
            }

            $count++;
        }

        return $count;
    }

    /**
     * Procesos que quedaron en curso mas tiempo del razonable.
     *
     * Se marcan como fallidos en vez de borrarlos: la fila explica al usuario que
     * paso, y ademas libera el control de concurrencia, que de otro modo no deja
     * volver a encolar ese cliente nunca mas.
     */
    protected function releaseStale()
    {
        $limit = now()->subHours((int) config('backup.stale_hours'));

        $records = DownloadTray::module(DownloadTray::MODULE_BACKUP)
            ->inProgress()
            ->where('created_at', '<=', $limit)
            ->get();

        $count = 0;

        foreach ($records as $tray) {
            $this->line("  colgado #{$tray->id} desde {$tray->created_at}");

            if (!$this->dry_run) {
                $tray->status = DownloadTray::STATUS_FAILED;
                $tray->error_message = 'El proceso quedo interrumpido y se dio por vencido tras '
                                     . config('backup.stale_hours') . ' horas.';
                $tray->date_end = now();
                $tray->save();
            }

            $count++;
        }

        return $count;
    }

    /**
     * Los fallidos no tienen archivo ni expires_at, asi que se limpian por antiguedad.
     */
    protected function pruneOldFailed()
    {
        $limit = now()->subDays((int) config('backup.retain_days'));

        $records = DownloadTray::module(DownloadTray::MODULE_BACKUP)
            ->where('status', DownloadTray::STATUS_FAILED)
            ->where('created_at', '<=', $limit)
            ->get();

        $count = 0;

        foreach ($records as $tray) {
            $this->line("  fallido antiguo #{$tray->id}");

            if (!$this->dry_run) {
                $this->deleteFile($tray);
                $tray->delete();
            }

            $count++;
        }

        return $count;
    }

    /**
     * Parciales de un backup global que nunca llego a armarse (worker caido a mitad
     * del lote, por ejemplo). Se borra el archivo y su anotacion.
     */
    protected function pruneOrphanPartials()
    {
        $directory = storage_path('app/backups/zip/parciales');
        if (!is_dir($directory)) return 0;

        $limit = now()->subHours((int) config('backup.orphan_partials_hours'));
        $count = 0;

        foreach (glob($directory . '/*.zip') as $file) {
            if (filemtime($file) > $limit->getTimestamp()) continue;

            $name = basename($file);
            $this->line("  parcial huerfano {$name}");

            if (!$this->dry_run) @unlink($file);

            $count++;
        }

        // Anotaciones sueltas de lotes que nunca cerraron, incluidas las de clientes
        // fallidos, que no dejan archivo. El filtro por payload->database aisla las
        // filas del backup: las de los reportes no traen esa clave.
        if (!$this->dry_run) {
            JobBatchingTray::where('created_at', '<=', $limit)
                ->whereNotNull('payload->database')
                ->delete();
        }

        return $count;
    }

    protected function deleteFile(DownloadTray $tray)
    {
        if (!$tray->path) return;

        try {
            $disk = Storage::disk($tray->disk);
            if ($disk->exists($tray->path)) $disk->delete($tray->path);
        } catch (Throwable $e) {
            // Que no exista el disco o el archivo no debe frenar la limpieza del resto.
            Log::warning("backup:prune no pudo borrar {$tray->path}: {$e->getMessage()}");
        }
    }
}
