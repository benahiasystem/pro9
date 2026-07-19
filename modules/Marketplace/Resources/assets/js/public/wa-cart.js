// Arma el enlace de WhatsApp del pedido de UNA tienda.
//
// El gemelo cliente de WhatsAppLink::forItem del servidor: aquel encabeza con
// el saludo de un producto, este con el del carrito y debajo la lista de ítems
// con cantidades. Se arma aquí y no en el servidor porque las cantidades son
// dinámicas y el carrito vive en el navegador; el número, la URL y el saludo
// vienen del payload.
//
// encodeURIComponent (no el rawurlencode del servidor) porque es un contexto JS,
// pero el resultado es equivalente: espacios %20 y saltos de línea %0A, que
// WhatsApp respeta.

const FALLBACK_GREETING = 'Hola, quiero hacer este pedido:'

export function buildCartLink(group, greeting) {
    const lines = group.items.map(
        (it) => `- ${it.qty}x ${it.name}${it.code ? ` (${it.code})` : ''}`,
    )

    // La URL canónica viene del servidor (respeta el subpath); no se reconstruye
    // desde location.origin, que lo perdería.
    const text = `${(greeting || '').trim() || FALLBACK_GREETING}\n${lines.join('\n')}\n${group.store.url}`

    // El número es E.164 sin '+', pero se codifica igual por si un dato de
    // catálogo trajera algo raro que rompiera el query.
    return `https://wa.me/${encodeURIComponent(group.store.whatsapp)}?text=${encodeURIComponent(text)}`
}
