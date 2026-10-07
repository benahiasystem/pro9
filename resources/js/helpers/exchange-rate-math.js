// ######## INICIO TASAS OCHO DECIMALES ########
function rational(value) {
    if (value instanceof ExactAmount) return value
    const text = String(value)
    const match = /^(-?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/.exec(text)
    if (!match) throw new Error('Importe decimal inválido')
    const fraction = match[3] || ''
    const numerator = BigInt(match[2] + fraction) * (match[1] ? -BigInt(1) : BigInt(1))
    const exponent = match[4] ? parseInt(match[4], 10) : 0
    if (Math.abs(exponent) > 1000) throw new Error('Importe fuera de rango')
    const scale = fraction.length - exponent
    return scale >= 0 ? new ExactAmount(numerator, BigInt(10) ** BigInt(scale))
        : new ExactAmount(numerator * BigInt(10) ** BigInt(-scale))
}
export class ExactAmount {
    constructor(numerator, denominator = BigInt(1)) {
        if (denominator === BigInt(0)) throw new Error('División por cero')
        let a = numerator < BigInt(0) ? -numerator : numerator, b = denominator < BigInt(0) ? -denominator : denominator
        while (b !== BigInt(0)) { const remainder = a % b; a = b; b = remainder }
        numerator /= a; denominator /= a
        this.n = denominator < BigInt(0) ? -numerator : numerator
        this.d = denominator < BigInt(0) ? -denominator : denominator
    }
    plus(value) { const b = rational(value); return new ExactAmount(this.n * b.d + b.n * this.d, this.d * b.d) }
    minus(value) { const b = rational(value); return new ExactAmount(this.n * b.d - b.n * this.d, this.d * b.d) }
    times(value) { const b = rational(value); return new ExactAmount(this.n * b.n, this.d * b.d) }
    dividedBy(value) { const b = rational(value); return new ExactAmount(this.n * b.d, this.d * b.n) }
    final(scale = 2) {
        const negative = this.n < BigInt(0)
        const scaled = (negative ? -this.n : this.n) * BigInt(10) ** BigInt(scale)
        let integer = scaled / this.d
        if ((scaled % this.d) * BigInt(2) >= this.d) integer += BigInt(1)
        const text = integer.toString().padStart(scale + 1, '0')
        return (negative && integer !== BigInt(0) ? '-' : '') + (scale ? text.slice(0, -scale) + '.' + text.slice(-scale) : text)
    }
}
export const exactAmount = rational
export function normalizeExchangeRate(value) {
    const match = /^(\d+)(?:[.,](\d*))?$/.exec(String(value))
    if (!match) throw new Error('La tasa debe conservar hasta ocho decimales sin redondeo')
    const integer = match[1].replace(/^0+(?=\d)/, '')
    const digits = match[2] || ''
    if (/[1-9]/.test(digits.slice(8))) throw new Error('La tasa debe conservar hasta ocho decimales sin redondeo')
    const fraction = digits.slice(0, 8).padEnd(8, '0')
    if (integer.length > 10 || !/[1-9]/.test(integer + fraction)) throw new Error('Tasa fuera de rango')
    return integer + '.' + fraction
}
export function rateMultiply(amount, rate, scale = 2) { return rational(amount).times(normalizeExchangeRate(rate)).final(scale) }
export function rateDivide(amount, rate, scale = 2) { return rational(amount).dividedBy(normalizeExchangeRate(rate)).final(scale) }
// ######## FIN TASAS OCHO DECIMALES ########
