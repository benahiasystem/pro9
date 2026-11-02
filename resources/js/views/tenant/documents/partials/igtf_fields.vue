<template>
    <div v-if="!isRecordedReceipt(payment)" class="mt-2">
        <small v-if="proposal && proposal.issuer_spe" class="d-block">Emisor: Sujeto Pasivo Especial vigente.</small>
        <small v-if="proposal && proposal.buyer_spe" class="d-block">Comprador: Sujeto Pasivo Especial vigente. RIF fiscal: {{ proposal.buyer.rif }}</small>
        <label>Moneda efectivamente recibida</label>
        <el-select :value="payment.currency_type_id || documentCurrency" :disabled="!!methodCurrency" @change="changeCurrency">
            <el-option value="VES" label="VES"/><el-option value="USD" label="USD"/>
        </el-select>
        <template v-if="payment.currency_type_id && payment.currency_type_id !== documentCurrency">
            <label>Importe recibido sin IGTF ni vuelto</label>
            <el-input v-model="payment.original_amount" @input="clearServerError('original_amount'); convertPrincipal()"/>
            <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
            <small v-if="settledReceived" class="d-block">Se liquidará {{ settledReceived }} {{ payment.currency_type_id }}.</small>
            <small class="d-block">El importe aplicado corresponde a los centavos recibidos.</small>
            <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
        </template>
        <template v-if="payment.currency_type_id && (payment.currency_type_id !== documentCurrency || !hasDocumentRate)">
            <label>Tasa VES por USD</label><el-input v-model="payment.exchange_rate" @input="clearServerError('exchange_rate'); convertPrincipal()"/>
        </template>
        <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
        <small v-if="payment.currency_type_id === documentCurrency && settledReceived" class="d-block">Se liquidará {{ settledReceived }} {{ payment.currency_type_id }}.</small>
        <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
        <label>Intermediación del cobro</label>
        <el-select v-model="payment.financial_intermediation" @change="evaluate">
            <el-option value="none" label="Sin institución financiera"/>
            <el-option value="financial_institution" label="Con institución financiera"/>
        </el-select>
        <template v-if="igtfEnabled">
            <label>Clasificación IGTF</label>
            <el-select v-model="payment.igtf_status" @change="evaluate" placeholder="No aplica">
                <el-option value="not_applicable" label="No aplica" />
                <el-option value="subject" label="Sujeto" />
                <el-option value="exempt" label="Excepción con autorización administrativa" />
            </el-select>
            <template v-if="payment.igtf_status === 'exempt'">
                <el-input v-model="payment.exemption_reason" placeholder="Motivo de excepción" />
                <el-input v-model="payment.exception_basis" placeholder="Fundamento de excepción" />
            </template>
            <small v-if="proposal && proposal.payment">Propuesta: {{ proposal.payment.igtf_status }}. IGTF adicional: {{ proposal.tax_amount }} {{ payment.currency_type_id || documentCurrency }}</small>
            <small v-if="payment.igtf_status === 'subject'">El IGTF se cobra adicional al importe neto aplicado.</small>
        </template>
        <small v-if="proposal && proposal.needs_intermediation">Indique la intermediación real para evaluar el cobro.</small>
        <small v-if="proposal && proposal.retention && proposal.retention.eligible">Retención IVA prevista: {{ proposal.retention.percentage }} %. El saldo se aplica al registrar el comprobante.</small>
        <small v-if="error" class="text-danger">{{ error }}</small>
        <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
        <small v-for="(message, index) in serverErrors" :key="index" class="d-block text-danger">{{ message }}</small>
        <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
    </div>
