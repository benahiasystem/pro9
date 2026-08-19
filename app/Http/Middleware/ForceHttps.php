<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Redirige peticiones HTTP a HTTPS cuando FORCE_HTTPS=true.
 * Evita que el checkout (Culqi, etc.) se abra en un contexto inseguro
 * solo porque se entró por http://.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('tenant.force_https')) {
            return $next($request);
        }

        if ($this->isAlreadyHttps($request)) {
            return $next($request);
        }

        $secureUrl = 'https://'.$request->getHttpHost().$request->getRequestUri();

        // 301 para GET/HEAD (caché de favoritos/enlaces viejos en http)
        // 308 para el resto (conserva método y body)
        $status = $request->isMethodSafe() ? 301 : 308;

        return redirect()->to($secureUrl, $status);
    }

    private function isAlreadyHttps(Request $request): bool
    {
        if ($request->secure()) {
            return true;
        }

        $forwarded = strtolower((string) $request->header('X-Forwarded-Proto', ''));

        return $forwarded === 'https';
    }
}
