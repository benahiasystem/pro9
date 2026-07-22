<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Report;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\CatalogCounters;
use Modules\Marketplace\Services\MarketplaceCache;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = Report::with(['store', 'item'])
            ->where('status', $request->input('status', Report::STATUS_OPEN))
            ->orderByDesc('created_at')
            ->paginate(20);

        $reports->getCollection()->transform(function (Report $r) {
            $disk = Storage::disk(config('marketplace.disk'));

            return [
                'id' => $r->id,
                'reason' => $r->reason,
                'ip' => $r->ip,
                'status' => $r->status,
                'created_at' => $r->created_at?->format('d/m/Y H:i'),
                'store' => $r->store ? [
                    'id' => $r->store->id,
                    'name' => $r->store->name,
                    'status' => $r->store->status,
                    'reports_count' => $r->store->reports_count,
                ] : null,
                'item' => $r->item ? [
                    'id' => $r->item->id,
                    'name' => $r->item->name,
                    'internal_code' => $r->item->internal_code,
                    'status' => $r->item->status,
                    'reports_count' => $r->item->reports_count,
                    'image_url' => $r->item->image_path ? $disk->url($r->item->image_path) : null,
                ] : null,
            ];
        });

        return response()->json($reports);
    }

    public function dismiss(int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        $report->status = Report::STATUS_DISMISSED;
        $report->save();

        // Descartar no cambia nada de lo público, pero se invalida igual para
        // que el contador de denuncias del admin quede consistente.
        MarketplaceCache::bump();

        return response()->json(['success' => true, 'message' => 'Denuncia descartada.']);
    }

    /**
     * El bloqueo es persistente: StoreSyncService nunca lo revierte, así que un
     * producto bloqueado sigue bloqueado por muchos syncs que lleguen. Solo se
     * deshace desde aquí.
     */
    public function blockItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['reason' => 'motivo']);

        $item = Item::unscoped()->findOrFail($id);

        $item->status = Item::STATUS_BLOCKED;
        $item->blocked_at = now();
        $item->blocked_reason = $data['reason'];
        $item->save();

        CatalogCounters::refreshAll($item->store_id);
        MarketplaceCache::bump();

        return response()->json([
            'success' => true,
            'message' => 'Producto bloqueado. No volverá a publicarse aunque la tienda lo resincronice.',
        ]);
    }

    public function unblockItem(int $id): JsonResponse
    {
        $item = Item::unscoped()->findOrFail($id);

        $item->status = Item::STATUS_ACTIVE;
        $item->blocked_at = null;
        $item->blocked_reason = null;
        $item->save();

        CatalogCounters::refreshAll($item->store_id);
        MarketplaceCache::bump();

        return response()->json(['success' => true, 'message' => 'Producto desbloqueado.']);
    }
}