</template>
<script>
import {rateMultiply, rateDivide, appliedReceiptAmount, exactAmount} from '../../../../helpers/exchange-rate-math'
import {isRecordedReceipt} from '../../../../helpers/payment-classification'
export default {
    props: {settingsVersion: {type: Number, default: 0}, igtfEnabled: {type: Boolean, default: false}, payment: {type: Object, required: true}, paymentMethods: {type: Array, default: () => []}, documentCurrency: {default: 'VES'}, documentRate: {default: null}, customerId: {default: null}, documentId: {default: null}},
    data: () => ({proposal: null, error: '', sequence: 0, lastApplied: null}),
    created() {
        if (isRecordedReceipt(this.payment)) return
        if (!this.payment.igtf_status) this.$set(this.payment, 'igtf_status', 'not_applicable')
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'exemption_reason')) this.$set(this.payment, 'exemption_reason', null)
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'financial_intermediation')) this.$set(this.payment, 'financial_intermediation', null)
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'exception_basis')) this.$set(this.payment, 'exception_basis', null)
        if (this.$http) { this.syncMethodCurrency(); this.evaluate() }
    },
    computed: {
        evaluationConfiguration() { return `${this.igtfEnabled}:${this.settingsVersion}` },
        methodCurrency() { return this.paymentMethods.find(method => String(method.id) === String(this.payment.payment_method_type_id))?.currency_type_id || null },
        hasDocumentRate() { try { return exactAmount(this.documentRate).compare(0) > 0 } catch (error) { return false } },
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        serverErrors() {
            const errors = this.payment.errors || {}
            return ['currency_type_id', 'original_amount', 'exchange_rate'].flatMap(field => {
                const messages = errors[field]
                return (Array.isArray(messages) ? messages : [messages]).filter(message => typeof message === 'string' && message.trim())
            })
        },
        settledReceived() {
            try {
                const amount = exactAmount(this.payment.original_amount)
                const settled = amount.final(2)
                return amount.compare(settled) === 0 ? null : settled
            } catch (e) { return null }
        },
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
    },
    beforeDestroy() { this.sequence++ },
    watch: {
        payment: 'evaluate', methodCurrency: 'syncMethodCurrency',
        'payment.payment'(value) {
            if (this.lastApplied !== null && String(value) === String(this.lastApplied)) this.lastApplied = null
            else { this.lastApplied = null; this.proposeReceived() }
            this.evaluate()
        },
        'payment.date_of_payment': 'evaluate',
        documentCurrency() { this.proposeReceived(); this.evaluate() },
        documentRate(value, previous) {
            if (isRecordedReceipt(this.payment) || (this.payment.exchange_rate && this.payment.exchange_rate !== previous)) return
            this.$set(this.payment, 'exchange_rate', value)
            this.proposeReceived()
        },
        customerId: 'evaluate', documentId: 'evaluate', evaluationConfiguration: 'evaluate',
    },
    methods: {
        isRecordedReceipt,
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        clearServerError(field) {
            if (this.payment.errors && Object.prototype.hasOwnProperty.call(this.payment.errors, field)) this.$delete(this.payment.errors, field)
        },
        // ######## FIN MONEDA DEL MEDIO DE COBRO ########
        proposeReceived() {
            const p = this.payment
            if (isRecordedReceipt(p) || !p.currency_type_id) return
            if (p.payment === null || p.payment === undefined || p.payment === '') {
                this.$set(p, 'original_amount', null)
                return
            }
            try {
                const received = p.currency_type_id === this.documentCurrency ? p.payment
                    : (p.currency_type_id === 'USD' ? rateDivide(p.payment, p.exchange_rate) : rateMultiply(p.payment, p.exchange_rate))
                this.$set(p, 'original_amount', received)
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                if (exactAmount(received).compare(0) > 0) {
                    const applied = appliedReceiptAmount({...p, original_amount: received}, this.documentCurrency).final(2)
                    if (exactAmount(applied).compare(p.payment) !== 0) { this.lastApplied = applied; this.$set(p, 'payment', applied) }
                }
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                this.error = ''
            } catch (e) {
                this.$set(p, 'original_amount', null)
                this.error = 'Indique una tasa válida para calcular el importe recibido.'
            }
        },
        syncMethodCurrency() {
            if (isRecordedReceipt(this.payment)) return
            this.clearServerError('currency_type_id')
            if (!this.methodCurrency) return
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            if (this.payment.currency_type_id === this.methodCurrency) {
                if (this.payment.original_amount === null || this.payment.original_amount === undefined) this.proposeReceived()
                else this.convertPrincipal()
                return
            }
            // ######## FIN MONEDA DEL MEDIO DE COBRO ########
            const currency = this.methodCurrency
            this.$set(this.payment, 'currency_type_id', currency)
            this.$set(this.payment, 'exchange_rate', this.documentRate)
            this.$set(this.payment, 'operation_key', this.payment.operation_key || crypto.randomUUID())
            this.$set(this.payment, 'original_amount', null)
            try {
                if (this.payment.payment !== null && this.payment.payment !== undefined && this.payment.payment !== '') {
                    const received = currency === this.documentCurrency ? this.payment.payment
                        : (currency === 'USD' ? rateDivide(this.payment.payment, this.documentRate) : rateMultiply(this.payment.payment, this.documentRate))
                    this.$set(this.payment, 'original_amount', received)
                    // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                    if (exactAmount(received).compare(0) > 0) {
                        const applied = appliedReceiptAmount({...this.payment, original_amount: received}, this.documentCurrency).final(2)
                        if (exactAmount(applied).compare(this.payment.payment) !== 0) { this.lastApplied = applied; this.$set(this.payment, 'payment', applied) }
                    }
                    // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                }
                this.error = ''
                this.evaluate()
            } catch (e) { this.error = 'Indique una tasa válida para calcular el importe recibido.' }
        },
        changeCurrency(currency) {
            if (isRecordedReceipt(this.payment)) return
            this.$set(this.payment, 'currency_type_id', currency)
            this.$set(this.payment, 'exchange_rate', this.documentRate)
            this.$set(this.payment, 'operation_key', this.payment.operation_key || crypto.randomUUID())
            try {
                const received = currency === this.documentCurrency ? this.payment.payment
                    : (currency === 'USD' ? rateDivide(this.payment.payment, this.documentRate) : rateMultiply(this.payment.payment, this.documentRate))
                this.$set(this.payment, 'original_amount', received)
            } catch (e) {
                this.$set(this.payment, 'original_amount', null)
                this.error = 'Indique una tasa válida para calcular el importe recibido.'
                return
            }
            this.convertPrincipal()
        },
        convertPrincipal() {
            if (isRecordedReceipt(this.payment)) return
            try {
                const p = this.payment
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                const applied = appliedReceiptAmount(p, this.documentCurrency).final(2)
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                this.lastApplied = applied
                p.payment = applied
                this.error = ''; this.evaluate()
            } catch (e) { this.error = e.message }
        },
        async evaluate() {
            if (!this.$http || isRecordedReceipt(this.payment)) return
            const sequence = ++this.sequence
            this.proposal = null
            try {
                const p = this.payment
                if (p.currency_type_id === this.documentCurrency && (p.original_amount === null || p.original_amount === undefined)) this.$set(p, 'original_amount', p.payment)
                const context = JSON.stringify([this.documentId, this.customerId, this.documentCurrency, this.igtfEnabled,
                    p.payment, p.original_amount, p.currency_type_id, p.financial_intermediation, p.date_of_payment])
                const {data} = await this.$http.post('/fiscal-profiles/evaluate', {customer_id: this.customerId, document_id: this.documentId,
                    date: p.date_of_payment || new Date().toLocaleDateString('en-CA', {timeZone: 'America/Caracas'}),
                    currency_type_id: p.currency_type_id || this.documentCurrency, financial_intermediation: p.financial_intermediation,
                    original_amount: p.currency_type_id && p.currency_type_id !== this.documentCurrency ? p.original_amount : p.payment})
                if (sequence !== this.sequence || this.payment !== p || isRecordedReceipt(p)) return
                if (context !== JSON.stringify([this.documentId, this.customerId, this.documentCurrency, this.igtfEnabled,
                    p.payment, p.original_amount, p.currency_type_id, p.financial_intermediation, p.date_of_payment])) return
                this.proposal = data.data; this.error = ''
                if (this.igtfEnabled && this.proposal.payment && p.igtf_status !== 'exempt') this.$set(p, 'igtf_status', this.proposal.payment.igtf_status)
            } catch (e) { if (sequence === this.sequence) this.error = 'No se pudo evaluar el cobro; se validará al guardar.' }
        }
    }
}
</script>
