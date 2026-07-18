<?php

namespace Modules\Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Http\Requests\SyncRequest;
use Modules\Marketplace\Services\StoreSyncService;
use Modules\Marketplace\Support\StorePayload;

/**
 * POST /api/v1/marketplace/sync
 *
 * El único endpoint de escritura del módulo. Envía todo: la tienda y su
 * catálogo completo.
 */
class SyncController extends Controller
{
    public function __construct(private StoreSyncService $storeSyncService){}

    public function store(SyncRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $store = $this->storeSyncService->sync($payload, $request->ip());

        return response()->json(
            StorePayload::forSync($store, count($payload['items'] ?? []))
        );
    }
}
