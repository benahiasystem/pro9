<?php
namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class DemoRestoreBackupDatabase extends \Illuminate\Console\Command
{
    protected $signature = 'demobk:dbrestore {database} {restore_dbname} {restore_type} {--activate}';
    protected $description = 'Valida y restaura un paquete PostgreSQL en una base independiente';
    public function handle(): int
    {
        $name=$this->argument('restore_dbname');
        \App\Database\PostgresBootstrap::identifier($name);
        $kind=$this->argument('restore_type');
        if (!in_array($kind,['demo','system'],true)) throw new \InvalidArgumentException('Tipo inválido.');
        $path=storage_path('app/demo_backups/'.$kind.'/'.$name.'.zip');
        $manifest=app(\App\Database\PostgresBackup::class)->verify($path);
        if (count($manifest['databases'])!==1) throw new \InvalidArgumentException('Seleccione un respaldo de una empresa.');
        $result=app(\App\Tenancy\TenantRestore::class)->restore($this->argument('database'),$path,$manifest['databases'][0],(bool)$this->option('activate'));
        $this->line(json_encode($result,JSON_THROW_ON_ERROR));return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
