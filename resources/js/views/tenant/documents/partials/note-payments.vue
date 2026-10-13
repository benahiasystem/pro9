<template>
    <div class="mt-3" data-testid="note-cash-payments">
        <h5>Cobros reales de la nota de débito</h5>
        <p v-if="!canRegister" class="text-warning">Se requiere permiso para registrar cobros.</p>
        <div v-for="(payment,index) in payments" :key="payment.operation_key" class="border rounded p-3 mb-2">
            <label>Fecha del cobro</label><el-date-picker v-model="payment.date_of_payment" type="date" value-format="yyyy-MM-dd" />
            <label>Medio de pago</label><el-select v-model="payment.payment_method_type_id" filterable><el-option v-for="method in realMethods" :key="method.id" :value="method.id" :label="method.description" /></el-select>
            <label>Caja o banco</label><el-select v-model="payment.payment_destination_id" filterable><el-option v-for="destination in destinations" :key="destination.id" :value="destination.id" :label="destination.description" /></el-select>
            <label>Principal aplicado ({{ currency }})</label><el-input v-model="payment.payment" :disabled="payment.currency_type_id !== currency" @input="setPrincipal(payment)" />
            <label>Referencia</label><el-input v-model="payment.reference" maxlength="255" />
            <igtf-payment-fields :payment="payment" :document-currency="currency" :document-rate="rate" :customer-id="customerId" />
            <el-button class="mt-2" @click="payments.splice(index,1)">Quitar cobro</el-button>
        </div>
        <el-button :disabled="!canRegister" @click="add">Agregar cobro real</el-button>
    </div>
</template>
<script>
import IgtfPaymentFields from './igtf_fields.vue';
import {noteRequestId} from '../../../../helpers/fiscal-note';
export default {
    components:{IgtfPaymentFields},
    props:{payments:{type:Array,required:true},methods:{type:Array,default:()=>[]},destinations:{type:Array,default:()=>[]},currency:{type:String,required:true},rate:{required:true},date:{type:String,required:true},customerId:{required:true},canRegister:{type:Boolean,default:false}},
    computed:{realMethods(){return this.methods.filter(method=>!Number(method.is_credit));}},
    methods:{
        add(){if(!this.canRegister)return;this.payments.push({operation_key:noteRequestId(),date_of_payment:this.date,payment_method_type_id:null,payment_destination_id:null,payment:'',original_amount:'',currency_type_id:this.currency,exchange_rate:this.rate,exchange_rate_date:this.date,exchange_rate_source:'manual',reference:null,igtf_status:'not_applicable',financial_intermediation:null,exemption_reason:null,exception_basis:null});},
        setPrincipal(payment){if(payment.currency_type_id===this.currency)payment.original_amount=payment.payment;}
    }
};
</script>
