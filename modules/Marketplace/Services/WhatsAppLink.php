<?php

namespace Modules\Marketplace\Services;

use Modules\Marketplace\Models\Item;
use Modules\Marketplace\Models\Store;

/**
 * La CTA única del marketplace.
 *
 * Se arma en el servidor y no en el front para que el saludo configurable y la
 * URL absoluta vivan en un solo sitio.
 */
class WhatsAppLink
{
    public static function forItem(Item $item, Store $store): string
    {
        $greeting = (string) Settings::get('whatsapp_greeting', '');

        // El código interno le permite a la tienda ubicar el producto de
        // inmediato en su app, que es de donde salió el catálogo.
        $code = $item->internal_code ? " ({$item->internal_code})" : '';

        return self::build($store->whatsapp, trim("{$greeting} {$item->name}{$code} — " . self::storeUrl($store)));
    }

    public static function forStore(Store $store): string
    {
        return self::build(
            $store->whatsapp,
            'Hola, vi tu tienda en el marketplace — ' . self::storeUrl($store)
        );
    }

    /**
     * El mensaje del pedido multi-producto, armado EN SERVIDOR.
     *
     * Antes lo componía wa-cart.js con el número en mano; ahora el navegador
     * manda ítems y cantidades a POST /contacto y el número nunca sale de aquí
     * (plan de seguridad, A2.2). $items: [{name, code, qty}].
     */
    public static function forCart(Store $store, array $items): string
    {
        $greeting = trim((string) Settings::get('whatsapp_cart_greeting', ''))
            ?: 'Hola, quiero hacer este pedido:';

        $lines = array_map(function (array $it) {
            $code = ! empty($it['code']) ? " ({$it['code']})" : '';

            return "- {$it['qty']}x {$it['name']}{$code}";
        }, $items);

        $text = $greeting . "\n" . implode("\n", $lines) . "\n" . self::storeUrl($store);

        return self::build($store->whatsapp, $text);
    }

    public static function storeUrl(Store $store): string
    {
        return url(config('marketplace.route_prefix') . '/tienda/' . $store->slug);
    }

    private static function build(string $whatsapp, string $text): string
    {
        return 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($text);
    }
}
