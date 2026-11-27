<?php

namespace App\Services\Storage;

use App\Models\Storage\StorageObject;
use App\Models\Tenant\Item;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GarageItemImages
{
    private const FIELDS = ['small' => 'image_small', 'original' => 'image'];
    private const RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    public function __construct(private TenantStorage $storage)
    {
    }

    public function urls(Request $request, iterable $items): array
    {
        if (!$request->boolean('garage_thumbnails') || !$request->hasSession() || !ItemMedia::employee()) {
            return [];
        }

        $pathsByItem = [];
        foreach ($items as $item) {
            foreach (self::FIELDS as $variant => $field) {
                if ($path = $this->path($item->$field)) {
                    $pathsByItem[$item->id][$variant] = $path;
                }
            }
        }

        $paths = array_unique(collect($pathsByItem)->flatten()->all());
        $objects = collect();
        foreach (array_chunk($paths, 300) as $chunk) {
            $objects = $objects->merge(StorageObject::whereIn('path', $chunk)
                ->whereIn('mime', self::RASTER_TYPES)->get(['path', 'version'])->keyBy('path'));
        }

        $urls = [];
        foreach ($pathsByItem as $id => $variants) {
            $links = [];
            foreach ($variants as $variant => $path) {
                $object = $objects->get($path);
                if ($object) {
                    $links[$variant] = route('tenant.pos.garage.image', [
                        'itemId' => $id,
                        'variant' => $variant,
                        'token' => $this->token($request, $id, $variant, $object),
                    ], false);
                }
            }
            if ($links) {
                $urls[$id] = [
                    'garage_thumbnail_url' => $links['small'] ?? $links['original'],
                    'garage_original_url' => isset($links['small']) ? ($links['original'] ?? null) : null,
                ];
            }
        }

        return $urls;
    }

    public function response(Request $request, Item $item, string $variant, string $token): StreamedResponse
    {
        $path = $this->path($item->{self::FIELDS[$variant]});
        $object = $path ? StorageObject::whereIn('mime', self::RASTER_TYPES)->find($path) : null;
        abort_unless($object && hash_equals($this->token($request, $item->id, $variant, $object), $token), 404);

        $stream = null;
        $response = response()->stream(function () use (&$stream) {
            if (!is_resource($stream)) {
                return;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $object->mime,
            'Content-Length' => $object->size,
            'Cache-Control' => 'private, max-age=300',
            'CDN-Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag($object->version);
        if (!$response->isNotModified($request) && !$request->isMethod('HEAD')) {
            // Read the immutable version authorized above, even if the logical path changes concurrently.
            $stream = $this->storage->physicalForUuid($this->storage->uuid())->readStream($object->object_key);
            abort_unless(is_resource($stream), 503);
        }

        return $response;
    }

    private function path(?string $name): ?string
    {
        if (!$name || $name === 'imagen-no-disponible.jpg' || basename($name) !== $name) {
            return null;
        }

        try {
            return TenantDisk::relative('uploads/items/'.$name);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function token(Request $request, int $id, string $variant, StorageObject $object): string
    {
        return hash_hmac('sha256', json_encode([
            'garage-item-image', $this->storage->uuid(), $request->user()->id,
            $request->session()->getId(), $id, $variant, $object->path, $object->version,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
