<?php

namespace App\Console\Commands;

use App\Tenancy\Environment;
use App\Tenancy\Models\Website;
use App\Tenancy\TenantReadiness;
use Illuminate\Console\Command;

class TenancyRun extends Command
{
    protected $signature = 'tenancy:run {run} {--tenant=*} {--argument=*} {--option=*}';
    protected $description = 'Ejecuta un comando Artisan en las empresas seleccionadas';

    public function handle(Environment $environment): int
    {
        $ids = $this->option('tenant');
        $query = Website::query()->when($ids, fn ($query) => $query->whereIn('id', $ids));
        $options = [];
        foreach (['argument' => '', 'option' => '--'] as $type => $prefix) {
            foreach ($this->option($type) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, true);
                $options[$prefix.$key] = $value;
            }
        }
        $result = 0;
        foreach ($query->lazyById() as $website) {
            TenantReadiness::ensure($website->uuid);
            $code = $environment->run($website, fn () => $this->call($this->argument('run'), $options));
            if ($code !== 0) {
                $result = $code;
            }
        }
        return $result;
    }
}
