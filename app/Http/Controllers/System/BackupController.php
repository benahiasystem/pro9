<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Jobs\System\AssembleGlobalBackup;
use App\Jobs\System\GenerateClientBackup;
use App\Models\System\BackupTrayDetail;
use App\Models\System\Client;
use App\Models\System\DownloadTray;
use App\Traits\BackupTrait;
use Exception;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{

    use BackupTrait;

    public function index() {

        $avail = new Process(['df', '-m', '-h', '--output=avail', '/']);
        $avail->run();
        $disc_used = $avail->getOutput();

        $df = Process::fromShellCommandline('du -sh '.storage_path().' | cut -f1');
        $df->run();
        $storage_size = trim($df->getOutput());

        $most_recent = $this->mostRecent();

        $clients = Client::without(['hostname','plan'])
            ->select('hostname_id', 'name')
            ->get();

        return view('system.backup.index')->with('disc_used', $disc_used)->with('storage_size', $storage_size)->with('last_zip', $most_recent)->with('clients', $clients);
    }

    /**
     * Encola la generacion del backup y devuelve de inmediato.
     *
     * Antes esto corria mysqldump dentro del request, lo que con clientes grandes
     * terminaba en 504 aunque el proceso siguiera vivo por detras.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:individual,todos',
            'hostname_id' => 'nullable|required_if:type,individual',
            'includes_files' => 'nullable|boolean',
        ]);

        $includes_files = $request->boolean('includes_files', true);

        return $request->type === BackupTrayDetail::SCOPE_INDIVIDUAL
            ? $this->generateIndividual($request, $includes_files)
            : $this->generateAll($includes_files);
    }

    protected function generateIndividual(Request $request, $includes_files)
    {
        $hostname = Hostname::findOrFail($request->hostname_id);
        $website = Website::findOrFail($hostname->website_id);

        if ($this->hasPendingBackup($hostname->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un backup en curso para este cliente.',
            ], 409);
        }

        $client = Client::where('hostname_id', $hostname->id)->first();

        $tray = $this->createTray(
            BackupTrayDetail::SCOPE_INDIVIDUAL,
            $hostname->id,
            $client ? $client->name : $hostname->fqdn,
            $website->uuid,
            $includes_files
        );

        GenerateClientBackup::dispatch(
            $tray->id,
            $website->uuid,
            now()->format('Ymd_His'),
            $includes_files
        );

        return response()->json([
            'success' => true,
            'message' => 'El backup se esta generando, lo vera en la bandeja de descargas.',
            'download_tray_id' => $tray->id,
        ], 202);
    }

    /**
     * Backup global: un unico registro de bandeja y un unico zip que adentro lleva
     * el zip de cada cliente.
     *
     * Cada cadena genera el parcial de su cliente y lo anota en job_batching_trays;
     * cuando el lote termina, AssembleGlobalBackup los junta y cierra la bandeja.
     */
    protected function generateAll($includes_files)
    {
        if ($this->hasPendingGlobalBackup()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya hay un backup general en curso.',
            ], 409);
        }

        $stamp = now()->format('Ymd_His');

        $tray = $this->createTray(
            BackupTrayDetail::SCOPE_ALL,
            null,
            'Todos los clientes',
            null,
            $includes_files
        );

        $jobs = [];
        $clients = Client::with('hostname')->select('id', 'hostname_id', 'name')->get();

        foreach ($clients as $client) {
            $website = optional($client->hostname)->website;
            if (!$website) continue;

            $jobs[] = new GenerateClientBackup(
                $tray->id,
                $website->uuid,
                $stamp,
                $includes_files,
                true,
                $client->name
            );
        }

        if (empty($jobs)) {
            $tray->delete();

            return response()->json([
                'success' => false,
                'message' => 'No hay clientes disponibles para respaldar en este momento.',
            ], 409);
        }

        // El callback se serializa, asi que solo puede capturar escalares.
        $tray_id = $tray->id;

        $batch = Bus::batch($jobs)
            ->name('backup-global-' . $stamp)
            ->allowFailures()
            ->onQueue(config('backup.queue'))
            ->finally(function (Batch $batch) use ($tray_id, $stamp) {
                AssembleGlobalBackup::dispatch($tray_id, $batch->id, $stamp);
            })
            ->dispatch();

        BackupTrayDetail::where('download_tray_id', $tray->id)->update(['batch_id' => $batch->id]);

        return response()->json([
            'success' => true,
            'message' => 'Se encolo el backup de ' . count($jobs) . ' clientes, lo vera en la bandeja de descargas.',
            'download_tray_id' => $tray->id,
            'batch_id' => $batch->id,
        ], 202);
    }

    protected function createTray($scope, $hostname_id, $client_name, $database, $includes_files)
    {
        $tray = DownloadTray::create([
            'user_id' => auth()->id(),
            'module' => DownloadTray::MODULE_BACKUP,
            'format' => 'zip',
            'type' => $scope,
            'status' => DownloadTray::STATUS_PENDING,
        ]);

        BackupTrayDetail::create([
            'download_tray_id' => $tray->id,
            'scope' => $scope,
            'hostname_id' => $hostname_id,
            'client_name' => $client_name,
            'database' => $database,
            'includes_files' => $includes_files,
        ]);

        return $tray;
    }

    /**
     * Evita que dos administradores encolen el mismo cliente a la vez.
     */
    protected function hasPendingBackup($hostname_id)
    {
        return DownloadTray::module(DownloadTray::MODULE_BACKUP)
            ->inProgress()
            ->whereHas('backup_detail', function ($query) use ($hostname_id) {
                $query->where('hostname_id', $hostname_id);
            })
            ->exists();
    }

    protected function hasPendingGlobalBackup()
    {
        return DownloadTray::module(DownloadTray::MODULE_BACKUP)
            ->inProgress()
            ->whereHas('backup_detail', function ($query) {
                $query->where('scope', BackupTrayDetail::SCOPE_ALL);
            })
            ->exists();
    }

    /**
     * Listado de la bandeja para la vista de backup.
     */
    public function tray(Request $request)
    {
        $records = DownloadTray::with('backup_detail')
            ->module(DownloadTray::MODULE_BACKUP)
            ->latest('id')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => collect($records->items())->map(function ($tray) {
                return [
                    'id' => $tray->id,
                    'client_name' => optional($tray->backup_detail)->client_name,
                    'database' => optional($tray->backup_detail)->database,
                    'scope' => optional($tray->backup_detail)->scope,
                    'batch_id' => optional($tray->backup_detail)->batch_id,
                    'status' => $tray->status,
                    'file_name' => $tray->file_name,
                    'size' => $tray->size,
                    'error_message' => $tray->error_message,
                    'date_init' => optional($tray->date_init)->format('d/m/Y H:i'),
                    'date_end' => optional($tray->date_end)->format('d/m/Y H:i'),
                    'created_at' => $tray->created_at->format('d/m/Y H:i'),
                    'downloadable' => $tray->isDownloadable(),
                ];
            }),
            'total' => $records->total(),
            'in_progress' => DownloadTray::module(DownloadTray::MODULE_BACKUP)->inProgress()->count(),
        ]);
    }

    public function trayDownload($id)
    {
        $tray = DownloadTray::module(DownloadTray::MODULE_BACKUP)->findOrFail($id);

        if (!$tray->isDownloadable()) {
            abort(404, 'El backup todavia no esta disponible.');
        }

        if (!Storage::disk($tray->disk)->exists($tray->path)) {
            abort(404, 'El archivo ya no existe en el servidor.');
        }

        return Storage::disk($tray->disk)->download($tray->path, $tray->file_name);
    }

    public function trayDestroy($id)
    {
        $tray = DownloadTray::module(DownloadTray::MODULE_BACKUP)->findOrFail($id);

        if ($tray->path && Storage::disk($tray->disk)->exists($tray->path)) {
            Storage::disk($tray->disk)->delete($tray->path);
        }

        $tray->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado',
        ]);
    }

    public function upload(Request $request)
    {

        $config = [
            'driver' => 'ftp',
            'host'   => $request['host'],
            'port' => $request['port'],
            'username' => $request['username'],
            'password'   => $request['password'],
            'port'  => 21,
            'passive'   => true,
        ];

        Config::set('filesystems.disks.ftp', $config);

        // definimos y subimos el archivo
        try {

            $most_recent = $this->mostRecent();

            $fileTo = $most_recent['name'];
            // $fileFrom = storage_path('app/'.$most_recent['path']);

            $fileFrom = Storage::get($most_recent['path']);

            $upload = Storage::disk('ftp')->put($fileTo, $fileFrom);

            return [
                'success' => $upload,
                'message' => 'Proceso finalizado satisfactoriamente'
            ];


        } catch (Exception $e) {

            $this->setErrorLog($e);
            return $this->getErrorMessage("Lo sentimos, ocurrió un error inesperado: {$e->getMessage()}");

        }

    }

    public function mostRecent()
    {
        $zips = Storage::allFiles('backups/zip/');
        if (count($zips) > 0) {
            $process = new Process([ 'bash' ,'-c','ls -t | head -1']);
            $process->setWorkingDirectory(storage_path('app/backups/zip'));
            $process->run();

            $filezip = trim($process->getOutput());
            $last_date = Storage::lastModified('backups/zip/'.$filezip);
            $most_recent_path = 'backups/zip/'.$filezip;
            return [
                'date' => \Carbon\Carbon::createFromTimestamp($last_date)->format('d-m-Y \a \l\a\s H:i'),
                'path' => $most_recent_path,
                'name' => $filezip
            ];
        } else {
            return '';
        }
    }


    public function download($filename)
    {
        // basename descarta cualquier ../ del nombre recibido: sin esto la ruta
        // permite salirse de la carpeta de backups y descargar otros archivos.
        $filename = basename($filename);
        $path = 'backups'.DIRECTORY_SEPARATOR.'zip'.DIRECTORY_SEPARATOR.$filename;

        if (!Storage::exists($path)) abort(404);

        return Storage::download($path);

    }

}
