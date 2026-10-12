<template>
    <!-- ######## INICIO PERSISTENCIA FISCAL VENEZUELA ######## -->
    <el-dialog title="Retenciones recibidas IVA / ISLR" :visible="showDialog" @close="close">
        <p v-if="prevision">Prevista: {{ prevision.amount }} {{ prevision.currency_type_id }}. Permanece pendiente hasta validar el comprobante.</p>
        <table class="table"><thead><tr><th>Tipo</th><th>Comprobante</th><th>Importe aplicado</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody><tr v-for="row in records" :key="row.id"><td>{{ row.tax_kind }}</td><td>{{ row.voucher_number }}</td><td>{{ row.applied_amount }}</td>
                <td>{{ applicationLabel(row.application_status) }}<br/>{{ hkaLabel(row.hka_status) }}<p v-if="row.diagnostic">{{ row.diagnostic }}</p></td>
                <td><el-button v-if="row.has_attachment" size="mini" @click="download(row)">Soporte</el-button>
                    <el-button v-if="row.can_query" size="mini" :disabled="loading" @click="action(row, 'query-hka')">Consultar HKA</el-button>
                    <el-button v-if="row.can_retry" size="mini" :disabled="loading" @click="action(row, 'retry-hka')">Reintentar</el-button>
                    <el-button v-if="row.can_reverse" size="mini" :disabled="loading" @click="reverse(row)">Revertir</el-button></td></tr></tbody>
        </table>
        <p v-if="draftForm">Los datos cargados quedan pendientes hasta cotejar el número y control reales de la factura.</p>
        <form v-if="canCreate" @submit.prevent="submit">
            <label>Tipo</label><el-select v-model="form.tax_kind"><el-option label="IVA" value="IVA"/><el-option v-if="!draftForm" label="ISLR" value="ISLR"/></el-select>
            <label>Número de comprobante</label><el-input v-model="form.voucher_number"/>
            <label>RIF del agente según comprobante</label><el-input v-model="form.agent_rif"/>
            <label>Número de factura</label><el-input v-model="form.invoice_number"/>
            <label>Control fiscal de factura</label><el-input v-model="form.invoice_control"/>
            <label>Fecha</label><el-date-picker v-model="form.voucher_date" value-format="yyyy-MM-dd"/>
            <template v-if="form.tax_kind === 'ISLR'"><label>Código de concepto ISLR</label><el-input v-model="form.concept_id"/><label>Fundamento del concepto aplicado</label><el-input v-model="form.fiscal_context.concept_basis"/><label>Referencia de evidencia ISLR</label><el-input v-model="form.fiscal_context.evidence_reference"/></template>
            <label>Moneda del comprobante</label><el-select v-model="form.currency_type_id"><el-option label="Bs. / VES" value="VES"/><el-option label="USD" value="USD"/></el-select>
            <label>Tasa VES por USD</label><el-input v-model="form.exchange_rate"/>
            <label>{{ form.tax_kind === 'IVA' ? 'IVA sujeto a retención' : 'Base retenida' }}</label><el-input v-model="form.base"/>
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
            <el-button native-type="submit" type="primary" :loading="loading">{{ draftForm ? 'Incorporar a la factura' : 'Registrar comprobante' }}</el-button>
            <el-button @click="close">Cerrar</el-button>
        </form>
    </el-dialog>
    <!-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## -->
