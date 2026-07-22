<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketplace\Http\Middleware\EnsureMarketplaceVisitor;
use Modules\Marketplace\Models\Category;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Recommendation;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\MarketplaceCache;
use Modules\Marketplace\Services\Settings;
use Modules\Marketplace\Support\PublicPresenter;

/**
 * El único endpoint de lectura del front.
 *
 * `Item` lleva PublishedScope como global scope, así que nada de lo que sale
 * de aquí puede pertenecer a una tienda no aprobada ni a un ítem bloqueado o
 * inactivo, aunque a alguien se le olvide filtrarlo.
 */
class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'categoria' => $request->input('categoria'),
            'tienda' => $request->input('tienda'),
            'tab' => $request->input('tab') === 'tiendas' ? 'tiendas' : 'productos',
            // Único orden alternativo. Se normaliza a null para que el cache no
            // guarde una entrada distinta por cada valor inventado.
            'orden' => $request->input('orden') === 'recomendados' ? 'recomendados' : null,
            'page' => max(1, (int) $request->input('page', 1)),
        ];

        // Sugerencias del buscador: mismo endpoint, respuesta mínima.
        if ($request->boolean('suggest')) {
            return response()->json($this->suggestions($filters['q']));
        }

        $key = 'feed:' . md5(json_encode($filters));

        $payload = MarketplaceCache::remember($key, 600, fn () => $this->payload($filters));

        // Detección de barrido (plan de seguridad, A2.5): una IP que recorre
        // muchas páginas distintas del feed en un minuto no es un vecino que
        // navega, es un script que descarga el directorio. No se bloquea (el
        // throttle de la ruta ya pone el techo): se deja constancia al admin.
        $this->detectSweep($request, $filters['page'], (int) ($payload['products']['last_page'] ?? 1));

        // Qué ha recomendado ESTE visitante se resuelve fuera del cache: el
        // payload es igual para todos y se comparte entre miles de visitantes,
        // pero el pulgar encendido es de cada uno. Mezclarlos haría que un
        // visitante viera los pulgares de otro.
        $payload['recommended'] = $this->recommendedByVisitor($request, $payload);

        return response()->json($payload);
    }

    /**
     * Ids de los productos de esta respuesta que el visitante ya recomendó.
     * Una sola consulta sobre el índice único (visitor_id, item_id).
     */
    private function recommendedByVisitor(Request $request, array $payload): array
    {
        return Recommendation::recommendedItemIds(
            EnsureMarketplaceVisitor::id($request),
            array_column($payload['products']['data'] ?? [], 'id')
        );
    }

    private function payload(array $filters): array
    {
        $perPage = (int) Settings::get('items_per_page', 24);

        $products = $this->products($filters)->paginate($perPage, ['*'], 'page', $filters['page']);
        $storesTotal = $this->stores($filters)->count();

        return [
            'products' => [
                // ->all() y no ->values(): el payload se cachea, y al releerlo
                // de Redis vuelve como array. Dejarlo como Collection haría que
                // el mismo dato fuera Collection en cache-miss y array en
                // cache-hit, y el overlay del visitante (array_column) reventaría
                // en una de las dos ramas.
                'data' => $products->getCollection()->map(fn (Item $i) => PublicPresenter::item($i))->values()->all(),
                'total' => $products->total(),
                'page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
            ],
            'stores' => $filters['tab'] === 'tiendas'
                ? $this->storeCards($filters)
                : [],
            'totals' => [
                'products' => $products->total(),
                'stores' => $storesTotal,
            ],
            'categories' => $this->categories($filters['tienda']),
        ];
    }

    // ---------------------------------------------------------------------
    // Consultas
    // ---------------------------------------------------------------------

    private function products(array $filters)
    {
        $query = Item::with(['store', 'category']);

        if ($filters['orden'] === 'recomendados') {
            // Los productos se ordenan por el aval de SU TIENDA, no por sus
            // propias recomendaciones: el reconocimiento es de la tienda, y así
            // un producto suelto muy recomendado de un negocio desconocido no
            // adelanta al catálogo de una tienda avalada. Un join —no whereHas—
            // porque hay que ordenar por su columna. select() para que las
            // columnas de la tienda no pisen las del producto.
            $query->join('marketplace_stores as ord_s', 'ord_s.id', '=', 'marketplace_items.store_id')
                ->select('marketplace_items.*')
                ->orderByRaw($this->rankingOrder('ord_s.recommendations_count'), [$this->threshold()])
                ->orderBy('marketplace_items.name');
        } else {
            $query->orderBy('name');
        }

        if ($filters['q'] !== '') {
            $needle = $this->normalize($filters['q']);
            $query->where(function ($sub) use ($needle, $filters) {
                $sub->where('name_normalized', 'like', "%{$needle}%")
                    ->orWhere('internal_code', 'like', $filters['q'] . '%')
                    ->orWhere('barcode', 'like', $filters['q'] . '%');
            });
        }

        if ($filters['categoria']) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $filters['categoria']));
        }

        if ($filters['tienda']) {
            $query->whereHas('store', fn ($s) => $s->where('slug', $filters['tienda']));
        }

        return $query;
    }

    private function stores(array $filters)
    {
        // visible() y no approved(): una tienda auto-ocultada no aparece.
        $query = Store::visible();

        $filters['orden'] === 'recomendados'
            ? $query->orderByRaw($this->rankingOrder('recommendations_count'), [$this->threshold()])->orderBy('name')
            : $query->orderBy('name');

        if ($filters['q'] !== '') {
            $query->where('name_normalized', 'like', '%' . $this->normalize($filters['q']) . '%');
        }

        return $query;
    }

    private function storeCards(array $filters): array
    {
        $stores = $this->stores($filters)->get();

        if ($stores->isEmpty()) {
            return [];
        }

        // Categoría principal = la que más ítems publicados aporta a la tienda.
        // Se resuelve en una sola consulta para no caer en N+1.
        $main = DB::connection('system')->table('marketplace_items as i')
            ->join('marketplace_categories as c', 'c.id', '=', 'i.category_id')
            ->select('i.store_id', 'c.name', DB::raw('COUNT(*) as total'))
            ->whereIn('i.store_id', $stores->pluck('id'))
            ->where('i.status', Item::STATUS_ACTIVE)
            ->groupBy('i.store_id', 'c.name')
            ->orderByDesc('total')
            ->get()
            ->groupBy('store_id')
            ->map(fn ($rows) => $rows->first()->name);

        return $stores->map(fn (Store $s) => PublicPresenter::store($s, $main[$s->id] ?? null))->values()->all();
    }

    /**
     * Solo las visibles y con productos: una categoría vacía en el filtro es
     * un callejón sin salida.
     */
    private function categories(?string $storeSlug): array
    {
        return Category::visible()
            ->where('items_count', '>', 0)
            ->orderByDesc('items_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'items_count' => $c->items_count,
            ])
            ->values()
            ->all();
    }

    private function suggestions(string $q): array
    {
        if ($q === '') {
            return ['products' => [], 'stores' => []];
        }

        $needle = $this->normalize($q);

        $products = Item::with(['store', 'category'])
            ->where(function ($sub) use ($needle, $q) {
                $sub->where('name_normalized', 'like', "%{$needle}%")
                    ->orWhere('internal_code', 'like', $q . '%');
            })
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Item $i) => PublicPresenter::item($i));

        $stores = Store::visible()
            ->where('name_normalized', 'like', "%{$needle}%")
            ->orderBy('name')
            ->limit(3)
            ->get()
            ->map(fn (Store $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'initials' => PublicPresenter::initials($s->name),
            ]);

        return ['products' => $products->values(), 'stores' => $stores->values()];
    }

    private function normalize(string $value): string
    {
        return Str::ascii(mb_strtolower(trim($value)));
    }

    /**
     * Registra en un set por IP las páginas pedidas en la última ventana de
     * 60 s. Si cubre 5+ páginas distintas (y el feed tiene más de una), crea
     * una alerta de seguridad — como mucho una por IP al día, para no inundar.
     */
    private function detectSweep(Request $request, int $page, int $lastPage): void
    {
        if ($lastPage < 2) {
            return;
        }

        $ip = (string) $request->ip();
        $key = 'mkt:sweep:' . $ip;

        $pages = \Illuminate\Support\Facades\Cache::get($key, []);
        $pages[$page] = true;
        \Illuminate\Support\Facades\Cache::put($key, $pages, 60);

        $threshold = min($lastPage, 5);

        if (count($pages) < $threshold) {
            return;
        }

        // Candado diario: la primera detección del día es la que avisa.
        if (! \Illuminate\Support\Facades\Cache::add('mkt:sweep-alerted:' . $ip, 1, 86400)) {
            return;
        }

        \Modules\Marketplace\Models\SecurityAlert::create([
            'type' => \Modules\Marketplace\Models\SecurityAlert::TYPE_FEED_SWEEP,
            'message' => "La IP {$ip} recorrió {$threshold}+ páginas del feed en menos de un minuto (patrón de scraper).",
            'meta' => ['ip' => $ip, 'pages' => array_keys($pages), 'last_page' => $lastPage],
        ]);
    }

    /**
     * Cuántos avales hacen falta para que una tienda cuente en el orden. Por
     * debajo, se la trata como 0 y no gana posición.
     */
    private function threshold(): int
    {
        return (int) Settings::get('ranking_threshold', 0);
    }

    /**
     * Expresión de orden que anula el ranking por debajo del umbral: una tienda
     * con menos avales que el mínimo cae al mismo grupo que las de cero y se
     * ordena por nombre, no por su puñado de recomendaciones. Es lo que evita
     * que un negocio con dos pulgares adelante a los demás.
     */
    private function rankingOrder(string $column): string
    {
        return "CASE WHEN {$column} >= ? THEN {$column} ELSE 0 END DESC";
    }
}
