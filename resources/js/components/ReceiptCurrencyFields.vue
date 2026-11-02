<template>
    <div v-if="!isRecordedReceipt(row) && (methodCurrency || serverErrors.length)" class="mt-2">
        <small v-if="methodCurrency" class="d-block">Moneda recibida: {{ methodCurrency }}</small>
        <template v-if="methodCurrency && methodCurrency !== documentCurrency">
            <label>Importe recibido ({{ methodCurrency }})</label>
            <el-input :value="row.original_amount" @input="changeReceived" />
            <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
            <small v-if="settledReceived" class="d-block">Se liquidará {{ settledReceived }} {{ methodCurrency }}.</small>
            <small class="d-block">El importe aplicado corresponde a los centavos recibidos.</small>
            <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
            <small class="d-block">Importe aplicado: {{ row.payment }} {{ documentCurrency }}</small>
        </template>
        <template v-if="methodCurrency && (methodCurrency !== documentCurrency || !hasDocumentRate)">
            <label>Tasa VES por USD</label>
            <el-input :value="row.exchange_rate" @input="changeRate" />
        </template>
        <small v-if="error" class="text-danger">{{ error }}</small>
        <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
        <small v-for="(message, index) in serverErrors" :key="index" class="d-block text-danger">{{ message }}</small>
        <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
    </div>
</template>

<script>
import {rateDivide, rateMultiply, appliedReceiptAmount, exactAmount} from '../helpers/exchange-rate-math'
import {isRecordedReceipt} from '../helpers/payment-classification'

// ######## INICIO MONEDA DEL MEDIO DE COBRO ########
export default {
    props: {
        row: {type: Object, required: true},
        methods: {type: Array, default: () => []},
        documentCurrency: {type: String, default: 'VES'},
        documentRate: {default: null},
    },
    data: () => ({error: '', lastApplied: null}),
    computed: {
        methodCurrency() {
            return this.methods.find(method => String(method.id) === String(this.row.payment_method_type_id))?.currency_type_id || null
        },
        hasDocumentRate() {
            try { return exactAmount(this.documentRate).compare(0) > 0 } catch (error) { return false }
        },
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        settledReceived() {
            try {
                const amount = exactAmount(this.row.original_amount)
                const settled = amount.final(2)
                return amount.compare(settled) === 0 ? null : settled
            } catch (e) { return null }
        },
        serverErrors() {
            const errors = this.row.errors || {}
            return ['currency_type_id', 'original_amount', 'exchange_rate'].flatMap(field => {
                const messages = errors[field]
                return (Array.isArray(messages) ? messages : [messages]).filter(message => typeof message === 'string' && message.trim())
            })
        },
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    },
    created() { this.syncMethod() },
    watch: {
        methodCurrency() { if (!isRecordedReceipt(this.row)) { this.clearServerError('currency_type_id'); this.syncMethod() } },
        documentCurrency() { this.syncMethod(true) },
        documentRate(value, previous) {
            if (isRecordedReceipt(this.row) || (this.row.exchange_rate && this.row.exchange_rate !== previous)) return
            this.$set(this.row, 'exchange_rate', value)
            this.proposeReceived()
        },
        'row.payment'(value) {
            if (this.lastApplied !== null && String(value) === String(this.lastApplied)) { this.lastApplied = null; return }
            this.lastApplied = null
            this.proposeReceived()
        },
    },
    methods: {
        isRecordedReceipt,
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        clearServerError(field) {
            if (this.row.errors && Object.prototype.hasOwnProperty.call(this.row.errors, field)) this.$delete(this.row.errors, field)
        },
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        syncMethod(force = false) {
            if (!this.methodCurrency || isRecordedReceipt(this.row)) return
            if (!force && this.row.currency_type_id === this.methodCurrency && this.row.exchange_rate) {
                if (this.row.original_amount === null || this.row.original_amount === undefined) this.proposeReceived()
                else this.updateApplied()
                return
            }
            this.$set(this.row, 'currency_type_id', this.methodCurrency)
            this.$set(this.row, 'exchange_rate', this.documentRate)
            this.proposeReceived()
        },
        proposeReceived() {
            if (!this.methodCurrency || isRecordedReceipt(this.row)) return
            const amount = this.row.payment
            if (amount === null || amount === undefined || amount === '') { this.$set(this.row, 'original_amount', null); return }
            try {
                const received = this.methodCurrency === this.documentCurrency ? amount
                    : (this.methodCurrency === 'USD' ? rateDivide(amount, this.row.exchange_rate) : rateMultiply(amount, this.row.exchange_rate))
                this.$set(this.row, 'original_amount', received)
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                if (exactAmount(received).compare(0) > 0) {
                    const applied = appliedReceiptAmount({...this.row, original_amount: received, currency_type_id: this.methodCurrency}, this.documentCurrency).final(2)
                    if (exactAmount(applied).compare(this.row.payment) !== 0) { this.lastApplied = applied; this.$set(this.row, 'payment', applied) }
                }
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                this.error = ''
            } catch (e) { this.$set(this.row, 'original_amount', null); this.error = 'Indique una tasa válida para calcular el importe recibido.' }
        },
        changeReceived(value) {
            if (isRecordedReceipt(this.row)) return
            this.clearServerError('original_amount')
            this.$set(this.row, 'original_amount', value)
            this.updateApplied()
        },
        changeRate(value) {
            if (isRecordedReceipt(this.row)) return
            this.clearServerError('exchange_rate')
            this.$set(this.row, 'exchange_rate', value)
            this.updateApplied()
        },
        updateApplied() {
            if (isRecordedReceipt(this.row)) return
            try {
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                const applied = appliedReceiptAmount({...this.row, currency_type_id: this.methodCurrency}, this.documentCurrency).final(2)
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                this.lastApplied = applied
                this.$set(this.row, 'payment', applied)
                this.error = ''
            } catch (e) { this.error = 'Indique un importe y una tasa válidos.' }
        },
    },
}
// ######## FIN MONEDA DEL MEDIO DE COBRO ########
</script>