</template>
<script>
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
export default {
    props: ['showDialog', 'documentId', 'draftForm'],
    data: () => ({ records: [], form: {}, loading: false, error: '', proposal: null, prevision: null, canCreate: true, sequence: 0 }),
    watch: { showDialog(value) { if (value) this.load(); else this.sequence++ }, documentId() { if (this.showDialog) this.load() } },
    methods: {
        async load() {
            const sequence = ++this.sequence;
            this.error = ''; this.records = []; this.proposal = null; this.prevision = null; this.canCreate=false; this.form={};
            try {
                let data;
                if (this.documentId) {
                    const response = await this.$http.get(`/documents/retention/${this.documentId}`); data = response.data;
                    if (sequence !== this.sequence) return;
                    const iva = data.document.iva_retention || {};
                    this.records = data.data.map(row => ({...row,...(iva.records || []).find(info => info.id === row.id)}));
                    this.prevision = iva.prevision;
                    this.canCreate = iva.can_create !== false;
                } else {
                    const draft = this.draftForm;
                    this.canCreate=true;
                    this.records = (draft.received_retentions || []).map(row => ({...row,applied_amount:0,application_status:'pending_validation'}));
                    this.prevision = draft.iva_retention_prevision;
                    data = {document:{...draft,number_full:'',control_number:'',total_igv:draft.total_igv}};
                }
                this.form = { agent_rif: '', invoice_control: data.document.control_number, invoice_number: data.document.number_full, fiscal_context: {concept_basis:null,iva_100_basis:null,evidence_reference:null,public_operation_scope:null,public_scope_reference:null}, document_id: this.documentId, agent_id: data.document.customer_id, tax_kind: 'IVA', voucher_number: '',
                    voucher_date: new Date().toLocaleDateString('en-CA', { timeZone: 'America/Caracas' }), currency_type_id: data.document.currency_type_id,
                    exchange_rate: data.document.exchange_rate_sale, base: data.document.total_igv, percentage: '', subtrahend: 0, amount: '', concept_id: null, attachment: null, operation_key: this.newKey() }
                if (this.draftForm && this.draftForm.received_retentions && this.draftForm.received_retentions[0]) this.form = {...this.form,...this.draftForm.received_retentions[0]};
            } catch (e) { this.error = 'No se pudieron cargar las retenciones.' }
        },
        async evaluate() {
            const sequence = this.sequence;
            const form = this.form;
            try {
                const {data} = await this.$http.post('/documents/retention/proposal', {document_id:this.documentId,customer_id:this.form.agent_id,date:this.form.voucher_date,currency_type_id:this.form.currency_type_id,
                    total_igv:this.draftForm ? this.draftForm.total_igv : 0,total_taxed:this.draftForm ? this.draftForm.total_taxed : 0,fiscal_context:this.form.fiscal_context})
                if (sequence !== this.sequence || form !== this.form) return;
                this.proposal = data.data; this.form.agent_rif = data.data.agent_rif || ''
                if (this.proposal.eligible) { this.form.percentage = this.proposal.percentage; this.form.amount = Math.round(Number(this.form.base) * this.proposal.percentage) / 100 }
            } catch (e) {if (sequence === this.sequence && form === this.form) this.error = 'No se pudo evaluar la retención.'}
        },
        async upload(event) {
            const sequence = this.sequence;
            const form = this.form;
            const file = event.target.files[0]
            if (!file) return
            const body = new FormData(); body.append('file', file); if (this.documentId) body.append('document_id', this.documentId)
            try { const { data } = await this.$http.post(this.documentId ? '/documents/retention/upload' : '/documents/retention/upload-draft', body); if (sequence !== this.sequence || form !== this.form) return; this.form.attachment = data.attachment; this.form.upload_id = data.upload_id }
            catch (e) { if (sequence === this.sequence && form === this.form) this.error = 'No se pudo adjuntar el comprobante.' }
        },
        async submit() {
            this.loading = true; this.error = ''
            try {
                if (this.draftForm) { this.$set(this.draftForm,'received_retentions',[{...this.form,tax_kind:'IVA'}]); this.close(); return; }
                await this.$http.post('/documents/retention', this.form); await this.load(); this.$eventHub.$emit('reloadData'); this.$emit('changed')
            }
            catch (e) { this.error = this.requestError(e,'No se pudo registrar el comprobante.') }
            finally { this.loading = false }
        },
        newKey() {
            const bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes); bytes[6]=(bytes[6]&15)|64; bytes[8]=(bytes[8]&63)|128;
            const hex=Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');
            return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
        },
        requestError(error,fallback) {
            const data=error.response && error.response.data;
            if (!data || typeof data !== 'object') return fallback;
            if (typeof data.message === 'string') return data.message;
            const messages=data.errors || (typeof data.message === 'object' ? data.message : data);
            return Object.values(messages).flat().filter(value=>typeof value==='string').join(' ') || fallback;
        },
        applicationLabel(status) { return {pending_validation:'Pendiente de validar',applied:'Aplicada localmente',invalid:'Requiere corrección',reversed:'Revertida'}[status] || status || '' },
        hkaLabel(status) { return {waiting_invoice:'Espera confirmación de factura',waiting:'HKA pendiente',pending:'Conciliando HKA',confirmed:'Confirmada en HKA',uncertain:'Por conciliar',rejected:'Rechazada por HKA',conflict:'Conflicto HKA',removed:'Retirada de HKA',not_applicable:''}[status] || '' },
        async action(row, action, extra = {}) {
            this.loading = true; this.error = '';
            try { await this.$http.post(`/documents/retention/${row.id}/${action}`,{operation_key:row.hka_operation_key || row.operation_key,...extra}); await this.load(); this.$eventHub.$emit('reloadData'); this.$emit('changed') }
            catch (e) { this.error='No se pudo completar la operación IVA. Consulte su estado antes de repetirla.' }
            finally { this.loading=false }
        },
        async reverse(row) {
            try {
                const {value} = await this.$prompt('Indique el motivo. El saldo se restituirá al conciliar HKA.','Revertir retención IVA',{inputValidator:value=>!!value && !!value.trim(),inputErrorMessage:'Indique el motivo'});
                await this.action(row,'reverse',{operation_key:this.newKey(),reason:value.trim()});
            } catch (e) { /* Prompt cancelled. */ }
        },
        async download(row) {
            try {
                const response=await this.$http.get(`/documents/retention/${row.id}/attachment`,{responseType:'blob'});
                const url=URL.createObjectURL(response.data), link=document.createElement('a');
                link.href=url; link.download=`Retencion-IVA-${row.voucher_number}`; link.click(); URL.revokeObjectURL(url);
            } catch (e) { this.error='No se pudo descargar el soporte.' }
        },
        close() { this.$emit('update:showDialog', false) }
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
</script>
