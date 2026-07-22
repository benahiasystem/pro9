<?php

namespace Modules\Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\MarketplaceCache;
use Modules\Marketplace\Support\StorePayload;

/**
 * POST /api/v1/marketplace/hide — «Ocultar mi tienda» (plan de seguridad, A3.2).
 *
 * Despublicación inmediata sin pasar por el admin: la ficha y el catálogo
 * desaparecen del marketplace y el enlace compartido muestra el 410 amable.
 * Nada se borra; el siguiente sync republica. Es el botón de pánico para el
 * peor día de un comerciante, así que el único requisito es demostrar que
 * quien lo pulsa ES la tienda: uuid + X-Store-Secret.
 */
class HideController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'external_uuid' => ['required', 'string', 'size:36'],
        ]);

        $store = Store::where('external_uuid', $request->input('external_uuid'))->first();

        if (! $store) {
            return response()->json(['message' => 'Tienda no encontrada.'], 404);
        }

        // Sin credencial no se puede ocultar: una app ajena con el token del
        // reseller no debe poder «desaparecer» tiendas de otros.
        $provided = (string) $request->header('X-Store-Secret', '');

        if ($store->secret_hash === null
            || $provided === ''
            || ! hash_equals($store->secret_hash, hash('sha256', $provided))) {
            return response()->json([
                'message' => 'Credencial de tienda inválida. Sincroniza primero desde este dispositivo o pide a la administración restablecer tu credencial.',
            ], 403);
        }

        if (! $store->isHidden()) {
            $store->hidden_at = now();
            $store->save();
            MarketplaceCache::bump();
        }

        return response()->json(StorePayload::forStatus($store));
    }
}
