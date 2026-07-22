<?php

namespace Modules\Marketplace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Marketplace\Services\Settings;

/**
 * Corta el paso cuando el admin tiene el marketplace apagado.
 *
 * Se aplica a las rutas de API y a las públicas, nunca a las de admin: el
 * admin debe poder revisar y aprobar tiendas con el marketplace apagado para
 * luego encenderlo.
 *
 * Clave de diseño: para la app **nunca es un error**. Devuelve 200 con la
 * bandera `marketplace_enabled: false` para que la app muestre "aún no
 * disponible" en vez de un 4xx/5xx que parezca fallo de red o bug.
 */
class EnsureMarketplaceEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (Settings::isEnabled()) {
            return $next($request);
        }

        $message = 'El marketplace no está disponible por ahora.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'marketplace_enabled' => false,
                'message' => $message,
                'status' => null,
                'status_reason' => null,
                'hidden' => null,
                'public_url' => null,
                'items_received' => 0,
                'items_published' => 0,
                'terms_url' => null,
                'last_synced_at' => null,
            ]);
        }

        // Pantalla pública: 503, no 404 ni 410. El 410 está reservado para una
        // tienda concreta que ya no existe; el 503 dice "esto vuelve".
        return response()->view('marketplace::public.unavailable', [
            'message' => $message,
        ], 503);
    }
}
