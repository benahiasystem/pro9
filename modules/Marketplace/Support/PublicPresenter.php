<?php

namespace Modules\Marketplace\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\WhatsAppLink;

/**
 * Forma de los objetos que consume el front público.
 *
 * Centralizado para que sea evidente qué se expone: aquí no hay precio, stock
 * ni datos internos de la tienda. Lo que no está en estos arrays, no llega al
 * navegador.
 */
class PublicPresenter
{
    public static function item(Item $item): array
    {
        $store = $item->store;

        return [
            'id' => $item->id,
            'name' => $item->name,
            'internal_code' => $item->internal_code,
            'category' => $item->category?->name,
            'image_url' => self::url($item->image_path),
            'initial' => mb_strtoupper(mb_substr($item->name, 0, 1)),
            'store' => [
                'slug' => $store->slug,
                'name' => $store->name,
                'initials' => self::initials($store->name),
            ],
            'wa_link' => WhatsAppLink::forItem($item, $store),
        ];
    }

    public static function store(Store $store, ?string $mainCategory = null): array
    {
        return [
            'slug' => $store->slug,
            'name' => $store->name,
            'initials' => self::initials($store->name),
            'description' => $store->description,
            'address' => $store->address,
            'items_count' => $store->items_count,
            'main_category' => $mainCategory,
            'logo_url' => self::url($store->logo_path),
            'url' => WhatsAppLink::storeUrl($store),
            'wa_link' => WhatsAppLink::forStore($store),
        ];
    }

    /**
     * Iniciales para el avatar cuando no hay logo. Dos letras como mucho.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: mb_strtoupper(mb_substr($name, 0, 1));
    }

    private static function url(?string $path): ?string
    {
        return $path ? Storage::disk(config('marketplace.disk'))->url($path) : null;
    }
}
