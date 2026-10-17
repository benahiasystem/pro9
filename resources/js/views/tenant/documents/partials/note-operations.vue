<template>
<el-dialog title="Devoluciones y ajustes de la nota de crédito" :visible="showDialog" width="75%" append-to-body @close="close">
    <div v-loading="busy">
        <el-alert v-if="error" :title="error" type="error" :closable="false"/>
        <h4>Devolución de dinero</h4><p>Disponible: {{ refunds.amount_available || 0 }} {{ refunds.currency_type_id }}. {{ refunds.block_reason }}</p>
        <table class="table"><thead><tr><th>Fecha / origen</th><th>Importe devuelto</th><th>Aplicado a la nota</th><th>Estado / motivo</th><th/></tr></thead><tbody><tr v-for="r in refunds.records || []" :key="r.id"><td>{{ r.date }} · Cobro {{ r.source_payment_id }}<small class="d-block">{{ r.payment_method }} · {{ r.destination }}</small></td><td>{{ r.original_amount }} {{ r.currency_type_id }}<small class="d-block">Tasa {{ r.exchange_rate }}</small></td><td>{{ r.amount }} {{ refunds.currency_type_id }}</td><td>{{ r.reversed_at ? 'Revertida' : 'Registrada' }}<small class="d-block">{{ r.reversal_reason || r.reference }}</small></td><td><el-button v-if="r.can_reverse" size="mini" @click="reverse(r)">Revertir</el-button></td></tr></tbody></table>
        <form v-if="refunds.can_create" @submit.prevent="refund">
            <div class="row"><div class="col-md-6"><label>Cobro original</label><el-select v-model="refundForm.document_payment_id"><el-option v-for="s in (refunds.sources || []).filter(r=>r.principal_eligible)" :key="s.id" :value="s.id" :label="s.date+' · '+s.original_amount+' '+s.currency_type_id"/></el-select></div>
                <div class="col-md-3"><label>Fecha devolución</label><el-date-picker v-model="refundForm.date_of_payment" value-format="yyyy-MM-dd" :clearable="false"/></div>
                <div class="col-md-3"><label>Moneda devuelta</label><el-select v-model="refundForm.currency_type_id"><el-option value="VES" label="VES"/><el-option value="USD" label="USD"/></el-select></div>
                <div class="col-md-3 mt-2"><label>Importe realmente devuelto</label><el-input v-model="refundForm.original_amount" type="number" min="0.01" step="0.01"/></div><div class="col-md-3 mt-2"><label>Tasa VES por USD</label><el-input v-model="refundForm.exchange_rate"/></div>
                <div class="col-md-3 mt-2"><label>Medio de devolución</label><el-select v-model="refundForm.payment_method_type_id"><el-option v-for="m in tables.payment_method_types || []" :key="m.id" :value="m.id" :label="m.description"/></el-select></div>
                <div class="col-md-3 mt-2"><label>Caja / banco de salida</label><el-select v-model="refundForm.payment_destination_id"><el-option v-for="d in tables.payment_destinations || []" :key="d.id" :value="d.id" :label="d.description"/></el-select></div>
                <div class="col-md-9 mt-2"><label>Referencia de la devolución</label><el-input v-model="refundForm.reference" maxlength="255"/></div><div class="col-md-3 mt-2"><el-button type="primary" native-type="submit" :disabled="busy">Registrar devolución</el-button></div>
            </div>
        </form>
        <h4 class="mt-4">Ajustes relacionados</h4>
        <table class="table"><thead><tr><th>Concepto / origen</th><th>Importe</th><th>Estado</th><th>Detalle / soporte</th><th/></tr></thead><tbody><tr v-for="a in adjustments.records || []" :key="a.id"><td>{{ a.kind }}<small class="d-block">{{ a.origin && a.origin.description }}</small></td><td>{{ a.amount }}</td><td>{{ adjustmentLabel(a.status) }}</td><td>{{ a.diagnostic }}<small class="d-block">{{ a.reason }}</small><a v-if="a.support_url" :href="a.support_url" target="_blank" rel="noopener">Comprobante original</a><a v-if="a.replacement_id" :href="'/documents/retention/'+a.replacement_id+'/attachment'" target="_blank" rel="noopener"> · Comprobante corregido</a></td><td><el-button v-if="allowRetentionManagement && a.can_replace" size="mini" @click="replacement(a)">Registrar comprobante corregido</el-button></td></tr></tbody></table>
        <form v-if="adjustments.can_create" @submit.prevent="adjust">
            <label>Concepto de origen</label><el-select v-model="selectedSource"><el-option v-for="s in availableAdjustmentSources" :key="s.kind+'-'+s.id" :value="s.kind+'-'+s.id" :label="s.description+' · '+s.amount"/></el-select>
            <label v-if="adjustmentForm.kind==='IGTF' || adjustmentForm.kind==='fund'">Importe que se ajusta</label><el-input v-if="adjustmentForm.kind==='IGTF' || adjustmentForm.kind==='fund'" v-model="adjustmentForm.amount" type="number" min="0.01" step="0.01"/>
            <label>Motivo y referencia del ajuste</label><el-input v-model="adjustmentForm.reason" maxlength="255"/>
            <el-button native-type="submit" class="mt-2" :disabled="busy || !selectedSource">Registrar ajuste</el-button>
            <el-button v-if="allowRetentionManagement && invoiceId" class="mt-2" @click="replacementEndpoint=null;replacementKind=null;showRetention=true">Consultar / conciliar retenciones de la factura</el-button>
        </form>
        <p class="mt-2">El ajuste IGTF permanece pendiente hasta contar con aceptación fiscal verificable. Una retención conserva su aplicación mientras su reversión HKA esté por conciliar.</p>
        <el-button class="mt-3" @click="load">Actualizar estados</el-button>
    </div>
    <retention-form v-if="allowRetentionManagement" :showDialog.sync="showRetention" :documentId="invoiceId" :replacementEndpoint="replacementEndpoint" :replacementKind="replacementKind" @changed="load"/>
