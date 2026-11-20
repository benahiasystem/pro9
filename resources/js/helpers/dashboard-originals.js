import { ExactAmount, exactAmount } from './exchange-rate-math'

// ######## INICIO CALCULOS EXACTOS PRO9 ########
/** UI originals are immutable tokens. Numeric chart coordinates are never fallback operands. */
function operand(value) {
    if (value instanceof ExactAmount) return value
    if (value && typeof value === 'object' && typeof value.n === 'string' && typeof value.d === 'string') return exactAmount(value)
    if (typeof value === 'string' && value.trim()) return exactAmount(value)
    throw new Error('Falta el importe original')
}
function token(value) { return Object.freeze(operand(value).fraction()) }
function optional(value) { try { return value == null ? null : token(value) } catch (_) { return null } }

export function readOriginal(record, field) {
    const snapshot = record && record.calculation_snapshot
    if (!snapshot || snapshot.version !== 1 || !snapshot.values) return null
    const value = snapshot.values[field]
    return value && typeof value === 'object' ? optional(value) : null
}
export function readVector(record, field) {
    const values = record && record[field]
    if (!Array.isArray(values)) return []
    return values.map(value => value && typeof value === 'object' ? optional(value) : null)
}
export function graphOriginals(dataset) {
    if (Array.isArray(dataset?.data_fractions)) return readVector(dataset, 'data_fractions')
    const snapshot = dataset && dataset.calculation_snapshot
    if (!snapshot || snapshot.version !== 1 || !snapshot.values) return []
    return Object.keys(snapshot.values).map(field => readOriginal(dataset, field))
}
function grouped(text, locale, trim = false) {
    const [whole, fraction] = text.split('.')
    const places = trim ? (fraction || '').replace(/0+$/, '') : fraction
    const spanish = locale === 'es-VE'
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, spanish ? '.' : ',') + (places ? (spanish ? ',' : '.') + places : '')
}
export function originalNumber(value, baseDecimals = 2, suffixDecimals = 1, locale = 'en-US') {
    try {
        const amount = operand(value), magnitude = amount.compare('0') < 0 ? exactAmount('0').minus(amount) : amount
        for (const [limit, suffix] of [['1000000', 'M'], ['1000', 'K']]) {
            if (magnitude.compare(limit) >= 0) return amount.dividedBy(limit).final(suffixDecimals).replace(/\.0+$/, '') + suffix
        }
        return grouped(amount.final(baseDecimals), locale, baseDecimals === 12)
    } catch (_) { return '—' }
}
export function formatUnitOriginal(value, unit = 'money', locale = 'es-VE') {
    try {
        const amount = operand(value)
        if (unit === 'percent') return grouped(amount.final(1), locale, true) + '%'
        if (unit === 'count') return grouped(amount.final(12), locale, true)
        return 'Bs. ' + grouped(amount.final(2), locale)
    } catch (_) { return '—' }
}
export function originalText(value, scale = 2, locale = 'en-US', trim = false) {
    try { return grouped(operand(value).final(scale), locale, trim) } catch (_) { return '—' }
}
export function originalToken(value) { return value && typeof value === 'object' ? optional(value) : null }
export function originalDecimal(value) { try { return operand(value).decimal(12) } catch (_) { return '—' } }
export function absoluteOriginal(value) {
    try { const amount = operand(value); return token(amount.compare('0') < 0 ? exactAmount('0').minus(amount) : amount) } catch (_) { return null }
}
export function ratioOriginal(value, total) {
    try { const denominator = operand(total); return denominator.compare('0') > 0 ? token(operand(value).dividedBy(denominator).times('100')) : null } catch (_) { return null }
}
export function formatCompactOriginal(value, unit = 'money', locale = 'en-US') {
    if (unit === 'percent') return formatUnitOriginal(value, unit, locale)
    const text = originalNumber(value, unit === 'count' ? 12 : 2, 1, locale)
    return text === '—' ? text : (unit === 'money' ? 'Bs. ' : '') + text
}
export function rawChange(current, previous, absoluteBase = true) {
    try {
        const a = operand(current), b = operand(previous)
        if (b.compare('0') === 0) return null
        const base = absoluteBase && b.compare('0') < 0 ? exactAmount('0').minus(b) : b
        const value = a.minus(b).dividedBy(base).times('100')
        return { pct: token(value.compare('0') < 0 ? exactAmount('0').minus(value) : value), up: value.compare('0') >= 0, value: token(value) }
    } catch (_) { return null }
}
export function sumOriginal(values) {
    try {
        let sum = exactAmount('0')
        for (const value of values) sum = sum.plus(operand(value))
        return token(sum)
    } catch (_) { return null }
}
export function compareOriginal(a, b) { try { return operand(a).compare(operand(b)) } catch (_) { return null } }
export function chartCoordinate(value) {
    try {
        const coordinate = Number(operand(value).decimal(12))
        return Number.isFinite(coordinate) ? coordinate : null
    } catch (_) { return null }
}
export function relativeWidth(value, maximum, minimum = 0) {
    try {
        const amount = operand(value), max = operand(maximum)
        if (amount.compare('0') <= 0 || max.compare('0') <= 0) return 0
        let percent = amount.dividedBy(max).times('100')
        if (percent.compare('100') > 0) percent = exactAmount('100')
        if (percent.compare(String(minimum)) < 0) percent = exactAmount(String(minimum))
        return Number(percent.final(2))
    } catch (_) { return 0 }
}
/** A library-generated axis tick is a graphic scale, not a business value. */
export function formatAxisTick(value, unit) {
    if (typeof value !== 'number' || !Number.isFinite(value)) return '—'
    return formatCompactOriginal(String(value), unit)
}
export function originalDatasetAvailable(dataset) {
    if (!dataset || dataset.error || !dataset.totals || !dataset.breakdown || !Array.isArray(dataset.labels) || !Array.isArray(dataset.series)) return false
    const snapshot = dataset.totals.calculation_snapshot
    if (!snapshot || snapshot.version !== 1 || !snapshot.values) return false
    for (const field of ['current', 'previous', 'delta']) {
        if (dataset.totals[field] != null && readOriginal(dataset.totals, field) === null) return false
    }
    const valid = (record, key, size) => Array.isArray(record[key]) && record[key].length === size
        && record[key].every(value => value === null || (value && typeof value === 'object' && optional(value) !== null))
    if (!Array.isArray(dataset.breakdown.labels) || !valid(dataset.breakdown, 'values_fractions', dataset.breakdown.labels.length)) return false
    return dataset.series.every(serie => valid(serie, 'data_fractions', dataset.labels.length))
}
export function escapeDashboardText(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]))
}
// ######## FIN CALCULOS EXACTOS PRO9 ########
