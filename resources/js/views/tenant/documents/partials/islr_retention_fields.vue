<template>
    <div v-if="form.document_type_id === '01'" class="border rounded p-2 my-2" data-testid="islr-retention-fields">
        <strong>Retención ISLR del cliente</strong>
        <template v-if="!readonly">
            <el-checkbox :value="enabled" @change="toggle">Prever retención ISLR</el-checkbox>
            <div v-if="enabled">
                <label>Concepto no salarial</label><el-select v-model="values.concept_id" filterable @change="evaluate"><el-option v-for="concept in concepts" :key="concept.id" :value="concept.id" :label="concept.id + ' — ' + concept.description"/></el-select>
                <el-checkbox v-model="fullNet" @change="evaluate">Usar el valor neto fiscal de la factura como base</el-checkbox>
                <label>Moneda del comprobante</label><el-select v-model="values.currency_type_id" @change="evaluate"><el-option value="VES" label="Bs. / VES"/><el-option value="USD" label="USD"/></el-select>
                <label>Tasa VES por USD</label><el-input v-model="values.exchange_rate" @change="evaluate"/>
                <label>Base del comprobante</label><el-input v-model="values.base" :disabled="fullNet" @change="evaluate"/>
                <label>Porcentaje acreditado</label><el-input v-model="values.percentage" @change="evaluate"/>
                <label>Sustraendo</label><el-input v-model="values.subtrahend" @change="evaluate"/>
                <label>Fundamento del concepto</label><el-input v-model="values.fiscal_context.concept_basis" @change="evaluate"/>
                <label>Referencia de evidencia</label><el-input v-model="values.fiscal_context.evidence_reference" @change="evaluate"/>
                <p>El catálogo ayuda a elegir el concepto. El porcentaje y el sustraendo deben estar acreditados por el comprobante.</p>
                <p v-if="proposal">Prevista: {{ proposal.amount }} {{ form.currency_type_id }}. Permanece como saldo pendiente hasta validar el comprobante.</p>
                <el-alert v-if="hkaNetBlocked" type="warning" :closable="false" title="La emisión HKA con cobro neto ISLR espera aceptación DEMO del circuito comercial."/>
                <el-button v-if="proposal && proposal.eligible" size="mini" @click="useNet">Usar cobro neto sugerido IVA + ISLR</el-button>
            </div>
        </template>
        <p v-else-if="form.islr_retention && form.islr_retention.prevision">Prevista: {{ form.islr_retention.prevision.amount }} {{ form.currency_type_id }}</p>
        <p v-if="error" class="text-danger">{{ error }}</p>
    </div>
</template>
<script>
export default {
    props:{form:{type:Object,required:true},documentId:{default:null},readonly:{type:Boolean,default:false}},
    data:()=>({values:{concept_id:null,currency_type_id:'VES',exchange_rate:'1.00000000',base:null,percentage:null,subtrahend:0,fiscal_context:{concept_basis:'',evidence_reference:''}},fullNet:true,concepts:[],proposal:null,error:'',sequence:0,hkaNetBlocked:false}),
    computed:{
        enabled(){return !!(this.form.islr_retention_prevision && this.form.islr_retention_prevision.enabled)},
        evaluationKey(){return [this.form.customer_id,this.form.date_of_issue,this.form.total_value,this.form.total,this.form.total_igv,this.form.currency_type_id,this.form.exchange_rate_sale,this.form.document_type_id].join('|')},
    },
    watch:{evaluationKey(){if(this.form.document_type_id !== '01' && !this.documentId && !this.readonly){this.sequence++;this.proposal=null;this.$set(this.form,'islr_retention_prevision',null)}else if(this.enabled)this.evaluate()}},
    async mounted(){
        const saved=this.form.islr_retention_prevision;
        if(saved){this.values={...this.values,...saved,currency_type_id:saved.voucher_currency_type_id || saved.currency_type_id,fiscal_context:{...this.values.fiscal_context,...saved.fiscal_context}};this.fullNet=saved.base_mode !== 'declared'}
        else{this.values.currency_type_id=this.form.currency_type_id;this.values.exchange_rate=this.form.exchange_rate_sale || '1.00000000'}
        try{const {data}=await this.$http.get('/documents/retention/concepts');this.concepts=data.data}catch(e){this.error='No se pudo cargar el catálogo ISLR.'}
        if(this.enabled)this.evaluate();
    },
    beforeDestroy(){this.sequence++},
    methods:{
        toggle(value){this.$set(this.form,'islr_retention_prevision',{...this.values,enabled:value});if(value)this.evaluate();else{this.sequence++;this.proposal=null;this.error=''}},
        async evaluate(){
            const sequence=++this.sequence;this.proposal=null;this.error='';
            const input={...this.values,base_mode:this.fullNet?'full_net':'declared',fiscal_context:{...this.values.fiscal_context}};
            delete input.amount; delete input.enabled;
            if(this.fullNet)delete input.base;
            this.$set(this.form,'islr_retention_prevision',{enabled:true,...input});
            if(!this.enabled || !this.form.customer_id || this.form.document_type_id !== '01')return;
            if(!input.concept_id || !input.percentage || !input.fiscal_context.concept_basis || !input.fiscal_context.evidence_reference)return;
            try{
                const {data}=await this.$http.post('/documents/retention/proposal',{...input,tax_kind:'ISLR',document_currency_type_id:this.form.currency_type_id,document_id:this.documentId,customer_id:this.form.customer_id,date:this.form.date_of_issue,total_value:this.form.total_value || 0,total_igv:this.form.total_igv || 0,total_taxed:this.form.total_taxed || 0,exchange_rate_sale:this.form.exchange_rate_sale || 1});
                if(sequence !== this.sequence || !this.enabled)return;
                this.proposal=data.data;this.hkaNetBlocked=data.hka_net_collection_blocked === true;
                if(!this.proposal.eligible){this.error=this.proposal.diagnostic;return}
                this.values.base=this.proposal.base;
                this.$set(this.form,'islr_retention_prevision',{enabled:true,...input,amount:this.proposal.amount});
            }catch(e){if(sequence===this.sequence){const errors=e.response && e.response.data;this.error=errors && errors.errors ? Object.values(errors.errors).flat().join(' ') : 'No se pudo consultar la previsión ISLR.'}}
        },
        useNet(){
            const iva=this.form.iva_retention_prevision;
            const amount=Math.round(Math.max(0,Number(this.form.total)-Number(this.proposal.amount)-Number(iva && iva.enabled ? iva.amount || 0 : 0))*100)/100;
            const rows=this.form.payments || [];
            if(rows.length>1 || (rows[0] && rows[0].currency_type_id && rows[0].currency_type_id !== this.form.currency_type_id)){this.$message.info('Conserve los abonos y ajuste el importe en su moneda de recepción.');return}
            if(rows[0]){this.$set(rows[0],'payment',amount);this.$set(rows[0],'original_amount',amount)}
            this.$emit('net',amount);
        },
    },
}
</script>
