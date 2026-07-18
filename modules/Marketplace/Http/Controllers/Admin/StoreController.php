<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\CatalogCounters;
use Modules\Marketplace\Services\MarketplaceCache;

class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Store::query();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->input('q'))) {
            $normalized = \Illuminate\Support\Str::ascii(mb_strtolower($q));
            $query->where(function ($sub) use ($normalized, $q) {
                $sub->where('name_normalized', 'like', "%{$normalized}%")
                    ->orWhere('tax_id', 'like', "{$q}%");
            });
        }

        $stores = $query->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected', 'disabled')")
            ->orderBy('name')
            ->paginate(20);

        $stores->getCollection()->transform(fn (Store $s) => $this->present($s));

        return response()->json($stores);
    }

    /**
     * Vista previa del catálogo para la fila expandible.
     * Sin el scope de publicación: el admin necesita ver también los inactivos
     * y los bloqueados, que son justamente los que va a querer revisar.
     */
    public function items(int $id): JsonResponse
    {
        $items = Item::unscoped()
            ->where('store_id', $id)
            ->orderByRaw("FIELD(status, 'blocked', 'active', 'inactive')")
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'internal_code' => $i->internal_code,
                'status' => $i->status,
                'blocked_reason' => $i->blocked_reason,
                'reports_count' => $i->reports_count,
                'image_url' => $i->image_path ? \Illuminate\Support\Facades\Storage::disk(config('marketplace.disk'))->url($i->image_path) : null,
            ]);

        return response()->json(['data' => $items]);
    }

    public function approve(int $id): JsonResponse
    {
        $store = Store::findOrFail($id);

        $store->status = Store::STATUS_APPROVED;
        $store->status_reason = null;
        $store->approved_at = now();
        $store->save();

        return $this->done($store, 'Tienda aprobada. Ya es visible en el marketplace.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        // El motivo es obligatorio: la tienda lo lee tal cual en GET /status y
        // es lo único que le dice qué corregir antes de reenviar.
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => 'motivo']);

        $store = Store::findOrFail($id);

        $store->status = Store::STATUS_REJECTED;
        $store->status_reason = $data['reason'];
        $store->rejected_at = now();
        $store->save();

        return $this->done($store, 'Tienda rechazada.');
    }

    public function disable(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => 'motivo']);

        $store = Store::findOrFail($id);

        $store->status = Store::STATUS_DISABLED;
        $store->status_reason = $data['reason'];
        $store->disabled_at = now();
        $store->save();

        return $this->done($store, 'Tienda deshabilitada. Sus productos ya no son visibles.');
    }

    public function enable(int $id): JsonResponse
    {
        $store = Store::findOrFail($id);

        $store->status = Store::STATUS_APPROVED;
        $store->status_reason = null;
        $store->disabled_at = null;
        $store->approved_at = now();
        $store->save();

        return $this->done($store, 'Tienda habilitada.');
    }

    private function done(Store $store, string $message): JsonResponse
    {
        // Aprobar o deshabilitar cambia qué ítems están publicados, así que los
        // conteos por categoría del filtro público quedan obsoletos.
        CatalogCounters::refreshCategories();
        MarketplaceCache::bump();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->present($store),
        ]);
    }

    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'tax_id' => $store->tax_id,
            'email' => $store->email,
            'whatsapp' => $store->whatsapp,
            'description' => $store->description,
            'address' => $store->address,
            'status' => $store->status,
            'status_reason' => $store->status_reason,
            'items_count' => $store->items_count,
            'reports_count' => $store->reports_count,
            'public_url' => $store->publicUrl(),
            'logo_url' => $store->logo_path ? \Illuminate\Support\Facades\Storage::disk(config('marketplace.disk'))->url($store->logo_path) : null,
            'last_synced_at' => $store->last_synced_at?->format('d/m/Y H:i'),
            'app_version' => $store->app_version,
        ];
    }
}
