<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Models\Tenant\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FrequentlyBoughtTogetherService
{
    public const CACHE_TTL_SECONDS = 1800;
    public const LOOKBACK_ORDERS = 800;
    public const DEFAULT_LIMIT = 8;

    /**
     * Productos frecuentemente comprados junto con $itemId, basados en pedidos reales.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forItem(int $itemId, int $limit = self::DEFAULT_LIMIT): array
    {
        $itemId = (int) $itemId;
        $limit = max(1, min(16, $limit));

        if ($itemId <= 0) {
            return [];
        }

        $cacheKey = "fbt:item:{$itemId}:limit:{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($itemId, $limit) {
            $pairCounts = $this->buildPairCounts($itemId);
            if ($pairCounts->isEmpty()) {
                return [];
            }

            $topIds = $pairCounts
                ->sortDesc()
                ->keys()
                ->take($limit)
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($topIds === []) {
                return [];
            }

            $items = Item::query()
                ->with('currency_type')
                ->whereIn('id', $topIds)
                ->where('apply_store', 1)
                ->get()
                ->keyBy('id');

            $result = [];
            foreach ($topIds as $id) {
                $item = $items->get($id);
                if (! $item) {
                    continue;
                }

                $result[] = [
                    'id' => $item->id,
                    'description' => $item->description,
                    'slug' => Str::slug($item->description),
                    'sale_unit_price' => (float) $item->sale_unit_price,
                    'currency_symbol' => optional($item->currency_type)->symbol ?: 'Bs.',
                    'image' => $item->image ?: 'imagen-no-disponible.jpg',
                    'image_url' => $this->resolveImageUrl($item->image),
                    'url' => url('/ecommerce/item/'.$item->id.'/'.Str::slug($item->description)),
                    'times_bought_together' => (int) $pairCounts->get($id, 0),
                ];
            }

            return $result;
        });
    }

    /**
     * Agrega co-ocurrencias para varios productos (checkout / carrito).
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array<string, mixed>>
     */
    public function forItems(array $itemIds, int $limit = self::DEFAULT_LIMIT): array
    {
        $itemIds = collect($itemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        $scores = [];
        foreach ($itemIds as $id) {
            foreach ($this->forItem($id, $limit * 2) as $row) {
                $otherId = (int) $row['id'];
                if (in_array($otherId, $itemIds, true)) {
                    continue;
                }
                if (! isset($scores[$otherId])) {
                    $scores[$otherId] = $row;
                    $scores[$otherId]['times_bought_together'] = 0;
                }
                $scores[$otherId]['times_bought_together'] += (int) $row['times_bought_together'];
            }
        }

        return collect($scores)
            ->sortByDesc('times_bought_together')
            ->take($limit)
            ->values()
            ->all();
    }

    protected function buildPairCounts(int $itemId): Collection
    {
        $needle = (string) $itemId;

        $orders = Order::query()
            ->select(['id', 'items'])
            ->whereNotNull('items')
            ->where(function ($q) use ($needle) {
                // Prefiltro barato sobre JSON serializado (evita escanear todo el payload en PHP).
                $q->where('items', 'like', '%"id":'.$needle.'%')
                    ->orWhere('items', 'like', '%"id":"'.$needle.'"%');
            })
            ->orderByDesc('id')
            ->limit(self::LOOKBACK_ORDERS)
            ->get();

        $counts = collect();

        foreach ($orders as $order) {
            $ids = $this->extractItemIds($order->items);
            if ($ids === [] || ! in_array($itemId, $ids, true)) {
                continue;
            }

            foreach ($ids as $otherId) {
                if ($otherId === $itemId) {
                    continue;
                }
                $counts[$otherId] = ((int) $counts->get($otherId, 0)) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  mixed  $items
     * @return array<int, int>
     */
    protected function extractItemIds($items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = $decoded;
        }

        if (is_object($items)) {
            $items = (array) $items;
        }

        if (! is_array($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? $row['item_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function resolveImageUrl(?string $image): string
    {
        if (! $image || $image === 'imagen-no-disponible.jpg') {
            return asset('logo/imagen-no-disponible.jpg');
        }

        return asset('storage/uploads/items/'.$image);
    }
}
