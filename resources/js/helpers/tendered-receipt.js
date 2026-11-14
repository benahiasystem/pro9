import {exactAmount, allocateExact} from './exchange-rate-math'

function tax(principal, rate) {
    return exactAmount(exactAmount(principal).times(rate).dividedBy('100').final(2))
}

export function totalTendered(principal, rate = '0') {
    const net = exactAmount(exactAmount(principal).final(2))
    return net.plus(tax(net, rate)).final(2)
}

// Preview only: the server independently resolves the net and change under lock.
export function splitTendered(amount, rate = '0') {
    const requested = exactAmount(amount), percentage = exactAmount(rate)
    if (requested.compare(0) < 0 || percentage.compare(0) < 0) throw new Error('Importe o tasa inválidos')
    const gross = exactAmount(requested.final(2))
    const principalExact = gross.times('100').dividedBy(exactAmount('100').plus(percentage))
    const taxExact = gross.minus(principalExact)
    // Tax first makes half-cent ties agree with HALF_UP; the split always sums to gross.
    const [taxAmount, principal] = allocateExact(gross, [percentage, '100'], 2)
    return {principal, tax: taxAmount, principalExact: principalExact.fraction(), taxExact: taxExact.fraction()}
}

export function principalFromTendered(amount, rate = '0') {
    return splitTendered(amount, rate).principal
}
