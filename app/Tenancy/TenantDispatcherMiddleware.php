<?php
namespace App\Tenancy;

// ########## INICIO CAMBIO POSTGRESQL 17
final class TenantDispatcherMiddleware
{
    public function handle($command,$next)
    {
        $environment=app(\Hyn\Tenancy\Environment::class);$previous=$environment->tenant();
        try {
            if (!empty($command->website_id)) TenantJobContext::select($command->website_id);
            return $next($command);
        } finally {
            TenantJobContext::clear();
            if ($previous) $environment->tenant($previous);
        }
    }
}
// ######### FIN CAMBIO POSTGRESQL 17
