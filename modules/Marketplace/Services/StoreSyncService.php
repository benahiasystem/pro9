<?php

namespace Modules\Marketplace\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;

/**
 * Procesa el sync completo de una tienda: crea o actualiza la tienda y
 * reemplaza su catálogo. Síncrono, en una transacción, sin batches ni colas.
 *
 * Invariante crítica: un ítem con status 'blocked' conserva su bloqueo pase lo
 * que pase. Se le actualizan nombre, imagen y categoría, pero nunca status,
 * blocked_at, blocked_reason ni reports_count. Y nunca se elimina, ni siquiera
 * si desaparece del payload.
 */
class StoreSyncService
{
    public function __construct(
        private CategoryResolver $categories,
        private ImageStorage $images,
    ) {
    }

    public function sync(array $payload, ?string $ip = null): Store
    {
        $store = DB::connection('system')->transaction(function () use ($payload, $ip) {
            $store = $this->upsertStore($payload['store'], $payload['app_version'] ?? null, $ip, count($payload['items'] ?? []));

            $this->syncItems($store, $payload['items'] ?? []);
            $this->recalculateCounts($store);

            return $store;
        });

        // Fuera de la transacción: invalida todo el cache del módulo.
        MarketplaceCache::bump();

        return $store->refresh();
    }

    // ---------------------------------------------------------------------
    // Tienda
    // ---------------------------------------------------------------------

    private function upsertStore(array $data, ?string $appVersion, ?string $ip, int $itemsReceived): Store
    {
        $store = Store::where('external_uuid', $data['external_uuid'])->first();
        $isNew = $store === null;

        if ($isNew) {
            $store = new Store([
                'external_uuid' => $data['external_uuid'],
                // El slug se genera una sola vez y no cambia aunque la tienda
                // se renombre: los links ya compartidos no se rompen.
                'slug' => $this->uniqueSlug($data['name']),
                'status' => Store::STATUS_PENDING,
            ]);

            // Aceptación implícita de los T&C al sincronizar por primera vez.
            $store->terms_accepted_at = now();
        } elseif ($store->status === Store::STATUS_REJECTED) {
            // "Corregir y reenviar": vuelve a la cola de revisión.
            $store->status = Store::STATUS_PENDING;
            $store->status_reason = null;
        }
        // pending sigue pending · approved sigue approved (publica al instante)
        // · disabled sigue disabled (solo el admin lo revierte).

        $store->fill([
            'name' => $data['name'],
            'name_normalized' => $this->normalize($data['name']),
            'tax_id' => $data['tax_id'] ?? null,
            'email' => $data['email'] ?? null,
            'whatsapp' => $data['whatsapp'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            // Ausente = false: una app vieja que no lo envíe deja los precios ocultos.
            'show_prices' => (bool) ($data['show_prices'] ?? false),
        ]);

        $store->last_synced_at = now();
        $store->last_sync_items = $itemsReceived;
        $store->last_sync_ip = $ip;
        $store->app_version = $appVersion;

        $store->save();

        $this->syncLogo($store, $data);

        return $store;
    }

    /**
     * El logo solo se toca si viene logo_base64. Es lo que mantiene ligeros los
     * syncs posteriores: sin cambios, la app no reenvía la imagen.
     */
    private function syncLogo(Store $store, array $data): void
    {
        if (empty($data['logo_base64'])) {
            return;
        }

        $path = $this->images->storeLogo($store->id, $data['logo_base64'], $store->logo_path);

        if ($path === null) {
            return;
        }

        $store->logo_path = $path;
        $store->logo_hash = $data['logo_hash'] ?? hash('sha256', $path);
        $store->save();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tienda';
        $slug = Str::limit($base, 170, '');
        $suffix = 1;

        while (Store::where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 170, '') . '-' . (++$suffix);
        }

        return $slug;
    }

    // ---------------------------------------------------------------------
    // Catálogo
    // ---------------------------------------------------------------------

    private function syncItems(Store $store, array $items): void
    {
        // unscoped(): hay que ver también los inactivos y los bloqueados.
        $existing = Item::unscoped()
            ->where('store_id', $store->id)
            ->get()
            ->keyBy('external_id');

        $received = [];

        foreach ($items as $data) {
            $externalId = (string) $data['external_id'];
            $received[$externalId] = true;

            $item = $existing->get($externalId) ?? new Item([
                'store_id' => $store->id,
                'external_id' => $externalId,
                'status' => Item::STATUS_ACTIVE,
            ]);

            $category = $this->categories->resolve($data['category'] ?? null);

            $item->fill([
                'name' => $data['name'],
                'name_normalized' => $this->normalize($data['name']),
                'internal_code' => $data['internal_code'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'category_id' => $category?->id,
                'source_category' => $data['category'] ?? null,
                'price' => $data['price'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            // Un ítem bloqueado se actualiza en todo salvo su estado: el
            // bloqueo sobrevive a cualquier número de sincronizaciones.
            if (! $item->isBlocked()) {
                $item->status = Item::STATUS_ACTIVE;
            }

            $item->last_synced_at = now();
            $item->save();

            $this->syncItemImage($store, $item, $data);
        }

        // Los ausentes se desactivan, nunca se borran: así un bloqueo sobrevive
        // incluso si el producto desaparece del catálogo de la app.
        foreach ($existing as $externalId => $item) {
            if (isset($received[$externalId]) || $item->isBlocked()) {
                continue;
            }

            if ($item->status !== Item::STATUS_INACTIVE) {
                $item->status = Item::STATUS_INACTIVE;
                $item->save();
            }
        }
    }

    private function syncItemImage(Store $store, Item $item, array $data): void
    {
        if (empty($data['image_base64'])) {
            return;
        }

        $path = $this->images->storeItemImage(
            $store->id,
            $item->external_id,
            $data['image_base64'],
            $item->image_path
        );

        if ($path === null) {
            return;
        }

        $item->image_path = $path;
        $item->image_hash = $data['image_hash'] ?? hash('sha256', $path);
        $item->save();
    }

    // ---------------------------------------------------------------------
    // Contadores
    // ---------------------------------------------------------------------

    private function recalculateCounts(Store $store): void
    {
        CatalogCounters::refreshAll($store->id);
        $store->refresh();
    }

    private function normalize(string $value): string
    {
        return Str::ascii(mb_strtolower(trim($value)));
    }
}
