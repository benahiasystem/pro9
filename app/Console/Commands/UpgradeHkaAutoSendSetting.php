<?php

namespace App\Console\Commands;

use App\Services\Fiscal\HkaAutoSendSettingUpgrade;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// ######## INICIO ENVÍO AUTOMÁTICO HKA ########
class UpgradeHkaAutoSendSetting extends Command
{
    protected $signature = 'hka:upgrade-auto-send-setting {--tenant= : ID de Website de una empresa} {--all-tenants} {--dry-run}';
    protected $description = 'Añade únicamente la preferencia de envío automático HKA, conservando los datos.';

    public function handle(HkaAutoSendSettingUpgrade $upgrade): int
    {
        $tenant = $this->option('tenant');
        if (($tenant !== null) === (bool) $this->option('all-tenants')
            || ($tenant !== null && (!ctype_digit((string) $tenant) || (int) $tenant < 1))) {
            $this->error('Indique --tenant=<website_id> o --all-tenants, exclusivamente.');
            return 1;
        }
        $websites = Website::orderBy('id')->when($tenant !== null, fn ($query) => $query->where('id', (int) $tenant))->get();
        if ($websites->isEmpty()) {
            $this->error('No se encontraron empresas para actualizar.');
            return 1;
        }
        $environment = app(Environment::class);
        $previous = $environment->tenant();
        $connectionName = app(\Hyn\Tenancy\Database\Connection::class)->tenantName();
        $previousConnection = config('database.connections.'.$connectionName);
        $exitCode = 0;
        try {
            foreach ($websites as $website) {
                try {
                    $environment->tenant($website);
                    $changed = $upgrade->upgrade(DB::connection('tenant'), (bool) $this->option('dry-run'));
                    $result = !$changed ? 'ya disponible' : ($this->option('dry-run') ? 'se añadirá (simulación)' : 'añadida');
                    $this->line('Tenant '.$website->id.': configurations.auto_send_hka '.$result.'.');
                } catch (\Throwable $exception) {
                    $this->error('Tenant '.$website->id.': no se pudo ampliar la configuración.');
                    $exitCode = 1;
                }
            }
        } finally {
            if ($previous) {
                $environment->tenant($previous);
            } else {
                // Hyn's tenant(null) is a getter, so explicitly clear a previously empty context.
                app()->instance(\Hyn\Tenancy\Contracts\Tenant::class, null);
                DB::purge($connectionName);
                config(['database.connections.'.$connectionName => $previousConnection]);
            }
        }
        return $exitCode;
    }
}
// ######## FIN ENVÍO AUTOMÁTICO HKA ########