</el-dialog>
</template>
<script>
import {noteRequestId} from '../../../../helpers/fiscal-note';
import RetentionForm from './retention.vue';
export default {
    components:{RetentionForm},props:{showDialog:{},documentId:{},allowRetentionManagement:Boolean},data(){return {busy:false,error:'',refunds:{},adjustments:{},tables:{},invoiceId:null,refundForm:{},adjustmentForm:{},selectedSource:null,showRetention:false,replacementEndpoint:null,replacementKind:null,sequence:0,reverseKeys:{}};},
    computed:{availableAdjustmentSources(){return (this.adjustments.sources || []).filter(source=>this.allowRetentionManagement || !['IVA','ISLR'].includes(source.kind))}},
    watch:{allowRetentionManagement(value){if(!value){this.showRetention=false;this.selectedSource=null}},showDialog(v){if(v)this.load();else this.sequence++;},documentId(){this.sequence++;this.refundForm={};this.adjustmentForm={};this.selectedSource=null;this.reverseKeys={};this.showRetention=false;this.replacementEndpoint=null;if(this.showDialog)this.load();},selectedSource(v){const s=this.availableAdjustmentSources.find(s=>s.kind+'-'+s.id===v);if(s)this.adjustmentForm={...this.adjustmentForm,kind:s.kind,source_id:s.id,amount:s.amount};}},
    methods:{async load(){const seq=++this.sequence;this.busy=true;this.error='';try{const [result,tables]=await Promise.all([this.$http.get(`/documents/${this.documentId}/refunds`),this.$http.get('/document_payments/tables')]);if(seq!==this.sequence)return;this.apply(result.data);this.tables=tables.data;if(!this.refundForm.operation_key)this.resetForms();}catch(e){if(seq===this.sequence)this.error=this.message(e);}finally{if(seq===this.sequence)this.busy=false;}},
        resetForms(){this.refundForm={operation_key:noteRequestId(),component:'principal',document_payment_id:null,date_of_payment:moment().format('YYYY-MM-DD'),currency_type_id:this.refunds.currency_type_id,original_amount:'',exchange_rate:'',exchange_rate_source:'manual',payment_method_type_id:null,payment_destination_id:null,reference:''};this.adjustmentForm={operation_key:noteRequestId(),reason:'',amount:null,kind:null,source_id:null};this.selectedSource=null;},
        apply(data){this.refunds=data.refunds;this.adjustments=data.adjustments;this.invoiceId=data.invoice_id;},
        async mutate(path,data){if(this.busy)return false;const seq=this.sequence;this.busy=true;this.error='';try{const result=await this.$http.post(path,data);if(seq!==this.sequence)return false;this.apply(result.data);this.$eventHub.$emit('reloadData');this.$emit('changed');return true;}catch(e){if(seq===this.sequence)this.error=this.message(e);return false;}finally{if(seq===this.sequence)this.busy=false;}},
        async refund(){if(await this.mutate(`/documents/${this.documentId}/refunds`,this.refundForm))this.resetForms();},
        async adjust(){if(!this.allowRetentionManagement && ['IVA','ISLR'].includes(this.adjustmentForm.kind))return;if(await this.mutate(`/documents/${this.documentId}/adjustments`,this.adjustmentForm))this.resetForms();},
        async reverse(record){try{const answer=await this.$prompt('Indique el motivo de reversión del registro de devolución.','Revertir devolución',{inputValidator:v=>Boolean(v&&v.trim())});if(!this.reverseKeys[record.id])this.$set(this.reverseKeys,record.id,noteRequestId());await this.mutate(`/documents/${this.documentId}/refunds/${record.id}/reverse`,{operation_key:this.reverseKeys[record.id],reason:answer.value});}catch(e){if(e!=='cancel'&&e!=='close')this.error=this.message(e);}},
        replacement(a){if(!this.allowRetentionManagement)return;this.replacementKind=a.kind;this.replacementEndpoint=`/documents/${this.documentId}/adjustments/${a.id}/replacement`;this.showRetention=true;},
        adjustmentLabel(s){return {pending:'Por conciliar',awaiting_support:'Espera comprobante corregido',applied:'Aplicado',blocked:'Pendiente fiscal',confirmed:'Confirmado'}[s]||s;},
        message(e){const d=e.response&&e.response.data;if(!d)return 'No se pudo confirmar la operación. Reintente conservando la misma solicitud.';if(d.errors)return Object.values(d.errors).flat().join(' ');return d.message||Object.values(d).flat().join(' ');},close(){this.$emit('update:showDialog',false);}}
};
</script>
