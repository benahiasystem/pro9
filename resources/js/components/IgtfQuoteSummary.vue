<template>
    <div class="igtf-quote mt-2" aria-live="polite">
        <small v-if="loading" class="d-block text-muted">Evaluando el cobro…</small>
        <small v-if="error" class="d-block text-danger" role="alert">{{ error }}</small>
        <template v-if="quote && !loading && !error">
            <small v-if="!quote.igtf_enabled" class="d-block">IGTF está deshabilitado en la configuración de la empresa.</small>
            <small v-else class="d-block">Tasa IGTF de la empresa: {{ quote.igtf_rate }} %</small>
            <small class="d-block">Principal aplicado: {{ quote.principal_applied }} {{ currency }}</small>
            <small class="d-block">IGTF adicional: {{ quote.tax_in_document_currency }} {{ currency }}</small>
            <small class="d-block"><strong>Total con IGTF: {{ quote.total_with_tax }} {{ currency }}</strong></small>
            <small v-for="(amount, money) in quote.received_by_currency" :key="'net-'+money" class="d-block">A recibir neto: {{ amount }} {{ money }}</small>
            <template v-for="(amount, money) in quote.tendered_by_currency">
                <small v-if="amount !== quote.received_by_currency[money]" :key="'tender-'+money" class="d-block">Total entregado antes del vuelto: {{ amount }} {{ money }}</small>
            </template>
            <small class="d-block">Saldo comercial pendiente: {{ quote.balance }} {{ currency }}</small>
            <small v-for="(row, index) in quote.payments" :key="index" class="d-block">{{ row.reason }} IGTF: {{ row.tax_amount }} {{ row.currency_type_id }}<template v-if="row.change !== '0.00'">; vuelto: {{ row.change }} {{ row.currency_type_id }}</template></small>
        </template>
    </div>
</template>
<script>
export default {props: {quote: {default: null}, loading: Boolean, error: {default: ''}, currency: {default: ''}}}
</script>
