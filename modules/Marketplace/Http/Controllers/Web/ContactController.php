<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Marketplace\Http\Middleware\EnsureMarketplaceVisitor;
use Modules\Marketplace\Http\Requests\ContactRequest as ContactFormRequest;
use Modules\Marketplace\Models\ContactRequest;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\PowChallenge;
use Modules\Marketplace\Services\Settings;
use Modules\Marketplace\Services\WhatsAppLink;

/**
 * La puerta de contacto: el ÚNICO lugar donde nace un enlace de WhatsApp.
 *
 * El número de la tienda nunca viaja al navegador (se quitó de
 * PublicPresenter); quien quiere escribirle pasa por aquí y deja rastro:
 * nombre, WhatsApp declarado, cookie firmada e IP. Encima, un proof-of-work
 * (PowChallenge) y un throttle por visitante. Plan de seguridad, A2.
 */
class ContactController extends Controller
{
    /**
     * GET /marketplace/desafio — un desafío PoW fresco para el modal.
     */
    public function challenge(): JsonResponse
    {
        return response()->json(PowChallenge::issue());
    }

    /**
     * POST /marketplace/contacto — valida, registra y devuelve el enlace.
     */
    public function store(ContactFormRequest $request): JsonResponse
    {
        if (! PowChallenge::verify($request->input('pow'))) {
            return response()->json([
                'message' => 'La verificación expiró o no es válida. Inténtalo de nuevo.',
            ], 422);
        }

        $visitorId = EnsureMarketplaceVisitor::id($request) ?? 'anon';
        $limit = max(1, (int) Settings::get('contact_limit_per_hour', 10));
        $key = 'mkt-contact:' . $visitorId . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json([
                'message' => 'Alcanzaste el límite de contactos por ahora. Vuelve a intentarlo más tarde.',
                'throttle' => ['used' => $limit, 'limit' => $limit],
            ], 429);
        }

        $store = Store::approved()
            ->whereNull('hidden_at')
            ->where('slug', $request->input('store'))
            ->first();

        if (! $store) {
            return response()->json(['message' => 'Esta tienda ya no está disponible.'], 410);
        }

        [$waLink, $itemId, $itemsSnapshot] = $this->resolveTarget($request, $store);

        if ($waLink === null) {
            return response()->json(['message' => 'El producto ya no está disponible.'], 410);
        }

        // El intento cuenta solo cuando va a generar un enlace de verdad: un
        // 410 o un PoW inválido no consumen cupo del vecino.
        RateLimiter::hit($key, 3600);

        $contact = ContactRequest::create([
            'store_id' => $store->id,
            'item_id' => $itemId,
            'type' => $request->input('type'),
            'items' => $itemsSnapshot,
            'buyer_name' => trim((string) $request->input('name')),
            'buyer_whatsapp' => $request->input('whatsapp'),
            'visitor_id' => $visitorId,
            'ip' => (string) $request->ip(),
        ]);

        return response()->json([
            'code' => $contact->code(),
            'wa_link' => $waLink,
            // El front muestra el aviso a pantalla completa al cruzar el 50%.
            'throttle' => [
                'used' => $limit - RateLimiter::remaining($key, $limit),
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Resuelve el enlace según el tipo. Los nombres del pedido se releen de la
     * base (no del cliente): el mensaje que recibe la tienda no es falsificable.
     *
     * @return array{0: ?string, 1: ?int, 2: ?array}
     */
    private function resolveTarget(Request $request, Store $store): array
    {
        switch ($request->input('type')) {
            case 'product':
                // Item lleva PublishedScope: un id bloqueado/inactivo da null.
                $item = Item::where('store_id', $store->id)->find($request->input('item_id'));

                return $item
                    ? [WhatsAppLink::forItem($item, $store), $item->id, null]
                    : [null, null, null];

            case 'cart':
                $wanted = collect($request->input('items', []))->keyBy('id');

                $items = Item::where('store_id', $store->id)
                    ->whereIn('id', $wanted->keys())
                    ->get();

                if ($items->isEmpty()) {
                    return [null, null, null];
                }

                $lines = $items->map(fn (Item $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'code' => $i->internal_code,
                    'qty' => (int) $wanted[$i->id]['qty'],
                ])->values()->all();

                return [WhatsAppLink::forCart($store, $lines), null, $lines];

            default: // 'store'
                return [WhatsAppLink::forStore($store), null, null];
        }
    }
}
