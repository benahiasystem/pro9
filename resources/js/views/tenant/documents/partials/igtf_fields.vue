<template>
    <div v-if="!isRecordedReceipt(payment)" class="mt-2">
        <small v-if="proposal && proposal.issuer_spe" class="d-block">Emisor: Sujeto Pasivo Especial vigente.</small>
        <small v-if="proposal && proposal.buyer_spe" class="d-block">Comprador: Sujeto Pasivo Especial vigente. RIF fiscal: {{ proposal.buyer.rif }}</small>
        <!-- ######## INICIO CAMPOS DE COBRO SIMPLIFICADOS ######## -->
        <!-- Moneda e intermediación proceden del método seleccionado. -->
        <!-- ######## FIN CAMPOS DE COBRO SIMPLIFICADOS ######## -->
        <template v-if="payment.currency_type_id && payment.currency_type_id !== documentCurrency">
            <label>Importe recibido para la venta, sin IGTF ({{ payment.currency_type_id }})</label>
            <el-input v-model="payment.original_amount" @input="clearServerError('original_amount'); convertPrincipal()"/>
            <small v-if="!documentId" class="d-block">Si hay exceso en efectivo, el vuelto se calcula antes del IGTF.</small>
            <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
            <small v-if="settledReceived" class="d-block">Se liquidará {{ settledReceived }} {{ payment.currency_type_id }}.</small>
            <small class="d-block">El importe aplicado corresponde a los centavos recibidos.</small>
            <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
        </template>
        <!-- ######## INICIO TASA DOCUMENTAL COMPARTIDA ######## -->
        <template v-if="!useDocumentRate && payment.currency_type_id && (payment.currency_type_id !== documentCurrency || !hasDocumentRate)">
            <label>Tasa VES por USD</label><el-input v-model="payment.exchange_rate" @input="clearServerError('exchange_rate'); convertPrincipal()"/>
        </template>
        <!-- ######## FIN TASA DOCUMENTAL COMPARTIDA ######## -->
        <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
        <small v-if="payment.currency_type_id === documentCurrency && settledReceived" class="d-block">Se liquidará {{ settledReceived }} {{ payment.currency_type_id }}.</small>
        <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
        <template v-if="igtfEnabled">
            <label>Clasificación IGTF</label>
            <small class="d-block">{{ payment.igtf_status === 'subject' ? 'Sujeto' : 'No aplica' }}</small>
            <small v-if="payment.igtf_status === 'subject'">El IGTF se cobra adicional al importe neto aplicado.</small>
        </template>
        <small v-if="proposal && proposal.retention && proposal.retention.eligible">Retención IVA prevista: {{ proposal.retention.percentage }} %. El saldo se aplica al registrar el comprobante.</small>
        <small v-if="error" class="text-danger">{{ error }}</small>
        <!-- ######## INICIO MONEDA DEL MEDIO DE COBRO ######## -->
        <small v-for="(message, index) in serverErrors" :key="index" class="d-block text-danger">{{ message }}</small>
        <!-- ######## FIN MONEDA DEL MEDIO DE COBRO ######## -->
    </div>
