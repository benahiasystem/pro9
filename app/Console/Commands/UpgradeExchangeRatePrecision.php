<?php
// ######## INICIO TASAS OCHO DECIMALES ########
namespace App\Console\Commands;

use App\Services\ExchangeRates\ExchangeRatePrecisionUpgrade;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpgradeExchangeRatePrecision extends Command
{
    protected $signature = 'exchange-rates:upgrade-precision {--all-tenants} {--dry-run}';
    protected $description = 'Amplía sólo columnas de tasas a DECIMAL(18,8), conservando los datos.';

    public function handle(ExchangeRatePrecisionUpgrade $upgrade): int
    {
        if (!$this->option('all-tenants')) {
            $this->error('Indique --all-tenants; no se modifica la conexión por defecto.');
            return 1;
        }
        $environment = app(Environment::class);
        $previous = $environment->tenant();
        try {
            foreach (Website::orderBy('id')->get() as $website) {
                $environment->tenant($website);
                $changes = $upgrade->upgrade(DB::connection('tenant'), (bool) $this->option('dry-run'));
                $this->line('Tenant '.$website->id.': '.count($changes).' columnas'.($this->option('dry-run') ? ' (simulación)' : ' ampliadas'));
                foreach ($changes as $change) $this->line('  '.$change);
            }
        } finally { $environment->tenant($previous); }
        return 0;
    }
}
// ######## FIN TASAS OCHO DECIMALES ########
