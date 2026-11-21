<?php

namespace App\Tenancy;

final class TenantDispatcherMiddleware
{
    public function handle($command, $next)
    {
        $environment = app(Environment::class);
        $previous = $environment->tenant();
        $id = $command->website_id ?? null;
        if ($id === null || (string) $previous?->getKey() === (string) $id) {
            return $next($command);
        }

        try {
            TenantJobContext::select($id);
            return $next($command);
        } finally {
            $environment->switchTenant($previous);
        }
    }
}