</template>
<script>
import {rateMultiply, rateDivide, appliedReceiptAmount, exactAmount} from '../../../../helpers/exchange-rate-math'
import {isRecordedReceipt, paymentMethodIntermediation, retireDraftIgtfException} from '../../../../helpers/payment-classification'
// ######## INICIO CAMPOS DE COBRO SIMPLIFICADOS ########
export default {
    props: {settingsVersion: {type: Number, default: 0}, igtfEnabled: {type: Boolean, default: false}, payment: {type: Object, required: true}, paymentMethods: {type: Array, default: () => []}, documentCurrency: {default: 'VES'}, documentRate: {default: null}, useDocumentRate: {type: Boolean, default: false}, customerId: {default: null}, documentId: {default: null}},
    data: () => ({proposal: null, error: '', sequence: 0, lastApplied: null}),
    created() {
        if (isRecordedReceipt(this.payment)) return
        if (!this.payment.igtf_status) this.$set(this.payment, 'igtf_status', 'not_applicable')
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'financial_intermediation')) this.$set(this.payment, 'financial_intermediation', null)
        this.syncMethodCurrency()
        this.evaluate()
    },
    computed: {
        evaluationConfiguration() { return `${this.igtfEnabled}:${this.settingsVersion}` },
        methodCurrency() { return this.paymentMethods.find(method => String(method.id) === String(this.payment.payment_method_type_id))?.currency_type_id || null },
        methodContext() {
            const method = this.paymentMethods.find(method => String(method.id) === String(this.payment.payment_method_type_id))
            return JSON.stringify([method?.id, method?.currency_type_id, method?.collection_kind])
        },
        hasDocumentRate() { try { return exactAmount(this.documentRate).compare(0) > 0 } catch (error) { return false } },
        // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
        serverErrors() {
            const errors = this.payment.errors || {}
            return ['currency_type_id', 'original_amount', 'exchange_rate', 'financial_intermediation', 'igtf_status', 'exemption_reason', 'exception_basis', 'quoted_tax_amount'].flatMap(field => {
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
        payment: 'syncMethodCurrency', methodContext: 'syncMethodCurrency',
        'payment.payment'(value) {
            if (this.lastApplied !== null && String(value) === String(this.lastApplied)) this.lastApplied = null
            else { this.lastApplied = null; this.proposeReceived() }
            this.evaluate()
        },
        'payment.date_of_payment': 'evaluate',
        documentCurrency() { this.proposeReceived(); this.evaluate() },
        documentRate(value, previous) {
            if (isRecordedReceipt(this.payment) || (!this.useDocumentRate && this.payment.exchange_rate && this.payment.exchange_rate !== previous)) return
            this.clearServerError('exchange_rate')
            this.$set(this.payment, 'exchange_rate', value)
            if (this.useDocumentRate && this.payment.original_amount != null) this.convertPrincipal()
            else { this.proposeReceived(); this.evaluate() }
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
            // ######## INICIO RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            retireDraftIgtfException(this.payment, this.$set, this.$delete)
            // ######## FIN RETIRADA DE EXCEPCION ADMINISTRATIVA IGTF ########
            this.clearServerError('currency_type_id')
            this.clearServerError('financial_intermediation')
            this.$set(this.payment, 'financial_intermediation', paymentMethodIntermediation(this.paymentMethods || [], this.payment.payment_method_type_id))
            if (this.useDocumentRate) this.$set(this.payment, 'exchange_rate', this.documentRate)
            if (!this.methodCurrency) return
            // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
            if (this.payment.currency_type_id === this.methodCurrency) {
                if (this.payment.original_amount === null || this.payment.original_amount === undefined) { this.proposeReceived(); this.evaluate() }
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
        convertPrincipal() {
            if (isRecordedReceipt(this.payment)) return
            if (this.useDocumentRate || this.payment.currency_type_id !== this.documentCurrency) {
                try {
                    if (exactAmount(this.payment.exchange_rate).compare(0) <= 0) throw new Error('invalid rate')
                } catch (_) {
                    this.sequence++
                    this.proposal = null
                    this.error = 'Indique una tasa VES por USD válida y mayor que cero.'
                    return
                }
            }
            try {
                const p = this.payment
                // ######## INICIO MONEDA DEL MEDIO DE COBRO ########
                const applied = appliedReceiptAmount(p, this.documentCurrency).final(2)
                // ######## FIN MONEDA DEL MEDIO DE COBRO ########
                this.lastApplied = applied
                p.payment = applied
                this.error = ''; this.evaluate()
            } catch (e) { this.error = 'Indique un importe recibido válido para calcular el cobro.' }
        },
        async evaluate() {
            if (!this.$http || isRecordedReceipt(this.payment)) return
            const sequence = ++this.sequence
            this.proposal = null
            if (this.useDocumentRate && !this.hasDocumentRate) {
                this.error = 'Indique una tasa VES por USD válida y mayor que cero.'
                return
            }
            try {
                const p = this.payment
                if (p.currency_type_id === this.documentCurrency && (p.original_amount === null || p.original_amount === undefined)) this.$set(p, 'original_amount', p.payment)
                const context = JSON.stringify([this.documentId, this.customerId, this.documentCurrency, this.igtfEnabled,
                    p.payment, p.original_amount, p.currency_type_id, p.payment_method_type_id, p.financial_intermediation, p.date_of_payment, p.exchange_rate])
                const {data} = await this.$http.post('/fiscal-profiles/evaluate', {customer_id: this.customerId, document_id: this.documentId,
                    date: p.date_of_payment || new Date().toLocaleDateString('en-CA', {timeZone: 'America/Caracas'}),
                    currency_type_id: p.currency_type_id || this.documentCurrency, payment_method_type_id: p.payment_method_type_id,
                    financial_intermediation: p.financial_intermediation,
                    original_amount: p.currency_type_id && p.currency_type_id !== this.documentCurrency ? p.original_amount : p.payment})
                if (sequence !== this.sequence || this.payment !== p || isRecordedReceipt(p)) return
                if (context !== JSON.stringify([this.documentId, this.customerId, this.documentCurrency, this.igtfEnabled,
                    p.payment, p.original_amount, p.currency_type_id, p.payment_method_type_id, p.financial_intermediation, p.date_of_payment, p.exchange_rate])) return
                this.proposal = data.data; this.error = ''
                if (this.igtfEnabled && this.proposal.payment) this.$set(p, 'igtf_status', this.proposal.payment.igtf_status)
            } catch (e) { if (sequence === this.sequence) this.error = 'No se pudo evaluar el cobro; se validará al guardar.' }
        }
    }
}
// ######## FIN CAMPOS DE COBRO SIMPLIFICADOS ########
</script>
