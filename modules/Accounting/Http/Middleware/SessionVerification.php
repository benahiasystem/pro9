<?php

namespace Modules\Accounting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\Permissions;
use Modules\Accounting\Services\SecurityService;
final class SessionVerification
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (app(Permissions::class)->requiresMfa($request->user())) {
            app(SecurityService::class)->requireVerified($request, false);
        }
        return $next($request);
    }
}
