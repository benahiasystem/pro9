<?php
namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresRestoreCentral extends \Illuminate\Console\Command
{
    protected $signature='sab:restore-central {package} {--source=}';
    protected $description='Recupera central en la base nueva vacía configurada para el propietario';
    public function handle(): int
    {
        $backup=new \App\Database\PostgresBackup();$manifest=$backup->verify($this->argument('package'));
        $source=$this->option('source');
        if (!$source || !in_array($source,$manifest['databases'],true)) throw new \InvalidArgumentException('Indique --source con el nombre central del manifiesto.');
        \App\Database\PostgresBootstrap::identifier($source);
        $config=config('database.connections.central_migrator');
        if (\App\Database\SchemaInspector::tables(\Illuminate\Support\Facades\DB::connection('central_migrator'))) throw new \LogicException('Configure una base central independiente y vacía antes de restaurar.');
        $directory=sys_get_temp_dir().'/sab-central-restore-'.bin2hex(random_bytes(8));
        try {
            $backup->extract($this->argument('package'),'databases/',$directory);
            $dump=$directory.'/'.$source.'.dump';$backup->requireTables($config,$dump,['clients','websites','hostnames','migrations','tenant_provisioning_runs']);
            $backup->restore($config,$dump);
            \App\Database\SequenceSynchronizer::sync(\Illuminate\Support\Facades\DB::connection('central_migrator'));
            $this->info('Base central recuperada en '.$config['database'].'. Recupere cada empresa antes de iniciar HTTP y trabajos.');return 0;
        } finally {(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);}
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
