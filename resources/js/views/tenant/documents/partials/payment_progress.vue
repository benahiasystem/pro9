<template>
    <!-- ######## INICIO RESUMEN PAGADO Y FALTA ######## -->
    <div class="invoice-payment-progress" aria-live="polite" :aria-busy="pending">
        <div class="payment-progress-values">
            <span>Pagado <strong data-testid="invoice-paid">{{ money(displaySummary.paid) }}</strong></span>
            <span aria-hidden="true">·</span>
            <span class="payment-progress-missing">Falta <strong data-testid="invoice-missing">{{ money(displaySummary.missing) }}</strong></span>
        </div>
        <small v-if="pending" class="payment-progress-status text-muted" role="status">Evaluando…</small>
    </div>
    <!-- ######## FIN RESUMEN PAGADO Y FALTA ######## -->
</template>

<script>
// ######## INICIO RESUMEN PAGADO Y FALTA ########
import {exactAmount, exactValue, normalizeExchangeRate} from '../../../../helpers/exchange-rate-math'
import {isRecordedReceipt} from '../../../../helpers/payment-classification'

export default {
    props: {
        document: {type: Object, required: true},
        quote: {type: Object, default: null},
        pending: Boolean,
        error: {type: String, default: ''},
        currencyTypes: {type: Array, default: () => []},
    },
    data: () => ({lastSettledSummary: null}),
    computed: {
        currencySymbol() { return this.currencyTypes.find(currency => currency.id === 'VES')?.symbol || '' },
        calculatedSummary() {
            const unavailable = {paid: null, missing: null}
            if (this.error || this.pending) return unavailable
            try {
                let paid, missing
                if (this.quote) {
                    // Los importes aplicados y el IGTF liquidado ya descuentan el vuelto.
                    paid = exactAmount(this.quote.principal_applied).plus(this.quote.tax_in_document_currency)
                    missing = exactAmount(this.quote.balance)
                } else {
                    paid = exactAmount(0)
                    for (const row of this.document.payments || []) {
                        if (row.reversed_at) continue
                        const amount = row.payment === '' || row.payment == null ? exactAmount(0) : exactValue(row, 'payment')
                        if (amount.compare(0) < 0) return unavailable
                        if (amount.compare(0) === 0) continue
                        // Los cobros nuevos requieren la evaluación del servidor. Los anteriores
                        // sólo aplican su principal conservado y no generan otro IGTF.
                        if (!isRecordedReceipt(row)) return unavailable
                        paid = paid.plus(amount)
                    }
                    const due = exactValue(this.document, this.document.total_payable == null ? 'total' : 'total_payable')
                    missing = due.minus(paid)
                    if (missing.compare(0) < 0) missing = exactAmount(0)
                }
                const convert = amount => {
                    if (this.document.currency_type_id === 'VES') return amount
                    if (this.document.currency_type_id !== 'USD') throw new Error('Moneda no disponible')
                    return amount.times(normalizeExchangeRate(this.document.exchange_rate_sale))
                }
                return {paid: convert(paid).final(2), missing: convert(missing).final(2)}
            } catch (_) { return unavailable }
        },
        displaySummary() {
            if (this.error) return {paid: null, missing: null}
            if (this.pending && this.lastSettledSummary) return this.lastSettledSummary
            return this.calculatedSummary
        },
    },
    watch: {
        calculatedSummary: {immediate: true, handler(value) {
            if (!this.pending && !this.error && value.paid !== null) this.lastSettledSummary = value
        }},
        document() { this.lastSettledSummary = null },
    },
    methods: {
        money(amount) {
            if (amount === null) return '—'
            return this.currencySymbol ? `${this.currencySymbol} ${amount}` : amount
        },
    },
}
// ######## FIN RESUMEN PAGADO Y FALTA ########
</script>

<style scoped>
/* ######## INICIO RESUMEN PAGADO Y FALTA ######## */
.invoice-payment-progress { position: relative; text-align: right; }
.payment-progress-values { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px; }
.payment-progress-missing { color: var(--bs-danger, #f02f6b); }
.payment-progress-status { position: absolute; top: 100%; right: 0; }
/* ######## FIN RESUMEN PAGADO Y FALTA ######## */
</style>
