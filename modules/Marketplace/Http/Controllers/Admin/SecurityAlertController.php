<?php

namespace Modules\Marketplace\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Marketplace\Models\SecurityAlert;

/**
 * Alertas de seguridad (plan de seguridad, A4.2): cambios de WhatsApp
 * post-aprobación, barridos del feed, resets de credencial.
 */
class SecurityAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SecurityAlert::with('store')->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $alerts = $query->paginate(30);

        $alerts->getCollection()->transform(fn (SecurityAlert $a) => [
            'id' => $a->id,
            'type' => $a->type,
            'message' => $a->message,
            'store' => $a->store?->name,
            'store_id' => $a->store_id,
            'meta' => $a->meta,
            'read_at' => $a->read_at?->format('d/m/Y H:i'),
            'created_at' => $a->created_at?->format('d/m/Y H:i'),
        ]);

        return response()->json($alerts);
    }

    public function markRead(int $id): JsonResponse
    {
        $alert = SecurityAlert::findOrFail($id);

        if ($alert->read_at === null) {
            $alert->read_at = now();
            $alert->save();
        }

        return response()->json(['success' => true]);
    }
}
