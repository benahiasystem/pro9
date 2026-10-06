<template>
    <!-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## -->
    <el-dialog title="Retenciones recibidas IVA / ISLR" :visible="showDialog" @close="close">
        <table class="table"><thead><tr><th>Tipo</th><th>Comprobante</th><th>Importe aplicado</th></tr></thead>
            <tbody><tr v-for="row in records" :key="row.id"><td>{{ row.tax_kind }}</td><td>{{ row.voucher_number }}</td><td>{{ row.applied_amount }}</td></tr></tbody>
        </table>
        <form @submit.prevent="submit">
            <label>Tipo</label><el-select v-model="form.tax_kind"><el-option label="IVA" value="IVA"/><el-option label="ISLR" value="ISLR"/></el-select>
            <label>Número de comprobante</label><el-input v-model="form.voucher_number"/>
            <label>Fecha</label><el-date-picker v-model="form.voucher_date" value-format="yyyy-MM-dd"/>
            <template v-if="form.tax_kind === 'ISLR'"><label>Código de concepto ISLR</label><el-input v-model="form.concept_id"/></template>
            <label>Moneda del comprobante</label><el-select v-model="form.currency_type_id"><el-option label="Bs. / VES" value="VES"/><el-option label="USD" value="USD"/></el-select>
            <label>Tasa VES por USD</label><el-input v-model="form.exchange_rate"/>
            <label>Base retenida</label><el-input v-model="form.base"/>
            <label>Porcentaje</label><el-input v-model="form.percentage"/>
            <label>Sustraendo</label><el-input v-model="form.subtrahend"/>
            <label>Importe retenido</label><el-input v-model="form.amount"/>
            <label>Adjunto PDF o imagen</label><input type="file" accept="application/pdf,image/png,image/jpeg" @change="upload"/>
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
    data: () => ({ records: [], form: {}, loading: false, error: '' }),
    watch: { showDialog(value) { if (value) this.load() } },
    methods: {
        async load() {
            try {
                const { data } = await this.$http.get(`/documents/retention/${this.documentId}`)
                this.records = data.data
                this.form = { document_id: this.documentId, agent_id: data.document.customer_id, tax_kind: 'IVA', voucher_number: '',
                    voucher_date: new Date().toLocaleDateString('en-CA', { timeZone: 'America/Caracas' }), currency_type_id: data.document.currency_type_id,
                    exchange_rate: data.document.exchange_rate_sale, base: '', percentage: '', subtrahend: 0, amount: '', concept_id: null, attachment: null }
            } catch (e) { this.error = 'No se pudieron cargar las retenciones.' }
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
