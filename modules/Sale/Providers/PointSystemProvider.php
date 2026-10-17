<?php

namespace Modules\Sale\Providers;

// ######## INICIO CALCULOS EXACTOS PRO9 ########
class PointSystemProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        foreach ([\App\Models\Tenant\Document::class, \App\Models\Tenant\SaleNote::class] as $class) {
            $class::saved(fn($record)=>\App\Support\Exact\LoyaltyPoints::synchronize($record));
            $class::deleting(fn($record)=>\App\Support\Exact\LoyaltyPoints::synchronize($record,true));
        }
    }
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
