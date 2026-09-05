<?php

namespace App\Jobs\System;

use App\Models\System\JobBatchingTray;
use App\Traits\System\DownloadTrayTrait;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;
use ZanySoft\Zip\Facades\Zip;

/**
 * Backup completo de un cliente: vuelca la base y empaqueta el resultado.
 *
 * Los dos pasos van en un unico job y no en una cadena a proposito. Una cadena
 * dentro de un lote cuenta sus jobs en pending_jobs, pero si el primero falla el
 * segundo nunca se despacha y ese pendiente no se salda nunca: el lote queda sin
 * cerrar y el finally() no corre (ver UpdatedBatchJobCounts::allJobsHaveRanExactlyOnce).
 * Con un job por cliente, un cliente que falla no deja el backup global colgado.
 *
 * Segun el modo, el zip que produce es el entregable o un insumo:
 *  - individual: cierra la bandeja y queda listo para descargar.
 *  - batching (backup global): se anota en job_batching_trays y el zip final con
 *    todos los clientes lo arma AssembleGlobalBackup al cerrar el lote.
 */
class GenerateClientBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable, DownloadTrayTrait;

    /**
     * Un backup de un cliente grande puede tardar horas.
     */
    public $timeout = 0;

    /**
     * Sin reintentos: reintentar sobre un dump a medio escribir deja archivos
     * basura y no arregla la causa (credenciales, disco, binario ausente).
     */
    public $tries = 1;

    public $download_tray_id;
    public $database;

    /**
     * Marca de tiempo fijada al encolar, no calculada dentro del job: es lo que
     * mantiene coherentes los nombres del .sql.gz y del zip aunque el proceso
     * arranque horas despues o cruce la medianoche.
     */
    public $stamp;

    public $includes_files;
    public $batching;
    public $client_name;

    protected $host;
    protected $username;
    protected $password;

    public function __construct($download_tray_id, $database, $stamp, $includes_files = true, $batching = false, $client_name = null)
    {
        $this->download_tray_id = $download_tray_id;
        $this->database = $database;
        $this->stamp = $stamp;
        $this->includes_files = $includes_files;
        $this->batching = $batching;
        $this->client_name = $client_name;

        $this->onQueue(config('backup.queue'));
    }

    public function handle()
    {
        if ($this->batch() && $this->batch()->cancelled()) return;

        $tray = $this->findSystemDownloadTray($this->download_tray_id);
        if (!$tray) return;

        $this->startSystemDownloadTray($tray);
        $this->initDbConfig();
        $this->assertFreeSpace();

        $sql_path = $this->dumpDatabase();
        [$file_name, $zip_path] = $this->packZip($sql_path);

        // El .sql.gz ya viaja dentro del zip; conservarlo duplica el espacio ocupado.
        @unlink($sql_path);

        $this->batching
            ? $this->registerInBatch($file_name, filesize($zip_path))
            : $this->finishTray($tray, $file_name, filesize($zip_path));

        Log::info("Backup {$this->database} generado en {$zip_path}");
    }

    /**
     * Se evita el shell por completo: sin pipe a gzip no hay que lidiar con que la
     * tuberia devuelva el codigo de salida de gzip y no el de mysqldump, que es
     * como un dump truncado termina pareciendo exitoso. La compresion se hace
     * sobre el stream, con memoria constante.
     */
    protected function dumpDatabase()
    {
        $directory = storage_path('app/backups/' . $this->database);
        if (!is_dir($directory)) mkdir($directory, 0775, true);

        $path = $directory . '/' . $this->database . '_' . $this->stamp . '.sql.gz';

        $command = array_merge(
            [config('backup.mysqldump')],
            config('backup.dump_options'),
            ['-h', $this->host, '-u', $this->username, $this->database]
        );

        $process = new Process($command);
        $process->setTimeout(null);
        $process->setEnv(['MYSQL_PWD' => (string) $this->password]);

        $handle = gzopen($path, 'wb6');
        if ($handle === false) throw new Exception("No se pudo escribir en {$path}");

        $tail = '';
        $stderr = '';

        try {
            $process->run(function ($type, $buffer) use ($handle, &$tail, &$stderr) {
                if ($type === Process::ERR) {
                    $stderr .= $buffer;
                    return;
                }
                gzwrite($handle, $buffer);
                // Solo se conserva el final del volcado para verificarlo despues.
                $tail = mb_substr($tail . $buffer, -200);
            });
        } finally {
            gzclose($handle);
        }

        if (!$process->isSuccessful()) {
            @unlink($path);
            throw new Exception("mysqldump fallo para {$this->database}: " . trim($stderr));
        }

        // mysqldump cierra el archivo con "-- Dump completed on ...". Si no esta,
        // el volcado se corto por la mitad aunque el proceso haya salido con 0.
        if (!str_contains($tail, 'Dump completed')) {
            @unlink($path);
            throw new Exception("El dump de {$this->database} quedo incompleto");
        }

        return $path;
    }

    protected function packZip($sql_path)
    {
        // Los parciales del backup global van aparte: junto a los zip finales
        // quedarian a la vista en la carpeta de descargas y mostRecent() podria
        // tomar uno a medio armar como "ultimo backup".
        $directory = $this->batching
            ? storage_path('app/backups/zip/parciales')
            : storage_path('app/backups/zip');

        if (!is_dir($directory)) mkdir($directory, 0775, true);

        $file_name = $this->database . '_' . $this->stamp . '.zip';
        $zip_path = $directory . '/' . $file_name;

        $zip = Zip::create($zip_path, true);
        $zip->add($sql_path);

        if ($this->includes_files) {
            $tenant_files = storage_path('app/tenancy/tenants/' . $this->database);

            if (is_dir($tenant_files)) {
                $zip->add($tenant_files . '/', true);
            } else {
                // No es un fallo: hay clientes sin archivos generados todavia.
                Log::warning("El cliente {$this->database} no tiene carpeta de archivos en storage");
            }
        }

        $zip->close();

        if (!file_exists($zip_path)) {
            throw new Exception("No se pudo generar el zip de {$this->database}");
        }

        return [$file_name, $zip_path];
    }

    /**
     * Deja constancia del zip de este cliente para que el armado final lo encuentre.
     * Las filas se borran cuando AssembleGlobalBackup termina.
     */
    protected function registerInBatch($file_name, $size)
    {
        JobBatchingTray::create([
            'job_batch_id' => $this->batchId,
            'generated_filename' => $file_name,
            'payload' => [
                'database' => $this->database,
                'client_name' => $this->client_name,
                'size' => $size,
            ],
        ]);
    }

    protected function finishTray($tray, $file_name, $size)
    {
        $tray->expires_at = now()->addDays((int) config('backup.retain_days'));

        $this->finishSystemDownloadTray($tray, $file_name, 'backups/zip/' . $file_name, $size);
    }

    protected function assertFreeSpace()
    {
        $free_mb = @disk_free_space(storage_path()) / 1024 / 1024;
        $min = (int) config('backup.min_free_space_mb');

        if ($free_mb && $free_mb < $min) {
            throw new Exception(
                'Espacio en disco insuficiente para generar el backup: quedan ' .
                round($free_mb) . ' MB y se exigen al menos ' . $min . ' MB'
            );
        }
    }

    protected function initDbConfig()
    {
        $config = config('database.connections.' . config('tenancy.db.system-connection-name', 'system'));

        $this->host = Arr::first(Arr::wrap($config['host'] ?? ''));
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    /**
     * En el backup global la bandeja es una sola y compartida: que falle un cliente
     * no puede marcarla como fallida. La falla se anota y AssembleGlobalBackup la
     * reporta junto al resto.
     */
    public function failed(Throwable $e)
    {
        Log::error("Backup {$this->database} fallo: {$e->getMessage()}");

        if ($this->batching) {
            JobBatchingTray::create([
                'job_batch_id' => $this->batchId,
                'generated_filename' => '',
                'payload' => [
                    'failed' => true,
                    'database' => $this->database,
                    'client_name' => $this->client_name,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ],
            ]);
            return;
        }

        $tray = $this->findSystemDownloadTray($this->download_tray_id);
        if ($tray) $this->failSystemDownloadTray($tray, $e->getMessage());
    }
}
