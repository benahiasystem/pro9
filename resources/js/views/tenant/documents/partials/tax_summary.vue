<template>
    <!-- ######## INICIO RESUMEN REACTIVO IVA E IGTF ######## -->
    <div class="invoice-tax-summary" aria-live="polite">
        <table class="table table-sm text-start invoice-tax-table" aria-label="Resumen de impuestos en bolívares">
            <colgroup><col style="width: 28%"><col style="width: 33%"><col style="width: 39%"></colgroup>
            <thead>
                <tr>
                    <th scope="col">Impuesto</th>
                    <th scope="col">Monto Imponible</th>
                    <th scope="col">Monto de Impuestos</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ igtfLabel }}</td>
                    <td class="text-start" data-testid="igtf-taxable-base">{{ money(igtfSummary.base) }}</td>
                    <td class="text-start" data-testid="igtf-tax-amount">{{ money(igtfSummary.tax) }}</td>
                </tr>
                <tr>
                    <td>{{ ivaLabel }}</td>
                    <td class="text-start" data-testid="iva-taxable-base">{{ money(ivaSummary.base) }}</td>
                    <td class="text-start" data-testid="iva-tax-amount">{{ money(ivaSummary.tax) }}</td>
                </tr>
            </tbody>
        </table>
        <small v-if="igtfSummary.pending" class="tax-quote-status text-muted" role="status">Evaluando el cobro…</small>
        <small v-if="igtfSummary.error" class="d-block text-danger" role="alert">{{ igtfSummary.error }}</small>
        <small v-if="document.currency_type_id && ivaSummary.error" class="d-block text-danger" role="alert">{{ ivaSummary.error }}</small>
    </div>
    <!-- ######## FIN RESUMEN REACTIVO IVA E IGTF ######## -->
</template>

<script>
// ######## INICIO RESUMEN REACTIVO IVA E IGTF ########
import {exactAmount, exactValue, normalizeExchangeRate} from '../../../../helpers/exchange-rate-math'

export default {
    props: {
        document: {type: Object, required: true},
        ivaRate: {type: [String, Number], required: true},
        quote: {type: Object, default: null},
        quotePending: Boolean,
        quoteError: {type: String, default: ''},
        settings: {type: Object, required: true},
        currencyTypes: {type: Array, default: () => []},
    },
    computed: {
        currencySymbol() { return this.currencyTypes.find(currency => currency.id === 'VES')?.symbol || '' },
        ivaLabel() { return `IVA - ${exactAmount(this.ivaRate).times(100).decimal().replace('.', ',')}% (G)` },
        igtfLabel() {
            const rate = this.quote?.igtf_rate ?? this.settings.rate
            return rate == null ? 'IGTF (IGTF)' : `IGTF - ${exactAmount(rate).decimal().replace('.', ',')}% (IGTF)`
        },
        ivaSummary() {
            try {
                const convert = amount => {
                    if (this.document.currency_type_id === 'VES') return amount
                    if (this.document.currency_type_id !== 'USD') throw new Error('Seleccione la moneda del comprobante.')
                    return amount.times(normalizeExchangeRate(this.document.exchange_rate_sale))
                }
                return {base: convert(exactValue(this.document, 'total_taxed')), tax: convert(exactValue(this.document, 'total_igv'))}
            } catch (_) {
                return {base: null, tax: null, error: 'Indique una moneda y una tasa válidas para mostrar el IVA en bolívares.'}
            }
        },
        igtfSummary() {
            const empty = {base: null, tax: null}
            if (this.quoteError || this.settings.error) return {...empty, error: this.quoteError || this.settings.error}
            if (!this.settings.loaded || this.settings.loading || (this.quotePending && !this.quote)) return {...empty, pending: true}
            let base = exactAmount(0), tax = exactAmount(0)
            if (this.settings.enabled && this.quote) {
                try {
                    for (const row of this.quote.payments) {
                        if (row.reused || row.igtf_status !== 'subject' || row.currency_type_id !== 'USD') continue
                        const rate = normalizeExchangeRate(row.exchange_rate)
                        // El principal recibido ya descuenta el vuelto. El impuesto es el cobrado,
                        // liquidado por el servidor, no el resultado de aplicar otra vez el porcentaje.
                        base = base.plus(exactValue(row, 'original_amount').times(rate))
                        tax = tax.plus(exactValue(row, 'tax_amount').times(rate))
                    }
                } catch (_) {
                    return {...empty, error: 'No se pudo mostrar el IGTF en bolívares. Revise los importes y la tasa del cobro.'}
                }
            }
            return {base, tax, pending: this.quotePending}
        },
    },
    methods: {
        money(amount) {
            if (amount === null) return '—'
            const [integer, cents] = amount.final(2).split('.')
            const formatted = `${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${cents}`
            return this.currencySymbol ? `${this.currencySymbol} ${formatted}` : formatted
        },
    },
}
// ######## FIN RESUMEN REACTIVO IVA E IGTF ########
</script>

<style scoped>
/* ######## INICIO RESUMEN REACTIVO IVA E IGTF ######## */
.invoice-tax-summary {
    width: 100%;
    max-width: 100%;
    position: relative;
    border: 1px solid #dedede;
    border-radius: 10px;
    padding: 16px;
}
.invoice-tax-summary .invoice-tax-table {
    width: 100%;
    table-layout: fixed !important;
    margin-bottom: 0;
}
.invoice-tax-table th,
.invoice-tax-table td {
    padding: 8px 10px;
    overflow-wrap: anywhere;
    white-space: normal;
}
.invoice-tax-table th {
    white-space: nowrap;
    overflow-wrap: normal;
}
.invoice-tax-summary .invoice-tax-table thead tr th {
    border-bottom: 1px solid #e5e5e5 !important;
}
.tax-quote-status { position: absolute; top: 100%; right: 0; }
@media (max-width: 1199px) {
    .invoice-tax-table th, .invoice-tax-table td { padding-left: 4px; padding-right: 4px; }
    .invoice-tax-table th { font-size: 11px; white-space: normal; }
}
.invoice-tax-table td {
    border-bottom: 1px solid var(--bs-border-color, #e9ecef);
}
/* ######## FIN RESUMEN REACTIVO IVA E IGTF ######## */
</style>
