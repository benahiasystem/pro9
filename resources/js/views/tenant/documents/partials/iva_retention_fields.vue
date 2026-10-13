<template>
    <div v-if="form.document_type_id === '01'" class="border rounded p-2 my-2" data-testid="iva-retention-fields">
        <islr-retention-fields :form="form" :document-id="documentId" :readonly="readonly" @net="$emit('net',$event)"/>
        <strong>Retención IVA del cliente</strong>
        <template v-if="!readonly">
            <el-checkbox :value="enabled" @change="toggle">Prever retención IVA</el-checkbox>
            <div v-if="enabled">
                <label>Fundamento del 100 %, cuando corresponda</label>
                <el-select v-model="context.iva_100_basis" clearable @change="evaluate">
                    <el-option value="portal_100" label="Portal fiscal: 100 %"/>
                    <el-option value="invoice_formalities" label="Formalidades"/>
                    <el-option value="undisclosed_iva" label="IVA no discriminado"/>
                    <el-option value="unregistered_rif" label="No inscrito en RIF"/>
                    <el-option value="precious_metals" label="Metales preciosos"/>
                </el-select>
                <el-input v-model="context.evidence_reference" placeholder="Referencia de evidencia" @change="evaluate"/>
                <el-select v-model="context.public_operation_scope" clearable placeholder="Operación de ente público" @change="evaluate">
                    <el-option value="subject" label="Sujeta"/><el-option value="excluded" label="Excluida"/>
                </el-select>
                <el-input v-model="context.public_scope_reference" placeholder="Fundamento de la operación pública" @change="evaluate"/>
                <p v-if="proposal">{{ proposal.percentage }} % del IVA: {{ proposal.amount }} {{ form.currency_type_id }}.</p>
                <p>La previsión permanece como saldo pendiente hasta validar el comprobante recibido.</p>
                <el-alert v-if="hkaNetBlocked" type="warning" :closable="false" title="HKA exige pagos por el total de la factura. El cobro neto puede guardarse localmente, pero su emisión digital queda pendiente."/>
                <el-button v-if="proposal && proposal.eligible" size="mini" @click="useNet">Usar cobro neto sugerido</el-button>
            </div>
            <el-button size="mini" @click="showVoucher = true">{{ form.received_retentions && form.received_retentions.length ? 'Revisar comprobante cargado' : 'Cargar comprobante recibido' }}</el-button>
            <el-button v-if="form.received_retentions && form.received_retentions.length && !documentId" size="mini" @click="$set(form,'received_retentions',[])">Quitar comprobante cargado</el-button>
        </template>
        <template v-else><p v-if="form.iva_retention && form.iva_retention.prevision">Prevista: {{ form.iva_retention.prevision.amount }} {{ form.currency_type_id }}</p></template>
        <p v-if="error" class="text-danger">{{ error }}</p>
        <retention-form :show-dialog.sync="showVoucher" :document-id="documentId" :draft-form="documentId ? null : form"/>
    </div>
</template>
<script>
import RetentionForm from './retention.vue'
import IslrRetentionFields from './islr_retention_fields.vue'
export default {
    components: {RetentionForm,IslrRetentionFields},
    props: {form: {type: Object, required: true}, documentId: {default: null}, readonly: {type: Boolean, default: false}},
    data: () => ({proposal: null, error: '', sequence: 0, showVoucher: false, hkaNetBlocked: false,
        context: {iva_100_basis:null,evidence_reference:null,public_operation_scope:null,public_scope_reference:null}}),
    computed: {
        enabled() { return !!(this.form.iva_retention_prevision && this.form.iva_retention_prevision.enabled) },
        evaluationKey() { return [this.form.customer_id,this.form.date_of_issue,this.form.total_igv,this.form.total_taxed,this.form.currency_type_id,this.form.document_type_id].join('|') },
    },
    watch: {
        evaluationKey() {
            if (this.form.document_type_id !== '01' && !this.documentId && !this.readonly) {
                this.sequence++; this.proposal=null;
                this.$set(this.form,'iva_retention_prevision',null); this.$set(this.form,'islr_retention_prevision',null); this.$set(this.form,'received_retentions',[]);
            } else if (this.enabled) this.evaluate();
        },
        enabled(value) { if (value) this.evaluate() },
    },
    mounted() {
        if (this.form.iva_retention_prevision) this.context = {...this.context,...this.form.iva_retention_prevision.fiscal_context};
        if (this.enabled) this.evaluate();
    },
    beforeDestroy() { this.sequence++ },
    methods: {
        useNet() {
            const amount = Math.round(Math.max(0, Number(this.form.total) - Number(this.proposal.amount) - Number(this.form.islr_retention_prevision && this.form.islr_retention_prevision.enabled ? this.form.islr_retention_prevision.amount || 0 : 0)) * 100) / 100;
            const rows = this.form.payments || [];
            if (rows.length > 1 || (rows[0] && rows[0].currency_type_id && rows[0].currency_type_id !== this.form.currency_type_id)) {
                this.$message.info('Conserve los abonos y ajuste el importe en su moneda de recepción.'); return;
            }
            if (rows[0]) { this.$set(rows[0],'payment',amount); this.$set(rows[0],'original_amount',amount); }
            this.$emit('net',amount);
        },
        toggle(value) {
            this.$set(this.form,'iva_retention_prevision',value ? {enabled:true,fiscal_context:{...this.context}} : {enabled:false});
            if (!value) { this.sequence++; this.proposal=null; this.error=''; }
        },
        async evaluate() {
            const sequence = ++this.sequence;
            this.proposal = null; this.error = '';
            if (!this.enabled || !this.form.customer_id || this.form.document_type_id !== '01') return;
            // A changed invoice invalidates the previous monetary suggestion immediately.
            this.$set(this.form,'iva_retention_prevision',{enabled:true,fiscal_context:{...this.context}});
            try {
                const {data} = await this.$http.post('/documents/retention/proposal', {
                    customer_id:this.form.customer_id, date:this.form.date_of_issue, total_igv:this.form.total_igv || 0,
                    total_taxed:this.form.total_taxed || 0,currency_type_id:this.form.currency_type_id,fiscal_context:this.context,
                });
                if (sequence !== this.sequence || !this.enabled) return;
                this.proposal = data.data;
                this.hkaNetBlocked = data.hka_net_collection_blocked === true;
                if (!this.proposal.eligible) { this.error='El cliente o la operación no acreditan retención IVA.'; return; }
                this.$set(this.form,'iva_retention_prevision',{enabled:true,amount:this.proposal.amount,fiscal_context:{...this.context}});
            } catch (e) {
                if (sequence === this.sequence) this.error='No se pudo consultar la previsión IVA.';
            }
        },
    },
}
</script>
