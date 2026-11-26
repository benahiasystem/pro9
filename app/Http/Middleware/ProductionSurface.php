<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ProductionSurface
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->isProduction()) {
            return $next($request);
        }

        $host = $request->getHost();
        $hostname = app(\App\Tenancy\Contracts\CurrentHostname::class);
        abort_unless(in_array($host, [config('app.url_base'), parse_url(config('app.url'), PHP_URL_HOST), $hostname?->fqdn], true), 404);

        $action = $request->route()?->getActionName() ?? '';
        foreach (['Ecommerce', 'Webhook', 'WhatsAppBot', 'WhatsAppApi', 'QrApi', 'MercadoPago'] as $module) {
            if (str_starts_with($action, "Modules\\".$module."\\")) {
                abort(404);
            }
        }
        abort_if($request->is(
            'logs', 'auto-update', 'auto-update/*',
            'certificates-qztray', 'certificates-qztray/*', 'api/certificates-qztray/*',
            'pagos/*', 'payment-gateway/*', 'api/whatsapp-notify/*'
        ), 404);

        return $next($request);
    }
}
