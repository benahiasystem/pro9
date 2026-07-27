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
    private const ITEMS_MAX = 200;

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

        $stores = $query->withCount('contactRequests')
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected', 'disabled')")
            ->orderBy('name')
            ->paginate(20);

        // Alertas sin revisar de las tiendas de esta página, en una sola
        // consulta. No hay pestaña de alertas: cada una se muestra como
        // «acción requerida» en la fila de su tienda, igual que una pendiente
        // de aprobar.
        $alerts = \Modules\Marketplace\Models\SecurityAlert::whereNull('read_at')
            ->whereIn('store_id', $stores->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('store_id');

        $stores->getCollection()->transform(
            fn (Store $s) => $this->present($s, $alerts->get($s->id))
        );

        return response()->json($stores);
    }

    /**
     * Catálogo de una tienda: alimenta tanto la vista previa de la fila
     * expandible (limit chico) como el panel lateral, que además filtra.
     * Sin el scope de publicación: el admin necesita ver también los inactivos
     * y los bloqueados, que son justamente los que va a querer revisar.
     */
    public function items(Request $request, int $id): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', self::ITEMS_MAX), self::ITEMS_MAX));

        $query = Item::unscoped()->where('store_id', $id);

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('internal_code', 'like', "%{$q}%");
            });
        }

        if (in_array($status = $request->input('status'), [Item::STATUS_ACTIVE, Item::STATUS_INACTIVE, Item::STATUS_BLOCKED], true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();

        $items = $query
            ->orderByRaw("FIELD(status, 'blocked', 'active', 'inactive')")
            ->orderByDesc('reports_count')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Item $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'internal_code' => $i->internal_code,
                'status' => $i->status,
                'blocked_reason' => $i->blocked_reason,
                'reports_count' => $i->reports_count,
                'recommendations_count' => $i->recommendations_count,
                'image_url' => $i->image_path ? \Illuminate\Support\Facades\Storage::disk(config('marketplace.disk'))->url($i->image_path) : null,
            ]);

        return response()->json(['data' => $items, 'total' => $total]);
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

    /**
     * Restablece la credencial TOFU de una tienda (plan de seguridad, A4.2).
     *
     * Para cuando la tienda cambió o perdió su dispositivo: sin el secreto no
     * puede volver a sincronizar. Con secret_hash en null, su siguiente sync
     * se comporta como el primero (emite credencial nueva) — y queda alerta,
     * porque un reset es exactamente lo que pediría también un suplantador.
     */
    public function resetSecret(int $id): JsonResponse
    {
        $store = Store::findOrFail($id);

        $store->secret_hash = null;
        $store->save();

        \Modules\Marketplace\Models\SecurityAlert::create([
            'store_id' => $store->id,
            'type' => \Modules\Marketplace\Models\SecurityAlert::TYPE_SECRET_RESET,
            'message' => "Se restableció la credencial de «{$store->name}»: el próximo sync emitirá una nueva (trust-on-first-use).",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credencial restablecida. El próximo sync de la tienda emitirá una nueva.',
        ]);
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

    private function present(Store $store, $alerts = null): array
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
            // «Ocultar mi tienda»: sigue approved, pero la tienda se
            // despublicó a sí misma. El admin debe verlo sin adivinar.
            'hidden' => $store->isHidden(),
            'items_count' => $store->items_count,
            'reports_count' => $store->reports_count,
            'recommendations_count' => $store->recommendations_count,
            // Cuántos vecinos pidieron su contacto — y de paso, el dato que
            // justifica el botón de exportar el CSV de esa tienda.
            'contacts_count' => (int) ($store->contact_requests_count ?? 0),
            // Sin credencial (tienda de antes del TOFU, o tras un reset) el
            // «Restablecer credencial» no aporta nada: el front lo etiqueta.
            'has_secret' => $store->secret_hash !== null,
            // Alertas sin revisar: es lo que pinta el «acción requerida» de la
            // fila. Vacío = nada pendiente.
            'alerts' => collect($alerts ?? [])->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'message' => $a->message,
                'created_at' => $a->created_at?->format('d/m/Y H:i'),
            ])->values()->all(),
            'public_url' => $store->publicUrl(),
            'logo_url' => $store->logo_path ? \Illuminate\Support\Facades\Storage::disk(config('marketplace.disk'))->url($store->logo_path) : null,
            'last_synced_at' => $store->last_synced_at?->format('d/m/Y H:i'),
            'app_version' => $store->app_version,
        ];
    }
}
