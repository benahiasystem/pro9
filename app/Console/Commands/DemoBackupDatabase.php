<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Tenancy\Environment;
use App\Models\System\Client;

class DemoBackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demobk:dbcreate {database} {new_database} {client_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demo backup database';

    protected $process;

    protected $host;
    protected $username;
    protected $password;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
        if (config('tenant_storage.connection.bucket')) {
            $this->error('El flujo demo local no está disponible con Storage privado. Usa Respaldos para conservar MySQL y sus archivos.');
            return self::FAILURE;
        }
        // ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE

        try {
            $database = $this->argument('database');
            $new_database = $this->argument('new_database');
            $client_id = $this->argument('client_id');

            $this->initDbConfig();

            if (!File::exists(storage_path('app/demo_backups/demo'))) {
                File::makeDirectory(storage_path('app/demo_backups/demo'), 0755, true);
            }

            \App\Database\PostgresBootstrap::identifier($new_database);
            app(\App\Database\PostgresBackupManager::class)->backup([$database],storage_path("app/demo_backups/demo/{$new_database}.zip"));
            Log::info('Backup ' . $database . ' into '. $new_database .' database success');

            $this->output->success('Copia de base de datos creada con éxito');



            return 0;

        }catch (Exception $e) {

            Log::error("Backup failed -- Line: {$e->getLine()} - Message: {$e->getMessage()} - File: {$e->getFile()}");

            $this->output->error('Error inesperado: ' . $e->getMessage());
            return 1;
        }

    }


    private function initDbConfig(){

        $dbConfig = config('database.connections.' . config('tenancy.db.system-connection-name', 'system'));

        $this->host = Arr::first(Arr::wrap($dbConfig['host'] ?? ''));
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];

    }

}
