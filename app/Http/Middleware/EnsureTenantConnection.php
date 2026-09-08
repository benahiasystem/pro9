<?php

namespace App\Http\Middleware;

use Closure;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// ########## INICIO CORRECCIÓN INICIALIZACIÓN TENANT ##########

class EnsureTenantConnection
{
    /**
     * Recupera la conexión cuando CurrentHostname fue resuelto durante el
     * arranque, antes de que Hyn registrara sus listeners de base de datos.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! config('database.connections.tenant')) {
            $hostname = Hostname::query()
                ->with('website')
                ->where('fqdn', $request->getHost())
                ->first();

            if ($hostname && $hostname->website) {
                Cache::forget("tenancy.hostname.{$hostname->fqdn}");
                Cache::forget("tenancy.website.{$hostname->website->uuid}");

                $tenancy = app(Environment::class);
                $tenancy->hostname($hostname);
                $tenancy->tenant($hostname->website);
            }
        }

        return $next($request);
    }
}

// ######### FIN CORRECCIÓN INICIALIZACIÓN TENANT ##########
