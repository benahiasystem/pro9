<?php

namespace Modules\Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Marketplace\Http\Requests\SyncRequest;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\StoreSyncService;
use Modules\Marketplace\Support\StorePayload;

/**
 * POST /api/v1/marketplace/sync
 *
 * El único endpoint de escritura del módulo. Envía todo: la tienda y su
 * catálogo completo.
 *
 * Credencial por tienda, trust-on-first-use (plan de seguridad, A3.1): el
 * token del guard system_api es del reseller y es global, así que por sí solo
 * permitiría suplantar el external_uuid de otra tienda. El primer sync válido
 * emite un store_secret que viaja UNA sola vez en la respuesta; desde entonces
 * todo sync de ese uuid debe traerlo en el header X-Store-Secret. Una tienda
 * anterior a esta versión (secret_hash null) adopta secreto en su siguiente
 * sync, por el mismo camino.
 */
class SyncController extends Controller
{
    public function __construct(private StoreSyncService $storeSyncService){}

    public function store(SyncRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $existing = Store::where('external_uuid', $payload['store']['external_uuid'])->first();

        // Tienda con credencial: sin el secreto correcto no hay sync. El 403
        // deja claro que no es un problema de datos sino de identidad.
        if ($existing && $existing->secret_hash !== null) {
            $provided = (string) $request->header('X-Store-Secret', '');

            if ($provided === '' || ! hash_equals($existing->secret_hash, hash('sha256', $provided))) {
                return response()->json([
                    'message' => 'Credencial de tienda inválida. Si cambiaste de dispositivo, pide a la administración restablecer tu credencial.',
                ], 403);
            }
        }

        $store = $this->storeSyncService->sync($payload, $request->ip());

        // TOFU: tienda nueva o legada sin credencial → se emite aquí, se guarda
        // solo el hash y el secreto en claro viaja únicamente en esta respuesta.
        $storeSecret = null;

        if ($store->secret_hash === null) {
            $storeSecret = Str::random(48);
            $store->secret_hash = hash('sha256', $storeSecret);
            $store->save();
        }

        return response()->json(
            StorePayload::forSync($store, count($payload['items'] ?? []), $storeSecret)
        );
    }
}
