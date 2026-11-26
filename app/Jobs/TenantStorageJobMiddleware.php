<?php
// ########## INICIO MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
namespace App\Jobs;

use App\Services\Storage\TenantContext;
use App\Tenancy\Models\Website;
use Illuminate\Support\Facades\Auth;

final class TenantStorageJobMiddleware
{
    public function handle($job,$next)
    {
        $website=Website::findOrFail($job->website_id);
        $guards=Auth::getDefaultDriver();$previous=Auth::guard($guards)->user();
        try{return app(TenantContext::class)->run($website->uuid,fn()=>$next($job));}
        finally{
            Auth::forgetGuards();
            if($previous)Auth::guard($guards)->setUser($previous);
        }
    }
}
// ######### FIN MIGRACIÓN ALMACENAMIENTO S3 CLOUD-ONPREMISE
