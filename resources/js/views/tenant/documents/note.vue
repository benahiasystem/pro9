<template>
<div>
    <div class="page-header"><h2>Nota de crédito o débito</h2><a href="/documents">Listado de comprobantes</a></div>
    <div class="card mt-4" v-loading="loading"><div class="card-body">
        <el-alert v-if="context.block_reason" :title="context.block_reason" type="warning" :closable="false"/>
        <p><strong>Factura afectada:</strong> {{ documentNumberFull(document.series, document.number) }} · Control {{ context.control_number || 'Sin asignar' }}</p>
        <p>{{ document.customer && document.customer.name }} · {{ document.currency_type_id }} · Disponible para acreditar: {{ context.credit_available }}</p>
        <p v-if="context.notes.length">Notas anteriores: <span v-for="note in context.notes" :key="note.id">{{ note.number }} ({{ note.total }}; {{ note.fiscal_status || 'Local' }}) </span></p>
        <form @submit.prevent="submit" autocomplete="off">
            <div class="row">
                <div class="col-md-3"><label>Tipo de nota</label><el-select v-model="form.document_type_id" @change="changeType"><el-option value="07" label="Nota de crédito"/><el-option value="08" label="Nota de débito"/></el-select></div>
                <div class="col-md-3"><label>Serie</label><el-select v-model="form.series_id"><el-option v-for="s in series" :key="s.id" :value="s.id" :label="s.number || '\u00a0'"/></el-select></div>
                <div class="col-md-6"><label>{{ credit ? 'Motivo de nota de crédito' : 'Motivo de nota de débito' }}</label><el-select v-model="form.note_credit_or_debit_type_id" @change="changeReason"><el-option v-for="reason in reasons" :key="reason.id" :value="reason.id" :label="reason.description"/></el-select></div>
                <div class="col-md-6 mt-2"><label>Descripción del ajuste</label><el-input v-model="form.note_description" maxlength="255"/></div>
                <div class="col-md-3 mt-2"><label>Fecha de emisión</label><el-date-picker v-model="form.date_of_issue" type="date" value-format="yyyy-MM-dd" :clearable="false" @change="changeDate"/></div>
                <div class="col-md-3 mt-2"><label>Tasa VES por USD <el-tooltip content="Tipo de cambio del día"><i class="fa fa-info-circle"/></el-tooltip></label><el-input v-model="form.exchange_rate_sale" :disabled="credit"/></div>
                <div v-if="!credit" class="col-md-3 mt-2"><label>Condición de pago</label><el-select v-model="form.payment_condition_id"><el-option value="02" label="Crédito"/><el-option value="01" label="Contado"/></el-select><small>Al contado registre los cobros reales; a crédito conservará su saldo pendiente.</small></div>
            </div>
            <el-alert v-if="full" class="mt-3" title="Se acreditará el importe comercial restante y se devolverán las cantidades pendientes de cada partida." type="info" :closable="false"/>
            <div class="table-responsive mt-3"><table class="table"><thead><tr><th>Partida original</th><th>Unidad / IVA</th><th>Pendiente devolución</th><th>Importe acreditable</th><th>{{ returning ? 'Cantidad que regresa' : 'Importe del ajuste' }}</th><th v-if="returning">Lotes / seriales originales</th><th/></tr></thead>
                <tbody><tr v-for="(row,index) in displayedRows" :key="index">
                    <td v-if="full">{{ source(row).description }}</td>
                    <td v-else-if="row.affected_document_item_id"><el-select v-model="row.affected_document_item_id" filterable @change="changeOriginal(row)"><el-option v-for="item in eligibleItems" :key="item.id" :value="item.id" :label="item.description"/></el-select></td>
                    <td v-else>{{ row.item && row.item.description }}</td>
                    <td>{{ source(row).unit_type_id || 'SERV' }} / {{ (source(row).iva_rate || row.iva_rate || {}).percentage }}%</td>
                    <td>{{ source(row).remaining_quantity }}</td><td>{{ source(row).remaining_total }}</td>
                    <td v-if="full">{{ source(row).remaining_quantity }}</td>
                    <td v-else><el-input v-if="returning" v-model="row.quantity" type="number" min="0.0001" step="0.0001"/><el-input v-else v-model="row.adjustment_amount" type="number" min="0.01" step="0.01"/></td>
                    <td v-if="returning"><el-select v-if="(source(row).serials || []).length" v-model="row.serial_ids" multiple placeholder="Seriales que regresan"><el-option v-for="s in source(row).serials" :key="s.id" :value="s.id" :label="s.series"/></el-select><div v-for="lot in source(row).lots || []" :key="lot.id"><label>Lote {{ lot.id }} · Disponible {{ lot.quantity }}</label><el-input :value="lotQuantity(row,lot.id)" type="number" min="0" step="0.0001" @input="setLot(row,lot.id,$event)"/></div></td>
                    <td><el-button v-if="!full" size="mini" @click="form.items.splice(index,1)">Quitar</el-button></td>
                </tr></tbody>
            </table></div>
            <el-button v-if="!full" @click="addOriginal">Agregar partida original</el-button>
            <el-button v-if="!credit && ['01','03'].includes(form.note_credit_or_debit_type_id)" @click="showItem=true">Agregar servicio / cargo</el-button>
            <p class="text-right mt-3"><strong>Total de la nota: {{ total }} {{ document.currency_type_id }}</strong></p>
            <note-payments v-if="!credit && form.payment_condition_id === '01'" :payments="form.payments" :methods="tables.payment_method_types || []" :destinations="tables.payment_destinations || []" :currency="form.currency_type_id" :rate="form.exchange_rate_sale" :date="form.date_of_issue" :customer-id="form.customer_id" :can-register="!!tables.can_create_payment" />
            <el-alert v-for="(messages,field) in errors" :key="field" :title="Array.isArray(messages) ? messages.join(' ') : String(messages)" type="error" :closable="false" class="mt-2"/>
            <div class="text-right mt-3"><el-button @click="close">Cancelar</el-button><el-button type="primary" native-type="submit" :disabled="!context.can_create || !form.series_id || !form.note_credit_or_debit_type_id || Number(total)<=0" :loading="busy">Generar nota</el-button></div>
        </form>
    </div></div>
    <document-form-item :showDialog.sync="showItem" :document-type-id="form.document_type_id" :note-credit-or-debit-type-id="form.note_credit_or_debit_type_id" operation-type-id="0101" :currency-type-id-active="form.currency_type_id" :exchange-rate-sale="form.exchange_rate_sale" :configuration="configuration" :typeUser="authUser.type" :percentage-igv="percentage_igv" @add="addService"/>
    <document-options :showDialog.sync="showOptions" :recordId="newId" :showClose="false"/>
