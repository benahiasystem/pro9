<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\Settings;
use Modules\Marketplace\Support\PublicPresenter;

class MarketplaceController extends Controller
{
    /**
     * La pantalla pública. Una sola, para todo.
     */
    public function index(Request $request): View
    {
        $item = $this->initialItem($request);

        return view('marketplace::public.index', [
            'meta' => $this->meta(),
            // Deep-link ?p={id}: el producto se resuelve en el servidor para
            // que el modal abra sin un viaje extra al feed.
            'boot' => $this->boot([
                'initial_item' => $item,
                'recommended_ids' => $this->recommendedIds($request, $item),
            ]),
        ]);
    }

    /**
     * La MISMA pantalla, precargada con esa tienda. Es el enlace compartible,
     * y su razón de ser son los Open Graph tags: es lo que se ve al pegarlo
     * en WhatsApp.
     *
     * Una tienda no aprobada responde 410 Gone, no 404: el enlace ya circuló
     * y merece una salida amable en vez de un error seco.
     */
    public function store(Request $request, string $slug)
    {
        $store = Store::where('slug', $slug)->first();

        // Una tienda auto-ocultada («Ocultar mi tienda») recibe el mismo 410
        // amable que una no aprobada: para el visitante, no existe.
        if (! $store || ! $store->isPubliclyVisible()) {
            return response()->view('marketplace::public.index', [
                'meta' => $this->meta(null, true),
                'boot' => $this->boot(['gone' => true]),
            ], 410);
        }

        $item = $this->initialItem($request);

        return view('marketplace::public.index', [
            'meta' => $this->meta($store),
            'boot' => $this->boot([
                'store' => PublicPresenter::store($store),
                'initial_item' => $item,
                'recommended_ids' => $this->recommendedIds($request, $item),
            ]),
        ]);
    }

    /**
     * Siembra el pulgar del producto del deep-link. El feed marca los de cada
     * página, pero el producto abierto con ?p= podría no estar en la primera, y
     * el modal abre antes de que llegue el feed: sin esto, su pulgar aparecería
     * apagado un instante aunque el visitante ya lo hubiera recomendado.
     */
    private function recommendedIds(Request $request, ?array $item): array
    {
        return \Modules\Marketplace\Models\Recommendation::recommendedItemIds(
            \Modules\Marketplace\Http\Middleware\EnsureMarketplaceVisitor::id($request),
            $item ? [$item['id']] : []
        );
    }

    /**
     * Producto del deep-link ?p={id}. Pasa por PublishedScope, así que un id
     * de algo bloqueado o de una tienda no aprobada devuelve null.
     */
    private function initialItem(Request $request): ?array
    {
        if (! $request->filled('p')) {
            return null;
        }

        $item = Item::with(['store', 'category'])->find($request->input('p'));

        return $item ? PublicPresenter::item($item) : null;
    }

    private function meta(?Store $store = null, bool $gone = false): array
    {
        if ($gone) {
            return [
                'title' => 'Esta tienda ya no está disponible',
                'description' => 'El enlace pertenece a una tienda que ya no forma parte del marketplace.',
                'image' => null,
                'noindex' => true,
            ];
        }

        if ($store) {
            return [
                'title' => $store->name . ' · ' . Settings::get('title', 'Marketplace'),
                'description' => $store->description ?: Settings::get('description', ''),
                'image' => $this->url($store->logo_path),
                'noindex' => false,
            ];
        }

        return [
            'title' => Settings::get('title', 'Marketplace'),
            'description' => Settings::get('description', ''),
            'image' => null,
            'noindex' => false,
        ];
    }

    /**
     * Lo que se inyecta en window.__marketplace. Solo ajustes públicos: nada
     * de max_items_per_store ni de otras claves que son del admin.
     */
    private function boot(array $extra = []): array
    {
        return array_merge([
            'settings' => [
                'title' => Settings::get('title'),
                'description' => Settings::get('description'),
                'community_name' => Settings::get('community_name'),
                'hero_title' => Settings::get('hero_title'),
                'hero_highlight' => Settings::get('hero_highlight'),
                'items_per_page' => Settings::get('items_per_page'),
                'report_reasons' => Settings::get('report_reasons', []),
                'whatsapp_cart_greeting' => Settings::get('whatsapp_cart_greeting'),
                'currency_symbol' => Settings::get('currency_symbol', 'S/'),
                // Para el banner de datos personales (Ley 29733). Vacío = el
                // enlace ARCO no se muestra.
                'arco_email' => Settings::get('arco_email', ''),
                // El front avisa a pantalla completa al cruzar la mitad de
                // este límite; la autoridad real es el servidor.
                'contact_limit' => (int) Settings::get('contact_limit_per_hour', 10),
            ],
            'terms_url' => Settings::termsUrl(),
            'prefix' => config('marketplace.route_prefix'),
            'gone' => false,
            'store' => null,
            'initial_item' => null,
            'recommended_ids' => [],
        ], $extra);
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::disk(config('marketplace.disk'))->url($path) : null;
    }
}
