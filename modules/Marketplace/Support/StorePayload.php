<?php

namespace Modules\Marketplace\Support;

use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\Settings;

/**
 * Forma del contrato que consume la app móvil.
 *
 * Vive aparte de los controladores porque las tres respuestas posibles (sync,
 * status y el corte del middleware cuando el marketplace está apagado) deben
 * compartir exactamente las mismas claves. La app lee `marketplace_enabled`
 * antes que `status`: es lo único que distingue "marketplace apagado" de "tu
 * tienda está pendiente".
 */
class StorePayload
{
    public static function forSync(Store $store, int $itemsReceived, ?string $storeSecret = null): array
    {
        return array_merge(self::base($store), [
            'items_received' => $itemsReceived,
            // La credencial TOFU. SOLO viaja en la respuesta del sync que la
            // emitió (primer sync, o primero tras un reset del admin): la app
            // la guarda en el almacén seguro del sistema y no vuelve a verla.
            ...($storeSecret !== null ? ['store_secret' => $storeSecret] : []),
        ]);
    }

    public static function forStatus(Store $store): array
    {
        return self::base($store);
    }

    private static function base(Store $store): array
    {
        return [
            'marketplace_enabled' => true,
            'status' => $store->status,
            'status_reason' => $store->status_reason,
            // «Ocultar mi tienda»: estado paralelo a status. La app lo lee
            // DESPUÉS de status: una tienda approved + hidden está oculta por
            // decisión propia y puede republicarse sincronizando.
            'hidden' => $store->isHidden(),
            'public_url' => $store->publicUrl(),
            // Solo una tienda aprobada tiene productos visibles al público.
            'items_published' => $store->isApproved() ? $store->items_count : 0,
            'terms_url' => Settings::termsUrl(),
            'last_synced_at' => $store->last_synced_at?->toIso8601String(),
        ];
    }
}
