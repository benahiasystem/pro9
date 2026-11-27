<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

final class StartWebSession extends StartSession
{
    protected function handleStatefulRequest(Request $request, $session, Closure $next)
    {
        if (!$request->routeIs('tenant.pos.garage.image')) {
            return parent::handleStatefulRequest($request, $session, $next);
        }

        // Images read the current login without saving back a stale session or
        // taking over the previous page used by redirects.
        $request->setLaravelSession($this->startSession($request, $session));
        $response = $next($request);
        if (!in_array($response->getStatusCode(), [200, 304], true)) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('CDN-Cache-Control', 'no-store');
        }

        return $response;
    }
}
