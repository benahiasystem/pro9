// Números compactos para el público: 940, 2.5k, 12k, 1.3M
//
// El valor exacto no le aporta nada a un vecino que está eligiendo dónde
// comprar, y a partir de cuatro cifras el número desmaquetaría la tarjeta. El
// admin sí ve el número exacto en su panel.
export function compactCount(value) {
    const n = Number(value) || 0

    if (n < 1000) return String(n)
    // El corte es 999 500 y no un millón porque a partir de ahí el redondeo de
    // short() daría «1000k» en vez de «1M».
    if (n < 999500) return short(n / 1000) + 'k'

    return short(n / 1000000) + 'M'
}

// Un decimal solo mientras aporte: 2.5k, pero 12k y no 12.3k.
function short(n) {
    return String(n < 10 ? Math.round(n * 10) / 10 : Math.round(n))
}

// Precio con símbolo y dos decimales: «S/ 12.50», «S/ 1,234.00». El símbolo lo
// decide el admin (ajuste currency_symbol). Siempre dos decimales, con
// separador de miles, que es como se lee un precio.
export function formatPrice(value, symbol) {
    if (value == null) return ''

    const n = Number(value)
    if (!Number.isFinite(n)) return ''

    const [int, dec] = n.toFixed(2).split('.')
    const withThousands = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',')

    return `${symbol || 'S/'} ${withThousands}.${dec}`
}