</div>
</template>
<script>
// ########## INICIO CAMBIO SUNAT A SENIAT
import { documentNumberFull } from '../../../helpers/document-number';
import {noteSeries,noteTotal,noteRequestId} from '../../../helpers/fiscal-note';
import {functions,exchangeRate} from '../../../mixins/functions';
import DocumentFormItem from './partials/item.vue';
import DocumentOptions from './partials/options.vue';
import NotePayments from './partials/note-payments.vue';
export default {
    components:{DocumentFormItem,DocumentOptions,NotePayments},mixins:[functions,exchangeRate],props:['document_affected','configuration','authUser'],
    data(){return {document:this.document_affected,context:this.document_affected.note_context,form:{items:[]},tables:{series:[],note_credit_types:[],note_debit_types:[]},loading:false,busy:false,errors:{},showItem:false,showOptions:false,newId:null};},
    computed:{credit(){return this.form.document_type_id==='07';},full(){return this.credit && this.form.note_credit_or_debit_type_id==='01';},returning(){return this.credit && this.form.note_credit_or_debit_type_id==='07';},
        series(){return noteSeries(this.tables.series,this.form.document_type_id,this.document.establishment_id);},reasons(){return (this.credit?this.tables.note_credit_types:this.tables.note_debit_types).filter(r=>r.id!=='IGTF');},
        eligibleItems(){return this.context.items.filter(r=>this.returning ? r.unit_type_id!=='SERV' && Number(r.remaining_quantity)>0 : !this.credit || Number(r.remaining_total)>0);},
        displayedRows(){return this.full?this.context.items.map(r=>({affected_document_item_id:r.id})):this.form.items;},total(){return noteTotal(this.form,this.context);}},
    async created(){this.reset();this.loading=true;try{const res=await this.$http.get('/documents/tables');this.tables=res.data;this.changeType();await this.getPercentageIgv();}catch(e){this.$message.error('No se pudieron cargar los catálogos de notas.');}finally{this.loading=false;}},
    watch:{'form.payment_condition_id'(value,previous){if(value!==previous)this.form.payments=[];}},
    methods:{documentNumberFull,source(row){return this.context.items.find(r=>r.id===row.affected_document_item_id)||{};},
        reset(){this.form={note_creation_key:noteRequestId(),document_type_id:'07',note_credit_or_debit_type_id:null,note_description:'',series_id:null,number:'#',date_of_issue:moment().format('YYYY-MM-DD'),time_of_issue:moment().format('HH:mm:ss'),establishment_id:this.document.establishment_id,customer_id:this.document.customer_id,currency_type_id:this.document.currency_type_id,exchange_rate_sale:this.document.exchange_rate_sale,exchange_rate_source:this.document.exchange_rate_source,exchange_rate_date:this.document.exchange_rate_date,affected_document_id:this.document.id,operation_type_id:'0101',items:[],payments:[],fee:[],payment_condition_id:null,actions:{format_pdf:'a4'},total_taxed:0,total_igv:0,total_taxes:0,total_value:0,total:0};this.errors={};},
        changeType(){this.form.note_credit_or_debit_type_id=null;this.form.items=[];this.form.series_id=this.series.length?this.series[0].id:null;this.form.payments=[];this.form.payment_condition_id=this.credit?null:'02';this.changeDate();},
        changeReason(){this.form.items=[];},async changeDate(){if(this.credit){this.form.exchange_rate_sale=this.document.exchange_rate_sale;this.form.exchange_rate_date=this.document.exchange_rate_date;this.form.exchange_rate_source=this.document.exchange_rate_source;}else{this.form.exchange_rate_sale=await this.searchExchangeRateByDate(this.form.date_of_issue);this.form.exchange_rate_date=this.form.date_of_issue;this.form.exchange_rate_source='BCV';}},
        addOriginal(){const item=this.eligibleItems.find(r=>!this.form.items.some(row=>row.affected_document_item_id===r.id));if(item){const row={affected_document_item_id:item.id,quantity:'1',adjustment_amount:'0.00',serial_ids:[],lot_returns:null};this.form.items.push(row);this.changeOriginal(row);}},
        changeOriginal(row){row.serial_ids=(this.source(row).serials || []).slice(0,Number(row.quantity)||1).map(s=>s.id);row.lot_returns=null;},
        lotQuantity(row,id){const selected=(row.lot_returns || []).find(l=>l.id===id);return selected ? selected.compromise_quantity : '';},
        setLot(row,id,value){row.lot_returns=(row.lot_returns || []).filter(l=>l.id!==id);if(Number(value)>0)row.lot_returns.push({id,compromise_quantity:value});},
        addService(row){if(row.item.unit_type_id!=='SERV')return this.$message.error('Seleccione un servicio para intereses o gastos.');this.form.items.push({...row,quantity:1,adjustment_amount:row.unit_price});},
        async submit(){if(this.busy)return;this.busy=true;this.errors={};try{const response=await this.$http.post('/documents',this.form);if(!response.data.success)return this.$message.error(response.data.message);this.newId=response.data.data.id;this.showOptions=true;const record=await this.$http.get(`/documents/note/record/${this.document.id}`);this.document=record.data;this.context=record.data.note_context;this.reset();this.changeType();}catch(e){const data=e.response&&e.response.data||{};if(e.response&&e.response.status===422)this.errors=data.errors||data;else this.$message.error(data.message||'No se pudo confirmar el guardado. Reintente con la misma nota.');}finally{this.busy=false;}},close(){location.href='/documents';}}
};
// ######### FIN CAMBIO SUNAT A SENIAT
</script>
