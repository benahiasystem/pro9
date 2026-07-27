<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Http\Requests\ReportRequest;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Report;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\CatalogCounters;
use Modules\Marketplace\Services\MarketplaceCache;
use Modules\Marketplace\Services\Settings;

/**
 * Denuncias del público. Mínimas a propósito: motivo, IP y fecha.
 * Sin captcha, sin email, sin campo de detalle.
 */
class ReportController extends Controller
{
    public function store(ReportRequest $request): JsonResponse
    {
        $item = null;

        if ($request->filled('item_id')) {
            // Con el scope de publicación: no se puede denunciar algo que no
            // es público, y de paso la tienda se deduce del propio ítem.
            $item = Item::find($request->input('item_id'));

            if (! $item) {
                return response()->json(['message' => 'El producto ya no está disponible.'], 404);
            }

            $store = Store::approved()->find($item->store_id);
        } else {
            $store = Store::approved()->where('slug', $request->input('store'))->first();
        }

        if (! $store) {
            return response()->json(['message' => 'La tienda ya no está disponible.'], 404);
        }

        Report::create([
            'store_id' => $store->id,
            'item_id' => $item?->id,
            'reason' => $request->input('reason'),
            'ip' => $request->ip(),
            'status' => Report::STATUS_OPEN,
        ]);

        // Los contadores son lo que el admin ordena en su tabla.
        $store->increment('reports_count');

        if ($item) {
            $item->increment('reports_count');
            $this->autoBlock($item);
        }

        return response()->json([
            'success' => true,
            'message' => 'Denuncia enviada. El administrador la revisará pronto.',
        ]);
    }

    /**
     * Bloqueo automático al superar el umbral de denuncias.
     *
     * Es una red de seguridad para cuando el admin no está mirando: retira el
     * producto y deja el rastro en `blocked_reason`. Como cualquier otro
     * bloqueo, sobrevive a los syncs y solo el admin puede revertirlo.
     *
     * Con el umbral en 0 no se bloquea nunca.
     */
    private function autoBlock(Item $item): void
    {
        $threshold = (int) Settings::get('auto_block_reports', 0);

        if ($threshold <= 0 || $item->isBlocked()) {
            return;
        }

        $item->refresh();

        if ($item->reports_count < $threshold) {
            return;
        }

        $item->status = Item::STATUS_BLOCKED;
        $item->blocked_at = now();
        $item->blocked_reason = "Bloqueo automático: alcanzó {$item->reports_count} denuncias.";
        $item->save();

        // Cambió lo publicado: contadores y cache.
        CatalogCounters::refreshAll($item->store_id);
        MarketplaceCache::bump();
    }
}
