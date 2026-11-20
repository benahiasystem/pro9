<?php

namespace App\Console\Commands;

use App\Tenancy\Database\Connection;
use App\Tenancy\Models\Website;
use App\Tenancy\TenantReadiness;
use Illuminate\Console\Command;

class TenancyMigrate extends Command
{
    protected $signature = 'tenancy:migrate {--website_id=*} {--path=} {--realpath} {--force}';
    protected $description = 'Ejecuta las migraciones de las empresas registradas';

    public function handle(Connection $connection): int
    {
        if (!$this->option('force') && !$this->confirm('¿Ejecutar las migraciones de empresas?')) {
            return 1;
        }
        $ids = $this->option('website_id');
        $query = Website::query()->when($ids, fn ($query) => $query->whereIn('id', $ids));
        $path = $this->option('path');
        if ($path && !$this->option('realpath')) {
            $path = base_path($path);
        }
        $result = 0;
        foreach ($query->lazyById() as $website) {
            TenantReadiness::ensure($website->uuid);
            if (!$connection->migrate($website, $path)) {
                $this->error('Fallaron las migraciones de '.$website->uuid);
                $result = 1;
            }
        }
        return $result;
    }
}
