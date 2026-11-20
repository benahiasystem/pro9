import { exactAmount, exactInput, exactSum } from './exchange-rate-math'

export function paymentLinkAmount(value) {
    if (typeof value !== 'string' && typeof value !== 'number') throw new Error('Indique un importe decimal válido')
    const amount = exactAmount(exactInput(String(value)))
    const settlement = amount.final(2)
    if (amount.compare(0) < 0 || exactAmount(settlement).compare(0) <= 0) throw new Error('Indique un importe a cobrar mayor a cero')
    return settlement
}

export function paymentLinkTotal(rows) {
    return exactSum(rows.map(row => {
        const amount = paymentLinkAmount(row.payment)
        if (row.pending !== undefined && exactAmount(amount).compare(exactInput(String(row.pending))) > 0) {
            throw new Error('El monto a pagar supera el saldo pendiente')
        }
        return amount
    })).final(2)
}

/** The checkout SDK accepts a Number containing integer minor units. */
export function paymentLinkMinorUnits(value) {
    const cents = exactAmount(paymentLinkAmount(value)).times('100')
    if (cents.d !== BigInt(1) || cents.n > BigInt(Number.MAX_SAFE_INTEGER)) {
        throw new Error('El importe excede el límite permitido por la pasarela')
    }
    return Number(cents.n)
}
