<?php

namespace App\Services\Storage;

use App\Models\Storage\StorageObject;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VersionedImage
{
    public function __construct(private TenantStorage $storage)
    {
    }

    public function token(Request $request, string $route, StorageObject $object): string
    {
        $user = $request->user('web');
        $employee = $user instanceof \App\Models\Tenant\User && $user->isActive();
        return hash_hmac('sha256', json_encode([
            $this->storage->uuid(), $route, $object->path, $object->version,
            $employee ? $user->getAuthIdentifier() : null, $employee ? $request->session()->getId() : null,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }

    /** Public catalog pages may render links in bulk. The image route checks publication on every fetch. */
    public function catalogUrls(iterable $items, bool $restaurant = false): array
    {
        $paths = [];
        foreach ($items as $item) {
            foreach (['image', 'image_small', 'image_medium'] as $field) {
                $name = data_get($item, $field);
                if ($name && $name !== 'imagen-no-disponible.jpg' && basename($name) === $name) {
                    $paths['uploads/items/'.$name] = $name;
                }
            }
        }
        $route = $restaurant ? 'tenant.media.restaurantImage' : 'tenant.media.image';
        $urls = [];
        foreach (array_chunk(array_keys($paths), 300) as $chunk) {
            foreach (StorageObject::whereIn('path', $chunk)->get(['path','version']) as $object) {
                $name = $paths[$object->path];
                $urls[$name] = route($route, [
                    'folder' => 'items', 'filename' => $name, 'v' => $this->token(request(), $route, $object),
                ]);
            }
        }
        return $urls;
    }

    public function response(Request $request, StorageObject $object): StreamedResponse
    {
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
            'Cache-Control' => 'private, max-age=1800',
            'CDN-Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
        $response->setEtag($object->version);
        if (!$response->isNotModified($request) && !$request->isMethod('HEAD')) {
            // Read the immutable version authorized above, even if the logical path changes concurrently.
            $stream = $this->storage->physicalForUuid($this->storage->uuid())->readStream($object->object_key);
            abort_unless(is_resource($stream), 503);
        }

        return $response;
    }

}
