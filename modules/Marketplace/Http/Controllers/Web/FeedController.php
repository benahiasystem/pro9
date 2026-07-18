<?php

namespace Modules\Marketplace\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketplace\Models\Category;
use Modules\Marketplace\Models\Item;
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
            'page' => max(1, (int) $request->input('page', 1)),
        ];

        // Sugerencias del buscador: mismo endpoint, respuesta mínima.
        if ($request->boolean('suggest')) {
            return response()->json($this->suggestions($filters['q']));
        }

        $key = 'feed:' . md5(json_encode($filters));

        return response()->json(
            MarketplaceCache::remember($key, 600, fn () => $this->payload($filters))
        );
    }

    private function payload(array $filters): array
    {
        $perPage = (int) Settings::get('items_per_page', 24);

        $products = $this->products($filters)->paginate($perPage, ['*'], 'page', $filters['page']);
        $storesTotal = $this->stores($filters)->count();

        return [
            'products' => [
                'data' => $products->getCollection()->map(fn (Item $i) => PublicPresenter::item($i))->values(),
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
        $query = Item::with(['store', 'category'])->orderBy('name');

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
        $query = Store::approved()->orderBy('name');

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

        $stores = Store::approved()
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
}
