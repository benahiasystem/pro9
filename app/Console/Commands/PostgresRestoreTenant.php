<?php

namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresRestoreTenant extends \Illuminate\Console\Command
{
    protected $signature = 'pro9:restore-tenant {database} {package} {--source=} {--activate}';
    protected $description = 'Restaura un ZIP PostgreSQL validado en una base independiente';
    public function handle(): int
    {
        $manifest = app(\App\Database\PostgresBackup::class)->verify($this->argument('package'));
        $source = $this->option('source');
        if (!$source && count($manifest['databases']) === 1) $source = $manifest['databases'][0];
        if (!$source || !in_array($source, $manifest['databases'], true)) throw new \InvalidArgumentException('Indique --source para elegir una empresa del respaldo.');
        $result = app(\App\Tenancy\TenantRestore::class)->restore($this->argument('database'), $this->argument('package'), $source, (bool)$this->option('activate'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
