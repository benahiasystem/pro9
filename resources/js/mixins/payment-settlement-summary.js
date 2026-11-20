import {exactAmount, exactInput, exactSum, exactValue} from '../helpers/exchange-rate-math'
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
        localTotalPayable() { return this.useLocalCalculations ? (this.igtfQuote && !this.igtfQuoteError ? this.igtfQuote.total_with_tax : null) : this.collectionTarget ?? this.form.total },
        singleTenderedCurrency() { return this.useLocalCalculations && this.form?.payments?.length === 1 ? this.form.payments[0].currency_type_id || this.form.currency_type_id : this.form?.currency_type_id },
        singleTenderedSymbol() { return this.singleTenderedCurrency === 'USD' ? '$' : this.singleTenderedCurrency === 'VES' ? 'Bs.' : this.currencyTypeActive?.symbol },
        // ######## INICIO RESUMEN LOCAL DE LIQUIDACION ########
        localInvoiceSettlement() {
            return this.useLocalCalculations && this.form?.document_type_id === '01'
        },
        localQuoteReady() {
            return Boolean(this.igtfQuote && !this.igtfQuoteError && !this.igtfQuoteLoading)
        },
        settlementPaymentBlocked() {
            if (!this.localInvoiceSettlement) return this.button_payment
            if (['02','03'].includes(this.form.payment_condition_id)) return false
            return !this.localQuoteReady || exactAmount(this.igtfQuote.balance).compare(0) > 0
        },
        localSettlementDisplay() {
            if (!this.useLocalCalculations) return null
            if (!this.igtfQuote || this.igtfQuoteError) return {label:'Vuelto / faltante',text:'Pendiente de cálculo'}
            const quote = this.igtfQuote
            if (exactAmount(quote.balance).compare(0) > 0) return {label:'Faltante', text:`${this.currencyTypeActive?.symbol || this.form.currency_type_id} ${quote.balance}`}
            const amounts = new Map()
            for (const row of quote.payments) if (!row.reused && exactAmount(row.change).compare(0) > 0) {
                amounts.set(row.currency_type_id,(amounts.get(row.currency_type_id) || exactAmount('0')).plus(row.change))
            }
            return {label:'Vuelto', text:amounts.size ? [...amounts].map(([currency, amount]) => `${currency === 'VES' ? 'Bs.' : '$'} ${amount.final(2)}`).join(' · ')
                : `${this.currencyTypeActive?.symbol || this.form.currency_type_id} 0.00`}
        },
        // ######## FIN RESUMEN LOCAL DE LIQUIDACION ########
        settlementDifferenceLabel() {
            if (this.localSettlementDisplay) return this.localSettlementDisplay.label
            try {
                return previewSettlementDifference(this.difference, this.form, this.payment_method_types, this.currencyTypeActive?.symbol).label
            } catch (_) { return 'Exceso' }
        },
        settlementDifferenceDisplay() {
            if (this.localSettlementDisplay) return this.localSettlementDisplay.text
            try { return previewSettlementDifference(this.difference, this.form, this.payment_method_types, this.currencyTypeActive?.symbol).text }
            catch (_) { return `${this.currencyTypeActive?.symbol || ''} 0.00`.trim() }
        },
        singleAmountLocked() {
            const rows = this.form && this.form.payments
            return Array.isArray(rows) && (rows.length > 1 || rows.some(isRecordedReceipt))
        },
        singleAmountInvalid() {
            const rows = this.form && this.form.payments
            if (this.singleAmountLocked || !Array.isArray(rows)) return false
            if (this.localInvoiceSettlement) {
                if (['02', '03'].includes(this.form.payment_condition_id)) return false
            } else if (rows.length !== 1) return false
            const text = String(this.enter_amount ?? '').trim()
            if (!text) return false
            if (!/^\d+(?:\.\d*)?$/.test(text)) return true
            if (!this.localInvoiceSettlement) return false
            try {
                exactInput(text.endsWith('.') ? text.slice(0, -1) : text)
                return false
            } catch (_) {
                return true
            }
        },
        paymentAppliedTotal() {
            const rows = this.form && this.form.payments
            if (!Array.isArray(rows) || rows.length === 0) return null
            try { return this.appliedReceiptTotal(rows) }
            catch (e) { return null }
        },
    },
    watch: {
        localSettlementDisplay(value) {
            if (this.localInvoiceSettlement || !this.useLocalCalculations || !value || !this.localQuoteReady) return
            this.button_payment = !['02','03'].includes(this.form.payment_condition_id) && exactAmount(this.igtfQuote.balance).compare(0) > 0
        },
        paymentAppliedTotal(total) {
            if (total === null) return
            this.amount = total
            this.inputAmount()
        },
    },
    methods: {
        checkoutValue(record, field) {
            if (this.form.document_type_id !== '01') return record[field]
            try { return exactValue(record, field) } catch (_) { return exactAmount(0) }
        },
        checkoutHasDiscount() {
            if (this.form.document_type_id !== '01') return this.form.total_discount > 0
            return this.checkoutValue(this.form, 'total_discount').compare(0) > 0
        },
        appliedReceiptTotal(rows) {
            const restoreExact = this.localInvoiceSettlement && rows.some(isRecordedReceipt)
            const total = exactSum(rows.map(row => restoreExact && isRecordedReceipt(row)
                ? exactValue(row, 'payment') : row.payment || '0'))
            return restoreExact ? total.final(2) : total.decimal()
        },
        enteredReceiptAmount(rows) {
            return this.useLocalCalculations && rows.length === 1 && !isRecordedReceipt(rows[0]) && rows[0].tendered_amount != null
                ? rows[0].tendered_amount : this.appliedReceiptTotal(rows)
        },
    },
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
