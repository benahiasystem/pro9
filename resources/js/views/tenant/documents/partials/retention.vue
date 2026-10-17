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
        <p v-if="initializing">Cargando formulario…</p>
        <div v-if="!initializing && document.id && !replacementKind">
            <label>Tipo</label><el-select v-model="form.tax_kind" @change="changeKind"><el-option label="IVA" value="IVA"/><el-option label="ISLR" value="ISLR"/></el-select>
        </div>
        <form v-if="canCreate && !initializing" @submit.prevent="submit">
            <label>Número de comprobante</label><el-input v-model="form.voucher_number"/>
            <label>RIF del agente según comprobante</label><el-input v-model="form.agent_rif"/>
            <label>Número de factura</label><el-input v-model="form.invoice_number"/>
            <label>Control fiscal de factura</label><el-input v-model="form.invoice_control"/>
            <label>Fecha</label><el-date-picker v-model="form.voucher_date" value-format="yyyy-MM-dd"/>
            <template v-if="form.tax_kind === 'ISLR'"><label>Concepto ISLR</label><el-select v-model="form.concept_id" filterable><el-option v-for="concept in concepts" :key="concept.id" :value="concept.id" :label="concept.id + ' — ' + concept.description"/></el-select><label>Fundamento del concepto aplicado</label><el-input v-model="form.fiscal_context.concept_basis"/><label>Referencia de evidencia ISLR</label><el-input v-model="form.fiscal_context.evidence_reference"/></template>
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
            <el-button v-if="form.tax_kind === 'ISLR'" @click="evaluate">Calcular propuesta ISLR declarada</el-button><p v-if="form.tax_kind === 'ISLR' && proposal">{{ proposal.voucher_amount }} {{ form.currency_type_id }}</p>
            <label>Adjunto privado PDF o imagen (obligatorio)</label><input type="file" accept="application/pdf,image/png,image/jpeg" :disabled="uploading" @change="upload"/>
            <p v-if="uploading">Cargando soporte…</p>
            <p v-else-if="form.upload_id || form.attachment">Soporte cargado.</p>
            <el-button native-type="submit" type="primary" :loading="loading" :disabled="uploading || evaluating">Registrar comprobante</el-button>
        </form>
        <el-button @click="close">Cerrar</el-button>
        <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
    </el-dialog>
    <!-- ######## FIN PERSISTENCIA FISCAL VENEZUELA ######## -->
