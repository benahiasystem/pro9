<?php

namespace App\Tenancy\Repositories;

use App\Tenancy\Models\Hostname;
use App\Tenancy\Models\Website;
use Illuminate\Database\Eloquent\Builder;

class HostnameRepository
{
    public function query(): Builder
    {
        return Hostname::query();
    }

    public function findByHostname(string $hostname): ?Hostname
    {
        return $this->query()->with('website')->where('fqdn', strtolower($hostname))->first();
    }

    public function create(Hostname $hostname): Hostname
    {
        return $this->update($hostname);
    }

    public function update(Hostname $hostname): Hostname
    {
        $hostname->fqdn = strtolower($hostname->fqdn);
        $hostname->saveOrFail();
        return $hostname;
    }

    public function delete(Hostname $hostname, bool $hard = false): Hostname
    {
        $hard ? $hostname->forceDelete() : $hostname->delete();
        return $hostname;
    }

    public function attach(Hostname $hostname, Website $website): Hostname
    {
        $hostname->website()->associate($website);
        $hostname->saveOrFail();
        $website->unsetRelation('hostnames');
        return $hostname;
    }
}
