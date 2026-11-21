<?php

namespace App\Tenancy\Middleware;

use App\Tenancy\Contracts\CurrentHostname;
use App\Tenancy\Environment;
use Closure;
use Illuminate\Http\Request;

class HostnameActions
{
    public function handle(Request $request, Closure $next)
    {
        $bootHostname = app(Environment::REQUEST_HOSTNAME);
        // SAB registers host-specific routes during FPM bootstrap. A stale
        // request scope must never dispatch through another domain's routes.
        abort_if($bootHostname && strcasecmp($bootHostname->fqdn, $request->getHost()) !== 0, 404);

        $hostname = app(CurrentHostname::class);
        if (!$hostname) {
            abort_if(config('tenancy.hostname.abort-without-identified-hostname'), 404);
            return $next($request);
        }

        abort_if($hostname->under_maintenance_since?->isPast(), 503);
        if ($hostname->redirect_to) {
            return redirect()->away($hostname->redirect_to);
        }
        if ($hostname->force_https && !$request->secure()) {
            return redirect()->secure(ltrim($request->getRequestUri(), '/'));
        }

        return $next($request);
    }
}
