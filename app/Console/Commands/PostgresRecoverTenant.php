<?php

namespace App\Console\Commands;

// ########## INICIO CAMBIO POSTGRESQL 17
class PostgresRecoverTenant extends \Illuminate\Console\Command
{
    protected $signature = 'tenant:recover-provisioning {operation} {--dry-run} {--apply}';
    protected $description = 'Inspecciona o compensa por UUID recursos propios de un alta tenant fallida';
    public function handle(): int
    {
        $result = app(\App\Tenancy\TenantProvisioning::class)->recover($this->argument('operation'), !$this->option('apply') || $this->option('dry-run'));
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return 0;
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
