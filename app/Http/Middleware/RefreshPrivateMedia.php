<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\Item;
use App\Services\Storage\{ItemImageDelivery, ItemMedia, TenantStorage, VersionedImage};
use App\Tenancy\Environment;
use Closure;
use Illuminate\Http\JsonResponse;

/** Keep cached catalog data independent of the requesting tenant session. */
final class RefreshPrivateMedia
{
    private const VARIANTS = [
        'image_url' => ['original', 'image'],
        'image_url_medium' => ['medium', 'image_medium'],
        'image_url_small' => ['small', 'image_small'],
    ];

    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if (!$response instanceof JsonResponse || $response->getStatusCode() >= 400 || !app(Environment::class)->tenant()) {
            return $response;
        }
        $data = $response->getData(true);
        if (!is_array($data)) return $response;

        $variants = self::VARIANTS;
        if (!$request->hasSession()) {
            // Older mobile catalog clients read the original URL from "image".
            $variants['image'] = ['original', 'image'];
        }
        $names = [];
        array_walk_recursive($data, function ($entry, $key) use (&$names, $variants) {
            if (isset($variants[$key]) && ($name = $this->filename($entry))) $names[$name] = true;
        });
        if (!$names) return $response;

        $employee = ItemMedia::employee();
        $items = [];
        $owners = [];
        if ($employee || (ItemMedia::ecommerceEnabled() && !optional(Configuration::first())->isLockedTenant())) {
            foreach (array_chunk(array_keys($names), 300) as $chunk) {
                $query = Item::withoutEagerLoads()->where(fn ($q) => $q->whereIn('image', $chunk)
                    ->orWhereIn('image_medium', $chunk)->orWhereIn('image_small', $chunk));
                if (!$employee) $query->where('active', 1)->where('apply_store', 1);
                foreach ($query->get(['id','image','image_medium','image_small']) as $item) {
                    $items[$item->id] = $item;
                    foreach (self::VARIANTS as [, $field]) {
                        if ($item->$field) $owners[$item->$field] = $item->id;
                    }
                }
            }
        }
        $web = $request->hasSession();
        $private = $web && $employee ? app(ItemImageDelivery::class)->variantUrls($request, $items) : [];
        $public = $web && !$employee ? app(VersionedImage::class)->catalogUrls($items) : [];
        $temporary = ['urls' => [], 'expires_at' => null];
        if (!$web && $owners) {
            $paths = array_map(fn ($name) => 'uploads/items/'.$name, array_keys(array_intersect_key($names, $owners)));
            $temporary = app(TenantStorage::class)->disk()->temporaryUrls($paths);
        }

        $refresh = function (array &$row) use (&$refresh, $items, $owners, $web, $private, $public, $temporary, $variants) {
            foreach ($row as $key => &$entry) {
                if (is_array($entry)) {
                    $refresh($entry);
                    continue;
                }
                if (!isset($variants[$key]) || !($name = $this->filename($entry))) continue;
                [$variant, $field] = $variants[$key];
                $id = $owners[$name] ?? null;
                if (!$id) {
                    $entry = asset('logo/imagen-no-disponible.jpg');
                    continue;
                }
                if (!$web) {
                    $url = $temporary['urls']['uploads/items/'.$name] ?? null;
                    $entry = $url ?? asset('logo/imagen-no-disponible.jpg');
                    if ($url) {
                        $row['image_media_id'] = $id;
                        $row['image_urls_expires_at'] = $temporary['expires_at'];
                    }
                    continue;
                }
                $rowId = $row['image_media_id'] ?? $row['id'] ?? null;
                if (is_numeric($rowId) && isset($items[$rowId]) && $items[$rowId]->$field === $name) $id = $rowId;
                $item = $items[$id];
                $entry = ($item->$field === $name ? ($private[$id][$variant] ?? null) : null) ?? $public[$name] ?? $entry;
                if ($key === 'image_url' && !isset($row['image_url_small']) && $item->image === $name) {
                    $small = $private[$id]['small'] ?? $public[$item->image_small] ?? null;
                    if ($small) $row['image_url_small'] = $small;
                }
                unset($row['image_urls_expires_at']);
            }
        };
        $refresh($data);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response->setData($data);
    }

    private function filename($url): ?string
    {
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;
        $prefix = '/media/images/items/';
        if (!is_string($path) || !str_starts_with($path, $prefix)) return null;
        $name = rawurldecode(substr($path, strlen($prefix)));
        return $name !== '' && basename($name) === $name ? $name : null;
    }
}
