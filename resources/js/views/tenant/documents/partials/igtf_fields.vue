<template>
    <div class="mt-2">
        <small v-if="proposal && proposal.issuer_spe" class="d-block">Emisor: Sujeto Pasivo Especial vigente.</small>
        <small v-if="proposal && proposal.buyer_spe" class="d-block">Comprador: Sujeto Pasivo Especial vigente. RIF fiscal: {{ proposal.buyer.rif }}</small>
        <label>Moneda efectivamente recibida</label>
        <el-select :value="payment.currency_type_id || documentCurrency" @change="changeCurrency">
            <el-option value="VES" label="VES"/><el-option value="USD" label="USD"/>
        </el-select>
        <template v-if="payment.currency_type_id && payment.currency_type_id !== documentCurrency">
            <label>Importe recibido sin IGTF ni vuelto</label>
            <el-input v-model="payment.original_amount" @input="convertPrincipal"/>
            <label>Tasa VES por USD</label><el-input v-model="payment.exchange_rate" @input="convertPrincipal"/>
        </template>
        <label>Intermediación del cobro</label>
        <el-select v-model="payment.financial_intermediation" @change="evaluate">
            <el-option value="none" label="Sin institución financiera"/>
            <el-option value="financial_institution" label="Con institución financiera"/>
        </el-select>
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
        <small v-if="proposal && proposal.needs_intermediation">Indique la intermediación real para evaluar el cobro.</small>
        <small v-if="proposal && proposal.retention.eligible">Retención IVA prevista: {{ proposal.retention.percentage }} %. El saldo se aplica al registrar el comprobante.</small>
        <small v-if="payment.igtf_status === 'subject'">El IGTF se cobra adicional al importe neto aplicado.</small>
        <small v-if="error" class="text-danger">{{ error }}</small>
    </div>
</template>
<script>
import {rateMultiply, rateDivide} from '../../../../helpers/exchange-rate-math'
export default {
    props: {payment: {type: Object, required: true}, documentCurrency: {default: 'VES'}, documentRate: {default: '1'}, customerId: {default: null}, documentId: {default: null}},
    data: () => ({proposal: null, error: '', sequence: 0}),
    created() {
        if (!this.payment.igtf_status) this.$set(this.payment, 'igtf_status', 'not_applicable')
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'exemption_reason')) this.$set(this.payment, 'exemption_reason', null)
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'financial_intermediation')) this.$set(this.payment, 'financial_intermediation', null)
        if (!Object.prototype.hasOwnProperty.call(this.payment, 'exception_basis')) this.$set(this.payment, 'exception_basis', null)
        if (this.$http) this.evaluate()
    },
    watch: {'payment.payment': 'evaluate', 'payment.date_of_payment': 'evaluate', documentCurrency: 'evaluate', customerId: 'evaluate'},
    methods: {
        changeCurrency(currency) {
            this.$set(this.payment, 'currency_type_id', currency)
            this.$set(this.payment, 'exchange_rate', this.documentRate)
            this.$set(this.payment, 'original_amount', currency === this.documentCurrency ? this.payment.payment : (currency === 'USD' ? rateDivide(this.payment.payment, this.documentRate) : rateMultiply(this.payment.payment, this.documentRate)))
            this.$set(this.payment, 'operation_key', this.payment.operation_key || crypto.randomUUID())
            this.convertPrincipal()
        },
        convertPrincipal() {
            try {
                const p = this.payment
                p.payment = p.currency_type_id === this.documentCurrency ? p.original_amount
                    : Number(p.currency_type_id === 'USD' ? rateMultiply(p.original_amount, p.exchange_rate) : rateDivide(p.original_amount, p.exchange_rate))
                this.error = ''; this.evaluate()
            } catch (e) { this.error = e.message }
        },
        async evaluate() {
            if (!this.$http) return
            const sequence = ++this.sequence
            try {
                const p = this.payment
                const {data} = await this.$http.post('/fiscal-profiles/evaluate', {customer_id: this.customerId, document_id: this.documentId,
                    date: p.date_of_payment || new Date().toLocaleDateString('en-CA', {timeZone: 'America/Caracas'}),
                    currency_type_id: p.currency_type_id || this.documentCurrency, financial_intermediation: p.financial_intermediation,
                    original_amount: p.currency_type_id && p.currency_type_id !== this.documentCurrency ? p.original_amount : p.payment})
                if (sequence !== this.sequence) return
                this.proposal = data.data; this.error = ''
                if (this.proposal.payment && p.igtf_status !== 'exempt') this.$set(p, 'igtf_status', this.proposal.payment.igtf_status)
                if (p.currency_type_id === this.documentCurrency) this.$set(p, 'original_amount', p.payment)
            } catch (e) { if (sequence === this.sequence) this.error = 'No se pudo evaluar el cobro; se validará al guardar.' }
        }
    }
}
</script>
