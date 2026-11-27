<?php

namespace App\Services\Storage;

use App\Models\Storage\StorageObject;
use App\Models\Tenant\Item;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ItemImageDelivery
{
    private const FIELDS = ['small' => 'image_small', 'medium' => 'image_medium', 'original' => 'image'];
    private const RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    public function __construct(private TenantStorage $storage)
    {
    }

    public function variantUrls(Request $request, iterable $items, bool $garage = false): array
    {
        if (!$request->hasSession() || !ItemMedia::employee()) {
            return [];
        }

        $pathsByItem = [];
        foreach ($items as $item) {
            foreach (self::FIELDS as $variant => $field) {
                if ((!$garage || $variant !== 'medium') && ($path = $this->path($item->$field))) {
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
                    $links[$variant] = route($garage ? 'tenant.pos.garage.image' : 'tenant.media.item', [
                        'itemId' => $id,
                        'variant' => $variant,
                        'token' => $this->token($request, $id, $variant, $object, $garage),
                    ], false);
                }
            }
            if ($links) {
                $urls[$id] = $links;
            }
        }

        return $urls;
    }

    public function response(Request $request, Item $item, string $variant, string $token, bool $garage = false): StreamedResponse
    {
        $path = $this->path($item->{self::FIELDS[$variant]});
        $object = $path ? StorageObject::whereIn('mime', self::RASTER_TYPES)->find($path) : null;
        abort_unless($object && hash_equals($this->token($request, $item->id, $variant, $object, $garage), $token), 404);

        return app(VersionedImage::class)->response($request, $object);
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

    private function token(Request $request, int $id, string $variant, StorageObject $object, bool $garage): string
    {
        return hash_hmac('sha256', json_encode([
            $garage ? 'garage-item-image' : 'tenant-item-image', $this->storage->uuid(), $request->user()->id,
            $request->session()->getId(), $id, $variant, $object->path, $object->version,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
