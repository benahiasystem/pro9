import {exactAmount, exactSum} from '../helpers/exchange-rate-math'
import {isRecordedReceipt} from '../helpers/payment-classification'

// Mirrors ReceiptChange: allocate documentary excess to cash rows in order,
// then convert each share to the currency actually received, without rounding.
export function previewSettlementDifference(difference, form, methods, symbol) {
    const amount = exactAmount(difference || '0')
    const documentCurrency = form?.currency_type_id
    const fallback = (label, value) => ({label, text: `${symbol || documentCurrency || ''} ${value.final(2)}`.trim()})
    if (amount.compare(0) < 0) return fallback('Faltante', exactAmount(0).minus(amount))
    if (amount.compare(0) === 0) return fallback('Vuelto', amount)
    let remaining = amount
    const totals = new Map()
    for (const row of form?.payments || []) {
        if (isRecordedReceipt(row)) continue
        const method = (methods || []).find(item => String(item.id) === String(row.payment_method_type_id))
        const kind = method?.collection_kind
        if (kind !== 'cash' || remaining.compare(0) <= 0) continue
        const currency = row.currency_type_id || method?.currency_type_id
        const applied = exactAmount(row.payment || '0')
        if (applied.compare(0) <= 0 || !currency) continue
        const share = remaining.compare(applied) < 0 ? remaining : applied
        let received = share
        if (currency !== documentCurrency) {
            const rate = exactAmount(row.exchange_rate)
            if (rate.compare(0) <= 0) return fallback('Exceso', amount)
            if (documentCurrency === 'VES' && currency === 'USD') received = share.dividedBy(rate)
            else if (documentCurrency === 'USD' && currency === 'VES') received = share.times(rate)
            else return fallback('Exceso', amount)
        }
        if (received.compare(received.final(2)) !== 0 || (row.original_amount != null && received.compare(row.original_amount) > 0)) {
            return fallback('Exceso', amount)
        }
        totals.set(currency, (totals.get(currency) || exactAmount(0)).plus(received))
        remaining = remaining.minus(share)
    }
    if (remaining.compare(0) > 0 || totals.size === 0) return fallback('Exceso', amount)
    return {label:'Vuelto', text:Array.from(totals, ([currency, value]) => `${currency === 'VES' ? 'Bs.' : currency === 'USD' ? 'USD' : currency} ${value.final(2)}`).join(' · ')}
}

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
// Keep POS totals synchronized when a receipt field changes its applied amount.
export default {
    computed: {
        settlementDifferenceLabel() {
            try {
                return previewSettlementDifference(this.difference, this.form, this.payment_method_types, this.currencyTypeActive?.symbol).label
            } catch (_) { return 'Exceso' }
        },
        settlementDifferenceDisplay() {
            try { return previewSettlementDifference(this.difference, this.form, this.payment_method_types, this.currencyTypeActive?.symbol).text }
            catch (_) { return `${this.currencyTypeActive?.symbol || ''} 0.00`.trim() }
        },
        singleAmountLocked() {
            const rows = this.form && this.form.payments
            return Array.isArray(rows) && (rows.length > 1 || rows.some(isRecordedReceipt))
        },
        singleAmountInvalid() {
            const rows = this.form && this.form.payments
            if (this.singleAmountLocked || !Array.isArray(rows) || rows.length !== 1) return false
            const text = String(this.enter_amount ?? '').trim()
            return text !== '' && !/^\d+(?:\.\d*)?$/.test(text)
        },
        paymentAppliedTotal() {
            const rows = this.form && this.form.payments
            if (!Array.isArray(rows) || rows.length === 0) return null
            try { return exactSum(rows.map(row => row.payment || '0')).decimal() }
            catch (e) { return null }
        },
    },
    watch: {
        paymentAppliedTotal(total) {
            if (total === null) return
            this.amount = total
            this.inputAmount()
        },
    },
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
