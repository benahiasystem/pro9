<?php

namespace Modules\Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Support\StorePayload;

/**
 * GET /api/v1/marketplace/status/{external_uuid}
 *
 * 404 si el uuid no existe: la app lo interpreta como "aún no sincronizada".
 */
class StatusController extends Controller
{
    public function show(string $external_uuid): JsonResponse
    {
        $store = Store::where('external_uuid', $external_uuid)->first();

        if (! $store) {
            return response()->json([
                'message' => 'Tienda no encontrada.',
            ], 404);
        }

        return response()->json(StorePayload::forStatus($store));
    }
}
