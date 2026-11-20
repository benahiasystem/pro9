<?php

namespace App\Console\Commands;

use App\Tenancy\Database\Connection;
use App\Tenancy\Models\Website;
use App\Tenancy\TenantReadiness;
use Illuminate\Console\Command;

class TenancySeed extends Command
{
    protected $signature = 'tenancy:db:seed {--website_id=*} {--class=} {--force}';
    protected $description = 'Ejecuta las semillas de las empresas seleccionadas';

    public function handle(Connection $connection): int
    {
        if (!$this->option('force') && !$this->confirm('¿Ejecutar las semillas de empresas?')) {
            return 1;
        }

        $ids = $this->option('website_id');
        $websites = Website::query()->when($ids, fn ($query) => $query->whereIn('id', $ids));
        $result = 0;
        foreach ($websites->lazyById() as $website) {
            TenantReadiness::ensure($website->uuid);
            if (!$connection->seed($website, $this->option('class'))) {
                $this->error('Fallaron las semillas de '.$website->uuid);
                $result = 1;
            }
        }
        return $result;
    }
}
