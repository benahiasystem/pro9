// ######## INICIO TASAS OCHO DECIMALES ########
function rational(value) {
    if (value instanceof ExactAmount) return value
    if (value && typeof value === 'object' && value.n !== undefined && value.d !== undefined) {
        if (typeof value.n !== 'string' || typeof value.d !== 'string' || !/^-?\d+$/.test(value.n) || !/^\d+$/.test(value.d) || BigInt(value.d) <= BigInt(0)) throw new Error('Fracción decimal inválida')
        return new ExactAmount(BigInt(value.n), BigInt(value.d))
    }
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
    compare(value) { const b = rational(value); const difference = this.n * b.d - b.n * this.d; return difference < BigInt(0) ? -1 : difference > BigInt(0) ? 1 : 0 }
    fraction() { return {n: this.n.toString(), d: this.d.toString()} }
    decimal(scale = 12) { const text = this.final(scale); return scale ? text.replace(/\.?0+$/, '') || '0' : text }
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
// ######## INICIO CALCULOS EXACTOS PRO9 ########
export function exactValue(record, field) { return rational(record.calculation_snapshot?.values?.[field] ?? record[field] ?? 0) }
export function exactSum(values) { return values.reduce((sum, value) => sum.plus(value), rational(0)) }
export function exactSnapshot(values, inputs = {}) { return {version: 1, inputs, values: Object.fromEntries(Object.entries(values).map(([key, value]) => [key, rational(value).fraction()]))} }
export function allocateExact(total, weights, scale = 2) {
    if (!weights.length) return []
    total = rational(total)
    if (total.compare(0) < 0) return allocateExact(rational(0).minus(total), weights, scale).map(value => rational(0).minus(value).final(scale))
    weights = weights.map(rational)
    if (weights.some(value => value.compare(0) < 0)) throw new Error('Peso negativo')
    const sum = exactSum(weights)
    if (sum.compare(0) === 0) {
        if (total.compare(0) !== 0) throw new Error('El reparto necesita una base positiva')
        return weights.map(() => rational(0).final(scale))
    }
    const unit = BigInt(10) ** BigInt(scale)
    const target = BigInt(total.times(unit.toString()).final(0))
    const shares = weights.map((weight, index) => {
        const share = weight.times(target.toString()).dividedBy(sum)
        const integer = share.n / share.d
        return {index, integer, remainder: share.minus(integer.toString())}
    })
    let left = target - shares.reduce((sum, share) => sum + share.integer, BigInt(0))
    const order = [...shares].sort((a, b) => b.remainder.compare(a.remainder) || a.index - b.index)
    for (const share of order) { if (left <= BigInt(0)) break; share.integer += BigInt(1); left -= BigInt(1) }
    return shares.map(share => rational(share.integer.toString()).dividedBy(unit.toString()).final(scale))
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
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
