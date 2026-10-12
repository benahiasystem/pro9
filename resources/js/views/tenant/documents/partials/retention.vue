<template>
    <!-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## -->
    <el-dialog title="Retenciones recibidas IVA / ISLR" :visible="showDialog" @close="close">
        <table class="table"><thead><tr><th>Tipo</th><th>Comprobante</th><th>Importe aplicado</th></tr></thead>
            <tbody><tr v-for="row in records" :key="row.id"><td>{{ row.tax_kind }}</td><td>{{ row.voucher_number }}</td><td>{{ row.applied_amount }}</td></tr></tbody>
        </table>
        <form @submit.prevent="submit">
            <label>Tipo</label><el-select v-model="form.tax_kind"><el-option label="IVA" value="IVA"/><el-option label="ISLR" value="ISLR"/></el-select>
            <label>Número de comprobante</label><el-input v-model="form.voucher_number"/>
            <label>RIF del agente según comprobante</label><el-input v-model="form.agent_rif"/>
            <label>Número de factura</label><el-input v-model="form.invoice_number"/>
            <label>Control fiscal de factura</label><el-input v-model="form.invoice_control"/>
            <label>Fecha</label><el-date-picker v-model="form.voucher_date" value-format="yyyy-MM-dd"/>
            <template v-if="form.tax_kind === 'ISLR'"><label>Código de concepto ISLR</label><el-input v-model="form.concept_id"/><label>Fundamento del concepto aplicado</label><el-input v-model="form.fiscal_context.concept_basis"/><label>Referencia de evidencia ISLR</label><el-input v-model="form.fiscal_context.evidence_reference"/></template>
            <label>Moneda del comprobante</label><el-select v-model="form.currency_type_id"><el-option label="Bs. / VES" value="VES"/><el-option label="USD" value="USD"/></el-select>
            <label>Tasa VES por USD</label><el-input v-model="form.exchange_rate"/>
            <label>Base retenida</label><el-input v-model="form.base"/>
            <label>Porcentaje</label><el-input v-model="form.percentage"/>
            <label>Sustraendo</label><el-input v-model="form.subtrahend"/>
            <label>Importe retenido</label><el-input v-model="form.amount"/>
            <template v-if="form.tax_kind === 'IVA'">
                <label>Fundamento del 100 %, si corresponde</label><el-select v-model="form.fiscal_context.iva_100_basis" clearable><el-option value="undisclosed_iva" label="IVA no discriminado"/><el-option value="invoice_formalities" label="Formalidades"/><el-option value="portal_100" label="Portal fiscal: 100 %"/><el-option value="unregistered_rif" label="No inscrito en RIF"/></el-select>
                <label>Referencia de evidencia</label><el-input v-model="form.fiscal_context.evidence_reference"/>
                <label>Operación de ente público</label><el-select v-model="form.fiscal_context.public_operation_scope" clearable><el-option value="subject" label="Sujeta según partidas y normativa"/><el-option value="excluded" label="Excluida"/></el-select>
                <label>Fundamento y revisión de partidas públicas</label><el-input v-model="form.fiscal_context.public_scope_reference"/>
                <el-button @click="evaluate">Consultar propuesta de retención</el-button><p v-if="proposal">{{ proposal.eligible ? 'Porcentaje propuesto: ' + proposal.percentage + ' %' : 'Sin facultad u operación elegible para retención IVA' }}</p>
            </template>
            <label>Adjunto privado PDF o imagen (obligatorio)</label><input type="file" accept="application/pdf,image/png,image/jpeg" @change="upload"/>
            <p v-if="error" class="text-danger">{{ error }}</p>
            <el-button native-type="submit" type="primary" :loading="loading">Registrar comprobante</el-button>
            <el-button @click="close">Cerrar</el-button>
        </form>
    </el-dialog>
    <!-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## -->
</template>
<script>
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
export default {
    props: ['showDialog', 'documentId'],
    data: () => ({ records: [], form: {}, loading: false, error: '', proposal: null }),
    watch: { showDialog(value) { if (value) this.load() } },
    methods: {
        async load() {
            try {
                const { data } = await this.$http.get(`/documents/retention/${this.documentId}`)
                this.records = data.data
                this.proposal = null
                this.form = { agent_rif: '', invoice_control: data.document.control_number, invoice_number: data.document.number_full, fiscal_context: {concept_basis:null,iva_100_basis:null,evidence_reference:null,public_operation_scope:null,public_scope_reference:null}, document_id: this.documentId, agent_id: data.document.customer_id, tax_kind: 'IVA', voucher_number: '',
                    voucher_date: new Date().toLocaleDateString('en-CA', { timeZone: 'America/Caracas' }), currency_type_id: data.document.currency_type_id,
                    exchange_rate: data.document.exchange_rate_sale, base: '', percentage: '', subtrahend: 0, amount: '', concept_id: null, attachment: null }
            } catch (e) { this.error = 'No se pudieron cargar las retenciones.' }
        },
        async evaluate() {
            try {
                const {data} = await this.$http.post('/fiscal-profiles/evaluate', {document_id:this.documentId,date:this.form.voucher_date,currency_type_id:this.form.currency_type_id,financial_intermediation:'financial_institution',fiscal_context:this.form.fiscal_context})
                this.proposal = data.data.retention; this.form.agent_rif = data.data.buyer.rif || ''
                if (this.proposal.eligible) this.form.percentage = this.proposal.percentage
            } catch (e) {this.error = 'No se pudo evaluar la retención.'}
        },
        async upload(event) {
            const file = event.target.files[0]
            if (!file) return
            const body = new FormData(); body.append('file', file); body.append('document_id', this.documentId)
            try { const { data } = await this.$http.post('/documents/retention/upload', body); this.form.attachment = data.attachment }
            catch (e) { this.error = 'No se pudo adjuntar el comprobante.' }
        },
        async submit() {
            this.loading = true; this.error = ''
            try { await this.$http.post('/documents/retention', this.form); await this.load(); this.$eventHub.$emit('reloadData') }
            catch (e) { const errors = e.response && e.response.data && e.response.data.errors; this.error = errors ? Object.values(errors).flat().join(' ') : 'No se pudo registrar el comprobante.' }
            finally { this.loading = false }
        },
        close() { this.$emit('update:showDialog', false) }
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
</script>
