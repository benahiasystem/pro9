<?php

namespace Modules\Marketplace\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;
use Modules\Marketplace\Services\Settings;
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
            // El precio solo viaja si la tienda lo habilitó; si no, ni siquiera
            // llega al navegador. La descripción se omite cuando coincide con el
            // nombre (productos donde la app aún fusiona ambos): repetir el
            // título en el modal no aporta nada.
            'price' => $store->show_prices && $item->price !== null ? (float) $item->price : null,
            'description' => $item->description && $item->description !== $item->name ? $item->description : null,
            'image_url' => self::url($item->image_path),
            'initial' => mb_strtoupper(mb_substr($item->name, 0, 1)),
            // Sin número: en el producto el pulgar solo se enciende o se apaga.
            // Si el visitante ya lo recomendó lo marca el feed aparte, no aquí,
            // porque esto va dentro del payload cacheado y sería igual para
            // todos.
            //
            // Sin whatsapp y sin wa_link (plan de seguridad, A2.4): el número
            // vive SOLO en el servidor. El enlace nace en POST /contacto, que
            // registra al comprador antes de entregarlo. Lo que no está en
            // este array, no llega al navegador — y no se puede scrapear.
            'store' => [
                'slug' => $store->slug,
                'name' => $store->name,
                'initials' => self::initials($store->name),
                // La URL canónica de la ficha, la misma que arma el servidor:
                // se usa para navegar y compartir, no lleva ningún contacto.
                'url' => WhatsAppLink::storeUrl($store),
            ],
        ];
    }

    public static function store(Store $store, ?string $mainCategory = null): array
    {
        return [
            'slug' => $store->slug,
            'name' => $store->name,
            'initials' => self::initials($store->name),
            'description' => $store->description,
            // La dirección exacta solo si la tienda la habilitó (opt-in). Si
            // no, la zona referencial que ella misma escribió — o nada. Es el
            // mismo patrón de show_prices: el dato que no viaja no se extrae.
            'address' => $store->show_address
                ? $store->address
                : ($store->address_zone ?: null),
            'address_is_zone' => ! $store->show_address && ! empty($store->address_zone),
            'items_count' => $store->items_count,
            // El número solo viaja cuando ya es notable; por debajo del umbral
            // llega 0 y el front no pinta la insignia. Es lo que hace del
            // ranking un aval y no un marcador que empieza en 1.
            'recommendations_count' => self::notableRanking($store->recommendations_count),
            'main_category' => $mainCategory,
            'logo_url' => self::url($store->logo_path),
            'url' => WhatsAppLink::storeUrl($store),
            // «Nuevo» durante el primer mes. Se usa en la ficha y en la tarjeta
            // del home.
            'is_new' => self::isNew($store->created_at),
            // Antigüedad gruesa para el detalle («Creado hace 3 meses»), nunca
            // en días. La fecha exacta viaja aparte para el tooltip.
            'created_label' => self::ageLabel($store->created_at),
            'created_on' => $store->created_at?->format('d/m/Y'),
        ];
    }

    private static function isNew(?Carbon $createdAt): bool
    {
        return $createdAt !== null && $createdAt->greaterThan(now()->subMonth());
    }

    /**
     * Antigüedad a trazo grueso: nada de «hace 2 días». Antes del mes no hay
     * cifra (el badge «Nuevo» ya lo dice); a partir de ahí, meses; y a partir
     * del año, años. Es a propósito impreciso: al vecino le basta con la escala.
     */
    private static function ageLabel(?Carbon $createdAt): ?string
    {
        if ($createdAt === null) {
            return null;
        }

        $months = $createdAt->diffInMonths(now());

        if ($months < 1) {
            return 'Creado hace menos de un mes';
        }

        if ($months < 12) {
            return 'Creado hace ' . $months . ($months === 1 ? ' mes' : ' meses');
        }

        $years = intdiv($months, 12);

        return 'Creado hace ' . $years . ($years === 1 ? ' año' : ' años');
    }

    /**
     * El ranking solo se expone si alcanza el umbral configurado. Por debajo
     * devuelve 0 para que no se muestre ni ordene: una tienda con un par de
     * recomendaciones no debe verse "mejor" que una que aún no tiene ninguna.
     * Con el umbral en 0 no hay gate.
     */
    private static function notableRanking(int $count): int
    {
        $threshold = (int) Settings::get('ranking_threshold', 0);

        return $count >= $threshold ? $count : 0;
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
