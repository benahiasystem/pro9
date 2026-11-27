<?php

namespace App\Services\Storage;

use App\Models\Tenant\Item;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GarageItemImages
{
    public function __construct(private ItemImageDelivery $images)
    {
    }

    public function urls(Request $request, iterable $items): array
    {
        if (!$request->boolean('garage_thumbnails')) {
            return [];
        }
        $urls = [];
        foreach ($this->images->variantUrls($request, $items, true) as $id => $links) {
            $urls[$id] = [
                'garage_thumbnail_url' => $links['small'] ?? $links['original'],
                'garage_original_url' => isset($links['small']) ? ($links['original'] ?? null) : null,
            ];
        }
        return $urls;
    }

    public function response(Request $request, Item $item, string $variant, string $token): StreamedResponse
    {
        return $this->images->response($request, $item, $variant, $token, true);
    }
}
