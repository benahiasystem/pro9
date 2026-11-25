import {exactAmount, exactInput, exactValue, normalizeExchangeRate} from './exchange-rate-math'
import {convertExact, localPaymentDecision} from './local-payment-quote'
import {splitTendered, totalTendered} from './tendered-receipt'

function taxRate(decision, context) {
    if (decision.status !== 'subject') return '0'
    if (!context.enabled || exactAmount(context.rate || '0').compare(0) <= 0) {
        throw new Error('Configure y habilite el IGTF antes de registrar pagos sujetos.')
    }
    return context.rate
}

export function suggestDocumentTender(row, document, context, methods) {
    const decision = localPaymentDecision(row, context, methods, document.currency_type_id)
    const balance = exactValue(document, 'total_difference')
    const rounded = convertExact(balance, document.currency_type_id, decision.currency, row.exchange_rate).final(2)
    const applied = convertExact(rounded, decision.currency, document.currency_type_id, row.exchange_rate).final(2)
    const principal = exactAmount(applied).compare(balance.final(2)) > 0 ? exactAmount(rounded).minus('0.01') : exactAmount(rounded)
    if (principal.compare(0) <= 0) return ''
    return totalTendered(principal, taxRate(decision, context))
}

export function quoteDocumentTender(row, document, context, methods) {
    const currency = document.currency_type_id
    const decision = localPaymentDecision(row, context, methods, currency)
    const rate = normalizeExchangeRate(row.exchange_rate)
    const tendered = exactInput(String(row.tendered_amount ?? ''))
    const percentage = taxRate(decision, context)
    const {principal, tax} = splitTendered(tendered, percentage)
    if (exactAmount(principal).compare(0) <= 0) throw new Error('Indique un total entregado positivo que cubra el principal.')
    const converted = convertExact(principal, decision.currency, currency, rate)
    const balance = exactValue(document, 'total_difference')
    const rounded = exactAmount(converted.final(2))
    const applied = rounded.compare(balance.final(2)) === 0 ? balance : rounded
    if (applied.compare(0) <= 0) throw new Error('El total entregado no cubre un importe aplicable al saldo.')
    const regularTax = exactAmount(principal).times(percentage).dividedBy('100').final(2)
    if (applied.compare(balance) > 0 || (applied.compare(balance) === 0 && exactAmount(regularTax).compare(tax) < 0)) {
        throw new Error('El total entregado supera lo necesario para liquidar el saldo sin vuelto.')
    }
    const appliedTax = convertExact(tax, decision.currency, currency, rate).final(2)
    return {tendered, rate, applied, appliedTax, principal, tax, status: decision.status}
}

export function documentTenderDetail(quote, currency) {
    const total = exactAmount(quote.principal).plus(quote.tax)
    const inDollars = currency === 'USD'
    const equivalent = convertExact(total, currency, inDollars ? 'VES' : 'USD', quote.rate).final(2)
    return {
        total: total.final(2),
        equivalent: `${inDollars ? 'Bs.' : '$'} ${equivalent}`,
        rate: exactAmount(quote.rate).decimal(8),
    }
}

export function documentPaymentProgress(document, quote) {
    const paid = exactValue(document, 'total_paid').plus(quote?.applied ?? '0').plus(quote?.appliedTax ?? '0')
    const missing = exactValue(document, 'total_difference').minus(quote?.applied ?? '0')
    const inBolivars = value => convertExact(value, document.currency_type_id, 'VES', document.exchange_rate_sale).final(2)
    return {paid: inBolivars(paid), missing: inBolivars(missing.compare(0) < 0 ? exactAmount(0) : missing)}
}
