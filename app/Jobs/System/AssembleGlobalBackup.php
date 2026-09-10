<?php

namespace App\Jobs\System;

use App\Models\System\JobBatchingTray;
use App\Traits\System\DownloadTrayTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Cierre del backup global: junta los zip de cada cliente en un unico archivo y
 * recien ahi da por terminada la bandeja.
 *
 * Se dispara desde el finally() del lote, cuando ya no queda ninguna cadena
 * corriendo. Los nombres de los zip parciales salen de job_batching_trays, que
 * es la tabla que el proyecto ya usa para este mismo patron en los reportes.
 */
class AssembleGlobalBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DownloadTrayTrait;

    public $timeout = 0;
    public $tries = 1;

    public $download_tray_id;
    public $batch_id;
    public $stamp;

    public function __construct($download_tray_id, $batch_id, $stamp)
    {
        $this->download_tray_id = $download_tray_id;
        $this->batch_id = $batch_id;
        $this->stamp = $stamp;

        $this->onQueue(config('backup.queue'));
    }

    public function handle()
    {
        $tray = $this->findSystemDownloadTray($this->download_tray_id);

        if (!$tray) {
            $this->clearBatchRows();
            return;
        }

        $rows = JobBatchingTray::where('job_batch_id', $this->batch_id)->get();

        $generated = $rows->filter(fn ($row) => $row->generated_filename !== '');
        $failed = $rows->filter(fn ($row) => data_get($row->payload, 'failed') === true);

        if ($generated->isEmpty()) {
            $this->clearBatchRows();
            $this->failSystemDownloadTray($tray, $this->failureSummary($failed) ?: 'No se genero ningun backup.');
            return;
        }

        $file_name = 'backup_global_' . $this->stamp . '.zip';
        $zip_path = storage_path('app/backups/zip/' . $file_name);

        $this->buildZip($zip_path, $generated, $failed);

        // Los parciales solo se borran despues de cerrar el zip: ZipArchive los
        // necesita en disco hasta el close().
        foreach ($generated as $row) {
            @unlink($this->partialsPath() . '/' . $row->generated_filename);
        }

        $this->clearBatchRows();

        $tray->expires_at = now()->addDays((int) config('backup.retain_days'));

        $this->finishSystemDownloadTray($tray, $file_name, 'backups/zip/' . $file_name, filesize($zip_path));

        Log::info(
            "Backup global armado en {$zip_path} con {$generated->count()} clientes"
            . ($failed->count() ? " y {$failed->count()} fallidos" : '')
        );
    }

    protected function partialsPath()
    {
        return storage_path('app/backups/zip/parciales');
    }

    protected function buildZip($zip_path, $generated, $failed)
    {
        $zip_directory = $this->partialsPath();

        $zip = new ZipArchive;
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("No se pudo crear el zip global en {$zip_path}");
        }

        foreach ($generated as $row) {
            $partial = $zip_directory . '/' . $row->generated_filename;

            if (!file_exists($partial)) {
                Log::warning("Falta el parcial {$row->generated_filename} al armar el backup global");
                continue;
            }

            $zip->addFile($partial, $row->generated_filename);

            // Los parciales ya estan comprimidos: volver a comprimirlos solo gasta
            // CPU y no baja el tamano.
            $zip->setCompressionName($row->generated_filename, ZipArchive::CM_STORE);
        }

        if ($failed->count()) {
            $zip->addFromString('_clientes_fallidos.txt', $this->failureSummary($failed));
        }

        if (!$zip->close()) {
            throw new Exception("No se pudo cerrar el zip global en {$zip_path}");
        }
    }

    protected function failureSummary($failed)
    {
        if ($failed->isEmpty()) return '';

        $lines = $failed->map(function ($row) {
            $name = data_get($row->payload, 'client_name') ?: data_get($row->payload, 'database');
            return $name . ': ' . data_get($row->payload, 'error');
        });

        return "Clientes que no pudieron respaldarse:\n\n" . $lines->implode("\n");
    }

    protected function clearBatchRows()
    {
        JobBatchingTray::where('job_batch_id', $this->batch_id)->delete();
    }

    public function failed(Throwable $e)
    {
        Log::error("Armado del backup global fallo: {$e->getMessage()}");

        $tray = $this->findSystemDownloadTray($this->download_tray_id);
        if ($tray) $this->failSystemDownloadTray($tray, $e->getMessage());
    }
}
