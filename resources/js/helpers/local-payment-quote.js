// ######## INICIO COTIZACION LOCAL EXACTA ########
import {exactAmount as D, exactInput, exactValue, normalizeExchangeRate} from './exchange-rate-math'
import {splitTendered} from './tendered-receipt'
import {isRecordedReceipt} from './payment-classification'

const min = (a, b) => a.compare(b) < 0 ? a : D(b)
const max = (a, b) => a.compare(b) > 0 ? a : D(b)
const settled = value => D(D(value).final(2))
const tax = (net, rate) => settled(D(net).times(rate).dividedBy('100'))
export function convertExact(amount, from, to, rate) {
    if (!['VES', 'USD'].includes(from) || !['VES', 'USD'].includes(to)) throw new Error('Seleccione una moneda válida.')
    if (from === to) return D(amount)
    const value = D(normalizeExchangeRate(rate))
    return from === 'USD' ? D(amount).times(value) : D(amount).dividedBy(value)
}
export function localPaymentDecision(row, context, methods, documentCurrency) {
    const date = row.date_of_payment
    const profile = context?.dates?.[date]
    if (!profile) throw new Error('Espere el contexto fiscal de la fecha del cobro.')
    const method = methods.find(value => String(value.id) === String(row.payment_method_type_id))
    if (!method || ![true, 1, '1'].includes(method.is_active) || !['cash', 'account'].includes(method.collection_kind)) throw new Error('Seleccione un método de pago activo.')
    const currency = row.currency_type_id || documentCurrency
    if (currency !== method.currency_type_id) throw new Error('La moneda del cobro debe coincidir con el método seleccionado.')
    const subject = profile.issuer_spe && currency === 'USD' && method.collection_kind === 'cash'
    return {profile, method, currency, status: subject ? 'subject' : 'not_applicable'}
}
function reason(row) {
    return row.reused ? 'Cobro ya registrado.' : row.igtf_status === 'subject' ? 'Cobro sujeto a IGTF.'
        : !row.issuerSpe ? 'El emisor no tiene un perfil de sujeto pasivo especial vigente.'
            : row.currency_type_id !== 'USD' ? 'El cobro no es en USD.' : 'El cobro tiene intermediación financiera.'
}
function normalize(row, request, context, methods, invoice) {
    const reused = isRecordedReceipt(row)
    const decision = reused || !invoice ? null : localPaymentDecision(row, context, methods, request.currency_type_id)
    const method = decision?.method || methods.find(value => String(value.id) === String(row.payment_method_type_id))
    if (!reused && (!method || ![true, 1, '1'].includes(method.is_active))) throw new Error('Seleccione un método de pago activo.')
    const currency = row.currency_type_id || request.currency_type_id
    if (!reused && currency !== method.currency_type_id) throw new Error('La moneda del cobro debe coincidir con el método seleccionado.')
    if (currency !== request.currency_type_id && (row.original_amount == null || row.exchange_rate == null)) throw new Error('Indique el importe y la tasa de la moneda recibida.')
    const rate = normalizeExchangeRate(row.exchange_rate ?? request.exchange_rate_sale)
    const status = decision?.status || 'not_applicable'
    const percentage = status === 'subject' ? context.rate : '0'
    if (status === 'subject' && (!context.enabled || D(percentage || '0').compare(0) <= 0)) throw new Error('Configure y habilite el IGTF antes de registrar pagos sujetos.')
    let original = D(exactInput(String(row.original_amount ?? row.payment)))
    let inclusive = null
    if (invoice && !reused && Object.prototype.hasOwnProperty.call(row, 'tendered_amount')) {
        const gross = settled(exactInput(String(row.tendered_amount)))
        if (gross.compare(0) <= 0) throw new Error('Indique un total entregado positivo.')
        const split = splitTendered(gross.final(2), percentage)
        original = D(split.principal)
        inclusive = {gross, principal: original, tax: D(split.tax), rate: percentage, capped: false}
    }
    if (original.compare(0) <= 0 || settled(original).compare(0) <= 0) throw new Error('El importe recibido debe ser positivo y liquidable en céntimos.')
    original = settled(original)
    const converted = convertExact(original, currency, request.currency_type_id, rate)
    return {currency_type_id: currency, exchange_rate: rate, original_amount: original,
        payment: reused ? exactValue(row, 'payment') : settled(converted), change: D('0'),
        igtf_status: status, reused, cash: !reused && method?.collection_kind === 'cash', inclusive,
        issuerSpe: !!decision?.profile.issuer_spe, percentage, settlement: null}
}
function finish(rows, payable) {
    for (const row of rows) {
        const input = row.inclusive
        if (!input || row.reused) continue
        const net = row.original_amount
        if (rows.length === 1 && row.payment.compare(payable) === 0 && net.plus(tax(net, input.rate)).compare(input.gross) <= 0) input.capped = true
        const collected = !input.capped && net.compare(input.principal) === 0 ? input.tax : tax(net, input.rate)
        row.change = input.gross.minus(net).minus(collected)
        if (row.change.compare(0) < 0) throw new Error('El total entregado no cubre el principal y su IGTF.')
        if (row.change.compare(0) > 0 && !row.cash) throw new Error('El total bancario debe coincidir con el principal y su IGTF, sin vuelto.')
    }
}
function allocateInvoice(rows, payable, currency) {
    const sum = rows.reduce((total, row) => total.plus(row.payment), D('0'))
    const closing = rows.filter(row => currency === 'VES' && row.currency_type_id === 'USD' && row.cash).slice(-1)[0]
    if (closing) {
        const remaining = payable.minus(sum.minus(closing.payment))
        if (remaining.compare(0) > 0) {
            const due = settled(convertExact(remaining, currency, 'USD', closing.exchange_rate))
            if (due.compare(0) > 0 && due.compare(closing.original_amount) <= 0) {
                const change = closing.original_amount.minus(due)
                if (change.compare(0) > 0) { closing.original_amount = due; closing.change = change }
                if (change.compare(0) > 0 || closing.payment.compare(remaining) !== 0) { closing.payment = remaining; closing.settlement = remaining }
                finish(rows, payable)
                return
            }
        }
    }
    let remaining = max(sum.minus(payable), '0')
    for (const row of rows) {
        if (!row.cash || remaining.compare(0) <= 0) continue
        const share = min(remaining, row.payment)
        const change = convertExact(share, currency, row.currency_type_id, row.exchange_rate)
        if (rows.length === 1 && settled(change).compare(0) === 0) row.settlement = row.payment.minus(share)
        else {
            if (change.compare(change.final(2)) !== 0) throw new Error('El vuelto convertido requiere un importe liquidable en céntimos.')
            if (change.compare(row.original_amount) > 0) throw new Error('El vuelto supera el efectivo recibido.')
            row.change = change; row.original_amount = row.original_amount.minus(change); row.payment = row.payment.minus(share)
        }
        remaining = remaining.minus(share)
    }
    if (remaining.compare(0) > 0) throw new Error('El exceso sólo puede devolverse desde el efectivo recibido.')
    finish(rows, payable)
}
function allocateSaleNote(rows, payable, currency) {
    const historical = rows.filter(row=>row.reused).reduce((sum,row)=>sum.plus(row.payment),D('0'))
    const target = payable.minus(historical)
    if (target.compare(0) < 0 || target.compare(target.final(2)) !== 0) throw new Error('El saldo después de los abonos debe ser no negativo y expresarse en céntimos.')
    let remaining = max(rows.filter(row=>!row.reused).reduce((total, row) => total.plus(row.payment), D('0')).minus(target), '0')
    for (const row of rows) {
        if (!row.cash || remaining.compare(0) <= 0) continue
        const share = min(remaining, row.payment)
        const change = convertExact(share, currency, row.currency_type_id, row.exchange_rate)
        if (change.compare(change.final(2)) !== 0) throw new Error('El vuelto no puede expresarse en céntimos de la moneda recibida; ajuste el importe.')
        if (change.compare(row.original_amount) > 0) throw new Error('El vuelto supera el importe recibido.')
        row.payment = row.payment.minus(share); row.change = change; row.original_amount = row.original_amount.minus(change)
        remaining = remaining.minus(share)
    }
    if (remaining.compare(0) > 0) throw new Error('El exceso sólo puede devolverse desde el efectivo recibido.')
}
export function localPaymentQuote(request, context, methods, kind = '01') {
    const invoice = kind !== '80'
    const payable = D(exactInput(String(request.principal_due)))
    if (payable.compare(0) < 0) throw new Error('El saldo no puede ser negativo.')
    if (!invoice && payable.compare(payable.final(2)) !== 0) throw new Error('El importe liquidable debe expresarse en céntimos.')
    const rows = request.payments.map(row => normalize(row, request, context, methods, invoice))
    if (invoice) allocateInvoice(rows, payable, request.currency_type_id)
    else allocateSaleNote(rows, payable, request.currency_type_id)
    let applied = D('0'), taxDocument = D('0'), appliedTax = D('0')
    const received = {}, tendered = {}
    const payments = rows.map(row => {
        const converted = convertExact(row.original_amount, row.currency_type_id, request.currency_type_id, row.exchange_rate)
        const input = row.inclusive
        const collected = invoice && row.igtf_status === 'subject'
            ? input && !input.capped && row.original_amount.compare(input.principal) === 0 ? input.tax : tax(row.original_amount, row.percentage) : D('0')
        if (invoice && !row.reused) row.payment = row.settlement ?? settled(converted)
        applied = applied.plus(row.payment)
        const convertedTax = convertExact(collected, row.currency_type_id, request.currency_type_id, row.exchange_rate)
        taxDocument = taxDocument.plus(convertedTax)
        appliedTax = appliedTax.plus(settled(convertedTax))
        if (!row.reused) {
            const net = row.original_amount.plus(collected), money = row.currency_type_id
            received[money] = (received[money] || D('0')).plus(net)
            tendered[money] = (tendered[money] || D('0')).plus(net).plus(row.change)
        }
        return {currency_type_id: row.currency_type_id, original_amount: row.original_amount.final(2), payment: row.payment.decimal(), payment_exact:row.payment.fraction(),
            exchange_rate: row.exchange_rate, change: row.change.final(2), tax_amount: collected.final(2), igtf_status: row.igtf_status,
            reused: row.reused, reason: reason(row), settlement_adjustment: row.settlement === null ? null : row.payment.minus(converted).final(2),
            settlement_adjustment_exact: row.settlement === null ? null : row.payment.minus(converted).fraction()}
    })
    const totalExact = D(request.commercial_total_exact ?? payable.fraction()).plus(taxDocument)
    const totalPayable = settled(totalExact)
    const balance = request.commercial_total_exact == null ? max(payable.minus(applied),'0')
        : max(totalPayable.minus(applied).minus(appliedTax).minus(request.settlements_exact || '0'),'0')
    return {payments, principal_due: payable.final(2), principal_applied: applied.final(2), tax_in_document_currency: taxDocument.final(2),
        total_with_tax: totalPayable.final(2), total_with_tax_exact:totalExact.fraction(), balance:balance.final(2), balance_exact:balance.fraction(),
        settlement_adjustment_exact:totalPayable.minus(totalExact).fraction(),
        received_by_currency: Object.fromEntries(Object.entries(received).map(([key, amount]) => [key, amount.final(2)])),
        tendered_by_currency: Object.fromEntries(Object.entries(tendered).map(([key, amount]) => [key, amount.final(2)])),
        can_submit: true, igtf_enabled: invoice && context.enabled, igtf_rate: context.rate}
}
// A corrected summary is presentation only. Never replace the tendered inputs.
export function quoteFromCalculation(previous, summary) {
    const applied = summary.payments.reduce((sum, row) => sum.plus(row.payment_exact ?? row.payment), D('0'))
    const tax = summary.payments.reduce((sum, row) => sum.plus(convertExact(row.tax_amount, row.currency_type_id, summary.currency_type_id, row.exchange_rate)), D('0'))
    const received = {}, tendered = {}
    const payments = summary.payments.map((row,index) => {
        if (!row.reused) {
            const net = D(row.original_amount).plus(row.tax_amount), money = row.currency_type_id
            received[money] = (received[money] || D('0')).plus(net)
            tendered[money] = (tendered[money] || D('0')).plus(net).plus(row.change)
        }
        return {...previous.payments?.[index], ...row}
    })
    return {...previous, server_verified:true, payments, principal_applied:applied.final(2), tax_in_document_currency:tax.final(2),
        igtf_enabled:summary.igtf_enabled,igtf_rate:summary.igtf_rate,
        total_with_tax:summary.total_with_tax, total_with_tax_exact:summary.total_with_tax_exact,
        balance:summary.balance, balance_exact:summary.balance_exact, settlement_adjustment_exact:summary.settlement_adjustment_exact,
        received_by_currency:Object.fromEntries(Object.entries(received).map(([key,value])=>[key,value.final(2)])),
        tendered_by_currency:Object.fromEntries(Object.entries(tendered).map(([key,value])=>[key,value.final(2)]))}
}
// ######## FIN COTIZACION LOCAL EXACTA ########