</template>
<script>
import {exactAmount} from "@helpers/exchange-rate-math"
// ######## INICIO PERSISTENCIA FISCAL VENEZUELA ########
export default {
    props: {
        showDialog: Boolean,
        documentId: {default: null},
        replacementEndpoint: {type: String, default: null},
        replacementKind: {type: String, default: null},
    },
    data: () => ({ records: [], form: {}, loading: false, uploading: false, evaluating: false, initializing: false, error: '', proposal: null, prevision: null, concepts: [], document: {}, canCreate: false, sequence: 0, proposalSequence: 0 }),
    watch: { showDialog(value) { if (value) this.load(); else this.sequence++ }, documentId() { if (this.showDialog) this.load() } },
    beforeDestroy() { this.sequence++ },
    methods: {
        async load() {
            const sequence = ++this.sequence;
            this.initializing = true;
            this.uploading = false;
            this.evaluating = false;
            this.error = ''; this.records = []; this.proposal = null; this.prevision = null; this.canCreate=false; this.form={};
            try {
                if (!this.documentId) { this.error = 'Seleccione una factura guardada para gestionar sus retenciones.'; return; }
                const response = await this.$http.get(`/documents/retention/${this.documentId}`);
                if (sequence !== this.sequence) return;
                const data = response.data;
                const iva = data.document.iva_retention || {};
                const islr = data.document.islr_retention || {};
                this.records = data.data.map(row => ({...row,...[...(iva.records || []),...(islr.records || [])].find(info => info.id === row.id)}));
                this.concepts = data.document.islr_concepts || [];
                if (this.replacementEndpoint) data.document={...data.document,total_igv:data.document.effective_total_igv,total_taxed:data.document.effective_total_taxed,total_value:data.document.effective_total_value};
                this.document=data.document;
                const kind = this.replacementKind || 'IVA';
                const summary = kind === 'ISLR' ? islr : iva;
                this.prevision = summary.prevision;
                this.canCreate = summary.can_create === true;
                this.form = { agent_rif: '', invoice_control: data.document.control_number, invoice_number: data.document.number_full, fiscal_context: {concept_basis:null,iva_100_basis:null,evidence_reference:null,public_operation_scope:null,public_scope_reference:null}, document_id: this.documentId, agent_id: data.document.customer_id, tax_kind: kind, voucher_number: '',
                    voucher_date: new Date().toLocaleDateString('en-CA', { timeZone: 'America/Caracas' }), currency_type_id: data.document.currency_type_id,
                    exchange_rate: data.document.exchange_rate_sale, base: kind === 'ISLR' ? data.document.total_value : data.document.total_igv, percentage: '', subtrahend: 0, amount: '', concept_id: null, attachment: null, operation_key: this.newKey() }
            } catch (e) { if (sequence === this.sequence) this.error = 'No se pudieron cargar las retenciones.' }
            finally { if (sequence === this.sequence) this.initializing = false }
        },
        changeKind(kind) {
            this.proposal=null; this.error=''; this.uploading=false; this.evaluating=false;
            const summary = this.document[kind === 'ISLR' ? 'islr_retention' : 'iva_retention'] || {};
            this.prevision = summary.prevision;
            this.canCreate = summary.can_create === true;
            this.form={...this.form,tax_kind:kind,fiscal_context:{concept_basis:null,iva_100_basis:null,evidence_reference:null,public_operation_scope:null,public_scope_reference:null},base:kind === 'ISLR' ? this.document.total_value : this.document.total_igv,percentage:'',subtrahend:0,amount:'',voucher_number:'',attachment:null,upload_id:null,operation_key:this.newKey()};
        },
        async evaluate() {
            if (!this.documentId || !this.canCreate) return;
            const sequence = this.sequence;
            const proposalSequence = ++this.proposalSequence;
            const form = this.form;
            this.evaluating = true; this.error = '';
            try {
                const {data} = await this.$http.post('/documents/retention/proposal', {...this.form,document_currency_type_id:this.document.currency_type_id,document_id:this.documentId,total_value:this.document.total_value,exchange_rate_sale:this.document.exchange_rate_sale,customer_id:this.form.agent_id,date:this.form.voucher_date,currency_type_id:this.form.currency_type_id,
                    total_igv:this.document.total_igv,total_taxed:this.document.total_taxed,fiscal_context:this.form.fiscal_context})
                if (sequence !== this.sequence || proposalSequence !== this.proposalSequence || form !== this.form) return;
                this.proposal = data.data; this.form.agent_rif = data.data.agent_rif || ''
                if (this.proposal.eligible) { this.form.percentage = this.proposal.percentage; this.form.amount = this.form.tax_kind === 'ISLR' ? this.proposal.voucher_amount : exactAmount(this.form.base).times(this.proposal.percentage).dividedBy(100).decimal() }
            } catch (e) {if (sequence === this.sequence && proposalSequence === this.proposalSequence && form === this.form) this.error = 'No se pudo evaluar la retención.'}
            finally { if (sequence === this.sequence && proposalSequence === this.proposalSequence && form === this.form) this.evaluating = false }
        },
        async upload(event) {
            if (!this.documentId || !this.canCreate) return;
            const sequence = this.sequence;
            const form = this.form;
            const file = event.target.files[0]
            if (!file) return
            if (this.uploading) return
            this.uploading = true; this.error = '';
            const body = new FormData(); body.append('file', file); if (this.documentId) body.append('document_id', this.documentId)
            try { const { data } = await this.$http.post('/documents/retention/upload', body); if (sequence !== this.sequence || form !== this.form) return; this.form.attachment = data.attachment; this.form.upload_id = data.upload_id }
            catch (e) { if (sequence === this.sequence && form === this.form) this.error = 'No se pudo adjuntar el comprobante.' }
            finally { if (sequence === this.sequence && form === this.form) this.uploading = false }
        },
        async submit() {
            if (!this.documentId || !this.canCreate || this.loading || this.uploading || this.evaluating) return;
            this.loading = true; this.error = ''
            try {
                await this.$http.post(this.replacementEndpoint || '/documents/retention', this.form); await this.load(); this.$eventHub.$emit('reloadData'); this.$emit('changed')
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
            catch (e) { this.error='No se pudo completar la operación de retención. Consulte su estado antes de repetirla.' }
            finally { this.loading=false }
        },
        async reverse(row) {
            try {
                const {value} = await this.$prompt('Indique el motivo. El saldo se restituirá al conciliar HKA.','Revertir retención ' + row.tax_kind,{inputValidator:value=>!!value && !!value.trim(),inputErrorMessage:'Indique el motivo'});
                await this.action(row,'reverse',{operation_key:this.newKey(),reason:value.trim()});
            } catch (e) { /* Prompt cancelled. */ }
        },
        async download(row) {
            try {
                const response=await this.$http.get(`/documents/retention/${row.id}/attachment`,{responseType:'blob'});
                const url=URL.createObjectURL(response.data), link=document.createElement('a');
                link.href=url; link.download=`Retencion-${row.tax_kind}-${row.voucher_number}`; link.click(); URL.revokeObjectURL(url);
            } catch (e) { this.error='No se pudo descargar el soporte.' }
        },
        close() { this.sequence++; this.$emit('update:showDialog', false) }
    }
}
// ######## FIN PERSISTENCIA FISCAL VENEZUELA ########
</script>
